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

			// The ad IDs won't match up, so we update this later.   * TODO: After ads are imported, need to add ad_order and ad_weight meta to each group.
			$do_not_copy_meta  = array( 'ad_order', 'ad_weights' );
			$allowed_meta_keys = array_merge( UtilDt::headings( 'groups', false, true, true, false ), $allowed_import_keys );

			foreach ( $meta as $meta_key => $meta_value ) {
				if ( ! in_array( $meta_key, $allowed_meta_keys, true ) || in_array( $meta_key, $do_not_copy_meta, true ) ) {
					continue;
				}

				$meta_key = $this->wo_meta()->make_key( $meta_key );

				delete_term_meta( $new_term['term_id'], $meta_key );
				add_term_meta( $new_term['term_id'], $meta_key, $meta_value );
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
	private function import_post( $data, $post_type, $do_not_copy, $allowed_keys ) {
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

		if ( ! in_array( 'post_status', $do_not_copy ) ) {
			$new_post_params['post_status'] = 'draft';
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

			$meta_key = $this->wo_meta()->make_key( $meta_key );
			delete_post_meta( $new_post_id, $meta_key );
			add_post_meta( $new_post_id, $meta_key, $meta_value );
		}

		return $new_post_id;
	}

	/**
	 * Import ads. Assumes data has already been processed, formatted, and sanitized.
	 *
	 * TODO: Import images
	 *
	 * @param array $data Array of posts and post meta.
	 *
	 * @return void|bool
	 */
	protected function import_ads( $data ) {
		if ( ! $data || empty( $data ) ) {
			return false;
		}

		$do_not_copy = array(
			'post' => array( 'ID', 'post_status', 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ),
			'meta' => array(),
		);

		$new_post_ids = array();

		foreach ( $data as $ad ) {
			$new_post_ids[] = $this->import_post( $ad, AdCommander::posttype_ad(), $do_not_copy, UtilDt::headings( 'ads', false, true, true, false ) );

			// Now import images.
		}
	}

	/**
	 * Import ads. Assumes data has already been processed, formatted, and sanitized.
	 *
	 * TODO: Update placement items
	 *
	 * @param array $data Array of posts and post meta.
	 *
	 * @return void|bool
	 */
	protected function import_placements( $data ) {
		if ( ! $data || empty( $data ) ) {
			return false;
		}

		$do_not_copy = array(
			'post' => array( 'ID', 'post_status', 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ),
			'meta' => array( 'placement_items' ),
		);

		$new_post_ids = array();

		foreach ( $data as $ad ) {
			$new_post_ids[] = $this->import_post( $ad, AdCommander::posttype_placement(), $do_not_copy, UtilDt::headings( 'placements', false, true, true, false ) );
		}
	}
}
