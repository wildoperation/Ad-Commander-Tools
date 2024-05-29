<?php
namespace ADCmdr;

/**
 * Misc data used throughout this plugin.
 */
class AdCommanderDt {
	/**
	 * Current version of AdCommanderPro.
	 *
	 * @return string
	 */
	public static function version() {
		return '1.0.0';
	}

	/**
	 * Current version of AdCommanderPro.
	 *
	 * @return string
	 */
	public static function required_adcmdr_version() {
		// TODO: Update this at launch to the current version of Ad Commander.
		return '1.0.7';
	}

	/**
	 * The path to the assets directory.
	 *
	 * @return string
	 */
	public static function assets_path() {
		return ADCMDRDT_PLUGIN_DIR . 'dist/';
	}

	/**
	 * The URL to the assets directory.
	 *
	 * @return string
	 */
	public static function assets_url() {
		return ADCMDRDT_PLUGIN_URL . 'dist/';
	}

	/**
	 * Display a notice on the plugin list page.
	 *
	 * @param string $notice Notice to display.
	 *
	 * @return void
	 */
	public static function plugin_list_notice( $notice ) {
		static $columns;

		if ( is_null( $columns ) ) {
			$columns = count( _get_list_table( 'WP_Plugins_List_Table' )->get_columns() );
		}

		printf(
			'<tr class="plugin-update-tr active"><td class="plugin-update colspanchange" colspan="%d"><div class="update-message notice inline notice-warning notice-alt"><p>%s</p></div></td></tr>',
			intval( $columns ),
			wp_kses_post( $notice )
		);
	}
}
