<?php
namespace ADCmdr;

/**
 * Interface with WP_Filesystem
 */
class Filesystem {

	/**
	 * An instance of this class.
	 *
	 * @var Filesystem|null
	 */
	private static $instance = null;

	/**
	 * Create an instance of self if necessary and return it.
	 *
	 * @return Filesystem
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set the WordPress filesystem method.
	 *
	 * @return string
	 */
	public function filesystem_method() {
		return 'direct';
	}

	/**
	 * Initialize the WordPress filesystem.
	 *
	 * @return bool
	 */
	public function init_wp_filesystem() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		add_filter( 'filesystem_method', array( $this, 'filesystem_method' ) );
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! isset( $wp_filesystem->method ) || $wp_filesystem->method !== 'direct' ) {
			return false;
		}

		return true;
	}

	/**
	 * Remove the filesystem method filter.
	 */
	public function end_wp_filesystem() {
		remove_filter( 'filesystem_method', array( $this, 'filesystem_method' ) );
	}

	/**
	 * Maybe create a directory.
	 *
	 * @param string $dir The directory to create.
	 * @param bool   $create_index Create an index file in the directory.
	 *
	 * @return bool|string
	 */
	public function maybe_create_dir( $dir, $create_index = true ) {

		if ( ! $dir ) {
			return false;
		}

		/**
		 * TODO: Consider creating .htaccess deny file
		 * TODO: Does this work with multisite?
		 */

		if ( ! $this->init_wp_filesystem() ) {
			return false;
		}

		global $wp_filesystem;

		$dir = trailingslashit( $dir );

		if ( ! file_exists( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
		}

		if ( $create_index && ! file_exists( $dir . 'index.html' ) ) {
			$wp_filesystem->put_contents(
				$dir . 'index.html',
				'',
				FS_CHMOD_FILE
			);
		}

		$this->end_wp_filesystem();

		return $dir;
	}

	/**
	 * Define the tmp dir.
	 *
	 * @param string $fallback_dir The directory if one doesn't exist yet.
	 *
	 * @return bool|string
	 */
	public function wp_tmp_dir( $fallback_dir ) {
		$tmp = get_temp_dir();

		if ( wp_is_writable( $tmp ) ) {
			return $tmp;
		}

		$free_space = @disk_free_space( get_temp_dir() );

		if ( ! $free_space || ( $free_space / MB_IN_BYTES ) < 200 ) {
			$tmp = $this->maybe_create_dir( trailingslashit( $fallback_dir ) );

			if ( $tmp && @is_dir( $tmp ) && wp_is_writable( $tmp ) ) {
				return $tmp;
			}
		}

		return false;
	}
}
