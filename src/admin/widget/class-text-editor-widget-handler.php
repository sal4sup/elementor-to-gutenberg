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
		$settings = $element['settings'] ?? array();
		$text     = $settings['editor'] ?? '';
		$color    = $settings['text_color'] ?? '';
		$class    = ! empty( $color ) ? 'has-text-color' : '';
		$style    = ! empty( $color ) ? sprintf( 'color:%s;', esc_attr( $color ) ) : '';

		if ( isset( $settings['typography_text_transform'] ) ) {
			$class .= ' has-text-transform-' . esc_attr( $settings['typography_text_transform'] );
		}

		$typography      = Style_Parser::parse_typography( $settings );
		$typography_attr = isset( $typography['attributes'] ) && is_array( $typography['attributes'] ) ? $typography['attributes'] : array();
		$typography_css  = isset( $typography['style'] ) && is_string( $typography['style'] ) ? $typography['style'] : '';
		$style          .= $typography_css;

		$attrs_array = array( 'style' => array() );
		if ( '' !== $color ) {
			$attrs_array['style']['color'] = array( 'text' => $color );
		}
		if ( ! empty( $typography_attr ) ) {
			$attrs_array['style']['typography'] = $typography_attr;
		}

		$attrs_array = array_merge_recursive( $attrs_array, Style_Parser::parse_spacing( $settings ) );
		if ( empty( $attrs_array['style'] ) ) {
			unset( $attrs_array['style'] );
		}

		$attrs = wp_json_encode( $attrs_array );

		$block_content = sprintf(
			'<!-- wp:html %s --><div class="wp-block-paragraph %s" style="%s">%s</div><!-- /wp:html -->' . "\n",
			$attrs,
			$class,
			$style,
			wp_kses_post( $text )
		);

		return $block_content;
	}
}
