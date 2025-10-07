<?php
/**
 * Widget handler for Elementor heading widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor heading widget.
 */
class Heading_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor heading to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
        public function handle( array $element ): string {
                $settings     = $element['settings'] ?? array();
                $title        = $settings['title'] ?? '';
                $text_color   = isset( $settings['title_color'] ) ? strtolower( (string) $settings['title_color'] ) : '';
                $custom_class = trim( (string) ( $settings['_css_classes'] ?? '' ) );
                $unique_class = 'heading-' . uniqid();
                $custom_id    = $settings['_element_id'] ?? '';
                $custom_css   = $settings['custom_css'] ?? '';

                $header_size = isset( $settings['header_size'] ) ? strtolower( (string) $settings['header_size'] ) : 'h2';
                $level       = 2;
                if ( preg_match( '/h([1-6])/', $header_size, $matches ) ) {
                        $level = (int) $matches[1];
                }

                $class_parts = array( 'wp-block-heading', $unique_class );
                if ( '' !== $custom_class ) {
                        $class_parts = array_merge( $class_parts, preg_split( '/\s+/', $custom_class ) ?: array() );
                }

                if ( ! empty( $settings['typography_text_transform'] ) ) {
                        $class_parts[] = 'has-text-transform-' . sanitize_html_class( $settings['typography_text_transform'] );
                }

                $typography    = Style_Parser::parse_typography( $settings );
                $typo_attrs    = is_array( $typography['attributes'] ?? null ) ? $typography['attributes'] : array();
                $typo_css      = is_string( $typography['style'] ?? null ) ? $typography['style'] : '';
                $border        = Style_Parser::parse_border( $settings );
                $border_attrs  = is_array( $border['attributes'] ?? null ) ? $border['attributes'] : array();
                $border_css    = is_string( $border['style'] ?? null ) ? $border['style'] : '';
                $spacing       = Style_Parser::parse_spacing( $settings );
                $spacing_attrs = is_array( $spacing['attributes'] ?? null ) ? $spacing['attributes'] : array();
                $spacing_css   = is_string( $spacing['style'] ?? null ) ? $spacing['style'] : '';

                $attrs_array = array(
                        'level' => $level,
                );

                $inline_style = '';
                if ( $this->is_preset_color_slug( $text_color ) ) {
                        $attrs_array['textColor']                                  = $text_color;
                        $attrs_array['style']['elements']['link']['color']['text'] = 'var:preset|color|' . $text_color;
                        $class_parts[]                                             = 'has-text-color';
                        $class_parts[]                                             = 'has-link-color';
                } elseif ( '' !== $text_color ) {
                        $attrs_array['style']['color']['text']                     = $text_color;
                        $attrs_array['style']['elements']['link']['color']['text'] = $text_color;
                        $inline_style                                             .= 'color:' . esc_attr( $text_color ) . ';';
                        $class_parts[]                                             = 'has-text-color';
                        $class_parts[]                                             = 'has-link-color';
                }

                if ( ! empty( $typo_attrs ) ) {
                        $attrs_array['style']['typography'] = $typo_attrs;
                }

                if ( ! empty( $spacing_attrs ) ) {
                        $attrs_array['style']['spacing'] = $spacing_attrs;
                }

                if ( ! empty( $border_attrs ) ) {
                        $attrs_array['style']['border'] = $border_attrs;
                }

                if ( isset( $attrs_array['style'] ) ) {
                        foreach ( $attrs_array['style'] as $key => $value ) {
                                if ( empty( $value ) ) {
                                        unset( $attrs_array['style'][ $key ] );
                                }
                        }
                        if ( empty( $attrs_array['style'] ) ) {
                                unset( $attrs_array['style'] );
                        }
                }

                $class_parts = array_filter( array_map( 'sanitize_html_class', $class_parts ) );
                $class_name  = trim( implode( ' ', $class_parts ) );

                if ( '' !== $class_name ) {
                        $attrs_array['className'] = $class_name;
                }

                $inline_style .= $typo_css . $border_css . $spacing_css;

                $attrs = wp_json_encode( $attrs_array );

                $block_content = sprintf(
                        '<!-- wp:heading %s --><h%s%s class="%s"%s>%s</h%s><!-- /wp:heading -->' . "\n",
                        $attrs,
                        esc_html( $level ),
                        $custom_id ? ' id="' . esc_attr( $custom_id ) . '"' : '',
                        esc_attr( $class_name ),
                        $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '',
                        esc_html( $title ),
                        esc_html( $level )
                );

                if ( ! empty( $custom_css ) ) {
                        Style_Parser::save_custom_css( $custom_css );
                }

                return $block_content;
        }

        /**
	 * Check if a given color value is a Gutenberg preset slug.
	 *
	 * @param string $color Color value.
	 * @return bool
	 */
	private function is_preset_color_slug( string $color ): bool {
		return ! empty( $color ) && strpos( $color, '#' ) === false;
	}
}
