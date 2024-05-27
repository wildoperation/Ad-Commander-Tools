<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Import functionality
 */
class Import extends AdminDt {

	/**
	 * A unique ID for the current import.
	 *
	 * @var string
	 */
	protected $current_import_id;

	/**
	 * An array that associates imported post IDs with new post IDs.
	 *
	 * @var array
	 */
	protected $imported_ad_ids;

	/**
	 * An array that associates imported group IDs with new group IDs.
	 *
	 * @var array
	 */
	protected $imported_group_ids;

	/**
	 * Group meta to update after the ad import.
	 *
	 * @var array
	 */
	protected $group_meta_after_ads;

	/**
	 * An instance of WOMeta.
	 *
	 * @var WOMeta
	 */
	private $wo_meta;

	/**
	 * Return or create an instance of WOMeta.
	 *
	 * @return WOMeta
	 */
	protected function wo_meta() {
		if ( ! $this->wo_meta ) {
			$this->wo_meta = new WOMeta( AdCommander::ns() );
		}

		return $this->wo_meta;
	}

	/**
	 * Redirect after an import and include arguments.
	 *
	 * @param array  $nonce The import nonce.
	 * @param string $type The type of import completed.
	 * @param bool   $success Whether this is a successful redirect or a failure.
	 *
	 * @return void
	 */
	public function redirect( $nonce, $type, $success = true ) {
		$url = admin_url( self::admin_path( 'tools' ) );

		$url = add_query_arg(
			array(
				'action'       => ( $success ) ? Util::ns( 'import_success' ) : Util::ns( 'import_fail' ),
				'import_type'  => $type,
				$nonce['name'] => wp_create_nonce( $nonce['action'] ),
				'tab'          => 'adcmdr_import',
			),
			$url
		);

		wp_safe_redirect( sanitize_url( $url ) );
		exit;
	}

	protected function sanitize_imported_value( $value ) {

		/***
		 * TODO: This should probably use the same sanitization as when we save meta keys (from WOAdmin)
		 * Doing so would implement current_user_can('unfiltered_html') and prevent someone from importing a CSV with scripts.
		 */
		if ( ! $value && $value !== 0 && $value !== '0' ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			$value = intval( $value );
		} elseif ( is_array( $value ) ) {
			$value = array_map( array( $this, 'sanitize_imported_value' ), $value );
		} else {
			$value = sanitize_text_field( $value );
		}

		return $value;
	}

	protected function maybe_unserialize_and_sanitize( $key, $value, $unfiltered = array() ) {
		$value = maybe_unserialize( $value );

		if ( ! in_array( $key, $unfiltered, true ) ) {
			return $this->sanitize_imported_value( $value );
		}

		return $value;
	}

	protected function deprefix_key( $key ) {
		return substr( $key, 0, 8 ) === '_adcmdr_' ? substr( $key, 8 ) : $key;
	}

	/**
	 * Import groups. Assumes data has already been processed, formatted, and sanitized.
	 *
	 * @param array $data Array of terms and term meta.
	 *
	 * @return void|bool
	 */
	protected function import_groups( $data, $args = array() ) {
		if ( ! $data || empty( $data ) ) {
			return false;
		}

		foreach ( $data as $group ) {
			$term                = $group['item'];
			$meta                = $group['meta'];
			$allowed_import_keys = array( 'import_id' );

			/**
			 * Save the old term data as meta
			 */
			$meta['import_id'] = $this->current_import_id ? $this->current_import_id : time();

			foreach ( $term as $term_key => $term_value ) {
				if ( isset( $meta[ 'imported_' . $term_key ] ) ) {
					continue;
				}

				$meta[ 'imported_' . $term_key ] = $term_value;
				$allowed_import_keys[]           = 'imported_' . $term_key;
			}

			/**
			 * Prepare term
			 */
			$new_group_args = array(
				'description' => null,
				'parent'      => null,
			);

			$term_title = $term['name'];

			while ( term_exists( $term_title, AdCommander::tax_group() ) ) {
				$term_title .= ' ' . $meta['import_id'];
			}

			$new_term = wp_insert_term( $term_title, AdCommander::tax_group(), $new_group_args );

			if ( ! $new_term || is_wp_error( $new_term ) ) {
				continue;
			}

			// The ad IDs won't match up, so we update this later.
			$do_not_copy_meta  = array( 'ad_order', 'ad_weights' );
			$allowed_meta_keys = array_merge( UtilDt::headings( 'groups', false, true, true, false ), $allowed_import_keys );

			foreach ( $meta as $meta_key => $meta_value ) {
				if ( ! in_array( $meta_key, $allowed_meta_keys, true ) ) {
					continue;
				}

				if ( in_array( $meta_key, $do_not_copy_meta, true ) ) {
					if ( ! $meta_value || isset( $meta[ 'imported_' . $meta_key ] ) ) {
						continue;
					}

					$meta_key            = 'imported_' . $meta_key;
					$allowed_meta_keys[] = $meta_key;

					if ( ! isset( $this->group_meta_after_ads[ $new_term['term_id'] ] ) ) {
						$this->group_meta_after_ads[ $new_term['term_id'] ] = array();
					}

					$this->group_meta_after_ads[ $new_term['term_id'] ][ $meta_key ] = $meta_value;
				}

				$meta_key = $this->wo_meta()->make_key( $meta_key );

				if ( $meta_value === '' ) {
					$meta_value = null;
				}

				delete_term_meta( $new_term['term_id'], $meta_key );
				add_term_meta( $new_term['term_id'], $meta_key, $meta_value );
			}

			/**
			 * Store group ID for syncing to groups later.
			 */
			if ( isset( $meta['imported_term_id'] ) ) {
				$this->imported_group_ids[ 'imported_term_id_' . $meta['imported_term_id'] ] = $new_term['term_id'];
			}
		}
	}

	private function import_group_post_relationships() {

		if ( ! empty( $this->group_meta_after_ads ) ) {
			// wo_log( $this->group_meta_after_ads );

			$key_is_post_id = array( 'ad_weights' );

			foreach ( $this->group_meta_after_ads as $term_id => $meta ) {
				foreach ( $meta as $meta_key => $meta_value ) {
					$meta_key = str_replace( 'imported_', '', $meta_key );
					$new_meta = array();

					/**
					 * This currently only works if the meta is an array, but we don't currently have anything that wouldn't be an array...
					 */
					if ( is_array( $meta_value ) ) {

						foreach ( $meta_value as $subkey => $subvalue ) {
							if ( in_array( $meta_key, $key_is_post_id, true ) ) {
								$post_id = $subkey;
							} else {
								$post_id = $subvalue;
							}

							if ( ! isset( $this->imported_ad_ids[ 'imported_post_id_' . $post_id ] ) ) {
								continue;
							}

							$new_post_id = $this->imported_ad_ids[ 'imported_post_id_' . $post_id ];

							if ( in_array( $meta_key, $key_is_post_id, true ) ) {
								$new_meta[ $new_post_id ] = $subvalue;
							} else {
								$new_meta[] = $new_post_id;
							}
						}
					}

					if ( ! empty( $new_meta ) ) {
						$new_key = $this->wo_meta()->make_key( $meta_key );
						delete_term_meta( $term_id, $new_key );
						add_term_meta( $term_id, $new_key, $new_meta );
					}
				}
			}
		}
	}

	/**
	 * Import a post (ad or placement)
	 *
	 * @param array  $data Array of posts and post meta.
	 * @param string $post_type The post type to import.
	 * @param array  $do_not_copy Post keys and meta keys to skip.
	 * @param array  $allowed_keys The allowed keys for this post type.
	 *
	 * @return int|bool
	 */
	private function import_post( $data, $post_type, $do_not_copy, $allowed_keys, $args = array() ) {
		if ( ! $data || empty( $data ) ) {
			return false;
		}

		$p    = $data['item'];
		$meta = $data['meta'];

		$allowed_import_keys = array( 'import_id' );

		/**
		 * Save the old  data as meta and prep the new post params.
		 */
		$meta['import_id'] = $this->current_import_id ? $this->current_import_id : time();
		$new_post_params   = array( 'post_type' => $post_type );

		if ( ( isset( $args['set_post_status'] ) && $args['set_post_status'] !== 'match' ) || ! isset( $args['set_post_status'] ) ) {
			$new_post_params['post_status'] = isset( $args['set_post_status'] ) ? $args['set_post_status'] : 'draft';
			$do_not_copy['post'][]          = 'post_status';
			$do_not_copy['post'][]          = 'post_date';
			$do_not_copy['post'][]          = 'post_date_gmt';
		}

		foreach ( $p as $p_key => $p_value ) {

			if ( in_array( $p_key, $do_not_copy['post'], true ) ) {
				$p_key = strtolower( $p_key );

				if ( $p_key === 'id' ) {
					$p_key = 'post_id';
				}

				if ( isset( $meta[ 'imported_' . $p_key ] ) ) {
					continue;
				}

				$meta[ 'imported_' . $p_key ] = $p_value;
				$allowed_import_keys[]        = 'imported_' . $p_key;

			} elseif ( ! isset( $new_post_params[ $p_key ] ) ) {
				$new_post_params[ $p_key ] = $p_value;
			}
		}

		/**
		 * Create post
		 */
		$new_post_id = wp_insert_post( $new_post_params );

		if ( ! $new_post_id || is_wp_error( $new_post_id ) ) {
			return false;
		}

		$allowed_meta_keys = array_merge( $allowed_keys, $allowed_import_keys );

		foreach ( $meta as $meta_key => $meta_value ) {
			if ( ! in_array( $meta_key, $allowed_meta_keys, true ) || in_array( $meta_key, $do_not_copy['meta'], true ) ) {
				continue;
			}

			if ( $meta_value === '' ) {
				$meta_value = null;
			}

			$meta_key = $this->wo_meta()->make_key( $meta_key );
			delete_post_meta( $new_post_id, $meta_key );
			add_post_meta( $new_post_id, $meta_key, $meta_value );
		}

		/**
		 * Ads only (not placements)
		 */
		if ( $post_type === AdCommander::posttype_ad() ) {
			/**
			 * Store post ID for syncing to groups later.
			 */
			if ( isset( $meta['imported_post_id'] ) ) {
				$this->imported_ad_ids[ 'imported_post_id_' . $meta['imported_post_id'] ] = $new_post_id;
			}

			/**
			 * Groups
			 */
			if ( isset( $args['importing_types'] ) && in_array( 'groups', $args['importing_types'], true ) && isset( $data['meta']['groups'] ) && ! empty( $data['meta']['groups'] ) ) {

				$term_ids = array();

				foreach ( $data['meta']['groups'] as $imported_term_id => $imported_group_name ) {
					if ( isset( $this->imported_group_ids[ 'imported_term_id_' . $imported_term_id ] ) ) {
						$term_ids[] = $this->imported_group_ids[ 'imported_term_id_' . $imported_term_id ];
					}
				}

				if ( ! empty( $term_ids ) ) {
					wp_set_object_terms( $new_post_id, $term_ids, AdCommander::tax_group() );
				}
			}
		}

		return $new_post_id;
	}

	/**
	 * Import ads. Assumes data has already been processed, formatted, and sanitized.
	 *
	 * @param array $data Array of posts and post meta.
	 *
	 * @return void|bool
	 */
	protected function import_ads( $data, $args = array() ) {
		if ( ! $data || empty( $data ) ) {
			return false;
		}

		$args = wp_parse_args(
			$args,
			array( 'set_post_status' => 'draft' )
		);

		$do_not_copy = array(
			'post' => array( 'ID', 'post_modified', 'post_modified_gmt' ),
			'meta' => array( 'featured_image_url', 'thumbnail_id' ),
		);

		$new_post_ids = array();
		$home_url     = home_url();

		foreach ( $data as $ad ) {
			$new_post_id = $this->import_post( $ad, AdCommander::posttype_ad(), $do_not_copy, UtilDt::headings( 'ads', false, true, true, false ), $args );

			if ( $new_post_id ) {
				$new_post_ids[] = $new_post_id;

				if ( ! apply_filters( 'adcmdr_dt_import_featured_images', true ) ) {
					continue;
				}

				/**
				 * Import featured image
				 */
				$same_site          = isset( $ad['meta']['source_site'] ) && $home_url === sanitize_url( $ad['meta']['source_site'] );
				$assigned_thumbnail = false;

				if ( $same_site && isset( $ad['meta']['thumbnail_id'] ) && $ad['meta']['thumbnail_id'] ) {
					$thumbnail_id = intval( $ad['meta']['thumbnail_id'] );

					if ( $thumbnail_id > 0 && wp_attachment_is( 'image', $thumbnail_id ) ) {
						set_post_thumbnail( $new_post_id, $thumbnail_id );
						$assigned_thumbnail = true;
					}
				}

				if ( ! $assigned_thumbnail && isset( $ad['meta']['featured_image_url'] ) && $ad['meta']['featured_image_url'] ) {
					$featured_image_url = sanitize_url( $ad['meta']['featured_image_url'] );

					if ( $featured_image_url ) {
						$image_id = media_sideload_image( $featured_image_url, $new_post_id, null, 'id' );

						if ( is_int( $image_id ) ) {
							set_post_thumbnail( $new_post_id, $image_id );
						}
					}
				}
			}
		}

		$this->import_group_post_relationships();
	}

	/**
	 * Import ads. Assumes data has already been processed, formatted, and sanitized.
	 *
	 * @param array $data Array of posts and post meta.
	 *
	 * @return void|bool
	 */
	protected function import_placements( $data, $args = array() ) {
		if ( ! $data || empty( $data ) ) {
			return false;
		}

		$args = wp_parse_args(
			$args,
			array( 'set_post_status' => 'draft' )
		);

		$do_not_copy = array(
			'post' => array( 'ID', 'post_modified', 'post_modified_gmt' ),
			'meta' => array( 'placement_items' ),
		);

		$new_post_ids = array();

		foreach ( $data as $ad ) {
			$new_post_id = $this->import_post( $ad, AdCommander::posttype_placement(), $do_not_copy, UtilDt::headings( 'placements', false, true, true, false ), $args );

			if ( $new_post_id ) {
				$new_post_ids[] = $new_post_id;

				$meta_placement_items = maybe_unserialize( $ad['meta']['placement_items'] );

				if ( is_array( $meta_placement_items ) && ! empty( $meta_placement_items ) ) {
					$placement_items = array();

					foreach ( $meta_placement_items as $placement_item ) {
						$type = substr( $placement_item, 0, 2 );
						$id   = intval( substr( $placement_item, 2 ) );

						if ( $type === 'g_' ) {
							if ( isset( $this->imported_group_ids[ 'imported_term_id_' . $id ] ) ) {
								$placement_items[] = 'g_' . $this->imported_group_ids[ 'imported_term_id_' . $id ];
							}
						} elseif ( $type === 'a_' ) {
							if ( isset( $this->imported_ad_ids[ 'imported_post_id_' . $id ] ) ) {
								$placement_items[] = 'a_' . $this->imported_ad_ids[ 'imported_post_id_' . $id ];
							}
						}
					}

					if ( ! empty( $placement_items ) ) {
						$meta_key = $this->wo_meta()->make_key( 'placement_items' );

						delete_post_meta( $new_post_id, $meta_key );
						add_post_meta( $new_post_id, $meta_key, $placement_items );
					}
				}
			}
		}
	}
}
