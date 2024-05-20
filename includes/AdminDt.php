<?php
namespace ADCmdr;

/**
 * Admin functionality for Ad Commander Data Tools
 */
class AdminDt extends Admin {

	/**
	 * Hooks
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ) );
	}

	/**
	 * The title of the Tools page
	 *
	 * @return string
	 */
	public function tools_title() {
		return __( 'Tools', 'ad-commander-data-tools' );
	}

	/**
	 * Create admin menu.
	 *
	 * @return void
	 */
	public function create_admin_menu() {
		$hook = add_submenu_page(
			self::admin_slug(),
			self::tools_title(),
			self::tools_title(),
			AdCommander::capability(), // TODO: 'manage_options'?
			self::admin_slug( 'tools' ),
			array( $this, 'tools_page' ),
			25
		);

		$this->admin_menu_hooks[ self::admin_slug( 'tools' ) ] = $hook;
	}

	/**
	 * The tools page.
	 *
	 * @return void
	 */
	public function tools_page() {
	}
}
