<?php
/**
 * The main class of the Mighty Kids plugin.
 *
 * @package Progressus\Gutenberg
 */

namespace Progressus\Gutenberg;

defined('ABSPATH') || exit;

use Progressus\Gutenberg\Admin\Admin_Settings;

/**
 * Class Gutenberg
 *
 * @package Progressus\Gutenberg
 */
class Gutenberg
{

    /**
     * Flag to ensure layout utilities stylesheet is only enqueued once per request.
     *
     * @var bool
     */
    private bool $layout_utilities_enqueued = false;

    /**
     * Instance to call certain functions globally within the plugin
     *
     * @var self|null _instance
     */
    protected static ?Gutenberg $instance = null;

    /**
     * Construct the plugin.
     */
    public function __construct()
    {
        add_action('init', array( $this, 'load_plugin' ), 0);
        add_action('gutenberg_plugin_activated', array( $this, 'activation_hooks' ));
        add_action('gutenberg_plugin_deactivated', array( $this, 'deactivation_hooks' ));
    }

    /**
     * Gutenberg Customization.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @static
     * @return Gutenberg|null Gutenberg instance.
     */
    public static function instance(): ?Gutenberg
    {
        if (is_null(self::$instance) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Plugin activation hooks.
     */
    public function activation_hooks()
    {
    }

    /**
     * Plugin activation hooks.
     */
    public function deactivation_hooks()
    {
    }

    /**
     * Determine which plugin to load.
     */
    public function load_plugin(): void
    {
        $this->init_hooks();
    }

    /**
     * Collection of hooks.
     */
    public function init_hooks(): void
    {
        add_action('init', array( $this, 'init' ), 1);
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
    }

    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_scripts(): void
    {
        wp_enqueue_style(
            'font-awesome-custom',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
            array(),
            '6.5.0'
        );

        $this->enqueue_layout_utilities_style();
    }

    /**
     * Enqueue styles for the block editor.
     */
    public function enqueue_editor_assets(): void
    {
        $this->enqueue_layout_utilities_style();
    }

    /**
     * Initialize the plugin.
     */
    public function init(): void
    {
                new Admin_Settings();
    }

    /**
     * Register the layout utilities stylesheet if needed.
     */
    private function enqueue_layout_utilities_style(): void
    {
        if ( $this->layout_utilities_enqueued ) {
            return;
        }

        $handle = 'elementor-to-gutenberg-layout-utilities';
        $css    = ' .etg-grid{display:grid;gap:var(--wp--style--block-gap,1.5rem);} '
            . '.etg-grid-cols-1{grid-template-columns:repeat(1,minmax(0,1fr));}'
            . '.etg-grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr));}'
            . '.etg-grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr));}'
            . '.etg-grid-cols-4{grid-template-columns:repeat(4,minmax(0,1fr));}'
            . '.etg-grid-cols-5{grid-template-columns:repeat(5,minmax(0,1fr));}'
            . '.etg-grid-cols-6{grid-template-columns:repeat(6,minmax(0,1fr));}';

        if ( ! wp_style_is( $handle, 'registered' ) ) {
            wp_register_style( $handle, false, array(), GUTENBERG_PLUGIN_VERSION );
        }

        wp_enqueue_style( $handle );
        wp_add_inline_style( $handle, $css );

        $this->layout_utilities_enqueued = true;
    }
}
