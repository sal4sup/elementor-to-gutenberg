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
$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
$content  = isset( $settings['editor'] ) ? (string) $settings['editor'] : '';
$custom_id    = isset( $settings['_element_id'] ) ? trim( (string) $settings['_element_id'] ) : '';
$custom_css   = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
$custom_class = isset( $settings['_css_classes'] ) ? trim( (string) $settings['_css_classes'] ) : '';
$text_color   = isset( $settings['text_color'] ) ? strtolower( (string) $settings['text_color'] ) : '';

$typography = Style_Parser::parse_typography( $settings );
$spacing    = Style_Parser::parse_spacing( $settings );
$border     = Style_Parser::parse_border( $settings );

$class_names = array( 'wp-block-paragraph' );
if ( '' !== $custom_class ) {
foreach ( preg_split( '/\s+/', $custom_class ) as $class ) {
$class = trim( $class );
if ( '' !== $class ) {
$class_names[] = sanitize_html_class( $class );
}
}
}

$inline_style_parts = array( $typography['style'], $spacing['style'], $border['style'] );
$attributes         = array();

if ( ! empty( $typography['attributes'] ) ) {
$attributes['style']['typography'] = $typography['attributes'];
}
if ( ! empty( $spacing['attributes'] ) ) {
$attributes['style']['spacing'] = $spacing['attributes'];
}
if ( ! empty( $border['attributes'] ) ) {
$attributes['style']['border'] = $border['attributes'];
}

if ( '' !== $text_color ) {
if ( $this->is_preset_color_slug( $text_color ) ) {
$attributes['textColor'] = $text_color;
$attributes['style']['elements']['link']['color']['text'] = 'var:preset|color|' . $text_color;
} else {
$attributes['style']['color']['text']                  = $text_color;
$attributes['style']['elements']['link']['color']['text'] = $text_color;
$inline_style_parts[] = 'color:' . $text_color . ';';
}
$class_names[] = 'has-text-color';
$class_names[] = 'has-link-color';
}

$inline_style = implode( '', array_filter( $inline_style_parts ) );

$wrapper_classes = implode( ' ', array_unique( array_filter( $class_names ) ) );
$div_attributes  = '' !== $custom_id ? ' id="' . esc_attr( $custom_id ) . '"' : '';
$div_attributes .= $wrapper_classes ? ' class="' . esc_attr( $wrapper_classes ) . '"' : '';
$div_attributes .= '' !== $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : '';

$inner_markup = sprintf( '<div%s>%s</div>', $div_attributes, wp_kses_post( $content ) );

if ( '' !== $custom_css ) {
Style_Parser::save_custom_css( $custom_css );
}

return Block_Builder::build( 'core/html', $attributes, $inner_markup );
}

/**
 * Determine if a color value refers to a preset slug.
 *
 * @param string $color Color value.
 */
private function is_preset_color_slug( string $color ): bool {
return '' !== $color && false === strpos( $color, '#' );
}
}
