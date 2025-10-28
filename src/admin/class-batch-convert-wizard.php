<?php
/**
 * Batch Convert Wizard admin page and AJAX controller.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin;

use Progressus\Gutenberg\Admin\Layout\Batch_Convert_List_Table;

use function absint;
use function admin_url;
use function check_ajax_referer;
use function current_time;
use function current_user_can;
use function delete_user_meta;
use function esc_attr;
use function esc_html__;
use function get_current_user_id;
use function get_option;
use function get_post;
use function get_post_meta;
use function get_post_thumbnail_id;
use function get_posts;
use function get_user_meta;
use function is_admin;
use function is_wp_error;
use function maybe_unserialize;
use function sanitize_text_field;
use function update_option;
use function update_post_meta;
use function update_user_meta;
use function wp_create_nonce;
use function wp_generate_uuid4;
use function wp_insert_post;
use function wp_parse_id_list;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function wp_update_post;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the admin wizard that batches Elementor → Gutenberg conversions.
 */
class Batch_Convert_Wizard {

        private const MENU_SLUG = 'ele2gb-batch-convert';

        private const QUEUE_META_KEY = '_ele2gb_batch_queue';

        private const RESULT_META_KEY = '_ele2gb_last_result';

        private const TEMPLATE_SLUG = 'full-width-elementor-style.php';

        /**
         * Singleton instance.
         *
         * @var Batch_Convert_Wizard|null
         */
        private static ?Batch_Convert_Wizard $instance = null;

        /**
         * Return singleton instance.
         */
        public static function instance(): Batch_Convert_Wizard {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Constructor.
         */
        public function __construct() {
                if ( ! is_admin() ) {
                        return;
                }

                add_action( 'admin_menu', array( $this, 'register_submenu' ), 15 );
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
                add_action( 'wp_ajax_ele2gb_start_batch', array( $this, 'ajax_start_batch' ) );
                add_action( 'wp_ajax_ele2gb_next_batch_item', array( $this, 'ajax_process_next' ) );
                add_action( 'wp_ajax_ele2gb_cancel_batch', array( $this, 'ajax_cancel_batch' ) );
                add_action( 'wp_ajax_ele2gb_resume_batch', array( $this, 'ajax_resume_batch' ) );
        }

        /**
         * Register wizard submenu page.
         */
        public function register_submenu(): void {
                if ( ! current_user_can( 'edit_pages' ) ) {
                        return;
                }

                add_submenu_page(
                        'gutenberg-settings',
                        esc_html__( 'Batch Convert Wizard', 'elementor-to-gutenberg' ),
                        esc_html__( 'Batch Convert Wizard', 'elementor-to-gutenberg' ),
                        'edit_pages',
                        self::MENU_SLUG,
                        array( $this, 'render_page' )
                );
        }

        /**
         * Enqueue admin assets for the wizard.
         */
        public function enqueue_assets( string $hook ): void {
                if ( 'gutenberg-settings_page_' . self::MENU_SLUG !== $hook ) {
                        return;
                }

                wp_enqueue_style(
                        'ele2gb-batch-wizard',
                        GUTENBERG_PLUGIN_DIR_URL . '/assets/css/batch-wizard.css',
                        array(),
                        GUTENBERG_PLUGIN_VERSION
                );

                wp_enqueue_script(
                        'ele2gb-batch-wizard',
                        GUTENBERG_PLUGIN_DIR_URL . '/assets/js/batch-wizard.js',
                        array( 'jquery' ),
                        GUTENBERG_PLUGIN_VERSION,
                        true
                );

                $list_table = $this->get_list_table();
                $filters    = $this->get_current_filters();
                $queue      = $this->get_active_queue();

                wp_localize_script(
                        'ele2gb-batch-wizard',
                        'ele2gbWizardData',
                        array(
                                'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                                'nonce'           => wp_create_nonce( 'ele2gb_batch_nonce' ),
                                'filters'         => $filters,
                                'userId'          => get_current_user_id(),
                                'totalMatching'   => $list_table ? (int) $list_table->get_total_items() : 0,
                                'queue'           => $queue,
                                'strings'         => array(
                                        'selectAllLabel'         => esc_html__( 'All pages matching the current filter will be converted.', 'elementor-to-gutenberg' ),
                                        'noSelection'            => esc_html__( 'Select at least one page to continue.', 'elementor-to-gutenberg' ),
                                        'selectedSingle'         => esc_html__( '%s page selected', 'elementor-to-gutenberg' ),
                                        'selectedPlural'         => esc_html__( '%s pages selected', 'elementor-to-gutenberg' ),
                                        'summaryAllMatching'     => esc_html__( 'Selection: all pages that match the current filter', 'elementor-to-gutenberg' ),
                                        'startButton'            => esc_html__( 'Start Conversion', 'elementor-to-gutenberg' ),
                                        'resumeButton'           => esc_html__( 'Resume Conversion', 'elementor-to-gutenberg' ),
                                        'resumePrompt'           => esc_html__( 'A previous batch is in progress. Resume now?', 'elementor-to-gutenberg' ),
                                        'processing'             => esc_html__( 'Processing %1$d of %2$d pages…', 'elementor-to-gutenberg' ),
                                        'complete'               => esc_html__( 'Conversion finished: %1$d succeeded, %2$d skipped, %3$d failed.', 'elementor-to-gutenberg' ),
                                        'cancelled'              => esc_html__( 'Conversion cancelled after processing %1$d pages.', 'elementor-to-gutenberg' ),
                                        'success'                => esc_html__( 'Success', 'elementor-to-gutenberg' ),
                                        'skipped'                => esc_html__( 'Skipped', 'elementor-to-gutenberg' ),
                                        'failed'                 => esc_html__( 'Failed', 'elementor-to-gutenberg' ),
                                        'ajaxError'              => esc_html__( 'Unexpected server error. Please review the logs.', 'elementor-to-gutenberg' ),
                                        'startError'             => esc_html__( 'Unable to start the batch conversion.', 'elementor-to-gutenberg' ),
                                        'exportFileName'         => 'ele2gb-results.csv',
                                        'modeUpdate'             => esc_html__( 'Mode: update existing pages', 'elementor-to-gutenberg' ),
                                        'modeCreate'             => esc_html__( 'Mode: create new Gutenberg pages', 'elementor-to-gutenberg' ),
                                        'optionAssignTemplate'   => esc_html__( 'Assign the full-width Elementor-style template', 'elementor-to-gutenberg' ),
                                        'optionWrap'             => esc_html__( 'Wrap converted content in a full-width group block', 'elementor-to-gutenberg' ),
                                        'optionPreserve'         => esc_html__( 'Preserve original as draft before updating', 'elementor-to-gutenberg' ),
                                        'optionKeepMeta'         => esc_html__( 'Keep featured image and custom meta', 'elementor-to-gutenberg' ),
                                        'optionSkipConverted'    => esc_html__( 'Skip pages already converted', 'elementor-to-gutenberg' ),
                                        'optionSkipConvertedOff' => esc_html__( 'Do not skip pages already converted', 'elementor-to-gutenberg' ),
                                        'optionNone'             => esc_html__( 'No additional options selected.', 'elementor-to-gutenberg' ),
                                ),
                        )
                );
        }

        /**
         * Render the wizard admin page.
         */
        public function render_page(): void {
                if ( ! current_user_can( 'edit_pages' ) ) {
                        wp_die( esc_html__( 'You do not have permission to access this page.', 'elementor-to-gutenberg' ) );
                }

                $list_table = $this->get_list_table();

                ?>
                <div class="wrap ele2gb-batch-wrap">
                        <h1><?php esc_html_e( 'Batch Convert Wizard', 'elementor-to-gutenberg' ); ?></h1>
                        <p class="description"><?php esc_html_e( 'Select Elementor pages, configure conversion options, and run the batch conversion queue safely.', 'elementor-to-gutenberg' ); ?></p>

                        <div class="ele2gb-step-indicator" role="tablist">
                                <span class="ele2gb-step-bubble is-active" data-step="1">1. <?php esc_html_e( 'Select Pages', 'elementor-to-gutenberg' ); ?></span>
                                <span class="ele2gb-step-bubble" data-step="2">2. <?php esc_html_e( 'Options', 'elementor-to-gutenberg' ); ?></span>
                                <span class="ele2gb-step-bubble" data-step="3">3. <?php esc_html_e( 'Confirm &amp; Run', 'elementor-to-gutenberg' ); ?></span>
                        </div>

                        <div class="ele2gb-step" data-step="1">
                                <form method="get" id="ele2gb-filter-form">
                                        <input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
                                        <?php
                                        if ( $list_table ) {
                                                $list_table->views();
                                                $list_table->search_box( esc_html__( 'Search Pages', 'elementor-to-gutenberg' ), 'ele2gb-search' );
                                                $list_table->display();
                                        }
                                        ?>
                                </form>
                                <div class="ele2gb-step-actions">
                                        <p id="ele2gb-selection-note" class="ele2gb-selection-note"></p>
                                        <div class="ele2gb-action-buttons">
                                                <button type="button" class="button" id="ele2gb-select-all-matching"><?php esc_html_e( 'Select all matching filter', 'elementor-to-gutenberg' ); ?></button>
                                                <button type="button" class="button button-primary" id="ele2gb-step1-next" disabled><?php esc_html_e( 'Next', 'elementor-to-gutenberg' ); ?></button>
                                        </div>
                                </div>
                        </div>

                        <div class="ele2gb-step" data-step="2" hidden>
                                <form id="ele2gb-options-form">
                                        <fieldset>
                                                <legend><?php esc_html_e( 'Conversion Mode', 'elementor-to-gutenberg' ); ?></legend>
                                                <label>
                                                        <input type="radio" name="mode" value="update" checked />
                                                        <?php esc_html_e( 'Update existing page', 'elementor-to-gutenberg' ); ?>
                                                </label>
                                                <label>
                                                        <input type="radio" name="mode" value="create" />
                                                        <?php esc_html_e( 'Create new page (append “Gutenberg” to title)', 'elementor-to-gutenberg' ); ?>
                                                </label>
                                        </fieldset>

                                        <fieldset>
                                                <legend><?php esc_html_e( 'Enhancements', 'elementor-to-gutenberg' ); ?></legend>
                                                <label>
                                                        <input type="checkbox" name="assign_template" value="1" />
                                                        <?php esc_html_e( 'Assign “Full-Width (Elementor-style)” template', 'elementor-to-gutenberg' ); ?>
                                                </label>
                                                <label>
                                                        <input type="checkbox" name="wrap_full_width" value="1" />
                                                        <?php esc_html_e( 'Wrap converted content in a full-width group block', 'elementor-to-gutenberg' ); ?>
                                                </label>
                                                <label>
                                                        <input type="checkbox" name="preserve_original" value="1" />
                                                        <?php esc_html_e( 'Preserve original as draft before updating', 'elementor-to-gutenberg' ); ?>
                                                </label>
                                                <label>
                                                        <input type="checkbox" name="keep_meta" value="1" />
                                                        <?php esc_html_e( 'Keep featured image and meta data', 'elementor-to-gutenberg' ); ?>
                                                </label>
                                                <label>
                                                        <input type="checkbox" name="skip_converted" value="1" checked />
                                                        <?php esc_html_e( 'Skip pages already converted', 'elementor-to-gutenberg' ); ?>
                                                </label>
                                        </fieldset>

                                        <div class="ele2gb-step-actions">
                                                <button type="button" class="button" data-ele2gb-back="1"><?php esc_html_e( 'Back', 'elementor-to-gutenberg' ); ?></button>
                                                <button type="button" class="button button-primary" id="ele2gb-step2-next"><?php esc_html_e( 'Review selection', 'elementor-to-gutenberg' ); ?></button>
                                        </div>
                                </form>
                        </div>

                        <div class="ele2gb-step" data-step="3" hidden>
                                <div class="ele2gb-summary">
                                        <h2><?php esc_html_e( 'Conversion Summary', 'elementor-to-gutenberg' ); ?></h2>
                                        <ul id="ele2gb-summary-list"></ul>
                                </div>
                                <div class="ele2gb-progress-controls">
                                        <button type="button" class="button" data-ele2gb-back="2"><?php esc_html_e( 'Back', 'elementor-to-gutenberg' ); ?></button>
                                        <button type="button" class="button button-primary" id="ele2gb-start-run"><?php esc_html_e( 'Start Conversion', 'elementor-to-gutenberg' ); ?></button>
                                        <button type="button" class="button" id="ele2gb-cancel-run" disabled><?php esc_html_e( 'Cancel', 'elementor-to-gutenberg' ); ?></button>
                                        <button type="button" class="button" id="ele2gb-export-results" disabled><?php esc_html_e( 'Export results (CSV)', 'elementor-to-gutenberg' ); ?></button>
                                </div>
                                <div id="ele2gb-messages" class="ele2gb-messages" aria-live="assertive"></div>
                                <div class="ele2gb-progress" aria-live="polite">
                                        <div class="ele2gb-progress-bar"><span class="ele2gb-progress-fill" style="width:0%"></span></div>
                                        <p id="ele2gb-progress-label"></p>
                                        <ul id="ele2gb-results"></ul>
                                </div>
                        </div>
                </div>
                <?php
        }

        /**
         * AJAX: Start batch conversion queue.
         */
        public function ajax_start_batch(): void {
                $this->verify_ajax_access();

                $selected_ids        = isset( $_POST['selected_ids'] ) ? wp_parse_id_list( wp_unslash( $_POST['selected_ids'] ) ) : array();
                $select_all_matching = ! empty( $_POST['select_all_matching'] );
                $options             = $this->sanitize_options( $_POST['options'] ?? array() );
                $filters             = $this->sanitize_filters( $_POST['filters'] ?? array() );

                if ( empty( $selected_ids ) && ! $select_all_matching ) {
                        wp_send_json_error(
                                array( 'message' => esc_html__( 'No pages were selected for conversion.', 'elementor-to-gutenberg' ) )
                        );
                }

                $queue = $this->prepare_queue( $selected_ids, $select_all_matching, $filters, $options );

                if ( empty( $queue['pending'] ) ) {
                        wp_send_json_error(
                                array( 'message' => esc_html__( 'No pages matched the current selection.', 'elementor-to-gutenberg' ) )
                        );
                }

                $this->save_queue( $queue );

                wp_send_json_success(
                        array(
                                'queue' => $this->format_queue_for_client( $queue ),
                                'item'  => null,
                        )
                );
        }

        /**
         * AJAX: Process the next queued item.
         */
        public function ajax_process_next(): void {
                $this->verify_ajax_access();

                $queue_id = sanitize_text_field( wp_unslash( $_POST['queue_id'] ?? '' ) );
                $queue    = $this->get_queue_by_id( $queue_id );

                if ( ! $queue ) {
                        wp_send_json_error(
                                array( 'message' => esc_html__( 'No active queue found. Start the wizard again.', 'elementor-to-gutenberg' ) )
                        );
                }

                if ( empty( $queue['pending'] ) ) {
                        wp_send_json_success(
                                array(
                                        'queue'    => $this->format_queue_for_client( $queue ),
                                        'item'     => null,
                                        'complete' => true,
                                )
                        );
                }

                $post_id = (int) array_shift( $queue['pending'] );

                $result = $this->process_single_post( $post_id, $queue['options'] );

                $queue['processed'][] = $post_id;
                $queue['results'][]   = $result;
                $queue['updated']     = time();

                if ( empty( $queue['pending'] ) ) {
                        $queue['completed'] = true;
                }

                $this->save_queue( $queue );

                wp_send_json_success(
                        array(
                                'queue'    => $this->format_queue_for_client( $queue ),
                                'item'     => $result,
                                'complete' => empty( $queue['pending'] ),
                        )
                );
        }

        /**
         * AJAX: Cancel the queue and remove stored state.
         */
        public function ajax_cancel_batch(): void {
                $this->verify_ajax_access();

                $queue_id = sanitize_text_field( wp_unslash( $_POST['queue_id'] ?? '' ) );
                $queue    = $this->get_queue_by_id( $queue_id );

                if ( ! $queue ) {
                        wp_send_json_error(
                                array( 'message' => esc_html__( 'No queue to cancel.', 'elementor-to-gutenberg' ) )
                        );
                }

                $queue['cancelled'] = true;
                $this->delete_queue();

                wp_send_json_success(
                        array(
                                'message' => esc_html__( 'Batch conversion cancelled. Completed pages remain unchanged.', 'elementor-to-gutenberg' ),
                        )
                );
        }

        /**
         * AJAX: Resume an existing queue if available.
         */
        public function ajax_resume_batch(): void {
                $this->verify_ajax_access();

                $queue = $this->get_active_queue();
                if ( ! $queue ) {
                        wp_send_json_error(
                                array( 'message' => esc_html__( 'No resumable batch found.', 'elementor-to-gutenberg' ) )
                        );
                }

                wp_send_json_success(
                        array(
                                'queue' => $queue,
                        )
                );
        }

        /**
         * Ensure current user can run AJAX action and nonce is valid.
         */
        private function verify_ajax_access(): void {
                if ( ! current_user_can( 'edit_pages' ) ) {
                        wp_send_json_error(
                                array( 'message' => esc_html__( 'You are not allowed to perform this action.', 'elementor-to-gutenberg' ) )
                        );
                }

                check_ajax_referer( 'ele2gb_batch_nonce', 'nonce' );
        }

        /**
         * Build WP_List_Table instance.
         */
        private function get_list_table(): ?Batch_Convert_List_Table {
                require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

                $filters = $this->get_current_filters();
                $table   = new Batch_Convert_List_Table( $filters );
                $table->prepare_items();

                return $table;
        }

        /**
         * Get filters based on current request.
         */
        private function get_current_filters(): array {
                $filters = array();

                if ( isset( $_GET['post_status'] ) ) {
                        $filters['post_status'] = sanitize_text_field( wp_unslash( $_GET['post_status'] ) );
                }

                if ( isset( $_GET['has_elementor'] ) ) {
                        $filters['has_elementor'] = sanitize_text_field( wp_unslash( $_GET['has_elementor'] ) );
                }

                if ( isset( $_GET['date_from'] ) ) {
                        $filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) );
                }

                if ( isset( $_GET['date_to'] ) ) {
                        $filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) );
                }

                if ( isset( $_GET['s'] ) ) {
                        $filters['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
                }

                return $filters;
        }

        /**
         * Sanitize options passed from the client.
         */
        private function sanitize_options( array $options ): array {
                $mode = 'update';
                if ( isset( $options['mode'] ) && in_array( $options['mode'], array( 'update', 'create' ), true ) ) {
                        $mode = $options['mode'];
                }

                return array(
                        'mode'             => $mode,
                        'assign_template'  => ! empty( $options['assign_template'] ),
                        'wrap_full_width'  => ! empty( $options['wrap_full_width'] ),
                        'preserve_original' => ! empty( $options['preserve_original'] ),
                        'keep_meta'        => ! empty( $options['keep_meta'] ),
                        'skip_converted'   => ! empty( $options['skip_converted'] ),
                );
        }

        /**
         * Sanitize filters supplied by the client.
         */
        private function sanitize_filters( array $filters ): array {
                $sanitized = array();

                foreach ( array( 'post_status', 'has_elementor', 'date_from', 'date_to', 'search' ) as $key ) {
                        if ( isset( $filters[ $key ] ) ) {
                                $sanitized[ $key ] = sanitize_text_field( wp_unslash( $filters[ $key ] ) );
                        }
                }

                return $sanitized;
        }

        /**
         * Prepare queue data structure.
         */
        private function prepare_queue( array $selected_ids, bool $select_all, array $filters, array $options ): array {
                $queue_id = wp_generate_uuid4();
                $ids      = $select_all ? $this->query_ids_for_filters( $filters ) : $selected_ids;

                $ids = array_values( array_unique( array_map( 'absint', $ids ) ) );

                return array(
                        'id'           => $queue_id,
                        'pending'      => $ids,
                        'processed'    => array(),
                        'results'      => array(),
                        'options'      => $options,
                        'filters'      => $filters,
                        'select_all'   => $select_all,
                        'total'        => count( $ids ),
                        'created'      => time(),
                        'updated'      => time(),
                        'completed'    => false,
                        'cancelled'    => false,
                );
        }

        /**
         * Query page IDs that match filters for select-all.
         */
        private function query_ids_for_filters( array $filters ): array {
                $args = array(
                        'post_type'      => 'page',
                        'post_status'    => 'any',
                        'posts_per_page' => -1,
                        'fields'         => 'ids',
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                );

                if ( ! empty( $filters['post_status'] ) && 'all' !== $filters['post_status'] ) {
                        $args['post_status'] = $filters['post_status'];
                }

                if ( ! empty( $filters['search'] ) ) {
                        $args['s'] = $filters['search'];
                }

                if ( ! empty( $filters['has_elementor'] ) && in_array( $filters['has_elementor'], array( 'yes', 'no' ), true ) ) {
                        $meta_compare = 'yes' === $filters['has_elementor'] ? '!=' : '=';
                        $args['meta_query'][] = array(
                                'key'     => '_elementor_data',
                                'value'   => '',
                                'compare' => $meta_compare,
                        );
                }

                if ( ! empty( $filters['date_from'] ) || ! empty( $filters['date_to'] ) ) {
                        $date_query = array();
                        if ( ! empty( $filters['date_from'] ) ) {
                                $date_query['after'] = $filters['date_from'];
                        }
                        if ( ! empty( $filters['date_to'] ) ) {
                                $date_query['before'] = $filters['date_to'];
                        }
                        if ( ! empty( $date_query ) ) {
                                $args['date_query'] = array( $date_query );
                        }
                }

                $posts = get_posts( $args );

                return is_array( $posts ) ? $posts : array();
        }

        /**
         * Persist queue for current user.
         */
        private function save_queue( array $queue ): void {
                update_user_meta( get_current_user_id(), self::QUEUE_META_KEY, $queue );
        }

        /**
         * Delete queue for current user.
         */
        private function delete_queue(): void {
                delete_user_meta( get_current_user_id(), self::QUEUE_META_KEY );
        }

        /**
         * Retrieve active queue for current user.
         */
        private function get_active_queue(): ?array {
                $queue = get_user_meta( get_current_user_id(), self::QUEUE_META_KEY, true );
                if ( ! is_array( $queue ) || empty( $queue['id'] ) ) {
                        return null;
                }

                return $this->format_queue_for_client( $queue );
        }

        /**
         * Retrieve queue by id.
         */
        private function get_queue_by_id( string $queue_id ): ?array {
                $queue = get_user_meta( get_current_user_id(), self::QUEUE_META_KEY, true );
                if ( ! is_array( $queue ) || empty( $queue['id'] ) || $queue['id'] !== $queue_id ) {
                        return null;
                }

                return $queue;
        }

        /**
         * Format queue for sending to the browser.
         */
        private function format_queue_for_client( array $queue ): array {
                $results = array();
                foreach ( $queue['results'] as $entry ) {
                        $results[] = array(
                                'post_id'   => isset( $entry['post_id'] ) ? (int) $entry['post_id'] : 0,
                                'title'     => $entry['title'] ?? '',
                                'status'    => $entry['status'] ?? 'queued',
                                'message'   => $entry['message'] ?? '',
                                'target_id' => isset( $entry['target_id'] ) ? (int) $entry['target_id'] : 0,
                        );
                }

                return array(
                        'id'           => $queue['id'],
                        'pending'      => array_map( 'absint', $queue['pending'] ?? array() ),
                        'processed'    => array_map( 'absint', $queue['processed'] ?? array() ),
                        'results'      => $results,
                        'options'      => $queue['options'] ?? array(),
                        'filters'      => $queue['filters'] ?? array(),
                        'select_all'   => ! empty( $queue['select_all'] ),
                        'total'        => (int) ( $queue['total'] ?? count( $queue['pending'] ?? array() ) ),
                        'created'      => (int) ( $queue['created'] ?? time() ),
                        'updated'      => (int) ( $queue['updated'] ?? time() ),
                        'completed'    => ! empty( $queue['completed'] ),
                        'cancelled'    => ! empty( $queue['cancelled'] ),
                );
        }

        /**
         * Process an individual page.
         */
        private function process_single_post( int $post_id, array $options ): array {
                $post = get_post( $post_id );
                if ( ! $post || 'page' !== $post->post_type ) {
                        return $this->result_entry( $post_id, 'failed', esc_html__( 'Post not found or is not a page.', 'elementor-to-gutenberg' ) );
                }

                if ( ! current_user_can( 'edit_post', $post_id ) ) {
                        return $this->result_entry( $post_id, 'failed', esc_html__( 'You cannot edit this page.', 'elementor-to-gutenberg' ) );
                }

                $elementor_json = get_post_meta( $post_id, '_elementor_data', true );
                if ( empty( $elementor_json ) ) {
                        return $this->mark_result( $post_id, $post, 'skipped', esc_html__( 'No Elementor data found.', 'elementor-to-gutenberg' ) );
                }

                if ( ! empty( $options['skip_converted'] ) ) {
                        $last_result = get_post_meta( $post_id, self::RESULT_META_KEY, true );
                        if ( is_array( $last_result ) && ( $last_result['status'] ?? '' ) === 'success' ) {
                                return $this->mark_result( $post_id, $post, 'skipped', esc_html__( 'Already converted previously.', 'elementor-to-gutenberg' ) );
                        }
                }

                $json_data = json_decode( $elementor_json, true );
                $content   = Admin_Settings::instance()->convert_json_to_gutenberg_content(
                        array(
                                'content' => is_array( $json_data ) ? $json_data : array(),
                        )
                );

                if ( empty( $content ) ) {
                        return $this->mark_result( $post_id, $post, 'failed', esc_html__( 'Conversion returned empty content.', 'elementor-to-gutenberg' ) );
                }

                if ( ! empty( $options['wrap_full_width'] ) ) {
                        $content = $this->wrap_full_width_group( $content );
                }

                if ( 'create' === $options['mode'] ) {
                        $result = $this->create_new_page( $post, $content, $options );
                } else {
                        $result = $this->update_existing_page( $post, $content, $options );
                }

                return $result;
        }

        /**
         * Create a new Gutenberg page from source.
         */
        private function create_new_page( \WP_Post $source, string $content, array $options ): array {
                $new_post = array(
                        'post_title'   => $source->post_title . ' (Gutenberg)',
                        'post_type'    => 'page',
                        'post_status'  => 'publish',
                        'post_content' => $content,
                        'post_parent'  => $source->post_parent,
                );

                $new_post_id = wp_insert_post( $new_post, true );

                if ( is_wp_error( $new_post_id ) || ! $new_post_id ) {
                        $message = is_wp_error( $new_post_id ) ? $new_post_id->get_error_message() : esc_html__( 'Unknown error creating page.', 'elementor-to-gutenberg' );
                        return $this->mark_result( $source->ID, $source, 'failed', $message );
                }

                if ( ! empty( $options['keep_meta'] ) ) {
                        $this->copy_page_meta( $source->ID, $new_post_id );
                }

                if ( ! empty( $options['assign_template'] ) ) {
                        $this->assign_full_width_template( $new_post_id );
                }

                $this->store_result_meta( $source->ID, $new_post_id, 'success', esc_html__( 'Created Gutenberg page copy.', 'elementor-to-gutenberg' ) );

                return $this->result_entry( $source->ID, 'success', esc_html__( 'Created new Gutenberg page.', 'elementor-to-gutenberg' ), $new_post_id, $source->post_title );
        }

        /**
         * Update existing page with converted content.
         */
        private function update_existing_page( \WP_Post $source, string $content, array $options ): array {
                if ( ! empty( $options['preserve_original'] ) ) {
                        $this->create_backup_draft( $source );
                }

                $update = array(
                        'ID'           => $source->ID,
                        'post_content' => $content,
                );

                $updated = wp_update_post( $update, true );

                if ( is_wp_error( $updated ) ) {
                        return $this->mark_result( $source->ID, $source, 'failed', $updated->get_error_message() );
                }

                if ( ! empty( $options['assign_template'] ) ) {
                        $this->assign_full_width_template( $source->ID );
                }

                $this->store_result_meta( $source->ID, $source->ID, 'success', esc_html__( 'Updated page with Gutenberg content.', 'elementor-to-gutenberg' ) );

                return $this->result_entry( $source->ID, 'success', esc_html__( 'Updated existing page.', 'elementor-to-gutenberg' ), $source->ID, $source->post_title );
        }

        /**
         * Store conversion metadata for diagnostics and resume.
         */
        private function store_result_meta( int $source_id, int $target_id, string $status, string $message ): void {
                update_post_meta( $target_id, '_ele2gb_last_converted', current_time( 'mysql' ) );
                update_post_meta( $source_id, '_ele2gb_target_post_id', $target_id );
                update_post_meta(
                        $source_id,
                        self::RESULT_META_KEY,
                        array(
                                'status'  => $status,
                                'message' => $message,
                                'target'  => $target_id,
                                'time'    => time(),
                        )
                );

                $this->append_log_entry( $source_id, $target_id, $status, $message );
        }

        /**
         * Append entry to rolling plugin log.
         */
        private function append_log_entry( int $source_id, int $target_id, string $status, string $message ): void {
                $log = get_option( 'ele2gb_conversion_log', array() );
                if ( ! is_array( $log ) ) {
                        $log = array();
                }

                $log[] = array(
                        'time'      => time(),
                        'user_id'   => get_current_user_id(),
                        'source_id' => $source_id,
                        'target_id' => $target_id,
                        'status'    => $status,
                        'message'   => $message,
                );

                $log = array_slice( $log, -20 );
                update_option( 'ele2gb_conversion_log', $log, false );
        }

        /**
         * Result helper with post meta storage for skip/failure.
         */
        private function mark_result( int $post_id, \WP_Post $post, string $status, string $message ): array {
                $target = 'success' === $status ? $post_id : 0;

                if ( 'success' === $status ) {
                        $this->store_result_meta( $post_id, $target, $status, $message );
                } else {
                        update_post_meta(
                                $post_id,
                                self::RESULT_META_KEY,
                                array(
                                        'status'  => $status,
                                        'message' => $message,
                                        'time'    => time(),
                                )
                        );
                        $this->append_log_entry( $post_id, $target, $status, $message );
                }

                return $this->result_entry( $post_id, $status, $message, $target, $post->post_title );
        }

        /**
         * Create result array.
         */
        private function result_entry( int $post_id, string $status, string $message, int $target_id = 0, string $title = '' ): array {
                return array(
                        'post_id'   => $post_id,
                        'title'     => $title,
                        'status'    => $status,
                        'message'   => $message,
                        'target_id' => $target_id,
                );
        }

        /**
         * Copy selected meta from source to new page.
         */
        private function copy_page_meta( int $source_id, int $target_id ): void {
                $meta = get_post_meta( $source_id );
                foreach ( $meta as $key => $values ) {
                        if ( in_array( $key, array( '_elementor_data', '_edit_lock', '_edit_last', self::RESULT_META_KEY, '_ele2gb_target_post_id' ), true ) ) {
                                continue;
                        }

                        foreach ( $values as $value ) {
                                update_post_meta( $target_id, $key, maybe_unserialize( $value ) );
                        }
                }

                $thumb_id = get_post_thumbnail_id( $source_id );
                if ( $thumb_id ) {
                        update_post_meta( $target_id, '_thumbnail_id', $thumb_id );
                }
        }

        /**
         * Assign custom full width template to a page.
         */
        private function assign_full_width_template( int $post_id ): void {
                update_post_meta( $post_id, '_wp_page_template', self::TEMPLATE_SLUG );
        }

        /**
         * Wrap content in a full-width group block.
         */
        private function wrap_full_width_group( string $content ): string {
                $open  = '<!-- wp:group {"layout":{"type":"default"}} -->';
                $close = '<!-- /wp:group -->';

                return $open . $content . $close;
        }

        /**
         * Create draft backup of original page.
         */
        private function create_backup_draft( \WP_Post $source ): void {
                $backup = array(
                        'post_title'   => $source->post_title . ' (Elementor backup)',
                        'post_type'    => 'page',
                        'post_status'  => 'draft',
                        'post_content' => $source->post_content,
                        'post_parent'  => $source->post_parent,
                );

                $backup_id = wp_insert_post( $backup );

                if ( $backup_id && ! is_wp_error( $backup_id ) ) {
                        $this->copy_page_meta( $source->ID, $backup_id );
                }
        }
}

