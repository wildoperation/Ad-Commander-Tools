<?php
namespace ADCmdr;

use ADCmdr\Vendor\WOAdminFramework\WOMeta;

/**
 * Export functionality
 */
class Export extends AdminTools {

	/**
	 * An instance of this class.
	 *
	 * @var Export|null
	 */
	private static $instance = null;

	/**
	 * Instance of WOMeta
	 *
	 * @var WOMeta
	 */
	private $wo_meta;

	/**
	 * Export suffix
	 *
	 * @var string
	 */
	private $export_suffix;

	/**
	 * Create an instance of self if necessary and return it.
	 *
	 * @return Export
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks
	 */
	public function hooks() {
		add_action( 'admin_action_adcmdr-do_export', array( $this, 'do_export' ) );
		add_action( 'admin_notices', array( $this, 'export_success' ) );
		add_action( 'admin_notices', array( $this, 'export_fail' ) );
	}


	/**
	 * Redirect after an import and include arguments.
	 *
	 * @param array $nonce The export nonce.
	 * @param bool  $success Whether this is a successful redirect or a failure.
	 *
	 * @return void
	 */
	public function redirect( $nonce, $success = true ) {
		$url = admin_url( self::admin_path( 'tools' ) );

		$url = add_query_arg(
			array(
				'action'       => ( $success ) ? Util::ns( 'export_success' ) : Util::ns( 'export_fail' ),
				$nonce['name'] => wp_create_nonce( $nonce['action'] ),
				'tab'          => 'adcmdr_export',
			),
			$url
		);

		wp_safe_redirect( sanitize_url( $url ) );
		exit;
	}

	/**
	 * Determine if we are capable of export.
	 *
	 * @return bool
	 */
	public static function can_export() {
		return class_exists( 'ZipArchive' ) && class_exists( 'DateTime' );
	}

	/**
	 * Create a suffix if one does not exist.
	 */
	private function export_suffix() {
		if ( ! $this->export_suffix ) {
			$dt = Util::datetime_wp_timezone( 'now' );

			$this->export_suffix = '_' . $dt->format( 'YmdHis' ) . '_' . preg_replace( '/[^a-z0-9]/i', '', strtolower( wp_generate_password( 5, false ) ) );
		}

		return $this->export_suffix;
	}

	/**
	 * Add admin notice on export success.
	 */
	public function export_success() {
		if ( isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) === Util::ns( 'export_success' ) ) {
			$export_nonce = $this->nonce_array( 'adcmdr-do_export', 'export' );
			if ( check_admin_referer( $export_nonce['action'], $export_nonce['name'] ) && current_user_can( AdCommander::capability() ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php esc_html_e( 'Your export was completed successfully. You can download your bundle below.', 'ad-commander-tools' ); ?>
					</p>
				</div>
					<?php
			}
		}
	}

	/**
	 * Add admin notice on export fail.
	 */
	public function export_fail() {
		if ( isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) === Util::ns( 'export_fail' ) ) {
			$export_nonce = $this->nonce_array( 'adcmdr-do_export', 'export' );
			if ( check_admin_referer( $export_nonce['action'], $export_nonce['name'] ) && current_user_can( AdCommander::capability() ) ) {
				?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<?php esc_html_e( 'Your export failed to process.', 'ad-commander-tools' ); ?>
					</p>
				</div>
					<?php
			}
		}
	}

	/**
	 * Get a list of bundles in the export directory.
	 *
	 * @param array $filters Only get files with these strings.
	 *
	 * @return array
	 */
	public function get_filelist( $filters = array( '.zip', 'bundle_' ) ) {
		$bundles = array();

		if ( ! Filesystem::instance()->init_wp_filesystem() ) {
			return false;
		}

		global $wp_filesystem;

		$filelist = $wp_filesystem->dirlist( self::export_dir(), false );

		if ( is_array( $filelist ) ) {
			$bundles = array_filter(
				$filelist,
				function ( $file ) use ( $filters ) {
					if ( $file['type'] !== 'f' ) {
						return false;
					}

					foreach ( $filters as $filter ) {
						if ( stripos( $file['name'], $filter ) === false ) {
							return false;
						}
					}

					return true;
				}
			);

			usort(
				$bundles,
				function ( $a, $b ) {
					return isset( $a['lastmodunix'] ) && isset( $b['lastmodunix'] ) ? $b['lastmodunix'] <=> $a['lastmodunix'] : 0;
				}
			);
		}

		Filesystem::instance()->end_wp_filesystem();

		return $bundles;
	}

	/**
	 * Perform an export.
	 *
	 * @return void|bool
	 */
	public function do_export() {

		$export_nonce = $this->nonce_array( 'adcmdr-do_export', 'export' );

		if ( ! isset( $_REQUEST['action'] ) ||
			! check_admin_referer( $export_nonce['action'], $export_nonce['name'] ) ||
			! current_user_can( AdCommander::capability() ) ) {
			wp_die();
		}

		if ( sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) !== Util::ns( 'do_export' ) ) {
			return false;
		}

		$this->wo_meta = new WOMeta( AdCommander::ns() );

		$ads        = $this->process_ads();
		$groups     = $this->process_groups();
		$placements = $this->process_placements();

		$bundles = array(
			'adcmdr_ads'        => $ads,
			'adcmdr_groups'     => $groups,
			'adcmdr_placements' => $placements,
		);

		if ( isset( $_REQUEST['adcmdr_export_include_stats'] ) && Util::truthy( sanitize_text_field( wp_unslash( $_REQUEST['adcmdr_export_include_stats'] ) ) ) ) {
			$bundles['adcmdr_stats'] = $this->process_stats( $ads );
		}

		if ( $this->create_bundle( $bundles ) ) {
			$this->redirect( $export_nonce, true );
		}

		$this->redirect( $export_nonce, false );
	}

	/**
	 * Delete a bundle file.
	 *
	 * @param string $file The filename to delete.
	 *
	 * @return bool
	 */
	public function delete_bundle( $file ) {
		if ( ! $file ) {
			return false;
		}

		$dir = self::export_dir();

		if ( file_exists( $dir . $file ) ) {
			wp_delete_file( $dir . $file );
		}

		return true;
	}

	/**
	 * Create a bundle from arrays.
	 *
	 * @param array $bundle Bundle of data.
	 *
	 * @return bool|void
	 */
	private function create_bundle( $bundle ) {

		if ( empty( $bundle ) ) {
			return false;
		}

		$dir = Filesystem::instance()->maybe_create_dir( self::export_dir() );

		if ( ! $dir ) {
			return false;
		}

		$csvs = array();

		foreach ( $bundle as $file => $data ) {
			if ( ! isset( $data['headings'] ) || ! $data['headings'] ) {
				continue;
			}

			$filename  = sanitize_title( $file . $this->export_suffix() ) . '.csv';
			$full_path = $dir . $filename;

			$fd = fopen( $full_path, 'w' );
			if ( $fd ) {
				$headings = $data['headings'];
				fputcsv( $fd, $headings );

				foreach ( $data['rows'] as $row ) {
					$this_row = array();
					foreach ( $headings as $heading ) {
						if ( isset( $row[ $heading ] ) ) {
							$this_row[] = $row[ $heading ];
						} else {
							$this_row[] = '';
						}
					}

					fputcsv( $fd, $this_row );
				}

				fclose( $fd );

				$csvs[] = $full_path;
			}
		}

		if ( ! empty( $csvs ) ) {
			$zip          = new \ZipArchive();
			$zip_filename = 'bundle' . $this->export_suffix() . '.zip';

			if ( file_exists( $dir . $zip_filename ) ) {
				wp_delete_file( $dir . $zip_filename );
			}

			if ( ! $zip->open( $dir . $zip_filename, \ZipArchive::CREATE ) ) {
				return false;
			}

			foreach ( $csvs as $csv ) {
				$zip->addFile( $csv, basename( $csv ) );
			}

			$zip->close();

			foreach ( $csvs as $csv ) {
				wp_delete_file( $csv );
			}

			return true;
		}

		return false;
	}

	/**
	 * Query and prepare groups for export.
	 *
	 * @return array
	 */
	private function process_groups() {
		$headings         = array_keys( UtilTools::headings( 'groups' ) );
		$processed_groups = array();

		$groups = Query::groups();

		if ( $groups ) {
			$home_url = home_url();

			foreach ( $groups as $group ) {
				$meta = $this->wo_meta->get_term_meta( $group->term_id, GroupTermMeta::tax_group_meta_keys() );

				$this_group = array(
					'source'      => 'adcmdr_export',
					'source_site' => $home_url,
				);

				foreach ( $headings as $heading ) {
					if ( isset( $this_group[ $heading ] ) ) {
						continue;
					}

					$value = '';

					if ( isset( $group->$heading ) ) {
						$value = $group->$heading;
					} elseif ( isset( $meta[ $heading ] ) ) {
						$value = $meta[ $heading ];
					}

					$this_group[ $heading ] = maybe_serialize( $value );
				}

				$processed_groups[] = $this_group;
			}
		}

		return array(
			'headings' => $headings,
			'rows'     => $processed_groups,
		);
	}

	/**
	 * Query and prepare ads for export.
	 *
	 * @return array
	 */
	private function process_ads() {
		$headings      = array_keys( UtilTools::headings( 'ads' ) );
		$processed_ads = array();

		$ads = Query::ads( 'post_title', 'asc', Util::any_post_status( array( 'trash' ) ) );

		if ( $ads ) {
			$home_url = home_url();

			foreach ( $ads as $ad ) {
				$meta         = $this->wo_meta->get_post_meta( $ad->ID, AdPostMeta::post_meta_keys() );
				$terms        = get_the_terms( $ad->ID, AdCommander::tax_group() );
				$terms_export = array();

				if ( $terms && ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$terms_export[ $term->term_id ] = $term->name;
					}
				}

				$featured_image_url = '';
				$thumbnail_id       = '';

				if ( has_post_thumbnail( $ad->ID ) ) {
					$thumbnail_id       = get_post_thumbnail_id( $ad->ID );
					$featured_image_url = get_the_post_thumbnail_url( $ad->ID, 'full' );
				}

				$this_ad = array(
					'source'             => 'adcmdr_export',
					'source_site'        => $home_url,
					'featured_image_url' => $featured_image_url,
					'thumbnail_id'       => $thumbnail_id,
					'groups'             => maybe_serialize( $terms_export ),
				);

				foreach ( $headings as $heading ) {
					if ( isset( $this_ad[ $heading ] ) ) {
						continue;
					}

					$value = '';

					if ( isset( $ad->$heading ) ) {
						$value = $ad->$heading;
					} elseif ( isset( $meta[ $heading ] ) ) {
						$value = $meta[ $heading ];
					}

					$this_ad[ $heading ] = maybe_serialize( $value );
				}

				$processed_ads[] = $this_ad;
			}
		}

		return array(
			'headings' => $headings,
			'rows'     => $processed_ads,
		);
	}

	/**
	 * Query and prepare placements for export.
	 *
	 * @return array
	 */
	private function process_placements() {
		$headings             = array_keys( UtilTools::headings( 'placements' ) );
		$processed_placements = array();

		$placements = Query::placements( Util::any_post_status( array( 'trash' ) ) );

		if ( $placements ) {
			$home_url = home_url();

			foreach ( $placements as $placement ) {
				$meta = $this->wo_meta->get_post_meta( $placement->ID, PlacementPostMeta::post_meta_keys() );

				$this_placement = array(
					'source'      => 'adcmdr_export',
					'source_site' => $home_url,
				);

				foreach ( $headings as $heading ) {
					if ( isset( $this_placement[ $heading ] ) ) {
						continue;
					}

					$value = '';

					if ( isset( $placement->$heading ) ) {
						$value = $placement->$heading;
					} elseif ( isset( $meta[ $heading ] ) ) {
						$value = $meta[ $heading ];
					}

					$this_placement[ $heading ] = maybe_serialize( $value );
				}

				$processed_placements[] = $this_placement;
			}
		}

		return array(
			'headings' => $headings,
			'rows'     => $processed_placements,
		);
	}

	/**
	 * Process an individual stat (impression or click).
	 *
	 * @param array  $this_stat The individual stat.
	 * @param object $stat The stat to process.
	 * @param array  $headings The CSV headings/keys.
	 *
	 * @return array
	 */
	private function process_stat( $this_stat, $stat, $headings ) {
		foreach ( $headings as $heading ) {
			if ( isset( $this_stat[ $heading ] ) ) {
				continue;
			}

			$value = '';

			if ( isset( $stat->$heading ) ) {
				$value = $stat->$heading;
			}

			$this_stat[ $heading ] = $value;
		}

		return $this_stat;
	}

	/**
	 * Process all stats.
	 *
	 * @param array $ads The ads that were exported in this bundle.
	 *
	 * @return array
	 */
	private function process_stats( $ads ) {

		$headings = array_keys( UtilTools::headings( 'stats' ) );

		if ( ! $ads['rows'] || empty( $ads['rows'] ) ) {
			return array(
				'headings' => $headings,
				'rows'     => array(),
			);
		}

		$ad_ids = wp_list_pluck( $ads['rows'], 'ID' );

		if ( ! $ad_ids || empty( $ad_ids ) ) {
			return array(
				'headings' => $headings,
				'rows'     => array(),
			);
		}

		global $wpdb;

		$home_url        = home_url();
		$processed_stats = array();

		$ad_ids             = array_map( 'absint', $ad_ids );
		$ad_ids_placeholder = implode( ', ', array_fill( 0, count( $ad_ids ), '%d' ) );

		$args        = array( TrackingLocal::get_tracking_table( 'impressions' ) );
		$impressions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE ad_id IN ($ad_ids_placeholder)", array_merge( $args, $ad_ids ) ) );

		$args   = array( TrackingLocal::get_tracking_table( 'clicks' ) );
		$clicks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE ad_id IN ($ad_ids_placeholder)", array_merge( $args, $ad_ids ) ) );

		if ( ! empty( $impressions ) ) {
			foreach ( $impressions as $impression ) {
				$processed_stats[] = $this->process_stat(
					array(
						'source'      => 'adcmdr_export',
						'source_site' => $home_url,
						'stat_type'   => 'impression',
					),
					$impression,
					$headings
				);
			}
		}

		if ( ! empty( $clicks ) ) {
			foreach ( $clicks as $click ) {
				$processed_stats[] = $this->process_stat(
					array(
						'source'      => 'adcmdr_export',
						'source_site' => $home_url,
						'stat_type'   => 'click',
					),
					$click,
					$headings
				);
			}
		}

		return array(
			'headings' => $headings,
			'rows'     => $processed_stats,
		);
	}

	/**
	 * Get the local path to the export folder.
	 *
	 * @return string
	 */
	public static function export_path() {
		return apply_filters( 'adcmdr_export_uploads_path', '/ad-commander/export' );
	}

	/**
	 * Get the full URL for the export folder.
	 *
	 * @return string
	 */
	public static function export_url() {
		$wp_upload_dir = wp_upload_dir();

		if ( ! isset( $wp_upload_dir['baseurl'] ) ) {
			return false;
		}

		return $wp_upload_dir['baseurl'] . trailingslashit( self::export_path() );
	}

	/**
	 * Get the full directory for the export folder.
	 *
	 * @return string
	 */
	public static function export_dir() {
		$wp_upload_dir = wp_upload_dir();

		if ( ! isset( $wp_upload_dir['basedir'] ) ) {
			return false;
		}

		return $wp_upload_dir['basedir'] . trailingslashit( self::export_path() );
	}
}
