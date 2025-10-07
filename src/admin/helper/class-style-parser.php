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
	 * @return array Array containing 'attributes' and 'style' keys.
	 */
        public static function parse_typography( array $settings ): array {
                $attrs       = array();
                $style_rules = array();

                $typography_fields = array(
                        'typography_font_family'     => array( 'attr' => 'fontFamily', 'style' => 'font-family' ),
                        'typography_text_transform'  => array( 'attr' => 'textTransform', 'style' => 'text-transform' ),
                        'typography_font_size'       => array( 'attr' => 'fontSize', 'style' => 'font-size', 'is_array' => true, 'unit' => 'px' ),
                        'typography_font_weight'     => array( 'attr' => 'fontWeight', 'style' => 'font-weight' ),
                        'typography_line_height'     => array( 'attr' => 'lineHeight', 'style' => 'line-height', 'is_array' => true, 'unit' => '' ),
                        'typography_font_style'      => array( 'attr' => 'fontStyle', 'style' => 'font-style' ),
                        'typography_text_decoration' => array( 'attr' => 'textDecoration', 'style' => 'text-decoration' ),
                        'typography_letter_spacing'  => array( 'attr' => 'letterSpacing', 'style' => 'letter-spacing', 'is_array' => true, 'unit' => 'px' ),
                        'typography_word_spacing'    => array( 'attr' => 'wordSpacing', 'style' => 'word-spacing', 'is_array' => true, 'unit' => 'px' ),
                );

                foreach ( $typography_fields as $key => $field ) {
                        if ( ! isset( $settings[ $key ] ) ) {
                                continue;
                        }

                        if ( ! empty( $field['is_array'] ) ) {
                                $value_data = $settings[ $key ];
                                if ( ! is_array( $value_data ) ) {
                                        continue;
                                }

                                $size = $value_data['size'] ?? null;
                                if ( null === $size || '' === $size ) {
                                        continue;
                                }

                                $unit = $value_data['unit'] ?? ( $field['unit'] ?? 'px' );
                                if ( ! is_string( $unit ) ) {
                                        $unit = (string) $unit;
                                }

                                $size = is_numeric( $size ) ? (string) $size : trim( (string) $size );
                                if ( '' === $size ) {
                                        continue;
                                }

                                if ( '' === $unit && isset( $field['unit'] ) && is_numeric( $size ) ) {
                                        $unit = $field['unit'];
                                }

                                $value = $size . $unit;
                                $attrs[ $field['attr'] ] = $value;
                                $style_rules[]           = sprintf( '%s:%s;', $field['style'], esc_attr( $value ) );
                        } else {
                                $value = $settings[ $key ];
                                if ( '' === $value || null === $value ) {
                                        continue;
                                }

                                $value = is_scalar( $value ) ? (string) $value : '';
                                if ( '' === $value ) {
                                        continue;
                                }

                                $attrs[ $field['attr'] ] = $value;
                                $style_rules[]           = sprintf( '%s:%s;', $field['style'], esc_attr( $value ) );
                        }
                }

                return array(
                        'attributes' => $attrs,
                        'style'      => implode( '', $style_rules ),
                );
        }

	/**
	 * Parse spacing settings from Elementor settings.
	 *
	 * @param array $settings The Elementor settings array.
	 * @return array Array containing 'attributes' and 'style' keys.
	 */
        public static function parse_spacing( array $settings ): array {
                $attrs       = array();
                $style_rules = array();

                foreach ( array( 'padding', 'margin' ) as $spacing ) {
                        $value_map = null;
                        foreach ( array( $spacing, '_' . $spacing ) as $key ) {
                                if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) {
                                        $value_map = $settings[ $key ];
                                        break;
                                }
                        }

                        if ( empty( $value_map ) || ! is_array( $value_map ) ) {
                                continue;
                        }

                        $unit = $value_map['unit'] ?? 'px';
                        if ( ! is_string( $unit ) ) {
                                $unit = 'px';
                        }

                        foreach ( array( 'top', 'bottom', 'left', 'right' ) as $side ) {
                                if ( ! isset( $value_map[ $side ] ) ) {
                                        continue;
                                }

                                $raw_value = $value_map[ $side ];
                                if ( '' === $raw_value || null === $raw_value ) {
                                        continue;
                                }

                                $value = is_numeric( $raw_value ) ? (string) $raw_value . $unit : trim( (string) $raw_value );
                                if ( '' === $value ) {
                                        continue;
                                }

                                $attrs[ $spacing ][ $side ] = $value;
                                $style_rules[]              = sprintf( '%s-%s:%s;', $spacing, $side, esc_attr( $value ) );
                        }
                }

                return array(
                        'attributes' => $attrs,
                        'style'      => implode( '', $style_rules ),
                );
        }

	/**
	 * Parse border settings from Elementor settings.
	 *
	 * @param array $settings The Elementor settings array.
	 * @return array Array containing 'attributes' and 'style' keys.
	 */
        public static function parse_border( array $settings ): array {
                $attrs       = array();
                $style_rules = array();

                $radius_map = array(
                        'top'    => array( 'topLeft', 'top-left' ),
                        'right'  => array( 'topRight', 'top-right' ),
                        'bottom' => array( 'bottomRight', 'bottom-right' ),
                        'left'   => array( 'bottomLeft', 'bottom-left' ),
                );

                if ( isset( $settings['_border_radius'] ) && is_array( $settings['_border_radius'] ) ) {
                        $unit = $settings['_border_radius']['unit'] ?? 'px';
                        if ( ! is_string( $unit ) ) {
                                $unit = 'px';
                        }

                        foreach ( $radius_map as $el_side => $gb_side ) {
                                if ( ! isset( $settings['_border_radius'][ $el_side ] ) ) {
                                        continue;
                                }

                                $raw_value = $settings['_border_radius'][ $el_side ];
                                if ( '' === $raw_value || null === $raw_value ) {
                                        continue;
                                }

                                $value = is_numeric( $raw_value ) ? (string) $raw_value . $unit : trim( (string) $raw_value );
                                if ( '' === $value ) {
                                        continue;
                                }

                                $attrs['radius'][ $gb_side[0] ] = $value;
                                $style_rules[]                  = sprintf( 'border-%s-radius:%s;', $gb_side[1], esc_attr( $value ) );
                        }
                }

                if ( isset( $settings['_border_width'] ) && is_array( $settings['_border_width'] ) ) {
                        $unit  = $settings['_border_width']['unit'] ?? 'px';
                        $color = isset( $settings['border_color'] ) ? strtolower( (string) $settings['border_color'] ) : '';

                        if ( ! is_string( $unit ) ) {
                                $unit = 'px';
                        }

                        foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
                                if ( ! isset( $settings['_border_width'][ $side ] ) ) {
                                        continue;
                                }

                                $raw_width = $settings['_border_width'][ $side ];
                                if ( '' === $raw_width || null === $raw_width ) {
                                        continue;
                                }

                                $width = is_numeric( $raw_width ) ? (string) $raw_width . $unit : trim( (string) $raw_width );
                                if ( '' === $width ) {
                                        continue;
                                }

                                $attrs[ $side ]['width'] = $width;
                                $style_rules[]           = sprintf( 'border-%s-width:%s;', $side, esc_attr( $width ) );

                                if ( '' !== $color ) {
                                        $attrs[ $side ]['color'] = $color;
                                        $style_rules[]           = sprintf( 'border-%s-color:%s;', $side, esc_attr( $color ) );
                                }
                        }
                }

                if ( ! empty( $settings['_border_border'] ) && is_string( $settings['_border_border'] ) ) {
                        $attrs['style'] = $settings['_border_border'];
                        $style_rules[]  = 'border-style:' . esc_attr( $settings['_border_border'] ) . ';';
                }

                if ( empty( $attrs ) && empty( $style_rules ) ) {
                        return array();
                }

                return array(
                        'attributes' => $attrs,
                        'style'      => implode( '', $style_rules ),
                );
        }

        /**
         * Parse container-level styles from Elementor settings.
         *
         * @param array $settings Elementor settings array.
         *
         * @return array
         */
        public static function parse_container_styles( array $settings ): array {
                $attrs       = array();
                $style_rules = array();

                $background = $settings['background_color'] ?? $settings['_background_color'] ?? '';
                if ( is_string( $background ) ) {
                        $background = trim( strtolower( $background ) );
                }

                if ( ! empty( $background ) ) {
                        $attrs['color']['background'] = $background;
                        $style_rules[]                = 'background-color:' . esc_attr( $background ) . ';';
                }

                if ( empty( $attrs ) && empty( $style_rules ) ) {
                        return array();
                }

                return array(
                        'attributes' => $attrs,
                        'style'      => implode( '', $style_rules ),
                );
        }


	/**
	 * Save custom CSS to the Customizer's Additional CSS.
	 *
	 * @param string $css The CSS string.
	 */
	public static function save_custom_css( string $css ) {
		$customizer_css_post = wp_get_custom_css_post();
		$existing_css        = $customizer_css_post ? $customizer_css_post->post_content : '';
		$new_css             = $existing_css . "\n" . $css;

		wp_update_custom_css_post( $new_css );
	}
}
