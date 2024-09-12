<?php
namespace ADCmdr;

/**
 * Load plugin textdomain
 */
class LocalizeTools {
	/**
	 * Hooks
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Loads the lugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		$locale = get_user_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'ad-commander-tools' );

		unload_textdomain( 'ad-commander-tools' );

		if ( load_textdomain( 'ad-commander-tools', WP_LANG_DIR . '/plugins/ad-commander-tools-' . $locale . '.mo' ) === false ) {
			load_textdomain( 'ad-commander-tools', WP_LANG_DIR . '/ad-commander-tools/ad-commander-tools-' . $locale . '.mo' );
		}

		load_plugin_textdomain( 'ad-commander-tools', false, dirname( ADCMDR_PLUGIN_BASENAME ) . '/languages' );
	}
}
