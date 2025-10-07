<?php
// Minimal WordPress function stubs for testing.
if ( ! function_exists( 'esc_attr' ) ) {
function esc_attr( $text ) {
return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
}
if ( ! function_exists( 'esc_url' ) ) {
function esc_url( $url ) {
return filter_var( $url, FILTER_SANITIZE_URL );
}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
function esc_url_raw( $url ) {
return filter_var( $url, FILTER_SANITIZE_URL );
}
}
if ( ! function_exists( 'esc_html' ) ) {
function esc_html( $text ) {
return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
}
if ( ! function_exists( 'esc_html__' ) ) {
function esc_html__( $text, $domain = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
return $text;
}
}
if ( ! function_exists( 'sanitize_html_class' ) ) {
function sanitize_html_class( $class ) {
return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
function wp_json_encode( $data ) {
return json_encode( $data );
}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
function wp_kses_post( $content ) {
return (string) $content;
}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
function sanitize_text_field( $text ) {
return trim( (string) $text );
}
}
if ( ! function_exists( 'sanitize_key' ) ) {
function sanitize_key( $key ) {
return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
}
if ( ! function_exists( 'wp_get_custom_css_post' ) ) {
function wp_get_custom_css_post() {
return (object) array( 'post_content' => '' );
}
}
if ( ! function_exists( 'wp_update_custom_css_post' ) ) {
function wp_update_custom_css_post( $css ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
return true;
}
}

echo "Running conversion smoke tests...\n";

if ( ! defined( 'ABSPATH' ) ) {
define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../src/admin/helper/class-style-parser.php';
require_once __DIR__ . '/../src/admin/helper/class-block-builder.php';
require_once __DIR__ . '/../src/admin/layout/class-container-classifier.php';
require_once __DIR__ . '/../src/admin/class-widget-handler-interface.php';
require_once __DIR__ . '/../src/admin/class-widget-handler-factory.php';
require_once __DIR__ . '/../src/admin/widget/class-heading-widget-handler.php';
require_once __DIR__ . '/../src/admin/widget/class-text-editor-widget-handler.php';
require_once __DIR__ . '/../src/admin/widget/class-button-widget-handler.php';
require_once __DIR__ . '/../src/admin/widget/class-image-widget-handler.php';
require_once __DIR__ . '/../src/admin/helper/class-file-upload-service.php';
require_once __DIR__ . '/../src/admin/class-admin-settings.php';

use Progressus\Gutenberg\Admin\Admin_Settings;

class Test_Admin_Settings extends Admin_Settings {
public function __construct() {}
}

$converter = new Test_Admin_Settings();

/**
 * Simple assertion helper.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message   Failure message.
 */
function expect_true( bool $condition, string $message ): void {
if ( ! $condition ) {
throw new RuntimeException( $message );
}
}

$simple_grid = array(
array(
'elType'   => 'container',
'settings' => array(
'container_type'     => 'grid',
'grid_columns_grid'  => array( 'size' => 3 ),
),
'elements' => array(
array(
'elType'     => 'widget',
'widgetType' => 'heading',
'settings'   => array(
'title'       => 'Grid Heading',
'header_size' => 'h2',
),
),
array(
'elType'     => 'widget',
'widgetType' => 'image',
'settings'   => array(
'image' => array(
'url' => 'https://example.com/image.jpg',
'alt' => 'Example',
),
),
),
array(
'elType'     => 'widget',
'widgetType' => 'button',
'settings'   => array(
'text' => 'Click me',
'link' => array( 'url' => 'https://example.com' ),
),
),
),
),
);

$simple_output = $converter->parse_elementor_elements( $simple_grid );
expect_true( str_contains( $simple_output, '<!-- wp:core/group {"layout":{"type":"grid","columnCount":3}} -->' ), 'Expected grid group layout comment missing.' );
expect_true( str_contains( $simple_output, '<!-- wp:core/heading' ), 'Heading block missing from simple grid.' );
expect_true( str_contains( $simple_output, '<!-- wp:core/image' ), 'Image block missing from simple grid.' );
expect_true( str_contains( $simple_output, '<!-- wp:core/buttons' ), 'Buttons block missing from simple grid.' );

$complex_grid = array(
array(
'elType'   => 'container',
'settings' => array(
'grid_template_columns' => 'repeat(3, 1fr)',
),
'elements' => array_map(
static function ( $index ) {
return array(
'elType'     => 'widget',
'widgetType' => 'heading',
'settings'   => array(
'title'       => 'Item ' . $index,
'header_size' => 'h3',
),
);
},
range( 1, 5 )
),
),
);

$complex_output = $converter->parse_elementor_elements( $complex_grid );
expect_true( str_contains( $complex_output, '"layout":{"type":"grid","columnCount":3}' ), 'Grid column count not detected for complex grid.' );
expect_true( substr_count( $complex_output, '<!-- wp:core/heading' ) === 5, 'Expected five heading blocks in complex grid.' );

echo "All conversion smoke tests passed\n";
