<?php
/**
 * Plugin Name:     Ad Commander Tools
 * Plugin URI:      https://wpadcommander.com
 * Description:     Import, export, and data management tools for Ad Commander.
 * Version:         1.0.0
 * Author:          Wild Operation
 * Author URI:      https://wildoperation.com
 * License:         GPL-3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:     ad-commander-tools
 *
 * @package WordPress
 * @subpackage Import, export, and data management tools for Ad Commander.
 * @since 1.0.0
 * @version 1.0.0
 */

/* Abort! */
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ADCMDRDT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADCMDRDT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ADCMDRDT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ADCMDRDT_PLUGIN_FILE', __FILE__ );

/**
 * Load
 */
require ADCMDRDT_PLUGIN_DIR . 'vendor/autoload.php';


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
						'after_plugin_row_' . ADCMDRDT_PLUGIN_BASENAME,
						function () {
							ADCmdr\AdCommanderDt::plugin_list_notice(
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

		if ( ADCmdr\UtilDt::needs_adcmdr_upgrade() ) {
			add_action(
				'admin_notices',
				function () {
					?>
				<div class="notice notice-error">
					<p>
					<?php
						/* translators: %1$s: The required version of Ad Commander */
						printf( esc_html__( 'Ad Commander Tools requires version %1$s or greater of Ad Commander. Please upgrade Ad Commander continue.', 'ad-commander-tools' ), AdCmdr\AdCommanderDt::required_adcmdr_version() );
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
						'after_plugin_row_' . ADCMDRDT_PLUGIN_BASENAME,
						function () {
							ADCmdr\AdCommanderDt::plugin_list_notice(
								/* translators: %1$s: The required version of Ad Commander */
								sprintf( esc_html__( 'Ad Commander Tools requires version %1$s or greater of Ad Commander. Please upgrade Ad Commander continue.', 'ad-commander-tools' ), AdCmdr\AdCommanderDt::required_adcmdr_version() )
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
		define( 'ADCMDRDT_LOADED', true );

		/**
		 * Has the plugin version updated?
		 */
		ADCmdr\InstallDt::maybe_update();

		/**
		 * Initiate classes and their hooks.
		 */
		$classes = array(
			'ADCmdr\AdminDt',
			'ADCmdr\Export',
			'ADCmdr\ImportBundle',
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
