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
        $settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
        $text     = isset( $settings['text'] ) ? trim( (string) $settings['text'] ) : '';
        $link     = is_array( $settings['link'] ?? null ) ? trim( (string) ( $settings['link']['url'] ?? '' ) ) : '';
        $custom   = isset( $settings['_css_classes'] ) ? (string) $settings['_css_classes'] : '';
        $custom_css = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';
        $text_color = isset( $settings['button_text_color'] ) ? strtolower( (string) $settings['button_text_color'] ) : '';
        $background = isset( $settings['background_color'] ) ? strtolower( (string) $settings['background_color'] ) : '';

        if ( '' === $text && '' === $link ) {
            return '';
        }

        $typography = Style_Parser::parse_typography( $settings );
        $spacing    = Style_Parser::parse_spacing( $settings );
        $border     = Style_Parser::parse_border( $settings );

        $button_attributes = array();

        if ( '' !== $text ) {
            $button_attributes['text'] = wp_strip_all_tags( $text );
        }

        if ( '' !== $link ) {
            $button_attributes['url'] = esc_url( $link );
        }

        if ( ! empty( $typography['attributes'] ) ) {
            $button_attributes['style']['typography'] = $typography['attributes'];
        }

        if ( ! empty( $spacing['attributes'] ) ) {
            unset( $spacing['attributes']['blockGap'] );
            if ( ! empty( $spacing['attributes'] ) ) {
                $button_attributes['style']['spacing'] = $spacing['attributes'];
            }
        }

        if ( ! empty( $border['attributes'] ) ) {
            $button_attributes['style']['border'] = $border['attributes'];
        }

        $class_names = array();

        if ( '' !== $text_color ) {
            if ( $this->is_preset_color_slug( $text_color ) ) {
                $button_attributes['textColor'] = $text_color;
                $button_attributes['style']['elements']['link']['color']['text'] = 'var:preset|color|' . $text_color;
            } else {
                $button_attributes['style']['color']['text']                  = $text_color;
                $button_attributes['style']['elements']['link']['color']['text'] = $text_color;
            }

            $class_names[] = 'has-text-color';
            $class_names[] = 'has-link-color';
        }

        if ( '' !== $background ) {
            if ( $this->is_preset_color_slug( $background ) ) {
                $button_attributes['backgroundColor'] = $background;
            } else {
                $button_attributes['style']['color']['background'] = $background;
            }

            $class_names[] = 'has-background';
        }

        if ( '' !== $custom ) {
            foreach ( preg_split( '/\s+/', $custom ) as $class ) {
                $clean = Style_Parser::clean_class( $class );
                if ( '' !== $clean ) {
                    $class_names[] = $clean;
                }
            }
        }

        if ( ! empty( $class_names ) ) {
            $unique = array();
            foreach ( $class_names as $class_name ) {
                $clean = Style_Parser::clean_class( $class_name );
                if ( '' === $clean ) {
                    continue;
                }
                $unique[ $clean ] = true;
            }

            if ( ! empty( $unique ) ) {
                $button_attributes['className'] = implode( ' ', array_keys( $unique ) );
            }
        }

        if ( '' !== $custom_css ) {
            Style_Parser::save_custom_css( $custom_css );
        }

        $button_block = Block_Builder::build( 'button', $button_attributes, '' );

        return Block_Builder::build( 'buttons', array(), $button_block );
    }

    /**
     * Check if a given color value is a Gutenberg preset slug.
     */
    private function is_preset_color_slug( string $color ): bool {
        return '' !== $color && false === strpos( $color, '#' );
    }
}
