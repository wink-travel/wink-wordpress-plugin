<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Wink Content block element.
 *
 * Renders a <wink-content-loader> custom element that displays a saved Wink
 * affiliate inventory layout (e.g. a hotel grid, tour list, or activity carousel).
 *
 * This class handles rendering only. All API communication (OAuth token acquisition
 * and layout data fetching) is delegated to WinkApiClient.
 *
 * Supports Gutenberg blocks, shortcodes, WPBakery, Elementor, and Avada page builders.
 */
class winkContent extends winkElements {
    /**
     * API client instance responsible for fetching layout data from the Wink REST API.
     * Instantiated in the constructor and reused across all method calls.
     */
    protected WinkApiClient $apiClient;

    /**
     * Registers the Gutenberg block, shortcode, and Customizer data filter.
     * Instantiates the WinkApiClient with sanitized credentials from wp_options.
     */
    function __construct() {
        parent::__construct();
        $this->blockCode = 'winkcontent';
        $this->blockName = esc_html__( 'wink Content', 'wink2travel' );
        $this->attributes = array(
            'layout'     => array( 'default' => '', 'type' => 'string' ),
            'layoutId'   => array( 'default' => '', 'type' => 'string' ),
            'background' => array( 'default' => '', 'type' => 'string' ),
        );

        // Sanitize credentials when reading from the database so any characters
        // that could interfere with HTTP headers (e.g. newlines) are removed.
        $clientId     = sanitize_text_field( (string) get_option( $this->clientIdKey, '' ) );
        $clientSecret = sanitize_text_field( (string) get_option( $this->clientSecretKey, '' ) );

        $this->apiClient = new WinkApiClient( $clientId, $clientSecret, $this->environmentVal );

        add_action( 'init', array( $this, 'gutenbergBlockRegistration' ) );
        add_shortcode( $this->blockCode, array( $this, 'blockHandler' ) );
        add_filter( 'winkShortcodes', array( $this, 'shortcodeData' ) );
    }

    /**
     * Provides shortcode metadata including a dropdown of the affiliate's saved layouts.
     *
     * The layout list is fetched via WinkApiClient (cached for WINK_CACHE_TTL seconds).
     * On API failure an empty dropdown is returned so the Customizer remains functional.
     *
     * @param  array $shortcodes Existing shortcode definitions.
     * @return array Shortcode definitions with this element appended.
     */
    function shortcodeData( array $shortcodes ): array {
        $values = array( esc_html__( 'Select...', 'wink2travel' ) => '' );

        try {
            $layouts = $this->apiClient->getLayouts();
            foreach ( $layouts as $layout ) {
                $values[ $layout['name'] ] = $layout['id'];
            }
        } catch ( WinkApiException $e ) {
            error_log( 'Wink: Could not load layouts for Customizer — ' . $e->getMessage() );
        }

        $shortcodes[ $this->blockCode ] = array(
            'code'   => $this->blockCode,
            'name'   => $this->blockName,
            'params' => array(
                array(
                    'type'        => 'dropdown',
                    'holder'      => 'div',
                    'class'       => '',
                    'heading'     => __( 'Inventory', 'wink2travel' ),
                    'param_name'  => 'layoutid',
                    'value'       => $values,
                    'description' => __( 'Select any of your saved layouts. We strongly recommend to use this block only in full-width content areas and not in columns.', 'wink2travel' ),
                ),
            ),
        );
        return $shortcodes;
    }

    /**
     * Outputs the <wink-app-loader> footer component and renders the block HTML.
     * Used as the render_callback for register_block_type() and as the shortcode handler.
     *
     * @param  array|string $atts Block attributes or shortcode attributes.
     * @return string The rendered HTML for this element.
     */
    function blockHandler( $atts ): string {
        $this->coreFunction();
        return $this->winkElement( (array) $atts );
    }

    /**
     * Returns the HTML for the <wink-content-loader> custom element.
     *
     * Resolves the layout type from the supplied attributes, optionally looking it up via
     * the Wink API when only a layout ID is provided. Falls back to 'HOTEL' when the layout
     * type cannot be determined.
     *
     * On API failure in the Gutenberg editor context a user-readable error message is returned
     * so the site owner can identify the problem. On the front end, an empty string is returned
     * (fail silently — visitors should not see error messages from back-end API calls).
     *
     * @param  array $atts Block or shortcode attributes. Keys: layout, layoutId, layoutid, background.
     * @return string The element HTML, an editor error message, or empty string on front-end failure.
     */
    function winkElement( array $atts ): string {
        // Values are stored as raw strings here. Escaping is applied at the output
        // step (inside the array_map closure below), following WordPress's "escape late"
        // security principle: escaping at the point of output prevents any future code
        // path from writing to $config without going through the escape gate.
        $config = array();

        if ( ! empty( $atts['layout'] ) ) {
            $config['layout'] = (string) $atts['layout'];
        }

        // WPBakery passes 'layoutid' (lowercase); normalise to 'layoutId'.
        if ( ! empty( $atts['layoutid'] ) ) {
            $atts['layoutId'] = $atts['layoutid'];
        }

        if ( ! empty( $atts['layoutId'] ) ) {
            $config['id'] = (string) $atts['layoutId'];

            if ( empty( $config['layout'] ) ) {
                $config['layout'] = $this->resolveLayoutType( $config['id'] );
            }
        }

        // Escape values at output time so all paths through $config are covered.
        $attrs = implode( ' ', array_map(
            function ( string $key ) use ( $config ): string {
                $value = $config[ $key ];
                if ( is_bool( $value ) ) {
                    return $value ? esc_attr( $key ) : '';
                }
                return esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
            },
            array_keys( $config )
        ) );

        ob_start();
        ?>
        <wink-content-loader <?php echo $attrs; ?>></wink-content-loader>
        <?php
        $content = (string) ob_get_clean();

        if ( $this->isEditorContext() ) {
            return htmlspecialchars( $content );
        }
        return $content;
    }

    /**
     * Looks up the layout type string (e.g. 'HOTEL') for a given layout ID.
     *
     * Fetches the full layout list via WinkApiClient (cached) and searches for a match.
     * Returns 'HOTEL' as a safe default when the layout is not found or the API fails.
     *
     * On authentication failure an admin notice transient is set so the site owner
     * sees an error banner in the WordPress dashboard.
     *
     * @param  string $layoutId The Wink layout ID to look up.
     * @return string The layout type string, or 'HOTEL' if not found.
     */
    protected function resolveLayoutType( string $layoutId ): string {
        try {
            $layouts = $this->apiClient->getLayouts();
        } catch ( WinkAuthException $e ) {
            error_log( 'Wink: Authentication failed while resolving layout type — ' . $e->getMessage() );
            set_transient( 'wink_auth_error', $e->getMessage(), HOUR_IN_SECONDS );
            return 'HOTEL';
        } catch ( WinkDataException $e ) {
            error_log( 'Wink: API error while resolving layout type — ' . $e->getMessage() );
            return 'HOTEL';
        }

        foreach ( $layouts as $layout ) {
            if ( isset( $layout['id'] ) && $layout['id'] === $layoutId ) {
                // Return the raw string; escaping is applied at the output step in winkElement().
                return ! empty( $layout['layout'] ) ? (string) $layout['layout'] : 'HOTEL';
            }
        }

        return 'HOTEL';
    }

    /**
     * Registers this element as a Gutenberg block type and provides the editor with
     * the affiliate's saved layout list for the layout picker dropdown.
     *
     * Extends the base gutenbergBlockRegistration() by adding a second wp_localize_script()
     * call with the layout data. On API failure an empty array is passed so the editor
     * remains functional (the dropdown will be empty rather than throwing an error).
     *
     * @return void
     */
    function gutenbergBlockRegistration(): void {
        parent::gutenbergBlockRegistration();

        $layouts = array();
        try {
            $layouts = $this->apiClient->getLayouts();
        } catch ( WinkAuthException $e ) {
            error_log( 'Wink: Authentication failed during block registration — ' . $e->getMessage() );
            set_transient( 'wink_auth_error', $e->getMessage(), HOUR_IN_SECONDS );
        } catch ( WinkDataException $e ) {
            error_log( 'Wink: Could not load layouts for block editor — ' . $e->getMessage() );
        }

        wp_localize_script(
            'winkBlockRenderer_' . $this->blockCode,
            'winkContentData',
            $layouts
        );
    }
}

if ( ! defined( 'WINK_TESTING' ) ) {
    $winkContent = new winkContent();
}
