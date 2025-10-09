<?php
/**
 * Widget handler for Elementor nested tabs widget.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Widget;

use Progressus\Gutenberg\Admin\Admin_Settings;
use Progressus\Gutenberg\Admin\Helper\Block_Builder;
use Progressus\Gutenberg\Admin\Helper\Style_Parser;
use Progressus\Gutenberg\Admin\Widget_Handler_Interface;

use function esc_html;

defined( 'ABSPATH' ) || exit;

/**
 * Widget handler for Elementor nested tabs widget.
 */
class Nested_Tabs_Widget_Handler implements Widget_Handler_Interface {
        /**
         * Handle conversion of nested tabs widget.
         *
         * @param array $element Elementor widget data.
         */
        public function handle( array $element ): string {
                $settings   = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
                $tabs       = is_array( $settings['tabs'] ?? null ) ? $settings['tabs'] : array();
                $custom_css = isset( $settings['custom_css'] ) ? (string) $settings['custom_css'] : '';

                if ( empty( $tabs ) ) {
                        return '';
                }

                $tab_blocks = array();
                foreach ( $tabs as $tab ) {
                        if ( ! is_array( $tab ) ) {
                                continue;
                        }

                        $tab_blocks[] = $this->render_tab_group( $tab );
                }

                $tab_blocks = array_filter( $tab_blocks );
                if ( empty( $tab_blocks ) ) {
                        return '';
                }

                if ( '' !== $custom_css ) {
                        Style_Parser::save_custom_css( $custom_css );
                }

                $attributes           = Style_Parser::parse_container_styles( $settings );
                $attributes['layout'] = array( 'type' => 'constrained' );

                return Block_Builder::build( 'group', $attributes, implode( '', $tab_blocks ) );
        }

        /**
         * Render a single tab section as a group block.
         *
         * @param array $tab Tab definition.
         */
        private function render_tab_group( array $tab ): string {
                $title = isset( $tab['tab_title'] ) ? (string) $tab['tab_title'] : (string) ( $tab['title'] ?? '' );
                $content_elements = array();

                if ( isset( $tab['tab_content'] ) ) {
                        if ( is_array( $tab['tab_content'] ) ) {
                                $content_elements = $tab['tab_content'];
                        } elseif ( is_string( $tab['tab_content'] ) ) {
                                $content_elements = array(
                                        array(
                                                'elType'     => 'widget',
                                                'widgetType' => 'text-editor',
                                                'settings'   => array( 'editor' => $tab['tab_content'] ),
                                        ),
                                );
                        }
                }

                $inner_blocks = array();
                if ( '' !== trim( $title ) ) {
                                $inner_blocks[] = Block_Builder::build(
                                        'heading',
                                        array( 'level' => 4 ),
                                        sprintf( '<h4>%s</h4>', esc_html( $title ) )
                                );
                }

                if ( ! empty( $content_elements ) ) {
                        $converter = Admin_Settings::instance();
                        $inner_blocks[] = $converter->parse_elementor_elements( $content_elements );
                }

                $inner_markup = implode( '', array_filter( $inner_blocks ) );
                if ( '' === $inner_markup ) {
                        return '';
                }

                return Block_Builder::build(
                        'group',
                        array( 'layout' => array( 'type' => 'constrained' ) ),
                        $inner_markup
                );
        }
}
