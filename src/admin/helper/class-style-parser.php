<?php
/**
 * Utility class for parsing styles and attributes.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Utility class for parsing styles and attributes.
 */
class Style_Parser {
	/**
	 * Parse typography settings from Elementor settings.
	 *
	 * @param array $settings The Elementor settings array.
	 *
	 * @return array Array containing 'attributes' and 'style' keys.
	 */
	public static function parse_typography( array $settings ): array {
		$attrs = array();
		$style = '';

		$typography_fields = array(
		'typography_font_family'     => array( 'attr' => 'fontFamily', 'style' => 'font-family' ),
		'typography_font_size'       => array( 'attr' => 'fontSize', 'style' => 'font-size', 'is_array' => true ),
		'typography_font_weight'     => array( 'attr' => 'fontWeight', 'style' => 'font-weight' ),
		'typography_line_height'     => array( 'attr' => 'lineHeight', 'style' => 'line-height', 'is_array' => true ),
		'typography_font_style'      => array( 'attr' => 'fontStyle', 'style' => 'font-style' ),
		'typography_text_decoration' => array( 'attr' => 'textDecoration', 'style' => 'text-decoration' ),
		'typography_letter_spacing'  => array( 'attr' => 'letterSpacing', 'style' => 'letter-spacing', 'is_array' => true ),
		'typography_word_spacing'    => array( 'attr' => 'wordSpacing', 'style' => 'word-spacing', 'is_array' => true ),
		);

		foreach ( $typography_fields as $key => $field ) {
			if ( ! isset( $settings[ $key ] ) ) {
				continue;
			}

			if ( isset( $field['is_array'] ) && true === $field['is_array'] ) {
				$size = $settings[ $key ]['size'] ?? '';
				$unit = $settings[ $key ]['unit'] ?? ( 'typography_line_height' === $key ? '' : 'px' );
				if ( '' !== $size ) {
					$value                  = $size . $unit;
					$attrs[ $field['attr'] ] = $value;
					$style                 .= sprintf( '%s:%s;', $field['style'], esc_attr( $value ) );
				}
			} else {
				$attrs[ $field['attr'] ] = $settings[ $key ];
				$style                 .= sprintf( '%s:%s;', $field['style'], esc_attr( $settings[ $key ] ) );
			}
		}

		return array(
		'attributes' => $attrs,
		'style'      => $style,
		);
	}

	/**
	 * Parse spacing settings from Elementor settings.
	 *
	 * @param array $settings The Elementor settings array.
	 *
	 * @return array Array containing style attributes for spacing.
	 */
	public static function parse_spacing( array $settings ): array {
		$spacing = array();

		foreach ( array( '_margin' => 'margin', 'margin' => 'margin', '_padding' => 'padding', 'padding' => 'padding' ) as $key => $type ) {
			if ( empty( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
				continue;
			}

			$box      = $settings[ $key ];
			$unit     = isset( $box['unit'] ) ? (string) $box['unit'] : 'px';
			$unit     = '' === $unit ? 'px' : $unit;
			$resolved = array();

			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( ! isset( $box[ $side ] ) ) {
					continue;
				}

				$value = self::resolve_dimension( $box[ $side ], $unit );
				if ( '' !== $value ) {
					$resolved[ $side ] = $value;
				}
			}

			if ( ! empty( $resolved ) ) {
				$spacing[ $type ] = $resolved;
			}
		}

		$gap_keys = array( 'gap', 'gap_columns', 'column_gap' );
		foreach ( $gap_keys as $gap_key ) {
			if ( empty( $settings[ $gap_key ] ) ) {
				continue;
			}

			$value = self::resolve_dimension( $settings[ $gap_key ], 'px' );
			if ( '' !== $value ) {
				$spacing['blockGap'] = $value;
				break;
			}
		}

		$result = array( 'style' => array() );
		if ( ! empty( $spacing ) ) {
			$result['style']['spacing'] = $spacing;
		}

		return $result;
	}

	/**
	 * Parse border settings from Elementor settings.
	 *
	 * @param array $settings Elementor settings array.
	 *
	 * @return array
	 */
	public static function parse_border( array $settings ): array {
		$attrs = array();

		if ( isset( $settings['border_radius'] ) && is_array( $settings['border_radius'] ) ) {
			$radius_unit = isset( $settings['border_radius']['unit'] ) ? $settings['border_radius']['unit'] : 'px';
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( isset( $settings['border_radius'][ $side ] ) ) {
					$attrs['style']['border']['radius'][ $side ] = self::resolve_dimension( $settings['border_radius'][ $side ], $radius_unit );
				}
			}
		}

		if ( isset( $settings['border_border'] ) && '' !== $settings['border_border'] ) {
			$attrs['style']['border']['style'] = $settings['border_border'];
		}

		if ( isset( $settings['border_width'] ) && is_array( $settings['border_width'] ) ) {
			$width_unit = isset( $settings['border_width']['unit'] ) ? $settings['border_width']['unit'] : 'px';
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				if ( isset( $settings['border_width'][ $side ] ) ) {
					$attrs['style']['border']['width'][ $side ] = self::resolve_dimension( $settings['border_width'][ $side ], $width_unit );
				}
			}
		}

		return $attrs;
	}

	/**
	 * Parse container styles to Gutenberg block attributes subset.
	 *
	 * @param array $settings Elementor settings array.
	 *
	 * @return array
	 */
	public static function parse_container_styles( array $settings ): array {
		$attrs = array();
		$style = array();

		$spacing = self::parse_spacing( $settings );
		if ( ! empty( $spacing['style']['spacing'] ) ) {
			$style['spacing'] = $spacing['style']['spacing'];
		}

		$background = self::get_background_color( $settings );
		if ( '' !== $background ) {
			$style['color']['background'] = $background;
		}

		if ( isset( $settings['css_classes'] ) && '' !== trim( (string) $settings['css_classes'] ) ) {
			$attrs['className'] = trim( (string) $settings['css_classes'] );
		}

		if ( ! empty( $style ) ) {
			$attrs['style'] = $style;
		}

		return $attrs;
	}

	/**
	 * Resolve a dimension value to a CSS-ready string.
	 *
	 * @param mixed  $value Raw Elementor value.
	 * @param string $fallback_unit Default unit when not provided.
	 *
	 * @return string
	 */
	private static function resolve_dimension( $value, string $fallback_unit ): string {
		if ( is_array( $value ) ) {
			$size = $value['size'] ?? $value['value'] ?? '';
			$unit = $value['unit'] ?? $fallback_unit;
			if ( '' === $unit ) {
				$unit = $fallback_unit;
			}
			if ( '' !== $size ) {
				return $size . $unit;
			}
			return '';
		}

		if ( null === $value || '' === $value ) {
			return '';
		}

		$value = (string) $value;
		if ( 'default' === strtolower( $value ) ) {
			return '';
		}
		if ( preg_match( '/[a-z%]+$/i', $value ) ) {
			return $value;
		}

		if ( '' === $fallback_unit ) {
			$fallback_unit = 'px';
		}

		return $value . $fallback_unit;
	}

	/**
	 * Extract background color from settings.
	 *
	 * @param array $settings Elementor settings.
	 *
	 * @return string
	 */
	private static function get_background_color( array $settings ): string {
		$keys = array( '_background_color', 'background_color' );
		foreach ( $keys as $key ) {
			if ( isset( $settings[ $key ] ) && '' !== (string) $settings[ $key ] ) {
				return (string) $settings[ $key ];
			}
		}

		return '';
	}
}
