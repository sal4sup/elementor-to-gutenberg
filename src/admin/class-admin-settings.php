<?php
/**
 * Main admin settings class for Elementor to Gutenberg conversion.
 *
 * @package Progressus\Gutenberg
 */
namespace Progressus\Gutenberg\Admin;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\File_Upload_Service;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Layout\Columns_Widths;
use Progressus\Gutenberg\Admin\Layout\Container_Classifier;
defined( 'ABSPATH' ) || exit;
/**
 * Main admin settings class for Elementor to Gutenberg conversion.
 */
class Admin_Settings {
	/**
	 * Singleton instance.
	 *
	 * @var Admin_Settings|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Admin_Settings
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
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			esc_html__( 'Elementor To Gutenberg Settings', 'elementor-to-gutenberg' ),
			esc_html__( 'Elementor To Gutenberg Settings', 'elementor-to-gutenberg' ),
			'manage_options',
			'gutenberg-settings',
			array( $this, 'settings_page_content' ),
			'dashicons-admin-generic',
			100
		);
	}

	/**
	 * Initialize settings.
	 */
	public function settings_init(): void {
		register_setting(
			'gutenberg_settings_group',
			'gutenberg_json_data',
			array(
				'sanitize_callback' => array( $this, 'handle_json_upload' ),
			)
		);

		add_settings_section(
			'gutenberg_settings_section',
			esc_html__( 'Upload JSON Data', 'elementor-to-gutenberg' ),
			null,
			'gutenberg-settings'
		);

		add_settings_field(
			'gutenberg_json_upload',
			esc_html__( 'JSON File', 'elementor-to-gutenberg' ),
			array( $this, 'json_upload_field_callback' ),
			'gutenberg-settings',
			'gutenberg_settings_section'
		);
	}

	/**
	 * Render JSON upload field.
	 */
	public function json_upload_field_callback(): void {
		?>
		<input type="file" name="json_upload" accept=".json" />
		<?php
	}

	/**
	 * Handle JSON file upload and conversion.
	 *
	 * @param mixed $option The option value.
	 * @return string The processed Gutenberg content or existing option.
	 */
	public function handle_json_upload( $option ): string {
		if ( empty( $_FILES['json_upload']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $option;
		}

		$json_content = File_Upload_Service::upload_file( $_FILES['json_upload'], 'json' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null === $json_content ) {
			return get_option( 'gutenberg_json_data', '' );
		}

		$data = json_decode( $json_content, true );
		$gutenberg_content = $this->convert_json_to_gutenberg_content( $data );

		$post_title = $data['title'] ?? 'Untitled';
		$post_type  = $data['type'] ?? 'page';

		// Check if a post with the same title and type exists
		$existing_post = get_page_by_title( $post_title, OBJECT, $post_type );

		if ( $existing_post ) {
			// Update existing post
			$new_post_id = wp_update_post(
				array(
					'ID'           => $existing_post->ID,
					'post_content' => $gutenberg_content,
					'post_status'  => 'publish',
				)
			);
		} else {
			// Create new post
			$new_post_id = wp_insert_post(
				array(
					'post_title'   => sanitize_text_field( $post_title ),
					'post_content' => $gutenberg_content,
					'post_type'    => sanitize_key( $post_type ),
					'post_status'  => 'publish',
				)
			);
		}

		if ( is_wp_error( $new_post_id ) ) {
			add_settings_error(
				'gutenberg_json_data',
				'json_upload_error',
				esc_html__( 'Failed to create new page.', 'elementor-to-gutenberg' ),
				'error'
			);
			return get_option( 'gutenberg_json_data', '' );
		}

		add_settings_error(
			'gutenberg_json_data',
			'json_upload_success',
			esc_html__( 'JSON file uploaded and page created successfully!', 'elementor-to-gutenberg' ),
			'updated'
		);
		return $gutenberg_content;
	}

	/**
	 * Render settings page content.
	 */
	public function settings_page_content(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gutenberg Settings', 'elementor-to-gutenberg' ); ?></h1>
			<?php settings_errors( 'gutenberg_json_data' ); ?>
			<form method="post" action="options.php" enctype="multipart/form-data" id="json-upload-form">
				<?php
				settings_fields( 'gutenberg_settings_group' );
				do_settings_sections( 'gutenberg-settings' );
				submit_button( esc_html__( 'Upload JSON File', 'elementor-to-gutenberg' ), 'primary', 'json-upload-btn' );
				?>
				<span id="json-upload-spinner" style="display:none;margin-left:10px;">
					<img src="<?php echo esc_url( admin_url( 'images/spinner.gif' ) ); ?>" alt="<?php esc_attr_e( 'Loading', 'elementor-to-gutenberg' ); ?>" /> <?php esc_html_e( 'Uploading...', 'elementor-to-gutenberg' ); ?>
				</span>
			</form>
			<script>
				( function() {
					'use strict';
					var form = document.getElementById( 'json-upload-form' );
					var button = document.getElementById( 'json-upload-btn' );
					var spinner = document.getElementById( 'json-upload-spinner' );
					if ( form && button && spinner ) {
						form.addEventListener( 'submit', function() {
							button.disabled = true;
							spinner.style.display = 'inline-block';
						} );
					}
				} )();
			</script>
		</div>
		<?php
	}

	/**
	 * Convert JSON data to Gutenberg blocks.
	 *
	 * @param array $json_data The JSON data to convert.
	 * @return string The converted Gutenberg content.
	 */
	public function convert_json_to_gutenberg_content( array $json_data ): string {
		if ( ! isset( $json_data['content'] ) || ! is_array( $json_data['content'] ) ) {
			return '';
		}
		return $this->parse_elementor_elements( $json_data['content'] );
	}

        /**
         * Parse Elementor elements to Gutenberg blocks.
         *
         * @param array $elements The Elementor elements array.
         * @return string The converted Gutenberg block content.
         */
        public function parse_elementor_elements( array $elements ): string {
                $block_content = '';

                foreach ( $elements as $element ) {
                        if ( ! is_array( $element ) ) {
                                continue;
                        }

                        $el_type = $element['elType'] ?? '';

                        if ( 'container' === $el_type ) {
                                $block_content .= $this->render_container( $element );
                                continue;
                        }

                        if ( 'widget' === $el_type ) {
                                $block_content .= $this->render_widget( $element );
                                continue;
                        }

                        $block_content .= $this->render_unknown_element();
                }

                return $block_content;
        }

        /**
         * Render a container element into Gutenberg markup.
         *
         * @param array $element Elementor container element.
         *
         * @return string
         */
        private function render_container( array $element ): string {
                $children = $this->get_container_children( $element );

                $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

                if ( empty( $children ) ) {
                        $attrs = Style_Parser::parse_container_styles( $settings );
                        return Block_Builder::build( 'group', $attrs, '' );
                }

                if ( Container_Classifier::should_render_columns( $element, $children ) ) {
                        return $this->render_columns_row( $element, $children );
                }

                if ( Container_Classifier::is_grid( $element ) ) {
                        return $this->render_grid_group( $element, $children );
                }

                return $this->render_group( $element, $children );
        }

        /**
         * Render container as columns block.
         *
         * @param array $element  Parent container.
         * @param array $children Child containers for columns.
         *
         * @return string
         */
        private function render_columns_row( array $element, array $children ): string {
                $settings  = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
                $row_attrs = Style_Parser::parse_container_styles( $settings );

                $normalized_widths = Columns_Widths::normalize( $children );
                $columns_html      = '';

                foreach ( $children as $index => $child ) {
                        $child_settings = isset( $child['settings'] ) && is_array( $child['settings'] ) ? $child['settings'] : array();
                        $child_attrs    = Style_Parser::parse_container_styles( $child_settings );
                        if ( isset( $normalized_widths[ $index ] ) ) {
                                $child_attrs['width'] = $normalized_widths[ $index ];
                        }

                        $inner_elements = $child['elements'] ?? array();
                        $inner_html     = $this->parse_elementor_elements( is_array( $inner_elements ) ? $inner_elements : array() );
                        $columns_html  .= Block_Builder::build( 'column', $child_attrs, $inner_html );
                }

                return Block_Builder::build( 'columns', $row_attrs, $columns_html );
        }

        /**
         * Render container as grid group.
         *
         * @param array $element  Parent container.
         * @param array $children Child elements.
         *
         * @return string
         */
        private function render_grid_group( array $element, array $children ): string {
                $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
                $attrs    = Style_Parser::parse_container_styles( $settings );
                $attrs['layout'] = array( 'type' => 'grid' );

                $column_count = Container_Classifier::get_grid_column_count( $element, count( $children ) );
                if ( $column_count > 0 ) {
                        $attrs['layout']['columnCount'] = $column_count;
                        $class_name = $attrs['className'] ?? '';
                        $class_name = trim( $class_name . ' etg-grid-cols-' . $column_count );
                        $attrs['className'] = trim( $class_name );
                }

                $items_html = '';
                foreach ( $children as $child ) {
                        $child_html = $this->parse_elementor_elements( array( $child ) );
                        $items_html .= Block_Builder::build(
                                'group',
                                array( 'layout' => array( 'type' => 'constrained' ) ),
                                $child_html
                        );
                }

                return Block_Builder::build( 'group', $attrs, $items_html );
        }

        /**
         * Render container as a regular group block.
         *
         * @param array $element  Container element.
         * @param array $children Child elements.
         *
         * @return string
         */
        private function render_group( array $element, array $children ): string {
                $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
                $attrs    = Style_Parser::parse_container_styles( $settings );
                $attrs['layout'] = array( 'type' => 'constrained' );

                $inner_html = $this->parse_elementor_elements( $children );

                return Block_Builder::build( 'group', $attrs, $inner_html );
        }

        /**
         * Render a widget element.
         *
         * @param array $element Elementor widget element.
         *
         * @return string
         */
        private function render_widget( array $element ): string {
                $widget_type = $element['widgetType'] ?? '';
                $handler     = $widget_type ? Widget_Handler_Factory::get_handler( $widget_type ) : null;

                if ( null !== $handler ) {
                        return $handler->handle( $element );
                }

                $label = $widget_type ? $widget_type : esc_html__( 'Unknown widget', 'elementor-to-gutenberg' );

                return Block_Builder::build(
                        'group',
                        array( 'layout' => array( 'type' => 'constrained' ) ),
                        sprintf(
                                '<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->',
                                esc_html( $label )
                        )
                );
        }

        /**
         * Render markup for unknown elements.
         *
         * @return string
         */
        private function render_unknown_element(): string {
                return Block_Builder::build(
                        'group',
                        array( 'layout' => array( 'type' => 'constrained' ) ),
                        sprintf(
                                '<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->',
                                esc_html__( 'Unknown element', 'elementor-to-gutenberg' )
                        )
                );
        }

        /**
         * Get valid container children.
         *
         * @param array $element Elementor container element.
         *
         * @return array
         */
        private function get_container_children( array $element ): array {
                $children = $element['elements'] ?? array();
                if ( ! is_array( $children ) ) {
                        return array();
                }

                return array_values( array_filter( $children, 'is_array' ) );
        }
}
