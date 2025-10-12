<?php
/**
 * Utility class for parsing styles and attributes.
 *
 * @package Progressus\Gutenberg
 */
namespace Progressus\Gutenberg\Admin\Helper;

use function sanitize_html_class;

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
 * @return array{attributes:array, style:string}
 */
    public static function parse_typography( array $settings ): array {
        // Typography settings are intentionally ignored to avoid inline font styling
        // that does not round-trip with Gutenberg serialization.
        unset( $settings );

        return array(
            'attributes' => array(),
            'style'      => '',
        );
    }

/**
 * Parse spacing settings from Elementor settings.
 *
 * @param array $settings Elementor settings array.
 *
 * @return array{attributes:array, style:string}
 */
    public static function parse_spacing( array $settings ): array {
        unset( $settings );

        return array(
            'attributes' => array(),
            'style'      => '',
        );
    }

/**
 * Parse border settings safely.
 *
 * @param array $settings Elementor settings array.
 *
 * @return array{attributes:array, style:string}
 */
    public static function parse_border( array $settings ): array {
        unset( $settings );

        return array(
            'attributes' => array(),
            'style'      => '',
        );
    }

/**
 * Parse container specific styles into block attributes.
 *
 * @param array $settings Elementor settings array.
 *
 * @return array
 */
    public static function parse_container_styles( array $settings ): array {
        $attributes = array();

        $background = self::sanitize_color( $settings['background_color'] ?? $settings['_background_color'] ?? '' );
        if ( self::is_preset_slug( $background ) ) {
            $attributes['backgroundColor'] = $background;
        } elseif ( '' !== $background && 1 === preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $background ) ) {
            $attributes['style']['color']['background'] = $background;
        }

        return $attributes;
}

/**
 * Determine if the supplied color is a preset slug.
 *
 * @param string $color Color string.
 */
    private static function is_preset_slug( string $color ): bool {
        return '' !== $color && false === strpos( $color, '#' ) && 0 !== strpos( $color, 'rgb' );
    }

/**
 * Sanitize a scalar value from Elementor settings.
 *
 * @param mixed $value Raw value.
 */
    private static function sanitize_scalar( $value ): string {
        if ( is_array( $value ) ) {
            return '';
        }

$value = is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value;

return trim( $value );
}

/**
 * Normalize color strings.
 *
 * @param mixed $value Potential color value.
 */
    private static function sanitize_color( $value ): string {
        if ( is_array( $value ) ) {
            $value = $value['value'] ?? $value['color'] ?? '';
        }

        return strtolower( self::sanitize_scalar( $value ) );
    }

    /**
     * Normalize Elementor dimension value into a CSS string suitable for block attributes.
     *
     * @param mixed  $value        Raw value from Elementor settings.
     * @param string $default_unit Default unit when none is provided.
     *
     * @return string|null Normalized dimension or null when not applicable.
     */
    public static function normalize_dimension_value( $value, string $default_unit = 'px' ): ?string {
        if ( is_array( $value ) ) {
            if ( isset( $value['size'] ) ) {
                return self::normalize_dimension_value( $value['size'], $value['unit'] ?? $default_unit );
            }

            if ( isset( $value['value'] ) ) {
                return self::normalize_dimension_value( $value['value'], $value['unit'] ?? $default_unit );
            }
        }

        if ( null === $value || '' === $value ) {
            return null;
        }

        if ( is_numeric( $value ) ) {
            $number = (string) ( 0 + $value );

            return $number . ( '' === $default_unit ? '' : $default_unit );
        }

        $string_value = trim( (string) $value );
        if ( '' === $string_value ) {
            return null;
        }

        if ( preg_match( '/^[0-9.]+$/', $string_value ) ) {
            return $string_value . ( '' === $default_unit ? '' : $default_unit );
        }

        return $string_value;
    }

    /**
     * Parse an Elementor box control into individual CSS values.
     *
     * @param mixed  $value        Elementor box control value.
     * @param string $default_unit Default unit when none provided.
     *
     * @return array<string,string> Associative array of side => value pairs.
     */
    public static function parse_box_sides( $value, string $default_unit = 'px' ): array {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $unit   = isset( $value['unit'] ) ? (string) $value['unit'] : $default_unit;
        $result = array();

        foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
            if ( array_key_exists( $side, $value ) ) {
                $normalized = self::normalize_dimension_value( $value[ $side ], $unit );
                if ( null !== $normalized ) {
                    $result[ $side ] = $normalized;
                }
            }
        }

        if ( empty( $result ) && isset( $value['size'] ) ) {
            $normalized = self::normalize_dimension_value( $value['size'], $unit );
            if ( null !== $normalized ) {
                foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
                    $result[ $side ] = $normalized;
                }
            }
        }

        if ( empty( $result ) && isset( $value['value'] ) ) {
            $normalized = self::normalize_dimension_value( $value['value'], $unit );
            if ( null !== $normalized ) {
                foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
                    $result[ $side ] = $normalized;
                }
            }
        }

        return $result;
    }

    /**
     * Sanitize a single class name while dropping Elementor-generated classes.
     *
     * @param string $class Raw class name.
     *
     * @return string Sanitized class name or empty string if disallowed.
     */
    public static function clean_class( string $class ): string {
        $class = trim( $class );
        if ( '' === $class ) {
            return '';
        }

        $sanitized = sanitize_html_class( $class );
        if ( '' === $sanitized ) {
            return '';
        }

        if ( self::is_disallowed_elementor_class( $sanitized ) ) {
            return '';
        }

        return $sanitized;
    }

    /**
     * Determine if a class should be stripped because it's Elementor-specific.
     *
     * @param string $class Sanitized class name.
     */
    private static function is_disallowed_elementor_class( string $class ): bool {
        $blocked_exact = array(
            'e-con',
            'e-con-full',
            'e-con-boxed',
            'e-con-child',
            'e-grid',
        );

        if ( in_array( $class, $blocked_exact, true ) ) {
            return true;
        }

        $blocked_prefixes = array(
            'elementor',
            'elementor-',
            'elementor_',
            'e-con-',
            'e-grid-',
            'wp-elements-',
        );

        foreach ( $blocked_prefixes as $prefix ) {
            if ( 0 === strpos( $class, $prefix ) ) {
                return true;
            }
        }

        return false;
    }

/**
 * Save custom CSS to the Customizer's Additional CSS store when available.
 *
 * @param string $css CSS string to append.
 */
public static function save_custom_css( string $css ): void {
$css = trim( $css );
if ( '' === $css ) {
return;
}

if ( ! function_exists( 'wp_get_custom_css_post' ) || ! function_exists( 'wp_update_custom_css_post' ) ) {
return;
}

$customizer_css_post = wp_get_custom_css_post();
$existing_css        = $customizer_css_post ? (string) $customizer_css_post->post_content : '';
$new_css             = rtrim( $existing_css ) . "\n" . $css;

wp_update_custom_css_post( $new_css );
}
}
