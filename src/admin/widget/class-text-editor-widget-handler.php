<?php
/**
 * Widget handler for Elementor text-editor widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor text-editor widget.
 */
class Text_Editor_Widget_Handler implements Widget_Handler_Interface {
	/**
	 * Handle conversion of Elementor text-editor to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
        public function handle( array $element ): string {
                $settings     = $element['settings'] ?? array();
                $text         = $settings['editor'] ?? '';
                $color        = isset( $settings['text_color'] ) ? (string) $settings['text_color'] : '';
                $custom_class = trim( (string) ( $settings['_css_classes'] ?? '' ) );
                $custom_id    = $settings['_element_id'] ?? '';
                $custom_css   = $settings['custom_css'] ?? '';

                $class_parts = array( 'wp-block-paragraph' );
                if ( '' !== $color ) {
                        $class_parts[] = 'has-text-color';
                }

                if ( ! empty( $settings['typography_text_transform'] ) ) {
                        $class_parts[] = 'has-text-transform-' . sanitize_html_class( $settings['typography_text_transform'] );
                }

                if ( '' !== $custom_class ) {
                        $class_parts = array_merge( $class_parts, preg_split( '/\s+/', $custom_class ) ?: array() );
                }

                $typography    = Style_Parser::parse_typography( $settings );
                $typo_attrs    = is_array( $typography['attributes'] ?? null ) ? $typography['attributes'] : array();
                $typo_css      = is_string( $typography['style'] ?? null ) ? $typography['style'] : '';
                $spacing       = Style_Parser::parse_spacing( $settings );
                $spacing_attrs = is_array( $spacing['attributes'] ?? null ) ? $spacing['attributes'] : array();
                $spacing_css   = is_string( $spacing['style'] ?? null ) ? $spacing['style'] : '';

                $attrs_array = array();
                if ( ! empty( $typo_attrs ) ) {
                        $attrs_array['style']['typography'] = $typo_attrs;
                }

                if ( ! empty( $spacing_attrs ) ) {
                        $attrs_array['style']['spacing'] = $spacing_attrs;
                }

                if ( '' !== $color ) {
                        $attrs_array['style']['color']['text'] = $color;
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

                $inline_style = '';
                if ( '' !== $color ) {
                        $inline_style .= 'color:' . esc_attr( $color ) . ';';
                }
                $inline_style .= $typo_css . $spacing_css;

                $attrs = ! empty( $attrs_array ) ? wp_json_encode( $attrs_array ) : '{}';

                $block_content = sprintf(
                        '<!-- wp:html %1$s --><div class="%2$s"%3$s%4$s>%5$s</div><!-- /wp:html -->' . "\n",
                        $attrs,
                        esc_attr( $class_name ),
                        $custom_id ? ' id="' . esc_attr( $custom_id ) . '"' : '',
                        $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '',
                        wp_kses_post( $text )
                );

                if ( ! empty( $custom_css ) ) {
                        Style_Parser::save_custom_css( $custom_css );
                }

                return $block_content;
        }
}
