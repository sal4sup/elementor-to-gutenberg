<?php
/**
 * Main admin settings class for Elementor to Gutenberg conversion.
 *
 * @package Progressus\Gutenberg
 */
namespace Progressus\Gutenberg\Admin;
use Progressus\Gutenberg\Admin\Helper\File_Upload_Service;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
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
                        $block_content .= $this->render_element( $element );
                }

                return $block_content;
        }

        /**
         * Render a single Elementor element into Gutenberg markup.
         *
         * @param array $element Elementor element data.
         * @return string
         */
        private function render_element( array $element ): string {
                if ( empty( $element['elType'] ) ) {
                        return '';
                }

                if ( 'container' === $element['elType'] ) {
                        return $this->render_container( $element );
                }

                if ( 'widget' === $element['elType'] ) {
                        return $this->render_widget( $element );
                }

                return sprintf(
                        '<!-- wp:paragraph -->%s<!-- /wp:paragraph -->' . "\n",
                        esc_html__( 'Unknown element', 'elementor-to-gutenberg' )
                );
        }

        /**
         * Render a container element.
         *
         * @param array $element Elementor container element.
         * @return string
         */
        private function render_container( array $element ): string {
                $children = $this->get_container_children( $element );

                if ( empty( $children ) ) {
                        $attributes = $this->extract_container_attributes( $element );
                        return $this->build_block_markup( 'group', $attributes, '' );
                }

                if ( $this->should_render_as_columns( $element, $children ) ) {
                        return $this->render_columns_row( $element, $children );
                }

                if ( $this->is_grid_container( $element ) ) {
                        return $this->render_grid_group( $element, $children );
                }

                return $this->render_group( $element, $children );
        }

        /**
         * Render a widget element using existing handlers.
         *
         * @param array $element Elementor widget element.
         * @return string
         */
        private function render_widget( array $element ): string {
                $widget_type = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
                if ( '' === $widget_type ) {
                        return '';
                }

                $handler = Widget_Handler_Factory::get_handler( $widget_type );
                if ( null !== $handler ) {
                        return $handler->handle( $element );
                }

                return sprintf(
                        '<!-- wp:paragraph -->%s<!-- /wp:paragraph -->' . "\n",
                        esc_html( $widget_type )
                );
        }

        /**
         * Render a generic group container.
         *
         * @param array $element Elementor container element.
         * @param array $children Container children.
         * @return string
         */
        private function render_group( array $element, array $children ): string {
                $inner_html = '';
                foreach ( $children as $child ) {
                        $inner_html .= $this->render_element( $child );
                }

                $attributes = $this->extract_container_attributes( $element );
                $attributes = $this->merge_block_attributes(
                        $attributes,
                        array( 'layout' => array( 'type' => 'constrained' ) )
                );

                return $this->build_block_markup( 'group', $attributes, $inner_html );
        }

        /**
         * Render a Columns block for a row-like container.
         *
         * @param array $element Elementor container element.
         * @param array $children Row children.
         * @return string
         */
        private function render_columns_row( array $element, array $children ): string {
                $column_widths = $this->normalize_column_widths( $children );
                $columns_html  = '';

                foreach ( $children as $index => $child ) {
                        $column_inner = $this->render_child_content( $child );

                        $column_attributes = array(
                                'layout' => array( 'type' => 'constrained' ),
                        );

                        if ( isset( $column_widths[ $index ] ) ) {
                                $column_attributes['width'] = $column_widths[ $index ];
                        }

                        if ( isset( $child['elType'] ) && 'container' === $child['elType'] ) {
                                $column_attributes = $this->merge_block_attributes(
                                        $column_attributes,
                                        $this->extract_container_attributes( $child )
                                );
                        }

                        $columns_html .= $this->build_block_markup( 'column', $column_attributes, $column_inner );
                }

                $attributes = $this->extract_container_attributes( $element );
                $gap_value  = $this->get_gap_value( $element );
                if ( '' !== $gap_value ) {
                        $attributes = $this->merge_block_attributes(
                                $attributes,
                                array( 'style' => array( 'spacing' => array( 'blockGap' => $gap_value ) ) )
                        );
                }

                $align = $this->get_align_from_settings( $element );
                if ( '' !== $align ) {
                        $attributes['align'] = $align;
                }

                return $this->build_block_markup( 'columns', $attributes, $columns_html );
        }

        /**
         * Render a grid group fallback.
         *
         * @param array $element Elementor container element.
         * @param array $children Child elements.
         * @return string
         */
        private function render_grid_group( array $element, array $children ): string {
                $grid_attributes = $this->extract_container_attributes( $element );
                $grid_attributes = $this->merge_block_attributes(
                        $grid_attributes,
                        array( 'layout' => array( 'type' => 'grid' ) )
                );

                $column_count = $this->get_grid_column_count( $element, count( $children ) );
                if ( $column_count > 0 ) {
                        $column_count = max( 1, min( 6, $column_count ) );
                        $grid_attributes['layout']['columnCount'] = $column_count;
                        $grid_attributes = $this->append_class( $grid_attributes, 'etg-grid etg-grid-cols-' . $column_count );
                } else {
                        $grid_attributes = $this->append_class( $grid_attributes, 'etg-grid' );
                }

                $gap_value = $this->get_gap_value( $element );
                if ( '' !== $gap_value ) {
                        $grid_attributes = $this->merge_block_attributes(
                                $grid_attributes,
                                array( 'style' => array( 'spacing' => array( 'blockGap' => $gap_value ) ) )
                        );
                }

                $items_html = '';
                foreach ( $children as $child ) {
                        $item_attributes = array( 'layout' => array( 'type' => 'constrained' ) );

                        if ( isset( $child['elType'] ) && 'container' === $child['elType'] ) {
                                $item_attributes = $this->merge_block_attributes(
                                        $item_attributes,
                                        $this->extract_container_attributes( $child )
                                );
                                $item_inner = $this->parse_elementor_elements(
                                        isset( $child['elements'] ) && is_array( $child['elements'] ) ? $child['elements'] : array()
                                );
                        } else {
                                $item_inner = $this->render_element( $child );
                        }

                        $items_html .= $this->build_block_markup( 'group', $item_attributes, $item_inner );
                }

                return $this->build_block_markup( 'group', $grid_attributes, $items_html );
        }

        /**
         * Retrieve container children.
         *
         * @param array $element Elementor container element.
         * @return array
         */
        private function get_container_children( array $element ): array {
                if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
                        return array();
                }

                return $element['elements'];
        }

        /**
         * Determine if the container should render as columns.
         *
         * @param array $element Elementor container element.
         * @param array $children Container children.
         * @return bool
         */
        private function should_render_as_columns( array $element, array $children ): bool {
                if ( count( $children ) < 2 ) {
                        return false;
                }

                if ( $this->is_grid_container( $element ) ) {
                        if ( count( $children ) > 4 ) {
                                return false;
                        }
                        return true;
                }

                $settings      = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
                $flex_direction = $this->get_flex_direction( $settings );

                if ( 'column' === $flex_direction || 'column-reverse' === $flex_direction ) {
                        return false;
                }

                $column_like_children = 0;
                foreach ( $children as $child ) {
                        if ( isset( $child['elType'] ) && 'container' === $child['elType'] ) {
                                $column_like_children++;
                        }
                }

                if ( 0 === $column_like_children ) {
                        return false;
                }

                if ( count( $children ) > 4 ) {
                        return false;
                }

                return true;
        }

        /**
         * Determine if container is configured as grid.
         *
         * @param array $element Elementor container element.
         * @return bool
         */
        private function is_grid_container( array $element ): bool {
                $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

                if ( isset( $settings['container_type'] ) && 'grid' === $settings['container_type'] ) {
                        return true;
                }

                if ( isset( $settings['layout'] ) && 'grid' === $settings['layout'] ) {
                        return true;
                }

                if ( isset( $settings['grid_columns_grid'] ) ) {
                        return true;
                }

                if ( isset( $settings['grid_template_columns'] ) || isset( $settings['grid_template_rows'] ) ) {
                        return true;
                }

                return false;
        }

        /**
         * Get flex direction from settings.
         *
         * @param array $settings Elementor container settings.
         * @return string
         */
        private function get_flex_direction( array $settings ): string {
                if ( isset( $settings['flex_direction'] ) && '' !== $settings['flex_direction'] ) {
                        return $settings['flex_direction'];
                }

                if ( isset( $settings['direction'] ) && '' !== $settings['direction'] ) {
                        return $settings['direction'];
                }

                return 'row';
        }

        /**
         * Normalize widths for each column.
         *
         * @param array $children Children elements.
         * @return array Array of percentage strings with trailing "%".
         */
        private function normalize_column_widths( array $children ): array {
                $width_hints = array();

                foreach ( $children as $child ) {
                        $width_hints[] = $this->get_child_width_hint( $child );
                }

                $count = count( $width_hints );
                if ( 0 === $count ) {
                        return array();
                }

                $total = 0;
                foreach ( $width_hints as $hint ) {
                        $total += $hint;
                }

                $normalized = array();
                if ( $total <= 0 ) {
                        $even = round( 100 / $count, 2 );
                        $normalized = array_fill( 0, $count, $even );
                } else {
                        foreach ( $width_hints as $hint ) {
                                $normalized[] = round( ( $hint / $total ) * 100, 2 );
                        }
                }

                $sum = 0;
                foreach ( $normalized as $value ) {
                        $sum += $value;
                }

                $difference = round( 100 - $sum, 2 );
                $last_index = count( $normalized ) - 1;
                $normalized[ $last_index ] = round( $normalized[ $last_index ] + $difference, 2 );

                $normalized_strings = array();
                foreach ( $normalized as $value ) {
                        $normalized_strings[] = rtrim( rtrim( sprintf( '%.2f', $value ), '0' ), '.' ) . '%';
                }

                return $normalized_strings;
        }

        /**
         * Get width hint from child settings.
         *
         * @param array $child Child element.
         * @return float
         */
        private function get_child_width_hint( array $child ): float {
                if ( ! isset( $child['settings'] ) || ! is_array( $child['settings'] ) ) {
                        return 0.0;
                }

                $settings = $child['settings'];

                if ( isset( $settings['_column_size'] ) ) {
                        return (float) $settings['_column_size'];
                }

                if ( isset( $settings['width'] ) ) {
                        if ( is_array( $settings['width'] ) && isset( $settings['width']['size'] ) ) {
                                return (float) $settings['width']['size'];
                        }

                        if ( is_numeric( $settings['width'] ) ) {
                                return (float) $settings['width'];
                        }
                }

                if ( isset( $settings['size'] ) && is_numeric( $settings['size'] ) ) {
                        return (float) $settings['size'];
                }

                return 0.0;
        }

        /**
         * Render content for a column child.
         *
         * @param array $child Child element.
         * @return string
         */
        private function render_child_content( array $child ): string {
                if ( isset( $child['elType'] ) && 'container' === $child['elType'] ) {
                        $grandchildren = isset( $child['elements'] ) && is_array( $child['elements'] ) ? $child['elements'] : array();
                        return $this->parse_elementor_elements( $grandchildren );
                }

                return $this->render_element( $child );
        }

        /**
         * Extract container attributes for Gutenberg blocks.
         *
         * @param array $element Elementor container element.
         * @return array
         */
        private function extract_container_attributes( array $element ): array {
                $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
                $attributes = array();

                if ( isset( $settings['background_color'] ) && '' !== $settings['background_color'] ) {
                        $attributes['style']['color']['background'] = $settings['background_color'];
                }

                if ( isset( $settings['_background_color'] ) && '' !== $settings['_background_color'] ) {
                        $attributes['style']['color']['background'] = $settings['_background_color'];
                }

                $spacing_attributes = Style_Parser::parse_spacing( $settings );
                $attributes         = $this->merge_block_attributes( $attributes, $spacing_attributes );

                if ( isset( $settings['css_classes'] ) && '' !== $settings['css_classes'] ) {
                        $attributes['className'] = $settings['css_classes'];
                }

                return $this->filter_block_attributes( $attributes );
        }

        /**
         * Merge block attributes.
         *
         * @param array $base Base attributes.
         * @param array $additional Additional attributes.
         * @return array
         */
        private function merge_block_attributes( array $base, array $additional ): array {
                foreach ( $additional as $key => $value ) {
                        if ( is_array( $value ) ) {
                                if ( ! isset( $base[ $key ] ) || ! is_array( $base[ $key ] ) ) {
                                        $base[ $key ] = array();
                                }
                                $base[ $key ] = $this->merge_block_attributes( $base[ $key ], $value );
                        } else {
                                $base[ $key ] = $value;
                        }
                }

                return $base;
        }

        /**
         * Clean empty entries from attribute arrays.
         *
         * @param array $attributes Attributes array.
         * @return array
         */
        private function filter_block_attributes( array $attributes ): array {
                foreach ( $attributes as $key => $value ) {
                        if ( is_array( $value ) ) {
                                $attributes[ $key ] = $this->filter_block_attributes( $value );
                                if ( empty( $attributes[ $key ] ) ) {
                                        unset( $attributes[ $key ] );
                                }
                        } elseif ( null === $value || '' === $value ) {
                                unset( $attributes[ $key ] );
                        }
                }

                return $attributes;
        }

        /**
         * Build block markup with attributes.
         *
         * @param string $block_name Gutenberg block name.
         * @param array  $attributes Block attributes.
         * @param string $inner_html Inner HTML.
         * @return string
         */
        private function build_block_markup( string $block_name, array $attributes, string $inner_html ): string {
                $attributes = $this->filter_block_attributes( $attributes );
                $attribute_json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );

                $output  = sprintf( '<!-- wp:%s%s -->', $block_name, $attribute_json );
                $output .= $inner_html;
                $output .= sprintf( '<!-- /wp:%s -->', $block_name );

                return $output . "\n";
        }

        /**
         * Determine align attribute from Elementor settings.
         *
         * @param array $element Elementor container element.
         * @return string
         */
        private function get_align_from_settings( array $element ): string {
                if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
                        return '';
                }

                $settings = $element['settings'];
                if ( isset( $settings['content_width'] ) ) {
                        if ( 'full' === $settings['content_width'] || 'full_width' === $settings['content_width'] ) {
                                return 'full';
                        }

                        if ( 'wide' === $settings['content_width'] ) {
                                return 'wide';
                        }
                }

                if ( isset( $settings['stretch_section'] ) && 'section-stretched' === $settings['stretch_section'] ) {
                        return 'full';
                }

                return '';
        }

        /**
         * Extract gap value from Elementor container.
         *
         * @param array $element Elementor container element.
         * @return string
         */
        private function get_gap_value( array $element ): string {
                if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
                        return '';
                }

                $settings = $element['settings'];

                if ( isset( $settings['gap'] ) && is_array( $settings['gap'] ) && isset( $settings['gap']['size'] ) && '' !== $settings['gap']['size'] ) {
                        $unit = isset( $settings['gap']['unit'] ) ? $settings['gap']['unit'] : 'px';
                        return $settings['gap']['size'] . $unit;
                }

                if ( isset( $settings['gap_columns'] ) && is_array( $settings['gap_columns'] ) && isset( $settings['gap_columns']['size'] ) && '' !== $settings['gap_columns']['size'] ) {
                        $unit = isset( $settings['gap_columns']['unit'] ) ? $settings['gap_columns']['unit'] : 'px';
                        return $settings['gap_columns']['size'] . $unit;
                }

                if ( isset( $settings['column_gap'] ) && '' !== $settings['column_gap'] ) {
                        return is_numeric( $settings['column_gap'] ) ? $settings['column_gap'] . 'px' : $settings['column_gap'];
                }

                return '';
        }

        /**
         * Determine grid column count for fallback grid groups.
         *
         * @param array $element Elementor container element.
         * @param int   $child_count Number of children.
         * @return int
         */
        private function get_grid_column_count( array $element, int $child_count ): int {
                if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
                        return 0;
                }

                $settings = $element['settings'];

                if ( isset( $settings['grid_columns_grid'] ) ) {
                        if ( is_array( $settings['grid_columns_grid'] ) && isset( $settings['grid_columns_grid']['size'] ) ) {
                                return (int) $settings['grid_columns_grid']['size'];
                        }

                        if ( is_numeric( $settings['grid_columns_grid'] ) ) {
                                return (int) $settings['grid_columns_grid'];
                        }
                }

                if ( isset( $settings['grid_columns'] ) ) {
                        if ( is_array( $settings['grid_columns'] ) && isset( $settings['grid_columns']['size'] ) ) {
                                return (int) $settings['grid_columns']['size'];
                        }

                        if ( is_numeric( $settings['grid_columns'] ) ) {
                                return (int) $settings['grid_columns'];
                        }
                }

                if ( isset( $settings['columns'] ) && is_numeric( $settings['columns'] ) ) {
                        return (int) $settings['columns'];
                }

                if ( $child_count > 0 && $child_count <= 4 ) {
                        return $child_count;
                }

                return 0;
        }

        /**
         * Append CSS class name to block attributes.
         *
         * @param array  $attributes Block attributes.
         * @param string $class_name Class name to append.
         * @return array
         */
        private function append_class( array $attributes, string $class_name ): array {
                $class_name = trim( $class_name );
                if ( '' === $class_name ) {
                        return $attributes;
                }

                if ( isset( $attributes['className'] ) && '' !== $attributes['className'] ) {
                        $attributes['className'] = trim( $attributes['className'] . ' ' . $class_name );
                } else {
                        $attributes['className'] = $class_name;
                }

                return $attributes;
        }
}
