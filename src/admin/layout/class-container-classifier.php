<?php
/**
 * Decide which Gutenberg layout to use when rendering Elementor containers.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg\Admin\Layout;

defined( 'ABSPATH' ) || exit;

/**
 * Determine which layout a container should use.
 */
class Container_Classifier {
	/**
	 * Determine if a container should render as a grid.
	 *
	 * @param array $element Elementor container element.
	 *
	 * @return bool
	 */
        public static function is_grid( array $element ): bool {
                $settings    = $element['settings'] ?? array();
                $child_count = isset( $element['elements'] ) && is_array( $element['elements'] ) ? count( $element['elements'] ) : 0;

                if ( isset( $settings['container_type'] ) && 'grid' === $settings['container_type'] ) {
                        return true; // Elementor “simple” export
                }

                if ( isset( $settings['layout'] ) && 'grid' === $settings['layout'] ) {
                        return true;
                }

		if ( isset( $settings['display'] ) && 'grid' === $settings['display'] ) {
			return true;
		}

                if ( isset( $settings['grid_columns'] ) || isset( $settings['grid_template_columns'] ) || isset( $settings['grid_auto_flow'] ) ) {
                        return true;
                }

                if ( isset( $settings['grid_columns_grid'] ) || isset( $settings['grid_rows_grid'] ) ) {
                        return true;
                }

		if ( isset( $settings['grid_row_gap'] ) || isset( $settings['gap_rows'] ) ) {
			return true;
		}

		if ( $child_count > 4 ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether we should render a container as columns.
	 *
	 * @param array $element  Elementor container element.
	 * @param array $children Children elements.
	 *
	 * @return bool
	 */
	public static function should_render_columns( array $element, array $children ): bool {
		$child_count = count( $children );
		if ( $child_count < 2 || $child_count > 4 ) {
			return false;
		}

		if ( self::is_grid( $element ) ) {
			return false;
		}

		$settings = $element['settings'] ?? array();
		$direction = self::get_flex_direction( $settings );
		if ( 'column' === $direction || 'column-reverse' === $direction ) {
			return false;
		}

		$wrap = $settings['flex_wrap'] ?? $settings['flex_wrap_mobile'] ?? '';
		if ( in_array( $wrap, array( 'wrap', 'wrap-reverse' ), true ) && $child_count > 3 ) {
			return false;
		}

		return true;
	}

	/**
	 * Infer a grid column count from Elementor settings.
	 *
	 * @param array $element     Elementor container element.
	 * @param int   $child_count Number of children.
	 *
	 * @return int
	 */
	public static function get_grid_column_count( array $element, int $child_count ): int {
                $settings = $element['settings'] ?? array();

                if ( ! empty( $settings['grid_columns_grid'] ) && is_array( $settings['grid_columns_grid'] ) ) {
                        $val = (int) ( $settings['grid_columns_grid']['size'] ?? 0 );
                        if ( $val > 0 ) {
                                return min( max( 1, $val ), max( 1, $child_count ) );
                        }
                }

                $possible_keys = array( 'grid_columns', 'columns', 'grid_columns_number', 'grid_template_columns' );

		foreach ( $possible_keys as $key ) {
			if ( empty( $settings[ $key ] ) ) {
				continue;
			}

			$value = $settings[ $key ];
			if ( is_array( $value ) ) {
				$value = $value['size'] ?? reset( $value );
			}

			$value = (int) $value;
			if ( $value > 0 ) {
				return min( max( 1, $value ), max( 1, $child_count ) );
			}
		}

		if ( $child_count >= 4 ) {
			return 4;
		}

		if ( $child_count >= 3 ) {
			return 3;
		}

		if ( $child_count > 0 ) {
			return $child_count;
		}

		return 1;
	}

	/**
	 * Get the flex direction configured for a container.
	 *
	 * @param array $settings Elementor settings array.
	 *
	 * @return string
	 */
	public static function get_flex_direction( array $settings ): string {
		$direction = $settings['flex_direction'] ?? $settings['direction'] ?? '';

		$valid = array( 'row', 'row-reverse', 'column', 'column-reverse' );
		if ( in_array( $direction, $valid, true ) ) {
			return $direction;
		}

		return 'row';
	}
}
