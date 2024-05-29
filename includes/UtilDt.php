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
			$primary = array(
				'ID'                => array( 'type' => 'int' ),
				'post_status'       => array(
					'type'       => 'str',
					'restricted' => Util::any_post_status( 'trash' ),
					'default'    => 'draft',
				),
				'post_date'         => array( 'type' => 'str' ),
				'post_date_gmt'     => array( 'type' => 'str' ),
				'post_content'      => array( 'type' => 'editor' ),
				'post_title'        => array( 'type' => 'str' ),
				'post_name'         => array( 'type' => 'str' ),
				'post_modified'     => array( 'type' => 'str' ),
				'post_modified_gmt' => array( 'type' => 'str' ),
				'menu_order'        => array(
					'type'    => 'int',
					'default' => 0,
				),
			);
			$meta    = AdPostMeta::post_meta_keys();
			$extra   = array(
				'groups'             => array( 'type' => 'str' ),
				'source'             => array( 'type' => 'str' ),
				'source_site'        => array( 'type' => 'str' ),
				'featured_image_url' => array( 'type' => 'str' ),
				'thumbnail_id'       => array( 'type' => 'int' ),
			);
		}

		if ( $type === 'groups' ) {
			$primary = array(
				'term_id' => array( 'type' => 'int' ),
				'name'    => array( 'type' => 'str' ),
				'slug'    => array( 'type' => 'str' ),
			);
			$meta    = GroupTermMeta::tax_group_meta_keys();
			$extra   = array(
				'source'      => array( 'type' => 'str' ),
				'source_site' => array( 'type' => 'str' ),
			);
		}

		if ( $type === 'placements' ) {
			$primary = array(
				'ID'                => array( 'type' => 'int' ),
				'post_status'       => array(
					'type'       => 'str',
					'restricted' => Util::any_post_status( 'trash' ),
					'default'    => 'draft',
				),
				'post_date'         => array( 'type' => 'str' ),
				'post_date_gmt'     => array( 'type' => 'str' ),
				'post_title'        => array( 'type' => 'str' ),
				'post_name'         => array( 'type' => 'str' ),
				'post_modified'     => array( 'type' => 'str' ),
				'post_modified_gmt' => array( 'type' => 'str' ),
				'menu_order'        => array(
					'type'    => 'int',
					'default' => 0,
				),
			);
			$meta    = PlacementPostMeta::post_meta_keys();
			$extra   = array(
				'source'      => array( 'type' => 'str' ),
				'source_site' => array( 'type' => 'str' ),
			);
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

		return $headings;
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

		$keyed = array();

		foreach ( $arr as $key => $value ) {
			$keyed[ $wo_meta->make_key( $key ) ] = $value;
		}

		return $keyed;
	}
}
