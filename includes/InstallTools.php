<?php
namespace ADCmdr;

/**
 * Class for handling various tasks during activation, updating, etc.
 */
class InstallTools {

	/**
	 * Fired on plugin activation.
	 *
	 * This is currently disabled. Doesn't work if Ad Commander isn't running, and it really isn't needed anyway.
	 *
	 * @return void
	 */
	public static function activate() {
		// self::maybe_update();
	}

	/**
	 * Set the plugin version in the database to the current version.
	 *
	 * @return void
	 */
	public static function set_dbversion() {
		Options::instance()->update( 'version_dt', AdCommanderTools::version() );
	}

	/**
	 * If the database version doesn't match the current version, run updates.
	 *
	 * @return void
	 */
	public static function maybe_update() {
		if ( ! wp_doing_ajax() && Util::get_dbversion( 'version_dt' ) !== AdCommanderTools::version() ) {
			self::update();
		}
	}

	/**
	 * Tasks to run during a version update.
	 *
	 * @return void
	 */
	public static function update() {
		self::set_dbversion();
	}
}
