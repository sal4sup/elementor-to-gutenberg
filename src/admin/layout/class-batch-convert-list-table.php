<?php
/**
 * WP_List_Table implementation for the batch wizard.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Layout;

use WP_List_Table;
use WP_Post;

use function absint;
use function add_query_arg;
use function esc_attr;
use function esc_attr_e;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function get_edit_post_link;
use function get_post;
use function get_post_meta;
use function get_post_status_object;
use function get_the_author_meta;
use function get_the_modified_date;
use function remove_query_arg;
use function selected;
use function submit_button;
use function wp_reset_postdata;

defined( 'ABSPATH' ) || exit;

/**
 * Displays Elementor pages with helpful filters.
 */
class Batch_Convert_List_Table extends WP_List_Table {

        /**
         * Active filters.
         *
         * @var array<string, string>
         */
        private array $filters = array();

        /**
         * Cached total items.
         */
        private int $total_items = 0;

        /**
         * Constructor.
         */
        public function __construct( array $filters = array() ) {
                parent::__construct(
                        array(
                                'singular' => 'page',
                                'plural'   => 'pages',
                                'ajax'     => false,
                        )
                );

                $this->filters = $filters;
        }

        /**
         * Retrieve total items.
         */
        public function get_total_items(): int {
                return $this->total_items;
        }

        /**
         * Prepare list table items.
         */
        public function prepare_items(): void {
                $per_page = $this->get_items_per_page( 'ele2gb_pages_per_page', 20 );
                $paged    = $this->get_pagenum();

                $args = array(
                        'post_type'      => 'page',
                        'post_status'    => $this->get_status_filter(),
                        'posts_per_page' => $per_page,
                        'paged'          => $paged,
                        'orderby'        => 'modified',
                        'order'          => 'DESC',
                        's'              => $this->filters['search'] ?? '',
                );

                $meta_query = array();
                if ( ! empty( $this->filters['has_elementor'] ) && in_array( $this->filters['has_elementor'], array( 'yes', 'no' ), true ) ) {
                        $compare      = 'yes' === $this->filters['has_elementor'] ? '!=' : '=';
                        $meta_query[] = array(
                                'key'     => '_elementor_data',
                                'value'   => '',
                                'compare' => $compare,
                        );
                }

                if ( ! empty( $meta_query ) ) {
                        $args['meta_query'] = $meta_query;
                }

                if ( ! empty( $this->filters['date_from'] ) || ! empty( $this->filters['date_to'] ) ) {
                        $range = array();
                        if ( ! empty( $this->filters['date_from'] ) ) {
                                $range['after'] = $this->filters['date_from'];
                        }
                        if ( ! empty( $this->filters['date_to'] ) ) {
                                $range['before'] = $this->filters['date_to'];
                        }
                        if ( ! empty( $range ) ) {
                                $args['date_query'] = array( $range );
                        }
                }

                $query = new \WP_Query( $args );

                $items = array();
                foreach ( $query->posts as $post ) {
                        if ( $post instanceof WP_Post ) {
                                $items[] = $post;
                        } else {
                                $items[] = get_post( $post );
                        }
                }

                $this->items       = array_filter( $items );
                $this->total_items = (int) $query->found_posts;

                wp_reset_postdata();

                $this->_column_headers = array( $this->get_columns(), array(), array() );

                $this->set_pagination_args(
                        array(
                                'total_items' => $this->total_items,
                                'per_page'    => $per_page,
                                'total_pages' => $per_page ? (int) ceil( $this->total_items / $per_page ) : 1,
                        )
                );
        }

        /**
         * Determine post status filter.
         */
        private function get_status_filter(): array|string {
                $status = $this->filters['post_status'] ?? 'any';
                if ( 'all' === $status || 'any' === $status ) {
                        return array( 'publish', 'draft', 'private', 'pending', 'future' );
                }

                return $status;
        }

        /**
         * Columns definition.
         */
        public function get_columns(): array {
                return array(
                        'cb'             => '<input type="checkbox" />',
                        'title'          => esc_html__( 'Title', 'elementor-to-gutenberg' ),
                        'status'         => esc_html__( 'Status', 'elementor-to-gutenberg' ),
                        'modified'       => esc_html__( 'Last Modified', 'elementor-to-gutenberg' ),
                        'author'         => esc_html__( 'Author', 'elementor-to-gutenberg' ),
                        'has_elementor'  => esc_html__( 'Has Elementor JSON', 'elementor-to-gutenberg' ),
                        'converted'      => esc_html__( 'Already Converted', 'elementor-to-gutenberg' ),
                );
        }

        /**
         * Checkbox column.
         */
        protected function column_cb( $item ): string {
                $post_id = absint( $item->ID );

                return sprintf(
                        '<label class="screen-reader-text" for="cb-select-%1$d">%2$s</label><input type="checkbox" class="ele2gb-page-checkbox" name="page_ids[]" value="%1$d" id="cb-select-%1$d" data-page-id="%1$d" />',
                        $post_id,
                        esc_html( $item->post_title )
                );
        }

        /**
         * Title column output.
         */
        public function column_title( $item ): string {
                $edit_link = get_edit_post_link( $item->ID );

                $title = sprintf(
                        '<strong><a href="%1$s">%2$s</a></strong>',
                        esc_url( $edit_link ),
                        esc_html( $item->post_title )
                );

                return $title;
        }

        /**
         * Default column handler.
         */
        public function column_default( $item, $column_name ): string {
                switch ( $column_name ) {
                        case 'status':
                                $status = get_post_status_object( $item->post_status );
                                return $status ? esc_html( $status->label ) : esc_html( $item->post_status );
                        case 'modified':
                                return esc_html( get_the_modified_date( '', $item ) );
                        case 'author':
                                return esc_html( get_the_author_meta( 'display_name', $item->post_author ) );
                        case 'has_elementor':
                                $has_data = get_post_meta( $item->ID, '_elementor_data', true );
                                return ! empty( $has_data ) ? esc_html__( 'Yes', 'elementor-to-gutenberg' ) : esc_html__( 'No', 'elementor-to-gutenberg' );
                        case 'converted':
                                $meta = get_post_meta( $item->ID, '_ele2gb_last_result', true );
                                if ( isset( $meta['status'] ) && 'success' === $meta['status'] ) {
                                        return esc_html__( 'Yes', 'elementor-to-gutenberg' );
                                }
                                return esc_html__( 'No', 'elementor-to-gutenberg' );
                        default:
                                return '';
                }
        }

        /**
         * Table views links.
         */
        protected function get_views(): array {
                $current = $this->filters['post_status'] ?? 'all';
                $statuses = array(
                        'all'      => esc_html__( 'All', 'elementor-to-gutenberg' ),
                        'publish'  => esc_html__( 'Published', 'elementor-to-gutenberg' ),
                        'draft'    => esc_html__( 'Drafts', 'elementor-to-gutenberg' ),
                        'pending'  => esc_html__( 'Pending', 'elementor-to-gutenberg' ),
                        'future'   => esc_html__( 'Scheduled', 'elementor-to-gutenberg' ),
                        'private'  => esc_html__( 'Private', 'elementor-to-gutenberg' ),
                );

                $views = array();
                foreach ( $statuses as $status => $label ) {
                        $url = add_query_arg(
                                array(
                                        'post_status' => $status,
                                        'page'        => 'ele2gb-batch-convert',
                                ),
                                remove_query_arg( 'paged' )
                        );

                        $class = $current === $status ? ' class="current"' : '';
                        $views[ $status ] = sprintf( '<a href="%1$s"%3$s>%2$s</a>', esc_url( $url ), esc_html( $label ), $class );
                }

                return $views;
        }

        /**
         * Additional controls above the table.
         */
        protected function extra_tablenav( $which ): void {
                if ( 'top' !== $which ) {
                        return;
                }

                $has_elementor = $this->filters['has_elementor'] ?? 'all';
                $date_from     = esc_attr( $this->filters['date_from'] ?? '' );
                $date_to       = esc_attr( $this->filters['date_to'] ?? '' );

                ?>
                <div class="alignleft actions ele2gb-filter-row">
                        <label class="screen-reader-text" for="filter-by-elementor"><?php esc_html_e( 'Filter by Elementor content', 'elementor-to-gutenberg' ); ?></label>
                        <select name="has_elementor" id="filter-by-elementor">
                                <option value="all" <?php selected( $has_elementor, 'all' ); ?>><?php esc_html_e( 'All pages', 'elementor-to-gutenberg' ); ?></option>
                                <option value="yes" <?php selected( $has_elementor, 'yes' ); ?>><?php esc_html_e( 'Has Elementor data', 'elementor-to-gutenberg' ); ?></option>
                                <option value="no" <?php selected( $has_elementor, 'no' ); ?>><?php esc_html_e( 'No Elementor data', 'elementor-to-gutenberg' ); ?></option>
                        </select>

                        <label class="screen-reader-text" for="filter-date-from"><?php esc_html_e( 'Modified after', 'elementor-to-gutenberg' ); ?></label>
                        <input type="date" id="filter-date-from" name="date_from" value="<?php echo $date_from; ?>" placeholder="<?php esc_attr_e( 'From', 'elementor-to-gutenberg' ); ?>" />

                        <label class="screen-reader-text" for="filter-date-to"><?php esc_html_e( 'Modified before', 'elementor-to-gutenberg' ); ?></label>
                        <input type="date" id="filter-date-to" name="date_to" value="<?php echo $date_to; ?>" placeholder="<?php esc_attr_e( 'To', 'elementor-to-gutenberg' ); ?>" />

                        <?php submit_button( esc_html__( 'Filter', 'elementor-to-gutenberg' ), 'secondary', 'filter_action', false ); ?>
                </div>
                <?php
        }

        /**
         * Disable bulk actions.
         */
        protected function get_bulk_actions(): array {
                return array();
        }
}

