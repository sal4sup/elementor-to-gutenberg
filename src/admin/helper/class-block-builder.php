<?php
/**
 * Helper for building block markup with wrapper elements.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

use function esc_attr;
use function sanitize_html_class;
use function wp_json_encode;

defined( 'ABSPATH' ) || exit;

/**
 * Helper for building block markup with wrapper elements.
 */
class Block_Builder {
	/**
	 * Blocks that require wrapper div output.
	 *
	 * @var array<string>
	 */
	private static $wrapper_blocks = array( 'group', 'columns', 'column' );

	/**
	 * Build the serialized markup for a block including wrapper markup when required.
	 *
	 * @param string $block      Block name without `core/` prefix (e.g. `group`).
	 * @param array  $attrs      Attributes array to encode in block comment.
	 * @param string $inner_html Inner HTML for the block.
	 *
	 * @return string
	 */
	public static function build( string $block, array $attrs, string $inner_html ): string {
		$attr_json = '';
		if ( ! empty( $attrs ) ) {
			$attr_json = ' ' . wp_json_encode( $attrs );
		}

		$opening_comment = sprintf( '<!-- wp:%s%s -->', $block, $attr_json );
		$closing_comment = sprintf( '<!-- /wp:%s -->', $block );

		if ( in_array( $block, self::$wrapper_blocks, true ) ) {
			$wrapper_class = self::build_wrapper_class( $block, $attrs );
			$style_attr    = self::build_style_attribute( $attrs );

			$wrapper = sprintf(
				'<div class="%s"%s>%s</div>',
				esc_attr( $wrapper_class ),
				$style_attr,
				$inner_html
			);

			return $opening_comment . $wrapper . $closing_comment . "\n";
		}

		return $opening_comment . $inner_html . $closing_comment . "\n";
	}

	/**
	 * Build the wrapper class attribute from block attributes.
	 *
	 * @param string $block Block name.
	 * @param array  $attrs Block attributes.
	 *
	 * @return string
	 */
	private static function build_wrapper_class( string $block, array $attrs ): string {
		$classes   = array( 'wp-block-' . $block );
		$align     = isset( $attrs['align'] ) ? trim( (string) $attrs['align'] ) : '';
		$class_raw = isset( $attrs['className'] ) ? (string) $attrs['className'] : '';

		if ( '' !== $align ) {
			$classes[] = 'align' . sanitize_html_class( $align );
		}

		if ( '' !== $class_raw ) {
			$class_parts = preg_split( '/\s+/', $class_raw );
			if ( is_array( $class_parts ) ) {
				foreach ( $class_parts as $class_part ) {
					$class_part = trim( $class_part );
					if ( '' !== $class_part ) {
						$classes[] = sanitize_html_class( $class_part );
					}
				}
			}
		}

		return implode( ' ', array_filter( $classes ) );
	}

	/**
	 * Build inline style attribute for wrapper blocks.
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string
	 */
	private static function build_style_attribute( array $attrs ): string {
		if ( empty( $attrs['style'] ) || ! is_array( $attrs['style'] ) ) {
			return '';
		}

		$styles = array();
		$style  = $attrs['style'];

		if ( isset( $style['spacing']['blockGap'] ) ) {
			$styles[] = 'gap:' . self::normalize_style_value( $style['spacing']['blockGap'] );
		}

		foreach ( array( 'padding', 'margin' ) as $box_type ) {
			if ( empty( $style['spacing'][ $box_type ] ) || ! is_array( $style['spacing'][ $box_type ] ) ) {
				continue;
			}

			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( ! isset( $style['spacing'][ $box_type ][ $side ] ) ) {
					continue;
				}

				$value = $style['spacing'][ $box_type ][ $side ];
				if ( '' === $value ) {
					continue;
				}

				$styles[] = sprintf(
					'%s-%s:%s',
					$box_type,
					$side,
					self::normalize_style_value( $value )
				);
			}
		}

		if ( isset( $style['color']['background'] ) && '' !== $style['color']['background'] ) {
			$styles[] = 'background-color:' . self::normalize_style_value( $style['color']['background'] );
		}

		if ( empty( $styles ) ) {
			return '';
		}

		return ' style="' . esc_attr( implode( ';', $styles ) ) . '"';
	}

	/**
	 * Normalize style values for inline usage (e.g. presets to CSS vars).
	 *
	 * @param string $value Raw value from attributes.
	 *
	 * @return string
	 */
	private static function normalize_style_value( $value ): string {
		$value = (string) $value;
		if ( 0 === strpos( $value, 'var:' ) ) {
			$without_prefix = substr( $value, 4 );
			$parts          = explode( '|', $without_prefix );
			if ( ! empty( $parts ) ) {
				$value = 'var(--wp--' . implode( '--', array_map( 'sanitize_html_class', $parts ) ) . ')';
			}
		}

		return $value;
	}
}
