<?php
namespace ADCmdr;

/**
 * Import AdRotate ads into Ad Commander
 */
class ImportPluginAdRotate extends ImportPlugin {
	protected function build_data( $all_import_types, $import_bundle_options ) {

		$data = array();

		foreach ( $all_import_types as $import_type ) {
			if ( ! in_array( $import_type, $import_bundle_options, true ) ) {
				continue;
			}

			switch ( $import_type ) {
				case 'groups':
					$this_data = $this->import_groups();
					break;

				case 'ads':
					$this_data = $this->import_ads();
					break;

				case 'placements':
					$this_data = $this->import_placements();
					break;
			}

			$data[ $import_type ] = $this_data;
		}

		return $data;
	}

	private function import_groups() {
	}

	private function import_ads() {
	}

	private function import_placements() {
	}
}
