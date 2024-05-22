<?php
namespace ADCmdr;

/**
 * Import functionality for bundle uploads
 */
class ImportBundle extends Import {
	/**
	 * Hooks
	 */
	public function hooks() {
		add_action( 'admin_action_adcmdr-do_import_bundle', array( $this, 'do_import_bundle' ) );
		// add_action( 'admin_notices', array( $this, 'export_success' ) );
	}

	/**
	 * Get the local path to the import folder.
	 *
	 * @return string
	 */
	public static function import_path() {
		return apply_filters( 'adcmdr_import_uploads_path', '/ad-commander/import' );
	}

	/**
	 * Get the full directory for the export folder.
	 *
	 * @return string
	 */
	public static function import_dir() {
		$wp_upload_dir = wp_upload_dir();

		if ( ! isset( $wp_upload_dir['basedir'] ) ) {
			return false;
		}

		return $wp_upload_dir['basedir'] . trailingslashit( self::import_path() );
	}

	/**
	 * Perform an import.
	 *
	 * @return void|bool
	 */
	public function do_import_bundle() {

		/**
		 * Make sure we have everything to proceed.
		 */
		$import_nonce = $this->nonce_array( 'adcmdr-do_import_bundle', 'import' );

		if ( ! isset( $_REQUEST['action'] ) ||
		! check_admin_referer( $import_nonce['action'], $import_nonce['name'] ) ||
		! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		if ( sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) !== Util::ns( 'do_import_bundle' ) ||
		! isset( $_FILES['adcmdr_import_bundle_file'] ) ||
		( isset( $_FILES['adcmdr_import_bundle_file'] ) && isset( $_FILES['adcmdr_import_bundle_file']['tmp_name'] ) && ! sanitize_text_field( $_FILES['adcmdr_import_bundle_file']['tmp_name'] ) ) ||
		( isset( $_FILES['adcmdr_import_bundle_file'] ) && isset( $_FILES['adcmdr_import_bundle_file']['name'] ) && ! sanitize_text_field( $_FILES['adcmdr_import_bundle_file']['name'] ) ) ) {
			$this->redirect( $import_nonce, 'bundle', false );
		}

		/**
		 * Sanitize and prep filenames
		 */
		$bundle_tmp  = sanitize_text_field( $_FILES['adcmdr_import_bundle_file']['tmp_name'] );
		$bundle_name = sanitize_text_field( $_FILES['adcmdr_import_bundle_file']['name'] );
		$file_type   = wp_check_filetype_and_ext( $bundle_tmp, $bundle_name );
		$basename    = pathinfo( $bundle_name, PATHINFO_FILENAME );

		if ( ! $basename ||
		! isset( $_REQUEST['adcmdr_import_bundle_options'] ) ||
		empty( $_REQUEST['adcmdr_import_bundle_options'] ) ||
		( strtolower( $file_type['ext'] ) !== 'zip' && strtolower( $file_type['type'] ) !== 'application/zip' ) ) {
			$this->cleanup_and_redirect( $import_nonce, $bundle_tmp );
		}

		/**
		 * Setup filesystem
		 */
		$filesystem = Filesystem::instance();

		if ( ! $filesystem->init_wp_filesystem() ) {
			$this->cleanup_and_redirect( $import_nonce, $bundle_tmp );
		}

		$tmp_dir = $filesystem->wp_tmp_dir( self::import_dir() );

		if ( ! $tmp_dir ) {
			$this->cleanup_and_redirect( $import_nonce, $bundle_tmp );
		}

		$extract_to_dir = $filesystem->maybe_create_dir( trailingslashit( $tmp_dir ) . $basename );

		if ( ! $extract_to_dir ) {
			$this->cleanup_and_redirect( $import_nonce, $bundle_tmp );
		}

		/**
		 * Unzip
		 */
		$zip = new \ZipArchive();
		if ( $zip->open( $bundle_tmp ) ) {
			$zip->extractTo( $extract_to_dir );
			$zip->close();
		} else {
			$this->cleanup_and_redirect( $import_nonce, $bundle_tmp, $extract_to_dir );
		}

		/**
		 * Find available import files
		 */
		global $wp_filesystem;

		$filelist = $wp_filesystem->dirlist( $extract_to_dir, false );

		if ( is_array( $filelist ) ) {
			$filelist = array_filter( $filelist, fn ( $file ) => ( $file['type'] === 'f' && stripos( $file['name'], '.csv' ) !== false && stripos( $file['name'], 'adcmdr_' ) !== false ) );
		}

		if ( ! $filelist || ! is_array( $filelist ) || empty( $filelist ) ) {
			$this->cleanup_and_redirect( $import_nonce, $bundle_tmp, $extract_to_dir );
		}

		$filelist = array_values( wp_list_pluck( $filelist, 'name' ) );

		/**
		 * Finally, we can load the files.
		 */
		$import_bundle_options = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['adcmdr_import_bundle_options'] ) );

		$all_import_types = array( 'groups', 'ads', 'placements', 'stats' ); // Order matters - this is how they will be imported.
		foreach ( $all_import_types as $import_type ) {
			if ( ! in_array( $import_type, $import_bundle_options, true ) ) {
				continue;
			}

			$file = $this->find_file( $import_type, $filelist );
			if ( ! $file ) {
				continue;
			}

			$data = $this->csv_to_array( $extract_to_dir . $file );

			if ( ! empty( $data ) ) {
				$this->import_data( $import_type, $data );
			}
		}

		/**
		 * Finished
		 */
		$this->cleanup_and_redirect( $import_nonce, $bundle_tmp, $extract_to_dir, false );
	}

	/**
	 * Import data by type; Determines which import function to call to process data.
	 *
	 * @param string $import_type The type of data we're importing.
	 * @param array  $data The data to import.
	 *
	 * @return bool
	 */
	private function import_data( $import_type, $data ) {
	}

	/**
	 * Find a file with a given string in the filename. Assumes we have already filtered down to accepted filetypes (e.g., .csv)
	 *
	 * @param string $type The type of import we are searching for (e.g., 'groups').
	 * @param array  $filelist The list of files.
	 *
	 * @return bool|string
	 */
	private function find_file( $type, $filelist ) {
		if ( empty( $filelist ) || ! $type ) {
			return false;
		}

		foreach ( $filelist as $file ) {
			if ( stripos( $file, 'adcmdr_' . $type ) !== false ) {
				return $file;
			}
		}

		return false;
	}

	/**
	 * Delete files and dirs, remove filesystem filters.
	 *
	 * @param array $nonce The import nonce.
	 * @param array $delete_files Files to delete.
	 * @param array $delete_dirs Directories to remove.
	 * @param bool  $fail Whether this is a failure or success redirect.
	 *
	 * @return void
	 */
	private function cleanup_and_redirect( $nonce, $delete_files = array(), $delete_dirs = array(), $fail = true ) {
		global $wp_filesystem;

		if ( ! is_array( $delete_files ) ) {
			$delete_files = array( $delete_files );
		}

		if ( ! empty( $delete_files ) ) {
			foreach ( $delete_files as $file ) {
				$wp_filesystem->delete( $file );
			}
		}

		if ( ! is_array( $delete_dirs ) ) {
			$delete_dirs = array( $delete_dirs );
		}

		if ( ! empty( $delete_dirs ) ) {
			foreach ( $delete_dirs as $dir ) {
				$wp_filesystem->rmdir( $dir );
			}
		}

		Filesystem::instance()->end_wp_filesystem();

		$this->redirect( $nonce, 'bundle', $fail );
	}

	/**
	 * Read a CSV file into an associative array.
	 *
	 * @param string $file Path to the file.
	 *
	 * @return array
	 */
	public static function csv_to_array( $file ) {

		if ( ! $file ) {
			return array();
		}

		$array  = array();
		$fields = array();

		$i      = 0;
		$handle = fopen( $file, 'r' );

		if ( $handle ) {
			while ( ( $row = fgetcsv( $handle, 4096 ) ) !== false ) {
				if ( empty( $fields ) ) {
						$fields = $row;
						continue;
				}

				foreach ( $row as $k => $value ) {
					$array[ $i ][ $fields[ $k ] ] = $value;
				}

				++$i;
			}
			if ( ! feof( $handle ) ) {
				wo_log( "Error: unexpected fgets() fail\n" );
			}

			fclose( $handle );
		}

		return $array;
	}
}
