<?php
/**
 * Widget handler for Elementor text-editor widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_html;
use function sanitize_html_class;
use function wp_kses_post;

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
        $settings     = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
        $content      = isset( $settings['editor'] ) ? (string) $settings['editor'] : '';
        $custom_id    = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
        $custom_css   = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
        $custom_class = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
        $text_color   = isset( $settings['text_color'] ) ? strtolower( (string) $settings['text_color'] ) : '';

        if ( '' === trim( $content ) ) {
                return '';
        }

        $typography = Style_Parser::parse_typography( $settings );
        $spacing    = Style_Parser::parse_spacing( $settings );
        $border     = Style_Parser::parse_border( $settings );

        $inline_style_parts = array( $typography['style'], $spacing['style'], $border['style'] );

        if ( $this->has_complex_html( $content ) ) {
                $class_names = array( 'wp-block-paragraph' );

                if ( '' !== $custom_class ) {
                        foreach ( preg_split( '/\s+/', $custom_class ) as $class ) {
                                $class = trim( $class );
                                if ( '' !== $class ) {
                                        $class_names[] = sanitize_html_class( $class );
                                }
                        }
                }

                if ( '' !== $text_color ) {
                        if ( $this->is_preset_color_slug( $text_color ) ) {
                                $inline_style_parts[] = 'color:var(--wp--preset--color--' . sanitize_html_class( $text_color ) . ');';
                        } else {
                                $inline_style_parts[] = 'color:' . $text_color . ';';
                        }
                        $class_names[] = 'has-text-color';
                        $class_names[] = 'has-link-color';
                }

                $inline_style     = implode( '', array_filter( $inline_style_parts ) );
                $wrapper_classes  = implode( ' ', array_unique( array_filter( $class_names ) ) );
                $div_attributes   = '' !== $custom_id ? ' id="' . esc_attr( $custom_id ) . '"' : '';
                $div_attributes  .= $wrapper_classes ? ' class="' . esc_attr( $wrapper_classes ) . '"' : '';
                $div_attributes  .= '' !== $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '';
                $inner_markup     = sprintf( '<div%s>%s</div>', $div_attributes, wp_kses_post( $content ) );

                if ( '' !== $custom_css ) {
                        Style_Parser::save_custom_css( $custom_css );
                }

                return Block_Builder::build( 'html', array(), $inner_markup );
        }

        $attributes      = array();
        $class_names     = array();
        $element_classes = array();

        if ( ! empty( $typography['attributes'] ) ) {
                $attributes['style']['typography'] = $typography['attributes'];
        }
        if ( ! empty( $spacing['attributes'] ) ) {
                $attributes['style']['spacing'] = $spacing['attributes'];
        }
        if ( ! empty( $border['attributes'] ) ) {
                $attributes['style']['border'] = $border['attributes'];
        }

        if ( '' !== $custom_class ) {
                foreach ( preg_split( '/\s+/', $custom_class ) as $class ) {
                        $class = trim( $class );
                        if ( '' !== $class ) {
                                $sanitized      = sanitize_html_class( $class );
                                $class_names[]  = $sanitized;
                                $element_classes[] = $sanitized;
                        }
                }
        }

        if ( '' !== $text_color ) {
                if ( $this->is_preset_color_slug( $text_color ) ) {
                        $attributes['textColor'] = $text_color;
                        $attributes['style']['elements']['link']['color']['text'] = 'var:preset|color|' . $text_color;
                        $inline_style_parts[] = 'color:var(--wp--preset--color--' . sanitize_html_class( $text_color ) . ');';
                } else {
                        $attributes['style']['color']['text']                  = $text_color;
                        $attributes['style']['elements']['link']['color']['text'] = $text_color;
                        $inline_style_parts[] = 'color:' . $text_color . ';';
                }

                $class_names[]     = 'has-text-color';
                $class_names[]     = 'has-link-color';
                $element_classes[] = 'has-text-color';
                $element_classes[] = 'has-link-color';
        }

        $inline_style = implode( '', array_filter( $inline_style_parts ) );

        if ( ! empty( $class_names ) ) {
                $attributes['className'] = implode( ' ', array_unique( $class_names ) );
        }

        if ( '' !== $custom_id ) {
                $attributes['anchor'] = $custom_id;
        }

        $element_classes = array_unique( array_filter( $element_classes ) );
        $paragraph_attr  = '' !== $custom_id ? ' id="' . esc_attr( $custom_id ) . '"' : '';
        if ( ! empty( $element_classes ) ) {
                $paragraph_attr .= ' class="' . esc_attr( implode( ' ', $element_classes ) ) . '"';
        }
        if ( '' !== $inline_style ) {
                $paragraph_attr .= ' style="' . esc_attr( $inline_style ) . '"';
        }

        $paragraph_markup = sprintf( '<p%s>%s</p>', $paragraph_attr, esc_html( $content ) );

        if ( '' !== $custom_css ) {
                Style_Parser::save_custom_css( $custom_css );
        }

        return Block_Builder::build( 'paragraph', $attributes, $paragraph_markup );
}

/**
 * Determine if a color value refers to a preset slug.
 *
 * @param string $color Color value.
 */
private function is_preset_color_slug( string $color ): bool {
        return '' !== $color && false === strpos( $color, '#' );
}

/**
 * Determine if the provided content contains complex HTML that should be preserved.
 *
 * @param string $content Raw editor content.
 */
private function has_complex_html( string $content ): bool {
        if ( false === strpos( $content, '<' ) ) {
                return false;
        }

        $pattern = '/<\s*\/?\s*(p|div|section|article|ul|ol|li|table|tbody|tr|td|th|iframe|blockquote|style|script|span|figure|figcaption|header|footer|nav|main|aside|form|input|textarea|button|h[1-6])/i';
        if ( preg_match( $pattern, $content ) ) {
                return true;
        }

        if ( preg_match( '/<[^>]+(style|class|id|data-)[^>]*>/i', $content ) ) {
                return true;
        }

        $allowed_inline = array( 'strong', 'em', 'b', 'i', 'u', 'code', 'a', 'br', 'sup', 'sub' );
        $stripped       = preg_replace( '/<\/?(' . implode( '|', $allowed_inline ) . ')(\s+[^>]*)?>/i', '', $content );

        return false !== strpos( (string) $stripped, '<' );
}
}
