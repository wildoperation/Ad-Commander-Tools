<?php
namespace ADCmdr;

/**
 * Manage stats functionality
 */
class StatsDelete extends AdminTools {

	/**
	 * Hooks
	 */
	public function hooks() {
		add_action( 'admin_action_adcmdr-do_delete_rogue_stats', array( $this, 'do_delete_rogue_stats' ) );
		add_action( 'admin_action_adcmdr-do_delete_all_stats', array( $this, 'do_delete_all_stats' ) );
		add_action( 'admin_action_adcmdr-do_delete_ad_stats', array( $this, 'do_delete_ad_stats' ) );

		add_action( 'admin_notices', array( $this, 'delete_stats_success' ) );
		add_action( 'admin_notices', array( $this, 'delete_stats_fail' ) );
	}

	/**
	 * Add admin notice on delete success.
	 */
	public function delete_stats_success() {
		if ( isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) === Util::ns( 'delete_stats_success' ) ) {
			$delete_stats_nonce = $this->nonce_array( 'adcmdr-do_delete_stats', 'stats' );
			if ( check_admin_referer( $delete_stats_nonce['action'], $delete_stats_nonce['name'] ) && current_user_can( AdCommander::capability() ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php esc_html_e( 'Your stats were successfully deleted.', 'ad-commander-tools' ); ?>
					</p>
				</div>
					<?php
			}
		}
	}

	/**
	 * Add admin notice on delete fail.
	 */
	public function delete_stats_fail() {
		if ( isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) === Util::ns( 'delete_stats_fail' ) ) {
			$delete_stats_nonce = $this->nonce_array( 'adcmdr-do_delete_stats', 'stats' );
			if ( check_admin_referer( $delete_stats_nonce['action'], $delete_stats_nonce['name'] ) && current_user_can( AdCommander::capability() ) ) {
				?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<?php esc_html_e( 'We were unable to delete your stats.', 'ad-commander-tools' ); ?>
					</p>
				</div>
					<?php
			}
		}
	}

	/**
	 * Redirect after stat delete and include arguments.
	 *
	 * @param array  $nonce The nonce.
	 * @param string $type The type of delete completed.
	 * @param bool   $success Whether this is a successful redirect or a failure.
	 *
	 * @return void
	 */
	public function redirect( $nonce, $type, $success = true ) {
		$url = admin_url( self::admin_path( 'tools' ) );

		$url = add_query_arg(
			array(
				'action'       => ( $success ) ? Util::ns( 'delete_stats_success' ) : Util::ns( 'delete_stats_fail' ),
				'delete_type'  => $type,
				$nonce['name'] => wp_create_nonce( $nonce['action'] ),
				'tab'          => 'adcmdr_delete_stats',
			),
			$url
		);

		wp_safe_redirect( sanitize_url( $url ) );
		exit;
	}

	/**
	 * Delete stats for a specific ad.
	 *
	 * @return bool|void
	 */
	public function do_delete_ad_stats() {
		$delete_stats_nonce = $this->nonce_array( 'adcmdr-do_delete_ad_stats', 'stats' );
		$redirect_nonce     = $this->nonce_array( 'adcmdr-do_delete_stats', 'stats' );

		if ( ! isset( $_REQUEST['action'] ) ||
			! check_admin_referer( $delete_stats_nonce['action'], $delete_stats_nonce['name'] ) ||
			! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		if ( sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) !== Util::ns( 'do_delete_ad_stats' ) ) {
			return false;
		}

		if ( ! isset( $_REQUEST['adcmdr_confirm_action'] ) ||
			( isset( $_REQUEST['adcmdr_confirm_action'] ) && ! Util::truthy( sanitize_text_field( wp_unslash( $_REQUEST['adcmdr_confirm_action'] ) ) ) ) ||
			! isset( $_REQUEST['adcmdr_ad_id'] ) ||
			( isset( $_REQUEST['adcmdr_ad_id'] ) && intval( $_REQUEST['adcmdr_ad_id'] ) <= 0 )
			) {
			$this->redirect( $redirect_nonce, 'delete_ad', false );
		}

		$ad_id = intval( $_REQUEST['adcmdr_ad_id'] );

		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE ad_id = %d', array( TrackingLocal::get_tracking_table( 'impressions' ), $ad_id ) ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE ad_id = %d', array( TrackingLocal::get_tracking_table( 'clicks' ), $ad_id ) ) );

		$this->redirect( $redirect_nonce, 'delete_ad' );
	}

	/**
	 * Delete all stats.
	 *
	 * @return bool|void
	 */
	public function do_delete_all_stats() {
		$delete_stats_nonce = $this->nonce_array( 'adcmdr-do_delete_all_stats', 'stats' );
		$redirect_nonce     = $this->nonce_array( 'adcmdr-do_delete_stats', 'stats' );

		if ( ! isset( $_REQUEST['action'] ) ||
			! check_admin_referer( $delete_stats_nonce['action'], $delete_stats_nonce['name'] ) ||
			! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		if ( sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) !== Util::ns( 'do_delete_all_stats' ) ) {
			return false;
		}

		if ( ! isset( $_REQUEST['adcmdr_confirm_action'] ) || ( isset( $_REQUEST['adcmdr_confirm_action'] ) && ! Util::truthy( sanitize_text_field( wp_unslash( $_REQUEST['adcmdr_confirm_action'] ) ) ) ) ) {
			$this->redirect( $redirect_nonce, 'delete_all', false );
		}

		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', TrackingLocal::get_tracking_table( 'impressions' ) ) );
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', TrackingLocal::get_tracking_table( 'clicks' ) ) );

		$this->redirect( $redirect_nonce, 'delete_all' );
	}

	/**
	 * Delete stats for ads that no longer exist.
	 *
	 * @return bool|void
	 */
	public function do_delete_rogue_stats() {
		$delete_stats_nonce = $this->nonce_array( 'adcmdr-do_delete_rogue_stats', 'stats' );
		$redirect_nonce     = $this->nonce_array( 'adcmdr-do_delete_stats', 'stats' );

		if ( ! isset( $_REQUEST['action'] ) ||
			! check_admin_referer( $delete_stats_nonce['action'], $delete_stats_nonce['name'] ) ||
			! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		if ( sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) !== Util::ns( 'do_delete_rogue_stats' ) ) {
			return false;
		}

		if ( ! isset( $_REQUEST['adcmdr_confirm_action'] ) || ( isset( $_REQUEST['adcmdr_confirm_action'] ) && ! Util::truthy( sanitize_text_field( wp_unslash( $_REQUEST['adcmdr_confirm_action'] ) ) ) ) ) {
			$this->redirect( $redirect_nonce, 'delete_rogue', false );
		}

		$rogue = self::find_rogue_entries();

		if ( $rogue && ! empty( $rogue ) ) {
			global $wpdb;

			if ( isset( $rogue['impressions'] ) && ! empty( $rogue['impressions'] ) ) {
				$ad_ids             = array_map( 'absint', array_unique( wp_list_pluck( $rogue['impressions'], 'ad_id' ) ) );
				$ad_ids_placeholder = implode( ', ', array_fill( 0, count( $ad_ids ), '%d' ) );

				$args = array_merge( array( TrackingLocal::get_tracking_table( 'impressions' ) ), $ad_ids );
				$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE ad_id IN ($ad_ids_placeholder)", $args ) );
			}

			if ( isset( $rogue['clicks'] ) && ! empty( $rogue['clicks'] ) ) {
				$ad_ids             = array_map( 'absint', array_unique( wp_list_pluck( $rogue['clicks'], 'ad_id' ) ) );
				$ad_ids_placeholder = implode( ', ', array_fill( 0, count( $ad_ids ), '%d' ) );

				$args = array_merge( array( TrackingLocal::get_tracking_table( 'clicks' ) ), $ad_ids );
				$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE ad_id IN ($ad_ids_placeholder)", $args ) );
			}
		}

		$this->redirect( $redirect_nonce, 'delete_rogue' );
	}

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
