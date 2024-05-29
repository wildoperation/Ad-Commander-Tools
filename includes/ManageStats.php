<?php
namespace ADCmdr;

/**
 * Manage stats functionality
 */
class ManageStats {

	/**
	 * Find stat entries that are not for existing ads.
	 *
	 * @return array
	 */
	public static function find_rogue_entries() {
		global $wpdb;

		$ads = Query::ads( 'ID', 'asc', Util::any_post_status() );

		if ( ! empty( $ads ) ) {

			$ad_ids             = array_map( 'absint', wp_list_pluck( $ads, 'ID' ) );
			$ad_ids_placeholder = implode( ', ', array_fill( 0, count( $ad_ids ), '%d' ) );

			$impressions = $wpdb->get_results( $wpdb->prepare( "SELECT tbl.timestamp, tbl.ad_id FROM %i as tbl WHERE ad_id NOT IN ($ad_ids_placeholder)", array_merge( array( TrackingLocal::get_tracking_table( 'impressions' ) ), $ad_ids ) ) );
			$clicks      = $wpdb->get_results( $wpdb->prepare( "SELECT tbl.timestamp, tbl.ad_id FROM %i as tbl WHERE ad_id NOT IN ($ad_ids_placeholder)", array_merge( array( TrackingLocal::get_tracking_table( 'clicks' ) ), $ad_ids ) ) );

		} else {
			/**
			 * Everything is rogue.
			 */
			$impressions = $wpdb->get_results( $wpdb->prepare( 'SELECT tbl.timestamp, tbl.ad_id FROM %i as tbl', TrackingLocal::get_tracking_table( 'impressions' ) ) );
			$clicks      = $wpdb->get_results( $wpdb->prepare( 'SELECT tbl.timestamp, tbl.ad_id FROM %i as tbl', TrackingLocal::get_tracking_table( 'clicks' ) ) );

		}

		return array(
			'impressions' => ( ! $impressions || is_wp_error( $impressions ) ) ? array() : $impressions,
			'clicks'      => ( ! $clicks || is_wp_error( $clicks ) ) ? array() : $clicks,
		);
	}
}
