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
	public static function headings( $type ) {
		if ( $type === 'ads' ) {
			return array_unique(
				array_merge(
					array( 'ID', 'post_status', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_name', 'post_modified', 'post_modified_gmt', 'menu_order' ),
					self::prefix_keys( AdPostMeta::post_meta_keys() ),
					array( 'groups', 'source' )
				)
			);
		}

		if ( $type === 'groups' ) {
			return array_unique(
				array_merge(
					array( 'term_id', 'name', 'slug' ),
					self::prefix_keys( GroupTermMeta::tax_group_meta_keys() ),
					array( 'source' )
				)
			);
		}

		if ( $type === 'placements' ) {
			return array_unique(
				array_merge(
					array( 'ID', 'post_status', 'post_date', 'post_date_gmt', 'post_title', 'post_name', 'post_modified', 'post_modified_gmt', 'menu_order' ),
					self::prefix_keys( PlacementPostMeta::post_meta_keys() ),
					array( 'source' )
				)
			);
		}

		return array();
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

		foreach ( array_keys( $arr ) as $key ) {
			$keys[] = $wo_meta->make_key( $key );
		}

		return $keys;
	}
}
