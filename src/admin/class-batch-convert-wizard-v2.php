<?php
/**
 * Modern batch conversion wizard for Elementor to Gutenberg.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

use Progressus\Gutenberg\Admin\Batch_Convert_Wizard;
use WP_Post;
use WP_Query;

use function absint;
use function add_submenu_page;
use function admin_url;
use function check_ajax_referer;
use function current_user_can;
use function date_i18n;
use function delete_user_meta;
use function esc_html__;
use function get_current_user_id;
use function get_edit_post_link;
use function get_option;
use function get_permalink;
use function get_post;
use function get_post_meta;
use function get_post_status;
use function get_post_type;
use function get_the_title;
use function get_user_meta;
use function is_array;
use function plugins_url;
use function sanitize_key;
use function sanitize_text_field;
use function set_transient;
use function time;
use function uniqid;
use function update_user_meta;
use function wp_create_nonce;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_reset_postdata;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function strtotime;

use const HOUR_IN_SECONDS;

defined( 'ABSPATH' ) || exit;

/**
 * Class Batch_Convert_Wizard_V2
 */
class Batch_Convert_Wizard_V2 {
	public const MENU_SLUG = 'ele2gb-batch-convert-v2';

	private const NONCE_ACTION = 'ele2gb_batch_convert_v2';

	private const NONCE_NAME = 'nonce';

	private const JOB_TRANSIENT_PREFIX = 'ele2gb_v2_job_';

	private const JOB_TRANSIENT_TTL = 6 * HOUR_IN_SECONDS;

	private const ITEMS_PER_QUERY = 250;

	/**
	 * Singleton instance.
	 *
	 * @var Batch_Convert_Wizard_V2|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ele2gb_v2_pages', array( $this, 'ajax_get_pages' ) );
		add_action( 'wp_ajax_ele2gb_v2_start_job', array( $this, 'ajax_start_job' ) );
		add_action( 'wp_ajax_ele2gb_v2_poll_job', array( $this, 'ajax_poll_job' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'gutenberg-settings',
			esc_html__( 'Conversion Wizard', 'elementor-to-gutenberg' ),
			esc_html__( 'Conversion Wizard', 'elementor-to-gutenberg' ),
			'edit_pages',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue assets for the wizard page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( empty( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_style(
			'ele2gb-batch-wizard-v2',
			plugins_url( 'assets/css/batch-wizard-v2.css', GUTENBERG_PLUGIN_MAIN_FILE ),
			array(),
			GUTENBERG_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'ele2gb-batch-wizard-v2',
			plugins_url( 'assets/js/batch-convert-wizard-v2.js', GUTENBERG_PLUGIN_MAIN_FILE ),
			array(),
			GUTENBERG_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'ele2gb-batch-wizard-v2',
			'ele2gbBatchWizardV2',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
				'pages'        => $this->get_elementor_pages_data(),
				'strings'      => $this->get_strings(),
				'activeJob'    => $this->get_active_job_for_user(),
				'userCanEdit'  => current_user_can( 'edit_pages' ),
				'maxBatchSize' => 1,
			)
		);
	}

	/**
	 * Render wizard page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'elementor-to-gutenberg' ) );
		}

		?>
		<div class="wrap ele2gb-wizard-v2-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Gutenberg Conversion Wizard', 'elementor-to-gutenberg' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Convert Elementor pages to Gutenberg blocks.', 'elementor-to-gutenberg' ); ?></p>
			<div id="ele2gb-batch-convert-v2-root" class="ele2gb-wizard-v2-root" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX request for Elementor pages data.
	 */
	public function ajax_get_pages(): void {
		$this->verify_ajax_request();

		wp_send_json_success(
			array(
				'pages' => $this->get_elementor_pages_data(),
			)
		);
	}

	/**
	 * Start a conversion job via AJAX.
	 */
	public function ajax_start_job(): void {
		$this->verify_ajax_request();

		$mode            = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'auto';
		$conflict_policy = isset( $_POST['conflictPolicy'] ) ? sanitize_key( wp_unslash( $_POST['conflictPolicy'] ) ) : 'skip';
		$skip_converted  = ! empty( $_POST['skipConverted'] );

		$raw_pages        = isset( $_POST['pages'] ) ? wp_unslash( $_POST['pages'] ) : array();
		$raw_disabled     = isset( $_POST['disabledMeta'] ) ? wp_unslash( $_POST['disabledMeta'] ) : array();
		$selected_page_ids = array_map( 'absint', (array) $raw_pages );
		$disabled_meta_ids = array_map( 'absint', (array) $raw_disabled );

		if ( 'auto' === $mode ) {
			$all_pages         = $this->get_elementor_pages_data();
			$selected_page_ids = array_map(
				static function ( array $page ): int {
					return (int) $page['id'];
				},
				$all_pages
			);
			$skip_converted = true;
		}

		$selected_page_ids = array_values( array_unique( array_filter( $selected_page_ids ) ) );

		if ( empty( $selected_page_ids ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Select at least one page before starting a conversion.', 'elementor-to-gutenberg' ),
				)
			);
		}

		$pages = $this->prepare_job_pages( $selected_page_ids, $disabled_meta_ids );
		if ( empty( $pages ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'No valid Elementor pages were found for conversion.', 'elementor-to-gutenberg' ),
				)
			);
		}

		$options = $this->build_job_options( $mode, $conflict_policy, $skip_converted );

		$job_id = uniqid( 'ele2gb_v2_', true );

		$job = array(
			'id'              => $job_id,
			'user_id'         => get_current_user_id(),
			'mode'            => $mode,
			'conflict_policy' => $conflict_policy,
			'status'          => 'queued',
			'created_at'      => time(),
			'started_at'      => 0,
			'completed_at'    => 0,
			'processed'       => 0,
			'pages'           => $pages,
			'options'         => $options,
			'counts'          => array(
				'success' => 0,
				'skipped' => 0,
				'error'   => 0,
				'partial' => 0,
			),
			'results'         => array(),
		);

		$this->store_job( $job );

		update_user_meta( get_current_user_id(), '_ele2gb_v2_job', $job_id );

		wp_send_json_success(
			array(
				'job' => $this->format_job_for_response( $job ),
			)
		);
	}

	/**
	 * Poll and process the next batch of a conversion job.
	 */
	public function ajax_poll_job(): void {
		$this->verify_ajax_request();

		$job_id = isset( $_POST['jobId'] ) ? sanitize_text_field( wp_unslash( $_POST['jobId'] ) ) : '';
		if ( '' === $job_id ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Missing job identifier.', 'elementor-to-gutenberg' ),
				)
			);
		}

		$job = $this->get_job( $job_id );
		if ( empty( $job ) || (int) $job['user_id'] !== get_current_user_id() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Conversion job could not be found.', 'elementor-to-gutenberg' ),
				)
			);
		}

		if ( 'completed' === $job['status'] ) {
			wp_send_json_success(
				array(
					'job' => $this->format_job_for_response( $job ),
				)
			);
		}

		if ( empty( $job['started_at'] ) ) {
			$job['started_at'] = time();
		}

		$job['status'] = 'running';

		$batch_size = 1;

		$total_pages = count( $job['pages'] );
		for ( $i = 0; $i < $batch_size; $i++ ) {
			if ( $job['processed'] >= $total_pages ) {
				break;
			}

			$index     = $job['processed'];
			$page_info = $job['pages'][ $index ];

			$options = $job['options'];
			$options['keep_meta'] = ! empty( $page_info['keep_meta'] );

			$start_time = microtime( true );
			$result     = Batch_Convert_Wizard::instance()->process_single_post( (int) $page_info['id'], $options );
			$duration   = max( 0, microtime( true ) - $start_time );

			$result_entry = array(
				'id'        => (int) $page_info['id'],
				'title'     => $page_info['title'],
				'status'    => $result['status'],
				'message'   => $result['message'],
				'target'    => $result['target'],
				'duration'  => $duration,
				'view_url'  => $result['target'] ? get_permalink( (int) $result['target'] ) : '',
				'keep_meta' => ! empty( $page_info['keep_meta'] ),
			);

			$job['results'][] = $result_entry;

			if ( isset( $job['counts'][ $result['status'] ] ) ) {
				$job['counts'][ $result['status'] ]++;
			}

			$job['processed']++;
		}

		if ( $job['processed'] >= $total_pages ) {
			$job['status']       = 'completed';
			$job['completed_at'] = time();
			delete_user_meta( get_current_user_id(), '_ele2gb_v2_job' );
		}

		$this->store_job( $job );

		wp_send_json_success(
			array(
				'job' => $this->format_job_for_response( $job ),
			)
		);
	}

	/**
	 * Verify AJAX request permissions and nonce.
	 */
	private function verify_ajax_request(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'You do not have permission to perform this action.', 'elementor-to-gutenberg' ),
				),
				403
			);
		}

		check_ajax_referer( self::NONCE_ACTION, self::NONCE_NAME );
	}

	/**
	 * Retrieve Elementor pages data for the wizard.
	 */
	private function get_elementor_pages_data(): array {
		$paged       = 1;
		$accumulated = array();

		do {
			$query = new WP_Query(
				array(
					'post_type'      => array( 'page' ),
					'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
					'posts_per_page' => self::ITEMS_PER_QUERY,
					'paged'          => $paged,
					'meta_query'     => array(
						array(
							'key'     => '_elementor_data',
							'value'   => '',
							'compare' => '!=',
						),
					),
				)
			);

			foreach ( $query->posts as $post ) {
				$accumulated[] = $this->map_page_to_array( $post );
			}

			$paged++;
			$max_pages = (int) $query->max_num_pages;
			wp_reset_postdata();
		} while ( $paged <= $max_pages );

		return $accumulated;
	}

	/**
	 * Map a WP_Post instance to the structure expected by the wizard.
	 *
	 * @param WP_Post $post Post object.
	 */
	private function map_page_to_array( WP_Post $post ): array {
		$last_result = get_post_meta( $post->ID, '_ele2gb_last_result', true );
		$status_key  = is_array( $last_result ) && isset( $last_result['status'] ) ? (string) $last_result['status'] : '';
		$last_time   = is_array( $last_result ) && ! empty( $last_result['time'] ) ? (string) $last_result['time'] : '';
		$target_id   = is_array( $last_result ) && ! empty( $last_result['target'] ) ? absint( $last_result['target'] ) : 0;

		$conversion_status = $this->map_status_to_badge( $status_key );

		$last_converted = '';
		if ( '' !== $last_time ) {
			$timestamp = strtotime( $last_time );
			if ( false !== $timestamp ) {
				$last_converted = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
			}
		}

		$view_converted = $target_id ? get_permalink( $target_id ) : '';

		return array(
			'id'                    => (int) $post->ID,
			'title'                 => get_the_title( $post ),
			'status'                => get_post_status( $post ),
			'conversionStatus'      => $conversion_status,
			'lastResultStatus'      => $status_key,
			'lastResultMessage'     => is_array( $last_result ) && isset( $last_result['message'] ) ? (string) $last_result['message'] : '',
			'lastConverted'         => $last_converted,
			'hasConflict'           => $target_id > 0,
			'targetId'              => $target_id,
			'viewConvertedUrl'      => $view_converted,
			'editUrl'               => get_edit_post_link( $post ),
			'slug'                  => $post->post_name,
		);
	}

	/**
	 * Prepare job pages configuration.
	 *
	 * @param array $page_ids        Selected page IDs.
	 * @param array $disabled_meta   Page IDs where meta copy is disabled.
	 */
	private function prepare_job_pages( array $page_ids, array $disabled_meta ): array {
		$pages = array();

		foreach ( $page_ids as $page_id ) {
			$post = get_post( $page_id );
			if ( ! $post instanceof WP_Post || 'page' !== get_post_type( $post ) ) {
				continue;
			}

			$pages[] = array(
				'id'        => (int) $page_id,
				'title'     => get_the_title( $post ),
				'keep_meta' => ! in_array( (int) $page_id, $disabled_meta, true ),
			);
		}

		return $pages;
	}

	/**
	 * Build job options based on mode and conflict policy.
	 */
	private function build_job_options( string $mode, string $conflict_policy, bool $skip_converted ): array {
		$options = array(
			'mode'            => 'create',
			'wrap_full_width' => false,
			'assign_template' => false,
			'keep_meta'       => true,
			'skip_converted'  => $skip_converted,
		);

		if ( 'overwrite' === $conflict_policy ) {
			$options['mode']           = 'update';
			$options['skip_converted'] = false;
		} elseif ( 'duplicate' === $conflict_policy ) {
			$options['mode']           = 'create';
			$options['skip_converted'] = false;
		}

		return $options;
	}

	/**
	 * Store job in a transient.
	 *
	 * @param array $job Job data.
	 */
	private function store_job( array $job ): void {
		set_transient( self::JOB_TRANSIENT_PREFIX . $job['id'], $job, self::JOB_TRANSIENT_TTL );
	}

	/**
	 * Retrieve a stored job.
	 */
	private function get_job( string $job_id ): array {
		$job = get_transient( self::JOB_TRANSIENT_PREFIX . $job_id );
		if ( ! is_array( $job ) ) {
			return array();
		}

		return $job;
	}

	/**
	 * Format job data for JSON responses.
	 *
	 * @param array $job Job data.
	 */
	private function format_job_for_response( array $job ): array {
		$total = count( $job['pages'] );
		$processed = min( (int) $job['processed'], $total );

		$duration = 0;
		if ( ! empty( $job['started_at'] ) ) {
			$end_time = ! empty( $job['completed_at'] ) ? (int) $job['completed_at'] : time();
			$duration = max( 0, $end_time - (int) $job['started_at'] );
		}

		$results = array_map(
			static function ( array $item ): array {
				return array(
					'id'        => (int) $item['id'],
					'title'     => $item['title'],
					'status'    => $item['status'],
					'message'   => $item['message'],
					'target'    => $item['target'],
					'duration'  => $item['duration'],
					'viewUrl'   => $item['view_url'],
					'keepMeta'  => ! empty( $item['keep_meta'] ),
				);
			},
			$job['results']
		);

		return array(
			'id'              => $job['id'],
			'status'          => $job['status'],
			'mode'            => $job['mode'],
			'conflictPolicy'  => $job['conflict_policy'],
			'skipConverted'   => ! empty( $job['options']['skip_converted'] ),
			'total'           => $total,
			'processed'       => $processed,
			'counts'          => $job['counts'],
			'results'         => $results,
			'createdAt'       => (int) $job['created_at'],
			'startedAt'       => (int) $job['started_at'],
			'completedAt'     => (int) $job['completed_at'],
			'duration'        => $duration,
		);
	}

	/**
	 * Map status value to a badge slug.
	 */
	private function map_status_to_badge( string $status ): string {
		switch ( $status ) {
			case 'success':
				return 'converted';
			case 'partial':
				return 'partial';
			case 'error':
				return 'error';
			case 'skipped':
				return 'skipped';
			default:
				return 'not_converted';
		}
	}

	/**
	 * Retrieve localized strings for the UI.
	 */
	private function get_strings(): array {
		return array(
			'step'                   => esc_html__( 'Step %1$s of %2$s — %3$s', 'elementor-to-gutenberg' ),
			'modeTitle'              => esc_html__( 'Choose Mode', 'elementor-to-gutenberg' ),
			'modeAutoTitle'          => esc_html__( 'Convert all pages automatically', 'elementor-to-gutenberg' ),
			'modeAutoDesc'           => esc_html__( 'Run with smart defaults: copy meta & featured image, skip pages already converted.', 'elementor-to-gutenberg' ),
			'modeCustomTitle'        => esc_html__( 'Choose specific pages', 'elementor-to-gutenberg' ),
			'modeCustomDesc'         => esc_html__( 'Pick exactly which pages to convert and fine-tune options per page.', 'elementor-to-gutenberg' ),
			'continue'               => esc_html__( 'Continue', 'elementor-to-gutenberg' ),
			'back'                   => esc_html__( 'Back', 'elementor-to-gutenberg' ),
			'selectPagesTitle'       => esc_html__( 'Select Pages', 'elementor-to-gutenberg' ),
			'selectAll'              => esc_html__( 'Select all', 'elementor-to-gutenberg' ),
			'selectionSummary'       => esc_html__( '%1$d selected / %2$d total', 'elementor-to-gutenberg' ),
			'noPagesFound'           => esc_html__( 'No Elementor pages found for conversion.', 'elementor-to-gutenberg' ),
			'skipConverted'          => esc_html__( 'Skip pages that were already converted', 'elementor-to-gutenberg' ),
			'disableMeta'            => esc_html__( 'Don’t copy meta fields & featured image', 'elementor-to-gutenberg' ),
			'conflictsTitle'         => esc_html__( 'Resolve Conflicts', 'elementor-to-gutenberg' ),
			'conflictDetected'       => esc_html__( '%1$d selected pages already have a converted version.', 'elementor-to-gutenberg' ),
			'conflictOverwrite'      => esc_html__( 'Update existing pages in place (overwrite)', 'elementor-to-gutenberg' ),
			'conflictSkip'           => esc_html__( 'Skip those pages', 'elementor-to-gutenberg' ),
			'conflictDuplicate'      => esc_html__( 'Create duplicates with “(Converted)” suffix', 'elementor-to-gutenberg' ),
			'reviewTitle'            => esc_html__( 'Review & Confirm', 'elementor-to-gutenberg' ),
			'reviewSummary'          => esc_html__( '%1$d pages selected — %2$d will be converted, %3$d skipped.', 'elementor-to-gutenberg' ),
			'metaDisabled'           => esc_html__( '%1$d pages will be converted without copying meta fields or featured image.', 'elementor-to-gutenberg' ),
			'startConversion'        => esc_html__( 'Start Conversion', 'elementor-to-gutenberg' ),
			'backgroundInfo'         => esc_html__( 'Conversion runs in the background. You can safely close this page.', 'elementor-to-gutenberg' ),
			'progressTitle'          => esc_html__( 'Progress & Results', 'elementor-to-gutenberg' ),
			'converted'              => esc_html__( 'Converted', 'elementor-to-gutenberg' ),
			'skipped'                => esc_html__( 'Skipped', 'elementor-to-gutenberg' ),
			'errors'                 => esc_html__( 'Errors', 'elementor-to-gutenberg' ),
			'duration'               => esc_html__( 'Duration', 'elementor-to-gutenberg' ),
			'viewConverted'          => esc_html__( 'View converted', 'elementor-to-gutenberg' ),
			'retry'                  => esc_html__( 'Retry', 'elementor-to-gutenberg' ),
			'viewPages'              => esc_html__( 'View converted pages', 'elementor-to-gutenberg' ),
			'startNew'               => esc_html__( 'Start new conversion', 'elementor-to-gutenberg' ),
			'statusConverted'        => esc_html__( 'Converted', 'elementor-to-gutenberg' ),
			'statusNotConverted'     => esc_html__( 'Not converted', 'elementor-to-gutenberg' ),
			'statusPartial'          => esc_html__( 'Partial', 'elementor-to-gutenberg' ),
			'statusError'            => esc_html__( 'Error', 'elementor-to-gutenberg' ),
			'statusSkipped'          => esc_html__( 'Skipped', 'elementor-to-gutenberg' ),
			'statusUnknown'          => esc_html__( 'Unknown', 'elementor-to-gutenberg' ),
			'tableTitle'             => esc_html__( 'Title', 'elementor-to-gutenberg' ),
			'tableStatus'            => esc_html__( 'Status', 'elementor-to-gutenberg' ),
			'tableConversionStatus'  => esc_html__( 'Conversion status', 'elementor-to-gutenberg' ),
			'tableLastConverted'     => esc_html__( 'Last converted', 'elementor-to-gutenberg' ),
			'tableActions'           => esc_html__( 'Actions', 'elementor-to-gutenberg' ),
			'jobCompleted'           => esc_html__( 'Conversion completed successfully in %s.', 'elementor-to-gutenberg' ),
			'jobCompletedWithErrors' => esc_html__( 'Conversion finished with issues in %s.', 'elementor-to-gutenberg' ),
			'jobRunning'             => esc_html__( 'Conversion in progress…', 'elementor-to-gutenberg' ),
			'resumeJob'              => esc_html__( 'Resuming an active conversion job.', 'elementor-to-gutenberg' ),
			'processing'             => esc_html__( 'Processing…', 'elementor-to-gutenberg' ),
			'noSelectionError'       => esc_html__( 'Select at least one page before continuing.', 'elementor-to-gutenberg' ),
			'retryFailed'            => esc_html__( 'Unable to retry conversion. Please try again.', 'elementor-to-gutenberg' ),
		);
	}

	/**
	 * Retrieve active job info for current user if any.
	 */
	private function get_active_job_for_user(): array {
		$job_id = get_user_meta( get_current_user_id(), '_ele2gb_v2_job', true );
		if ( empty( $job_id ) ) {
			return array();
		}

		$job = $this->get_job( (string) $job_id );
		if ( empty( $job ) ) {
			delete_user_meta( get_current_user_id(), '_ele2gb_v2_job' );
			return array();
		}

		return $this->format_job_for_response( $job );
	}
}