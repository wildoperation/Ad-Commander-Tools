<?php
/**
 * Plugin Name:      Ad Commander Tools
 * Plugin URI:       https://wpadcommander.com
 * Description:      Add-on for the Ad Commander plugin that allows you to import, export, and manage ad statistics.
 * Requires Plugins: ad-commander
 * Version:          1.0.3
 * Author:           Wild Operation
 * Author URI:       https://wildoperation.com
 * License:          GPL-3.0
 * License URI:      http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:      ad-commander-tools
 *
 * @package WordPress
 * @subpackage ad-commander-tools
 * @since 1.0.0
 * @version 1.0.3
 */

/* Abort! */
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ADCMRDRTOOLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADCMRDRTOOLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ADCMRDRTOOLS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ADCMRDRTOOLS_PLUGIN_FILE', __FILE__ );

/**
 * Load
 */
require ADCMRDRTOOLS_PLUGIN_DIR . 'vendor/autoload.php';


/**
 * Initialize; plugins_loaded
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'ADCMDR_LOADED' ) || ADCMDR_LOADED !== true ) {

			add_action(
				'admin_notices',
				function () {
					?>
				<div class="notice notice-error">
					<p>
					<?php
						/* translators: %1$s: anchor tag with URL, %2$s: close anchor tag */
						printf( esc_html__( 'Ad Commander Tools requires the %1$sAd Commander plugin%2$s. Please enable Ad Commander to continue.', 'ad-commander-tools' ), '<a href="https://wordpress.org/plugins/ad-commander/" target="_blank">', '</a>' );
					?>
					</p>
				</div>
					<?php
				}
			);

			add_action(
				'load-plugins.php',
				function () {
					add_action(
						'after_plugin_row_' . ADCMRDRTOOLS_PLUGIN_BASENAME,
						function () {
							ADCmdr\AdCommanderTools::plugin_list_notice(
								/* translators: %1$s: anchor tag with URL, %2$s: close anchor tag */
								sprintf( esc_html__( 'Ad Commander Tools requires the %1$sAd Commander plugin%2$s. Please enable Ad Commander to continue.', 'ad-commander-tools' ), '<a href="https://wordpress.org/plugins/ad-commander/" target="_blank">', '</a>' )
							);
						},
						10,
						2
					);
				}
			);

			return false;
		}

		if ( ADCmdr\UtilTools::needs_adcmdr_upgrade() ) {
			add_action(
				'admin_notices',
				function () {
					?>
				<div class="notice notice-error">
					<p>
					<?php
						/* translators: %1$s: The required version of Ad Commander */
						echo esc_html( sprintf( __( 'Ad Commander Tools requires version %1$s or greater of Ad Commander. Please update Ad Commander.', 'ad-commander-tools' ), AdCmdr\AdCommanderTools::required_adcmdr_version() ) );
					?>
					</p>
				</div>
					<?php
				}
			);

			add_action(
				'load-plugins.php',
				function () {
					add_action(
						'after_plugin_row_' . ADCMRDRTOOLS_PLUGIN_BASENAME,
						function () {
							ADCmdr\AdCommanderTools::plugin_list_notice(
								/* translators: %1$s: The required version of Ad Commander */
								sprintf( esc_html__( 'Ad Commander Tools requires version %1$s or greater of Ad Commander. Please update Ad Commander.', 'ad-commander-tools' ), AdCmdr\AdCommanderTools::required_adcmdr_version() )
							);
						},
						10,
						2
					);
				}
			);

			return false;
		}

		/**
		 * DT is loaded.
		 */
		define( 'ADCMRDRTOOLS_LOADED', true );

		/**
		 * Has the plugin version updated?
		 */
		ADCmdr\InstallTools::maybe_update();

		/**
		 * Initiate classes and their hooks.
		 */
		$classes = array(
			'ADCmdr\LocalizeTools',
			'ADCmdr\AdminTools',
			'ADCmdr\Export',
			'ADCmdr\ImportBundle',
			'ADCmdr\StatsDelete',
		);

		foreach ( $classes as $class ) {
			$instance = new $class();

			if ( method_exists( $instance, 'hooks' ) ) {
				$instance->hooks();
			}
		}
	},
	12
);
