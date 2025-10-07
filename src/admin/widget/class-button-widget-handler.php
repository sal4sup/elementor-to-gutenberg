<?php
/**
 * Widget handler for Elementor button widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Widget_Handler_Interface;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor button widget.
 */
class Button_Widget_Handler implements Widget_Handler_Interface {

	/**
	 * Handle conversion of Elementor button to Gutenberg block.
	 *
	 * @param array $element The Elementor element data.
	 * @return string The Gutenberg block content.
	 */
        public function handle( array $element ): string {
                $settings     = $element['settings'] ?? array();
                $text         = $settings['text'] ?? '';
                $url          = $settings['link']['url'] ?? '';
                $custom_class = trim( (string) ( $settings['_css_classes'] ?? '' ) );
                $custom_id    = $settings['_element_id'] ?? '';
                $custom_css   = $settings['custom_css'] ?? '';

                $class_parts = array();
                if ( '' !== $custom_class ) {
                        $class_parts = array_merge( $class_parts, preg_split( '/\s+/', $custom_class ) ?: array() );
                }

                $attrs_array = array();
                $inline_style = '';

                if ( $url ) {
                        $attrs_array['url'] = esc_url( $url );
                }

                $text_color = isset( $settings['button_text_color'] ) ? strtolower( (string) $settings['button_text_color'] ) : '';
                if ( '' !== $text_color ) {
                        $class_parts[] = 'has-text-color';
                        $class_parts[] = 'has-link-color';
                        if ( $this->is_preset_color_slug( $text_color ) ) {
                                $attrs_array['textColor'] = $text_color;
                                $attrs_array['style']['elements']['link']['color']['text'] = 'var:preset|color|' . $text_color;
                        } else {
                                $attrs_array['style']['color']['text']                     = $text_color;
                                $attrs_array['style']['elements']['link']['color']['text'] = $text_color;
                                $inline_style                                               .= 'color:' . esc_attr( $text_color ) . ';';
                        }
                }

                $background_color = isset( $settings['background_color'] ) ? strtolower( (string) $settings['background_color'] ) : '';
                if ( '' !== $background_color ) {
                        $class_parts[] = 'has-background';
                        if ( $this->is_preset_color_slug( $background_color ) ) {
                                $attrs_array['backgroundColor'] = $background_color;
                        } else {
                                $attrs_array['style']['color']['background'] = $background_color;
                                $inline_style                                .= 'background-color:' . esc_attr( $background_color ) . ';';
                        }
                }

                $typography    = Style_Parser::parse_typography( $settings );
                $typo_attrs    = is_array( $typography['attributes'] ?? null ) ? $typography['attributes'] : array();
                $typo_css      = is_string( $typography['style'] ?? null ) ? $typography['style'] : '';
                $spacing       = Style_Parser::parse_spacing( $settings );
                $spacing_attrs = is_array( $spacing['attributes'] ?? null ) ? $spacing['attributes'] : array();
                $spacing_css   = is_string( $spacing['style'] ?? null ) ? $spacing['style'] : '';
                $border        = Style_Parser::parse_border( $settings );
                $border_attrs  = is_array( $border['attributes'] ?? null ) ? $border['attributes'] : array();
                $border_css    = is_string( $border['style'] ?? null ) ? $border['style'] : '';

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

                $inline_style .= $typo_css . $spacing_css . $border_css;

                $class_parts = array_filter( array_map( 'sanitize_html_class', $class_parts ) );
                $class_name  = trim( implode( ' ', $class_parts ) );
                $class_suffix = '' !== $class_name ? ' ' . $class_name : '';

                $attrs = ! empty( $attrs_array ) ? wp_json_encode( $attrs_array ) : '{}';

                $block_content = sprintf(
                        '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button %1$s --><div class="wp-block-button"><a id="%2$s" class="wp-block-button__link%3$s wp-element-button"%4$s%5$s>%6$s</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' . "\n",
                        $attrs,
                        esc_attr( $custom_id ),
                        esc_attr( $class_suffix ),
                        $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '',
                        $url ? ' href="' . esc_url( $url ) . '"' : '',
                        esc_html( $text )
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
