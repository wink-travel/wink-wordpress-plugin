<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Abstract base class for all Wink block elements.
 *
 * Provides the shared infrastructure used by every Wink element:
 * - Gutenberg block registration (can be used as-is or overridden)
 * - Block editor context detection (safe, sanitized $_REQUEST check)
 * - The <wink-app-loader> footer component (rendered exactly once per page)
 *
 * Child classes must assign $this->blockCode and $this->blockName in their
 * constructor before calling any methods that depend on those values.
 */
class winkElements {
    /**
     * Tracks whether the <wink-app-loader> custom element has already been output
     * to the page footer. Static so it is shared across all element instances.
     */
    private static bool $loaderEnqueued = false;

    /** WordPress option key holding the affiliate Client ID credential. */
    protected string $clientIdKey = WINK_CLIENT_ID_OPTION;

    /** WordPress option key holding the affiliate Client Secret credential. */
    protected string $clientSecretKey = 'winkSecret';

    /**
     * Short identifier for this block type (e.g. 'winklookup').
     * Used as the block slug, script handle suffix, and shortcode name.
     * Must be set by the child constructor.
     */
    protected string $blockCode;

    /**
     * Human-readable display name shown in the Gutenberg block inserter.
     * Must be set by the child constructor.
     */
    protected string $blockName;

    /**
     * Gutenberg block attribute definitions passed to register_block_type().
     * Override in child classes that accept block attributes (e.g. winkContent).
     *
     * @var array<string, array{type: string, default: string}>
     */
    protected array $attributes = array();

    /**
     * Absolute URL to the plugin's includes/ directory, with trailing slash.
     * Computed once in the constructor; never changed after that.
     */
    protected string $pluginURL;

    /**
     * Absolute URL to the plugin's img/ directory, with trailing slash.
     * Computed once in the constructor; never changed after that.
     */
    protected string $imgURL;

    /**
     * Current deployment environment: 'production', 'staging', or 'development'.
     * Read from wp_options once in the constructor; never changed after that.
     */
    protected string $environmentVal;

    /**
     * Initialises the shared properties used by all Wink element blocks.
     * Called via parent::__construct() in each child class constructor.
     */
    function __construct() {
        $this->pluginURL      = trailingslashit( plugin_dir_url( __FILE__ ) );
        $this->imgURL         = trailingslashit( dirname( plugin_dir_url( __FILE__ ) ) ) . 'img/';
        $this->environmentVal = (string) get_option( 'winkEnvironment', 'production' );
    }

    /**
     * Registers the wp_footer action to output the <wink-app-loader> web component.
     *
     * Call this from blockHandler() so the Wink app-loader element is injected into
     * the page footer whenever a Wink block is present on the page.
     *
     * @return void
     */
    function coreFunction(): void {
        add_action( 'wp_footer', array( $this, 'coreComponent' ) );
    }

    /**
     * Outputs the <wink-app-loader> custom element to the page footer.
     *
     * The loader must appear exactly once per page regardless of how many Wink
     * blocks are present. After the first call outputs the element, all subsequent
     * calls from other blocks on the same page are silently ignored.
     *
     * @return bool True once the loader has been output; false if already output (no-op).
     */
    function coreComponent(): bool {
        if ( self::$loaderEnqueued === false ) {
            $clientId = (string) get_option( $this->clientIdKey, '' );
            echo '<wink-app-loader client-id="' . esc_attr( $clientId ) . '"></wink-app-loader>';
            self::$loaderEnqueued = true;
        }
        return self::$loaderEnqueued;
    }

    /**
     * Determines whether the current request originates from the Gutenberg block editor.
     *
     * Gutenberg's REST API preview requests include context=edit or action=edit in the
     * request parameters. Values are sanitized with sanitize_key() before comparison so
     * that any characters outside [a-z0-9_-] are stripped, even though we only compare
     * and never output the value.
     *
     * @return bool True when rendering inside the block editor; false on the front end.
     */
    protected function isEditorContext(): bool {
        $context = isset( $_REQUEST['context'] ) ? sanitize_key( $_REQUEST['context'] ) : '';
        $action  = isset( $_REQUEST['action'] )  ? sanitize_key( $_REQUEST['action'] )  : '';
        return is_admin() || $context === 'edit' || $action === 'edit';
    }

    /**
     * Registers this element as a Gutenberg block type.
     *
     * Hooked to the WordPress 'init' action. Does nothing when register_block_type()
     * is not available (i.e. Gutenberg is not active).
     *
     * Child classes may override this method to supply additional wp_localize_script()
     * data (see winkContent for an example).
     *
     * @return void
     */
    function gutenbergBlockRegistration(): void {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        $handle = 'winkBlockRenderer_' . $this->blockCode;

        wp_register_script(
            $handle,
            $this->pluginURL . 'elements/js/' . $this->blockCode . '.js',
            array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-editor' ),
            false
        );

        wp_localize_script( $handle, 'winkData', array(
            'blockCat' => 'wink2travel-blocks',
            'imgURL'   => $this->imgURL,
            'mode'     => $this->environmentVal,
        ) );

        register_block_type( 'wink-blocks/' . $this->blockCode, array(
            'editor_script'   => $handle,
            'render_callback' => array( $this, 'blockHandler' ),
            'attributes'      => $this->attributes,
            'category'        => 'wink2travel-blocks',
        ) );
    }
}

// Exception hierarchy and API client must load first so any element file can reference them.
require_once dirname( __FILE__ ) . '/WinkException.php';
require_once dirname( __FILE__ ) . '/WinkApiClient.php';
require_once 'elements/winklookup.php';
require_once 'elements/winkitinerary.php';
require_once 'elements/winkitineraryform.php';
require_once 'elements/winksearch.php';
require_once 'elements/winkaccount.php';
require_once 'elements/winkcontent.php';

// Page-builder integrations call add_action()/add_filter() at the module level,
// so skip them entirely when running under PHPUnit.
if ( ! defined( 'WINK_TESTING' ) ) {
    require_once 'elements/wpbakery/vcElements.php';
    require_once 'elements/elementor/elementorWidgets.php';
    require_once 'elements/avada/fusionElements.php';
}
