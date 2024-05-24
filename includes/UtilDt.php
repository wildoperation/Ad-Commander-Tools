<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Shared utility functionality
 */
class UtilDt {

	/**
	 * Allowed CSV headings for import and export
	 *
	 * @param string $type The type of CSV file.
	 *
	 * @return array
	 */
	public static function headings( $type, $include_primary = true, $include_meta = true, $include_extra = true, $prefix_meta_keys = true ) {
		$primary  = array();
		$meta     = array();
		$extra    = array();
		$headings = array();

		if ( $type === 'ads' ) {
			$primary = array( 'ID', 'post_status', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_name', 'post_modified', 'post_modified_gmt', 'menu_order' );
			$meta    = array_keys( AdPostMeta::post_meta_keys() );
			$extra   = array( 'groups', 'source', 'source_site', 'featured_image' );
		}

		if ( $type === 'groups' ) {
			$primary = array( 'term_id', 'name', 'slug' );
			$meta    = array_keys( GroupTermMeta::tax_group_meta_keys() );
			$extra   = array( 'source', 'source_site' );
		}

		if ( $type === 'placements' ) {
			$primary = array( 'ID', 'post_status', 'post_date', 'post_date_gmt', 'post_title', 'post_name', 'post_modified', 'post_modified_gmt', 'menu_order' );
			$meta    = array_keys( PlacementPostMeta::post_meta_keys() );
			$extra   = array( 'source', 'source_site' );
		}

		if ( $include_primary ) {
			$headings = array_merge( $headings, $primary );
		}

		if ( $include_meta ) {
			if ( $prefix_meta_keys ) {
				$meta = self::prefix_keys( $meta );
			}

			$headings = array_merge( $headings, $meta );
		}

		if ( $include_extra ) {
			$headings = array_merge( $headings, $extra );
		}

		return array_unique( $headings );
	}


	/**
	 * Prefix an array of keys with meta namespace.
	 *
	 * @param array $arr The array to prefix.
	 *
	 * @return array
	 */
	public static function prefix_keys( $arr ) {
		$wo_meta = new WOMeta( AdCommander::ns() );

		$keys = array();

		foreach ( $arr as $key ) {
			$keys[] = $wo_meta->make_key( $key );
		}

		return $keys;
	}
}
