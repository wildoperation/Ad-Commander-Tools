<?php
namespace ADCmdr;

/**
 * Import functionality
 */
class Import extends AdminDt {

	/**
	 * Redirect after an import and include arguments.
	 *
	 * @param array  $nonce The import nonce.
	 * @param string $type The type of import completed.
	 *
	 * @return void
	 */
	public function redirect( $nonce, $type, $success = true ) {
		$url = admin_url( self::admin_path( 'tools' ) );

		$url = add_query_arg(
			array(
				'action'       => ( $success ) ? Util::ns( 'import_success' ) : Util::ns( 'import_fail' ),
				'import_type'  => $type,
				$nonce['name'] => wp_create_nonce( $nonce['action'] ),
				'tab'          => 'adcmdr_import',
			),
			$url
		);

		wp_safe_redirect( sanitize_url( $url ) );
		exit;
	}
}
