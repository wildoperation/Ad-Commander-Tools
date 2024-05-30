<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;
use ADCmdr\Vendor\WOAdminFramework\WOUtilities;

/**
 * Import functionality
 */
class Import extends AdminTools {

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

	/**
	 * The types of imports in the order they will be imported.
	 *
	 * @return array
	 */
	protected function all_import_types() {
		return array( 'groups', 'ads', 'placements', 'stats' );
	}

	/**
	 * Import data by type; Determines which import function to call to process data.
	 *
	 * @param string $import_type The type of data we're importing.
	 * @param array  $data The data to import.
	 * @param array  $args Additional arguments used during import.
	 *
	 * @return void
	 */
	protected function import_data( $import_type, $data, $args = array() ) {
		switch ( $import_type ) {
			case 'groups':
				$this->import_groups( $this->process( $data, 'groups' ), $args );
				break;

			case 'ads':
				$this->import_ads( $this->process( $data, 'ads' ), $args );
				break;

			case 'placements':
				$this->import_placements( $this->process( $data, 'placements' ), $args );
				break;

			case 'stats':
				$this->import_stats( $this->process( $data, 'stats' ), $args );
				break;
		}
	}

	/**
	 * Process data for import.
	 *
	 * @param array  $data The data to prepare.
	 * @param string $type The type of import.
	 *
	 * @return array
	 */
	protected function process( $data, $type ) {
		$processed = array();

		switch ( $type ) {
			case 'groups':
				$primary_keys     = array_keys( UtilTools::headings( 'groups', true, false, false, false ) );
				$meta_keys        = array_keys( UtilTools::headings( 'groups', false, true, true, false ) );
				$all_allowed_keys = UtilTools::headings( 'groups', true, true, true, false );
				break;

			case 'ads':
				$primary_keys     = array_keys( UtilTools::headings( 'ads', true, false, false, false ) );
				$meta_keys        = array_keys( UtilTools::headings( 'ads', false, true, true, false ) );
				$all_allowed_keys = UtilTools::headings( 'ads', true, true, true, false );
				break;

			case 'placements':
				$primary_keys     = array_keys( UtilTools::headings( 'placements', true, false, false, false ) );
				$meta_keys        = array_keys( UtilTools::headings( 'placements', false, true, true, false ) );
				$all_allowed_keys = UtilTools::headings( 'placements', true, true, true, false );
				break;

			case 'stats':
				$primary_keys     = array_keys( UtilTools::headings( 'stats', true, false, false, false ) );
				$meta_keys        = array_keys( UtilTools::headings( 'stats', false, true, true, false ) );
				$all_allowed_keys = UtilTools::headings( 'stats', true, true, true, false );
				break;
		}

		/**
		 * $data is a single array of unsanitized data.
		 */
		$data = $this->sanitize_data_for_input( $data, $all_allowed_keys );

		foreach ( $data as $item ) {
			$this_item = array(
				'item' => array(),
				'meta' => array(),
			);

			foreach ( $item as $key => $value ) {
				$key = $this->deprefix_key( $key );

				if ( in_array( $key, $primary_keys, true ) ) {
					$this_item['item'][ $key ] = $value;
					continue;
				}

				if ( in_array( $key, $meta_keys, true ) ) {
					$this_item['meta'][ $key ] = $value;
				}
			}

			$processed[] = $this_item;
		}

		return $processed;
	}

	/**
	 * This function sanitizses data in the same way it's sanitized if saved in the WordPress admin.
	 * All data is sanitized with native WordPress sanitization functions (in WOAdminFramework),
	 * but this logic allows us to have default values and special sanitization logic to match expected data types,
	 *
	 * @param array $all_data The full array of data to sanitize.
	 * @param array $allowed_keys The allowed keys and associated settings.
	 *
	 * @return array
	 */
	protected function sanitize_data_for_input( $all_data, $allowed_keys ) {

		$sanitized_data = array();

		foreach ( $all_data as $data ) {
			/**
			 * Loop through all allowed keys and their values.
			 * Any data now in the allowed_keys set will be ignored.
			 */
			$processed_data = array();

			foreach ( $allowed_keys as $key => $allowed_keyvalue ) {
				$value = null;

				/**
				 * Create namespaced key for checking $data
				 * Either the namespaced key (meta) or non-namedspaced (primary and extra data) will be accepted.
				 */
				$full_key = $this->wo_meta()->make_key( $key );
				$this_key = ( isset( $data[ $full_key ] ) ) ? $full_key : $key;

				if ( isset( $data[ $this_key ] ) ) {
					$data[ $this_key ] = maybe_unserialize( $data[ $this_key ] );
				}

				if ( isset( $data[ $this_key ] ) && isset( $allowed_keyvalue['children'] ) ) {
					/**
					 * This allowed_keyvalue has children.
					 * This is often used with repeater fields that have sub-fields.
					 * Each child will be processed, and the entire value will be saved as a serialized array.
					 */
					$value = array();

					/**
					 * Most often in this scenario, the $data value will be an array.
					 * If it's not, we'll make it one.
					 */
					$data_arrs = WOUtilities::arrayify( $data[ $this_key ] );

					foreach ( $data_arrs as $data_arr ) {
						if ( ! empty( $allowed_keyvalue['children'] ) ) {
							$child_values = array();

							/**
							 * Loop through each child key and sanitize it.
							 */
							foreach ( $allowed_keyvalue['children'] as $child_key => $child_value ) {
								if ( ! isset( $data_arr[ $child_key ] ) || ! $data_arr[ $child_key ] ) {

									/**
									 * In some cases, if a particular child is missing we may want to invalidate the entire row.
									 * If that happens, reset our child_values so that it is not later added to the stored value.
									 */
									if ( isset( $child_value['required'] ) && $child_value['required'] === true ) {
										$child_values = array();
										break;
									}

									/**
									 * Oherwise, parse the default.
									 */
									$child_values[ $child_key ] = $this->wo_meta()->parse_default( $child_value );
								} else {
									/**
									 * If the child was posted, sanitize the input.
									 */
									$child_values[ $child_key ] = $this->wo_meta()->sanitize_meta_input( $child_value, $data_arr[ $child_key ] );
								}
							}

							/**
							 * Store this child in our parent meta array.
							 */
							if ( ! empty( $child_values ) ) {
								$value[] = $child_values;
							}
						}
					}

					/**
					 * If we didn't have any children rows, parse the default of the parent.
					 */
					if ( empty( $value ) ) {
						$value = $this->wo_meta()->parse_default( $allowed_keyvalue );
					}
				} elseif ( isset( $data[ $this_key ] ) ) {
					/**
					 * This is just a normal field. Sanitize and save the value.
					 */
					$value = $this->wo_meta()->sanitize_meta_input( $allowed_keyvalue, $data[ $this_key ] );
				} elseif ( isset( $allowed_keyvalue['type'] ) && $allowed_keyvalue['type'] === 'bool' ) {
					/**
					 * If this field wasn't in the post, and it's a bool, we'll default to 0 always.
					 */
					$value = 0;
				} else {
					/**
					 * If this field wasn't in the post, parse the default value.
					 */
					$value = $this->wo_meta()->parse_default( $allowed_keyvalue );
				}

				$processed_data[ $this_key ] = $value;
			}

			if ( ! empty( $processed_data ) ) {
				$sanitized_data[] = $processed_data;
			}
		}

		return $sanitized_data;
	}

	/**
	 * Remove the namespace from a key if one exists.
	 *
	 * @param string $key Key to deprefix.
	 *
	 * @return string
	 */
	protected function deprefix_key( $key ) {
		return substr( $key, 0, 8 ) === '_adcmdr_' ? substr( $key, 8 ) : $key;
	}

	/**
	 * Import statistics.
	 *
	 * @param array $data Data to import.
	 *
	 * @return void
	 */
	protected function import_stats( $data ) {
		if ( $data && ! empty( $data ) ) {
			foreach ( $data as $stat ) {
				$entry = $stat['item'];

				if ( ! isset( $this->imported_ad_ids[ 'imported_post_id_' . $entry['ad_id'] ] ) ) {
					continue;
				}

				$new_ad_id = $this->imported_ad_ids[ 'imported_post_id_' . $entry['ad_id'] ];

				if ( $entry['stat_type'] === 'impression' ) {
					$table = TrackingLocal::get_tracking_table( 'impressions' );
				} elseif ( $entry['stat_type'] === 'click' ) {
					$table = TrackingLocal::get_tracking_table( 'clicks' );
				}

				$args = array(
					$table,
					$new_ad_id,
					$entry['timestamp'],
					$entry['count'],
				);

				global $wpdb;
				$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (`ad_id`, `timestamp`, `count`) VALUES (%d, %d, %d) ON DUPLICATE KEY UPDATE ad_id=ad_id', $args ) );
			}
		}
	}

	/**
	 * Import groups. Assumes data has already been processed, formatted, and sanitized.
	 *
	 * @param array $data Array of terms and term meta.
	 *
	 * @return void|bool
	 */
	protected function import_groups( $data ) {
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
			$allowed_meta_keys = array_merge( array_keys( UtilTools::headings( 'groups', false, true, true, false ) ), $allowed_import_keys );

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

	/**
	 * Update group meta with newly imported post IDs.
	 *
	 * @return void
	 */
	private function import_group_post_relationships() {

		if ( ! empty( $this->group_meta_after_ads ) ) {
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
	 * @param array  $args Optional arguments used during import.
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
	 * @param array $args Optional arguments used during import.
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
			$new_post_id = $this->import_post( $ad, AdCommander::posttype_ad(), $do_not_copy, array_keys( UtilTools::headings( 'ads', false, true, true, false ) ), $args );

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
	 * Import placements. Assumes data has already been processed, formatted, and sanitized.
	 *
	 * @param array $data Array of posts and post meta.
	 * @param array $args Optional arguments used during import.
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
			$new_post_id = $this->import_post( $ad, AdCommander::posttype_placement(), $do_not_copy, array_keys( UtilTools::headings( 'placements', false, true, true, false ) ), $args );

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
