<?php
if ( ! function_exists( 'esc_html' ) ) {
function esc_html( $text ) {
return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
}
if ( ! function_exists( 'esc_attr' ) ) {
function esc_attr( $text ) {
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
if ( ! function_exists( 'esc_url' ) ) {
function esc_url( $url ) {
return (string) $url;
}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
function wp_strip_all_tags( $text ) {
return trim( strip_tags( (string) $text ) );
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
if ( ! function_exists( 'add_action' ) ) {
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
}
}
if ( ! function_exists( 'add_filter' ) ) {
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
}
}
if ( ! function_exists( 'add_menu_page' ) ) {
function add_menu_page() {}
}
if ( ! function_exists( 'register_setting' ) ) {
function register_setting() {}
}
if ( ! function_exists( 'add_settings_section' ) ) {
function add_settings_section() {}
}
if ( ! function_exists( 'add_settings_field' ) ) {
function add_settings_field() {}
}
if ( ! function_exists( 'admin_url' ) ) {
function admin_url( $path = '' ) {
return $path;
}
}
if ( ! function_exists( 'wp_nonce_url' ) ) {
function wp_nonce_url( $url, $nonce = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
return $url;
}
}
if ( ! function_exists( 'check_admin_referer' ) ) {
function check_admin_referer() {}
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
function wp_safe_redirect( $location ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
return true;
}
}
if ( ! function_exists( 'wp_die' ) ) {
function wp_die( $message = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
throw new RuntimeException( 'wp_die called' );
}
}
if ( ! function_exists( 'get_the_title' ) ) {
function get_the_title() {
return 'Sample';
}
}
if ( ! function_exists( 'get_post_meta' ) ) {
function get_post_meta() {
return '';
}
}
if ( ! function_exists( 'wp_insert_post' ) ) {
function wp_insert_post() {
return 0;
}
}
if ( ! function_exists( 'wp_update_post' ) ) {
function wp_update_post() {
return 0;
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
require_once __DIR__ . '/../src/admin/widget/class-video-widget-handler.php';
require_once __DIR__ . '/../src/admin/widget/class-icon-box-widget-handler.php';
require_once __DIR__ . '/../src/admin/widget/class-icon-list-widget-handler.php';
require_once __DIR__ . '/../src/admin/widget/class-image-box-widget-handler.php';
require_once __DIR__ . '/../src/admin/widget/class-testimonial-widget-handler.php';
require_once __DIR__ . '/../src/admin/widget/class-social-icon-widget-handler.php';
require_once __DIR__ . '/../src/admin/widget/class-nested-tabs-widget-handler.php';
require_once __DIR__ . '/../src/admin/helper/class-file-upload-service.php';
require_once __DIR__ . '/../src/admin/class-admin-settings.php';

use Progressus\Gutenberg\Admin\Admin_Settings;

$converter = Admin_Settings::instance();

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
expect_true( str_contains( $simple_output, '<!-- wp:group {"layout":{"type":"grid","columnCount":3}} -->' ), 'Expected grid group layout comment missing.' );
expect_true( str_contains( $simple_output, '<!-- wp:heading' ), 'Heading block missing from simple grid.' );
expect_true( str_contains( $simple_output, '<!-- wp:image' ), 'Image block missing from simple grid.' );
expect_true( str_contains( $simple_output, '<!-- wp:buttons' ), 'Buttons block missing from simple grid.' );
expect_true( str_contains( $simple_output, '<!-- wp:button ' ) && str_contains( $simple_output, '"text":"Click me"' ) && str_contains( $simple_output, '"url":"https:\/\/example.com"' ), 'Button block should be self closing with text and URL.' );

$flex_container = array(
    array(
        'elType'   => 'container',
        'settings' => array(
            '_css_classes' => 'e-con e-con-boxed',
        ),
        'elements' => array(
            array(
                'elType'   => 'container',
                'settings' => array(
                    '_css_classes' => 'e-con e-con-child',
                ),
                'elements' => array(
                    array(
                        'elType'     => 'widget',
                        'widgetType' => 'heading',
                        'settings'   => array(
                            'title'       => 'Flex Heading',
                            'header_size' => 'h3',
                        ),
                    ),
                    array(
                        'elType'     => 'widget',
                        'widgetType' => 'text-editor',
                        'settings'   => array(
                            'editor' => '<p>Flex description text.</p>',
                        ),
                    ),
                ),
            ),
            array(
                'elType'   => 'container',
                'settings' => array(
                    '_css_classes' => 'e-con e-con-child',
                ),
                'elements' => array(
                    array(
                        'elType'     => 'widget',
                        'widgetType' => 'image',
                        'settings'   => array(
                            'image' => array(
                                'url' => 'https://example.com/flex.jpg',
                                'alt' => 'Flex',
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
);

$flex_output = $converter->parse_elementor_elements( $flex_container );
expect_true( str_contains( $flex_output, '"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}' ), 'Flex container should render as flex group.' );
expect_true( substr_count( $flex_output, '<!-- wp:group {"layout":{"type":"constrained"}} -->' ) >= 2, 'Flex children should be wrapped in constrained groups.' );
expect_true( str_contains( $flex_output, 'has-global-padding' ), 'Boxed flex container should keep global padding class.' );
expect_true( ! str_contains( $flex_output, 'e-con' ), 'Elementor classes should not leak into output.' );
expect_true( str_contains( $flex_output, '<p>Flex description text.</p>' ), 'Paragraph should remain a single p element.' );

$card_row = array(
    array(
        'elType'   => 'container',
        'settings' => array(
            '_css_classes' => 'e-con e-con-boxed',
        ),
        'elements' => array_map(
            static function ( int $index ) {
                return array(
                    'elType'   => 'container',
                    'settings' => array(
                        '_css_classes' => 'e-con e-con-child',
                    ),
                    'elements' => array(
                        array(
                            'elType'     => 'widget',
                            'widgetType' => 'heading',
                            'settings'   => array(
                                'title'       => 'Card ' . $index,
                                'header_size' => 'h3',
                            ),
                        ),
                        array(
                            'elType'     => 'widget',
                            'widgetType' => 'text-editor',
                            'settings'   => array(
                                'editor' => '<p>Card paragraph ' . $index . '.</p>',
                            ),
                        ),
                        array(
                            'elType'     => 'widget',
                            'widgetType' => 'button',
                            'settings'   => array(
                                'text' => 'Learn ' . $index,
                                'link' => array( 'url' => 'https://example.com/card' . $index ),
                            ),
                        ),
                    ),
                );
            },
            range( 1, 3 )
        ),
    ),
);

$card_output = $converter->parse_elementor_elements( $card_row );
expect_true( str_contains( $card_output, '<!-- wp:columns' ), 'Card row should render columns block.' );
expect_true( 3 === substr_count( $card_output, '<!-- wp:column --' ), 'Card row should contain three columns.' );
expect_true( 3 === substr_count( $card_output, '<!-- wp:button {' ), 'Each card should contain a button block.' );

$youtube_widget = array(
    array(
        'elType'     => 'widget',
        'widgetType' => 'video',
        'settings'   => array(
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ?t=30',
        ),
    ),
);

$youtube_output = $converter->parse_elementor_elements( $youtube_widget );
expect_true( str_contains( $youtube_output, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ), 'YouTube embed should use canonical watch URL.' );
expect_true( str_contains( $youtube_output, '<!-- wp:embed {"url":"https:\\/\\/www.youtube.com\\/watch?v=dQw4w9WgXcQ"' ), 'YouTube should render as embed block.' );
expect_true( ! str_contains( $youtube_output, '<iframe' ), 'YouTube embed should not render raw iframe.' );

$strong_text = array(
    array(
        'elType'     => 'widget',
        'widgetType' => 'text-editor',
        'settings'   => array(
            'editor' => '<p><strong>First</strong></p><p><strong>Second</strong></p>',
        ),
    ),
);

$strong_output = $converter->parse_elementor_elements( $strong_text );
expect_true( 2 === substr_count( $strong_output, '<!-- wp:paragraph' ), 'Strong text should render as two paragraph blocks.' );
expect_true( substr_count( $strong_output, '<strong>' ) === 2, 'Strong tags should be preserved in paragraphs.' );

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
expect_true( substr_count( $complex_output, '<!-- wp:heading' ) === 5, 'Expected five heading blocks in complex grid.' );

$text_elements = array(
array(
'elType'     => 'widget',
'widgetType' => 'text-editor',
'settings'   => array(
'editor'     => 'Plain text paragraph',
'text_color' => '#333333',
),
),
);

$plain_output = $converter->parse_elementor_elements( $text_elements );
expect_true( str_contains( $plain_output, '<!-- wp:paragraph' ), 'Plain text should render as paragraph.' );
expect_true( ! str_contains( $plain_output, '<!-- wp:html' ), 'Plain text should not render as HTML block.' );

$html_elements = array(
array(
'elType'     => 'widget',
'widgetType' => 'text-editor',
'settings'   => array(
'editor' => '<p><span style="color:red">Rich</span> content</p>',
),
),
);

$html_output = $converter->parse_elementor_elements( $html_elements );
expect_true( str_contains( $html_output, '<!-- wp:paragraph' ), 'Complex HTML should render as a paragraph block when possible.' );
expect_true( str_contains( $html_output, '<span style="color:red">Rich</span> content' ), 'Inline HTML should be preserved within the paragraph.' );

$image_box = array(
array(
'elType'     => 'widget',
'widgetType' => 'image-box',
'settings'   => array(
'image'            => array( 'url' => 'https://example.com/card.jpg', 'alt' => 'Card' ),
'title_text'       => 'Card Title',
'description_text' => 'Card description.',
),
),
);

$image_box_output = $converter->parse_elementor_elements( $image_box );
expect_true( str_contains( $image_box_output, '<!-- wp:image' ), 'Image box should render an image block.' );
expect_true( str_contains( $image_box_output, '<!-- wp:heading' ), 'Image box should render a heading block.' );
expect_true( str_contains( $image_box_output, '<!-- wp:paragraph' ), 'Image box should render a paragraph block.' );

$testimonials = array(
array(
'elType'   => 'container',
'settings' => array(),
'elements' => array_map(
static function ( $i ) {
return array(
'elType'     => 'widget',
'widgetType' => 'testimonial',
'settings'   => array(
'testimonial_content' => 'Testimonial ' . $i,
'testimonial_name'    => 'Person ' . $i,
),
);
},
range( 1, 3 )
),
),
);

$testimonials_output = $converter->parse_elementor_elements( $testimonials );
expect_true( str_contains( $testimonials_output, '"layout":{"type":"grid","columnCount":3}' ), 'Testimonials should form a three-column grid.' );

$social_icons = array(
array(
'elType'     => 'widget',
'widgetType' => 'social-icons',
'settings'   => array(
'social_icon_list' => array(
array(
'social_icon' => array( 'value' => 'fab fa-facebook' ),
'link'        => array( 'url' => 'https://facebook.com/example' ),
),
array(
'social_icon' => array( 'value' => 'fab fa-twitter' ),
'link'        => array( 'url' => 'https://twitter.com/example' ),
),
),
),
),
);

$social_output = $converter->parse_elementor_elements( $social_icons );
expect_true( str_contains( $social_output, '<!-- wp:social-links' ), 'Social icons should produce a social-links block.' );
expect_true( str_contains( $social_output, '"service":"facebook"' ), 'Facebook service should be detected.' );

$nested_tabs = array(
array(
'elType'     => 'widget',
'widgetType' => 'nested-tabs',
'settings'   => array(
'tabs' => array(
array(
'tab_title'   => 'First Tab',
'tab_content' => array(
array(
'elType'     => 'widget',
'widgetType' => 'heading',
'settings'   => array(
'title'       => 'Nested Heading',
'header_size' => 'h3',
),
),
),
),
),
),
),
);

$nested_output = $converter->parse_elementor_elements( $nested_tabs );
expect_true( str_contains( $nested_output, '<!-- wp:heading' ), 'Nested tabs should render inner heading blocks.' );

echo "All conversion smoke tests passed\n";
