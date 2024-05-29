<?php
namespace ADCmdr;

/**
 * Admin functionality for Ad Commander Tools
 */
class AdminDt extends Admin {

	/**
	 * Admin menu hooks specific to this plugin.
	 *
	 * @var array
	 */
	private $admin_menu_hooks_dt = array();

	/**
	 * Nonces
	 *
	 * @var array
	 */
	private $nonce_arrays = array();

	/**
	 * Hooks
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_print_styles', array( $this, 'admin_print_styles' ) );

		add_filter( 'adcmdr_admin_menu_hooks', array( $this, 'admin_menu_hooks' ) );

		foreach ( $this->get_action_keys() as $key ) {
			$key_underscore = self::_key( $key );
			add_action( 'wp_ajax_' . $this->action_string( $key ), array( $this, 'action_' . $key_underscore ) );
		}

		/**
		 * TODO: Remove later
		 */
		if ( WP_DEBUG === true ) {
			add_filter(
				'adcmdr_dt_import_featured_images',
				function () {
					// return false;
					return true;
				}
			);
		}
	}

	/**
	 * Create or get nonce
	 *
	 * @param string $action The action for the nonce.
	 * @param string $key The key to create the nonce string from.
	 */
	protected function nonce_array( $action, $key ) {
		if ( ! isset( $this->nonce_arrays[ $action ] ) ) {
			$this->nonce_arrays[ $action ] = $this->nonce( $action, $key );
		}

		return $this->nonce_arrays[ $action ];
	}

	/**
	 * Enqueue scripts
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		if ( $this->is_screen( $this->admin_menu_hooks_dt ) ) {
			wp_enqueue_script( 'jquery' );

			$handle = Util::ns( 'dt-impexp' );

			wp_register_script(
				$handle,
				AdCommanderDt::assets_url() . 'js/importexport.js',
				array(
					'jquery',
				),
				AdCommanderDt::version(),
				array( 'in_footer' => true )
			);

			wp_enqueue_script( $handle );

			Util::enqueue_script_data(
				$handle,
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'actions' => $this->get_ajax_actions(),
				)
			);

		}
	}

	/**
	 * Get necessary action keys, which will be used to create wp_ajax hooks.
	 *
	 * @return array
	 */
	private function get_action_keys() {
		return array(
			'delete-bundle',
		);
	}

	/**
	 * Creates an array of all of the necessary actions.
	 *
	 * @return array
	 */
	private function get_ajax_actions() {
		$actions = array();

		foreach ( $this->get_action_keys() as $key ) {
			$actions[ self::_key( $key ) ] = array(
				'action'   => $this->action_string( $key ),
				'security' => wp_create_nonce( $this->nonce_string( $key ) ),
			);
		}

		return $actions;
	}

	/**
	 * Delete a bundle
	 *
	 * @return void
	 */
	public function action_delete_bundle() {
		$action = 'delete-bundle';

		check_ajax_referer( $this->nonce_string( $action ), 'security' );

		if ( ! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		$file = isset( $_REQUEST['file'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['file'] ) ) : false;

		if ( Export::instance()->delete_bundle( $file ) ) {
			wp_send_json_success(
				array(
					'action' => $action,
				)
			);
		}

		wp_die();
	}

	/**
	 * Enqueue styles if we're on a screen that needs them.
	 *
	 * @return void
	 */
	public function admin_print_styles() {
		if ( $this->is_screen( $this->admin_menu_hooks_dt ) ) {
			wp_enqueue_style( Util::ns( 'dt-tools' ), AdCommanderDt::assets_url() . 'css/admin.css', array(), AdCommanderDt::version() );
		}
	}

	/**
	 * The admin_url for the tools page.
	 *
	 * @return string
	 */
	public static function tools_admin_url() {
		return admin_url( self::admin_path( 'tools' ) );
	}

	/**
	 * The title of the Tools page
	 *
	 * @return string
	 */
	public function tools_title() {
		return __( 'Tools', 'ad-commander-tools' );
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
			AdCommander::capability(),
			self::admin_slug( 'tools' ),
			array( $this, 'tools_page' ),
			25
		);

		$this->admin_menu_hooks_dt[] = $hook;
	}

	/**
	 * Filter Ad Commander menu hooks and add this plugin.
	 *
	 * @param array $hooks The current hooks.
	 *
	 * @return array
	 */
	public function admin_menu_hooks( $hooks ) {

		if ( is_array( $hooks ) && ! empty( $this->admin_menu_hooks_dt ) ) {
			$hooks = array_merge( $hooks, $this->admin_menu_hooks_dt );
		}

		return $hooks;
	}

	/**
	 * The tools page.
	 *
	 * @return void
	 */
	public function tools_page() {
		$this->sf()->start();
		$this->sf()->title( __( 'Tools', 'ad-commander-tools' ) );

		$tabs                  = array();
		$tools['import']       = __( 'Import', 'ad-commander-tools' );
		$tools['export']       = __( 'Export', 'ad-commander-tools' );
		$tools['manage_stats'] = __( 'Manage Stats', 'ad-commander-tools' );

		$admin_url = self::tools_admin_url();

		foreach ( $tools as $key => $text ) {
			$opt_key = $this->sf()->key( $key );
			$tabs[]  = array(
				'key'  => $opt_key,
				'text' => esc_html( $text ),
				'url'  => $this->sf()->get_tab_url( $opt_key, $admin_url ),
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification isn't needed for admin page added via add_submenu_page and processing no action.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : $tabs[0]['key'];
		$this->sf()->display_tabs( $tabs, $active_tab );

		foreach ( $tabs as $tab ) {
			if ( $active_tab !== $tab['key'] ) {
				continue;
			}

			switch ( $tab['key'] ) {
				case 'adcmdr_export':
					$this->export_page();
					break;

				case 'adcmdr_import':
					$this->import_page();
					break;

				case 'adcmdr_manage_stats':
					$this->manage_stats_page();
					break;
			}
		}
		// Close .woforms-form-inner div from display_tabs().
		?>
		</div>
		<?php
		$this->sf()->end();
	}

	/**
	 * Start a form tag.
	 *
	 * @param string $method Get or post method.
	 * @param bool   $formdata Whether to accept file uploads.
	 *
	 * @return void
	 */
	private function form_start( $method = 'post', $formdata = false ) {
		$this->sf()->form_start( admin_url( 'admin.php' ), $method, $formdata );
	}

	/**
	 * End a form.
	 *
	 * @return void
	 */
	private function form_end() {
		?>
		</form>
		<?php
	}

	/**
	 * Create the export page.
	 *
	 * @return void
	 */
	private function export_page() {
		$export_nonce = $this->nonce_array( 'adcmdr-do_export', 'export' );
		?>
		<h2><?php esc_html_e( 'Export', 'ad-commander-tools' ); ?></h2>
		<?php
		if ( ! Export::can_export() ) {
			$this->info( esc_html__( "Your host doesn't appear to support the necessary libraries for exporting and compressing your data. We are unable to create export bundles at this time.", 'ad-commander-tools' ), array( 'adcmdr-metaitem__warning' ) );
		} else {
			$this->form_start();
			?>
		<input type="hidden" name="action" value="<?php echo esc_attr( Util::ns( 'do_export' ) ); ?>" />
			<?php
			$this->nonce_field( $export_nonce );

			Html::admin_table_start();
			?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Include stats', 'ad-commander-tools' ); ?></th>
			<td>
				<?php
				$id = $this->sf()->key( 'export_include_stats' );
				$this->sf()->checkbox( $id, 1 );
				$this->sf()->label( $id, __( 'Include statistics in export bundle.', 'ad-commander-tools' ) );
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Export bundle', 'ad-commander-tools' ); ?></th>
			<td>
				<input type="submit" value="<?php echo esc_attr( __( 'Create export bundle now', 'ad-commander-tools' ) ); ?>" class="button button-primary adcmdrdt-submit" /> <span class="adcmdr-loader"></span>
				<?php
				/* translators: %1$s: line break tag */
				$this->sf()->message( sprintf( esc_html__( 'A bundle will be created with your ads, groups, placements, and (optionally) stats.%1$sWhen importing this bundle into another site, you can choose which data to import.', 'ad-commander-tools' ), '<br />' ) );
				?>
			</td>
		</tr>
			<?php
			$bundles = Export::instance()->get_filelist();
			if ( ! empty( $bundles ) ) :
				$url = Export::instance()->export_url();
				?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Exported files', 'ad-commander-tools' ); ?></th>
			<td>
				<ul class="adcmdrdt-export-list">
				<?php
				$dt     = Util::datetime_wp_timezone( 'now' );
				$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
				foreach ( $bundles as $file ) :
					?>
					<?php
					$created = null;

					if ( isset( $file['lastmodunix'] ) ) {
						$dt->setTimestamp( $file['lastmodunix'] );
						$created = $dt->format( $format );
					}
					?>
					<li data-file="<?php echo esc_attr( $file['name'] ); ?>">
						<a href="<?php echo esc_url( $url . $file['name'] ); ?>" target="_blank" title="<?php echo esc_attr__( 'Download', 'ad-commander-tools' ); ?>">
							<span><?php echo esc_html( $file['name'] ); ?></span><i class="dashicons dashicons-download"></i>
						</a>
						<?php if ( $created ) : ?>
						<em><?php echo esc_html( $created ); ?></em>
						<?php endif; ?>
						<button title="Delete" class="adcmdrdt-del"><i class="dashicons dashicons-remove"></i></button>
					</li>
				<?php endforeach; ?>
				</ul>
			</td>
		</tr>
				<?php
		endif;
			Html::admin_table_end();

			$this->form_end();
		}
	}

	/**
	 * A select element with the bundle files to import.
	 *
	 * @return void
	 */
	private function import_bundle_options() {
		$options = array(
			'ads'        => __( 'Ads', 'ad-commander-tools' ),
			'groups'     => __( 'Groups', 'ad-commander-tools' ),
			'placements' => __( 'Placements', 'ad-commander-tools' ),
			'stats'      => __( 'Stats', 'ad-commander-tools' ),
		);

		$id = $this->sf()->key( 'import_bundle_options' );
		$this->sf()->label( $id, __( 'Import the following data (if present in the bundle file):', 'ad-commander-tools' ) );
		$this->sf()->checkgroup(
			$id,
			$options,
			array_filter( array_keys( $options ), fn( $option ) => $option !== 'stats' )
		);
	}

	/**
	 * Create the import/export page.
	 *
	 * @return void
	 */
	private function import_page() {
		?>
		<h2><?php esc_html_e( 'Import', 'ad-commander-tools' ); ?></h2>
		<?php
		if ( ! self::allow_unfiltered_html() ) {
			?>
			<div class="adcmdr-notification adcmdr-notice-warn">
				<p>
					<?php esc_html_e( 'Your user does not have permission to use unfiltered HTML. Scripts and some other HTML will be stripped from Text/Code ads, Rich Content ads, and custom code.', 'ad-commander' ); ?>
					<?php Doc::doc_link( 'unfiltered_html' ); ?>
				</p>
			</div>
			<?php
		}

		$import_bundle_nonce = $this->nonce_array( 'adcmdr-do_import_bundle', 'import' );
		$this->form_start( 'post', true );
		?>
		<input type="hidden" name="action" value="<?php echo esc_attr( Util::ns( 'do_import_bundle' ) ); ?>" />
		<?php
		$this->nonce_field( $import_bundle_nonce );
		Html::admin_table_start();
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Import bundle', 'ad-commander-tools' ); ?></th>
			<td>
				<?php $this->sf()->message( __( "Upload a bundle zip created by Ad Commander's export tool. This zip will be processed and imported based on the options selected below.", 'ad-commander-tools' ) ); ?>
				<div class="adcmdrdt-sub">
				<?php
				$id = $this->sf()->key( 'import_bundle_file' );
				$this->sf()->input( $id, null, 'file', array( 'accept' => '.zip' ) );
				$this->sf()->label( $id, __( 'Select a bundle file to upload.', 'ad-commander-tools' ) );
				?>
				</div>
				<div class="adcmdrdt-sub adcmdrdt-sub--col adcmdrdt-sub--divide">
					<?php $this->import_bundle_options(); ?>
				</div>
				<div class="adcmdrdt-sub adcmdrdt-sub--col">
				<?php
				$options = array(
					'draft' => __( 'Draft', 'ad-commander-tools' ),
					'match' => __( 'Match imported status and post date', 'ad-commander-tools' ),
				);

				$id = $this->sf()->key( 'import_set_status' );
				$this->sf()->label( $id, __( 'Set the status of imported Ads and Placements to:', 'ad-commander-tools' ) );
				$this->sf()->select(
					$id,
					$options,
					'draft'
				);
				?>
				</div>
				<div class="adcmdrdt-sub adcmdrdt-sub--divide">
					<input type="submit" value="<?php echo esc_attr( __( 'Upload & Import Bundle', 'ad-commander-tools' ) ); ?>" class="button button-primary adcmdrdt-submit" /> <span class="adcmdr-loader"></span>
				</div>
			</td>
		</tr>
		<?php
		Html::admin_table_end();
		$this->form_end();
	}

	/**
	 * Create the manage stats page.
	 *
	 * @return void
	 */
	private function manage_stats_page() {
		$this->manage_stats_delete_rogue();
		$this->manage_stats_delete_all();
	}

	/**
	 * Delete rogue stats section.
	 *
	 * @return void
	 */
	private function manage_stats_delete_rogue() {
		?>
		<h2><?php esc_html_e( 'Delete rogue stats', 'ad-commander-tools' ); ?></h2>
		<?php
		$this->sf()->message( __( 'A stat is considered rogue if an ad no longer exists. Stats for ads in the trash are not considered rogue. The ads must be completely deleted.', 'ad-commander-tools' ) );

		$rogue = ManageStats::find_rogue_entries();
		/* translators: %1$s the total number of query results */
		$this->sf()->message( '<em>' . sprintf( __( 'You currently have %1$s rogue stat entries.', 'ad-commander-tools' ), count( $rogue['impressions'] ) + count( $rogue['clicks'] ) ) . '</em>' );

		$delete_all_stats_nonce = $this->nonce_array( 'adcmdr-do_delete_rogue_stats', 'import' );
		$this->form_start( 'post', true );
		?>
		<input type="hidden" name="action" value="<?php echo esc_attr( Util::ns( 'do_delete_rogue_stats' ) ); ?>" />
		<?php
		$this->nonce_field( $delete_all_stats_nonce );
		Html::admin_table_start();
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Confirm delete', 'ad-commander-tools' ); ?></th>
			<td>
				<?php
				$id = $this->sf()->key( 'confirm_delete_rogue_stats' );
				$this->sf()->checkbox( $id, 0 );
				$this->sf()->label( $id, __( 'I understand I am deleting statistics for ads that no longer exist. These stats will no longer be available in Reports. This action cannot be undone.', 'ad-commander-tools' ) );
				?>
				<div class="adcmdrdt-sub">
					<input type="submit" value="<?php echo esc_attr( __( 'Delete rogue statistics', 'ad-commander-tools' ) ); ?>" class="button button-primary adcmdrdt-submit" /> <span class="adcmdr-loader"></span>
				</div>
			</td>
		</tr>
		<?php
		Html::admin_table_end();
		$this->form_end();
	}

	/**
	 * Delete all stats section.
	 *
	 * @return void
	 */
	private function manage_stats_delete_all() {
		?>
		<h2><?php esc_html_e( 'Delete all stats', 'ad-commander-tools' ); ?></h2>
		<?php
		$delete_all_stats_nonce = $this->nonce_array( 'adcmdr-do_delete_all_stats', 'import' );
		$this->form_start( 'post', true );
		?>
		<input type="hidden" name="action" value="<?php echo esc_attr( Util::ns( 'do_delete_all_stats' ) ); ?>" />
		<?php
		$this->nonce_field( $delete_all_stats_nonce );
		Html::admin_table_start();
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Confirm delete', 'ad-commander-tools' ); ?></th>
			<td>
				<?php
				$id = $this->sf()->key( 'confirm_delete_all_stats' );
				$this->sf()->checkbox( $id, 0 );
				$this->sf()->label( $id, __( 'I understand I am deleting ads statistics for all ads and that this action cannot be undone.', 'ad-commander-tools' ) );
				?>
				<div class="adcmdrdt-sub">
					<input type="submit" value="<?php echo esc_attr( __( 'Delete all statistics', 'ad-commander-tools' ) ); ?>" class="button button-primary adcmdrdt-submit" /> <span class="adcmdr-loader"></span>
				</div>
			</td>
		</tr>
		<?php
		Html::admin_table_end();
		$this->form_end();
	}
}
