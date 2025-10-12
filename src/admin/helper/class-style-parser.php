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
 * Normalize Elementor dimension value.
 *
 * @param mixed  $value Raw value.
 * @param string $default_unit Default unit when missing.
 */
private static function normalize_dimension( $value, string $default_unit ): ?string {
if ( is_array( $value ) ) {
if ( isset( $value['size'] ) ) {
return self::normalize_dimension( $value['size'], $value['unit'] ?? $default_unit );
}
if ( isset( $value['value'] ) ) {
return self::normalize_dimension( $value['value'], $value['unit'] ?? $default_unit );
}
}

if ( null === $value || '' === $value ) {
return null;
}

if ( is_numeric( $value ) ) {
return $value . ( '' === $default_unit ? '' : $default_unit );
}

$value = trim( (string) $value );
if ( '' === $value ) {
return null;
}

if ( preg_match( '/[a-z%]+$/i', $value ) ) {
return $value;
}

return $value . ( '' === $default_unit ? '' : $default_unit );
}

/**
 * Extract padding/margin side values.
 *
 * @param array  $data Elementor box model array.
 * @param string $side Side to extract.
 */
private static function extract_box_value( array $data, string $side ): ?string {
if ( array_key_exists( $side, $data ) ) {
return self::normalize_dimension( $data[ $side ], $data['unit'] ?? 'px' );
}

return null;
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
