<?php
namespace ADCmdr;

/**
 * Import plugin data into Ad Commander
 */
class ImportPlugin extends Import {
	/**
	 * Hooks
	 */
	public function hooks() {
		add_action( 'admin_action_adcmdr-do_import_plugin', array( $this, 'do_import_plugin' ) );
	}

	/**
	 * Perform an import.
	 *
	 * @return void|bool
	 */
	public function do_import_plugin() {

		/**
		 * Make sure we have everything to proceed.
		 */
		$import_nonce = $this->nonce_array( 'adcmdr-do_import_plugin', 'import' );

		if ( ! isset( $_REQUEST['action'] ) ||
		! check_admin_referer( $import_nonce['action'], $import_nonce['name'] ) ||
		! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		$plugin_to_import      = isset( $_REQUEST['adcmdr_plugin_to_import'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['adcmdr_plugin_to_import'] ) ) : null;
		$import_bundle_options = isset( $_REQUEST['adcmdr_import_bundle_options'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['adcmdr_import_bundle_options'] ) ) : array();

		if ( sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) !== Util::ns( 'do_import_plugin' ) ||
			! in_array( $plugin_to_import, array_keys( UtilDt::importable_plugins() ), true ) ||
			empty( $import_bundle_options ) ) {
			$this->redirect( $import_nonce, 'plugin', false );
		}

		/**
		 * Build and import data from specified plugin.
		 */
		switch ( $plugin_to_import ) {
			case 'adrotate':
				$importer = new ImportPluginAdRotate();
				break;
		}

		$all_import_types = $this->all_import_types();
		$data             = $importer->build_data( $all_import_types, $import_bundle_options );

		foreach ( $all_import_types as $import_type ) {
			if ( ! in_array( $import_type, $import_bundle_options, true ) ) {
				continue;
			}

			if ( isset( $data[ $import_type ] ) && ! empty( $data[ $import_type ] ) ) {
				$this->import_data(
					$import_type,
					$data[ $import_type ],
					array(
						'importing_types' => $all_import_types,
						'set_post_status' => 'draft',
					)
				);
			}
		}
	}
}
