<?php
/**
 * Widget handler for Elementor button widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_attr;
use function esc_url;
use function wp_strip_all_tags;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor button widget.
 */
class Button_Widget_Handler implements Widget_Handler_Interface {
    /**
     * Handle conversion of Elementor button to Gutenberg block.
     *
     * @param array $element The Elementor element data.
     */
    public function handle( array $element ): string {
        $settings   = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
        $text       = isset( $settings['text'] ) ? trim( (string) $settings['text'] ) : '';
        $link_data  = is_array( $settings['link'] ?? null ) ? $settings['link'] : array();
        $url        = isset( $link_data['url'] ) ? esc_url( (string) $link_data['url'] ) : '';
        $custom_css = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
        $custom_raw = isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '';
        $text_color = isset( $settings['button_text_color'] ) ? strtolower( (string) $settings['button_text_color'] ) : '';
        $background = isset( $settings['background_color'] ) ? strtolower( (string) $settings['background_color'] ) : '';

        if ( '' === $text ) {
            $text = isset( $link_data['custom_text'] ) ? trim( (string) $link_data['custom_text'] ) : '';
        }

        if ( '' === $text && '' === $url ) {
            return '';
        }

        $custom_classes = array();
        if ( '' !== $custom_raw ) {
            foreach ( preg_split( '/\s+/', $custom_raw ) as $class ) {
                $clean = Style_Parser::clean_class( $class );
                if ( '' === $clean ) {
                    continue;
                }
                $custom_classes[] = $clean;
            }
        }

        $button_attributes = array();
        if ( ! empty( $custom_classes ) ) {
            $button_attributes['className'] = implode( ' ', array_unique( $custom_classes ) );
        }

        if ( '' !== $text ) {
            $button_attributes['text'] = wp_strip_all_tags( $text );
        }

        if ( '' !== $text_color ) {
            if ( $this->is_preset_color_slug( $text_color ) ) {
                $button_attributes['textColor'] = $text_color;
            } elseif ( $this->is_hex_color( $text_color ) ) {
                $button_attributes['style']['color']['text'] = $text_color;
            }
        }

        if ( '' !== $background ) {
            if ( $this->is_preset_color_slug( $background ) ) {
                $button_attributes['backgroundColor'] = $background;
            } elseif ( $this->is_hex_color( $background ) ) {
                $button_attributes['style']['color']['background'] = $background;
            }
        }

        $padding_values = $this->extract_padding( $settings );
        if ( ! empty( $padding_values ) ) {
            $button_attributes['style']['spacing']['padding'] = $padding_values;
        }

        $radius_value = $this->extract_border_radius( $settings );
        if ( null !== $radius_value ) {
            $button_attributes['style']['border']['radius'] = $radius_value;
        }

        if ( '' !== $url ) {
            $button_attributes['url'] = $url;
        }

        $rel_tokens = array();
        if ( ! empty( $link_data['is_external'] ) ) {
            $button_attributes['linkTarget'] = '_blank';
            $rel_tokens[] = 'noopener';
        }

        if ( ! empty( $link_data['nofollow'] ) ) {
            $rel_tokens[] = 'nofollow';
        }

        if ( ! empty( $rel_tokens ) ) {
            $button_attributes['rel'] = implode( ' ', array_unique( $rel_tokens ) );
        }

        if ( '' !== $custom_css ) {
            Style_Parser::save_custom_css( $custom_css );
        }

        $anchor_classes = array( 'wp-block-button__link', 'wp-element-button' );
        $anchor_attrs   = array();
        $anchor_attrs[] = 'class="' . esc_attr( implode( ' ', $anchor_classes ) ) . '"';

        if ( '' !== $url ) {
            $anchor_attrs[] = 'href="' . esc_url( $url ) . '"';
        }

        if ( ! empty( $link_data['is_external'] ) ) {
            $anchor_attrs[] = 'target="_blank"';
        }

        if ( ! empty( $rel_tokens ) ) {
            $anchor_attrs[] = 'rel="' . esc_attr( implode( ' ', array_unique( $rel_tokens ) ) ) . '"';
        }

        $anchor_html = sprintf(
            '<a %s>%s</a>',
            implode( ' ', $anchor_attrs ),
            wp_strip_all_tags( $text )
        );

        $button_block = Block_Builder::build( 'button', $button_attributes, $anchor_html );

        return Block_Builder::build( 'buttons', array(), $button_block );
    }

    /**
     * Check if a given color value is a Gutenberg preset slug.
     */
    private function is_preset_color_slug( string $color ): bool {
        return '' !== $color && false === strpos( $color, '#' );
    }

    /**
     * Determine if the provided color string is hexadecimal.
     */
    private function is_hex_color( string $color ): bool {
        return 1 === preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color );
    }

    /**
     * Extract padding values from Elementor settings.
     */
    private function extract_padding( array $settings ): array {
        $padding_sources = array(
            $settings['text_padding'] ?? null,
            $settings['padding'] ?? null,
        );

        foreach ( $padding_sources as $source ) {
            $values = Style_Parser::parse_box_sides( $source ?? array() );
            if ( ! empty( $values ) ) {
                return $values;
            }
        }

        return array();
    }

    /**
     * Extract a uniform border radius value for the button block.
     */
    private function extract_border_radius( array $settings ): ?string {
        $radius_sources = array(
            $settings['border_radius'] ?? null,
            $settings['button_border_radius'] ?? null,
        );

        foreach ( $radius_sources as $radius ) {
            if ( null === $radius ) {
                continue;
            }

            if ( is_array( $radius ) ) {
                $unit   = isset( $radius['unit'] ) ? (string) $radius['unit'] : 'px';
                $values = Style_Parser::parse_box_sides( $radius, $unit );

                if ( ! empty( $values ) ) {
                    $unique = array_unique( array_values( $values ) );
                    if ( 1 === count( $unique ) ) {
                        return $unique[0];
                    }
                }

                $fallback = Style_Parser::normalize_dimension_value( $radius['size'] ?? $radius['value'] ?? null, $unit );
                if ( null !== $fallback ) {
                    return $fallback;
                }

                continue;
            }

            $normalized = Style_Parser::normalize_dimension_value( $radius );
            if ( null !== $normalized ) {
                return $normalized;
            }
        }

        return null;
    }
}
