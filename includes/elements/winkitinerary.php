<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Wink Itinerary block element.
 *
 * Renders a <wink-itinerary-button> custom element that opens a user's saved
 * travel itinerary from the Wink affiliate platform.
 * Supports Gutenberg blocks, shortcodes, WPBakery, Elementor, and Avada.
 */
class winkItinerary extends winkElements {
    /**
     * Registers the Gutenberg block, shortcode, and Customizer data filter for this element.
     */
    function __construct() {
        parent::__construct();
        $this->blockCode = 'winkitinerary';
        $this->blockName = esc_html__( 'wink Itinerary Button', 'wink2travel' );
        add_action( 'init', array( $this, 'gutenbergBlockRegistration' ) );
        add_shortcode( $this->blockCode, array( $this, 'blockHandler' ) );
        add_filter( 'winkShortcodes', array( $this, 'shortcodeData' ) );
    }

    /**
     * Provides shortcode metadata for the Customizer settings panel and WPBakery.
     *
     * @param  array $shortcodes Existing shortcode definitions.
     * @return array Shortcode definitions with this element appended.
     */
    function shortcodeData( array $shortcodes ): array {
        $shortcodes[] = array(
            'code'   => $this->blockCode,
            'name'   => $this->blockName,
            'params' => array(),
        );
        return $shortcodes;
    }

    /**
     * Outputs the <wink-app-loader> footer component and renders the block HTML.
     * Used as the render_callback for register_block_type() and as the shortcode handler.
     *
     * @param  array|string $atts Block attributes or shortcode attributes (unused for this element).
     * @return string The rendered HTML for this element.
     */
    function blockHandler( $atts ): string {
        $this->coreFunction();
        return $this->winkElement();
    }

    /**
     * Returns the HTML for the <wink-itinerary-button> custom element.
     *
     * In the Gutenberg editor context the HTML is returned as an escaped string so the
     * block preview renders the tag text rather than trying to initialise the web component.
     *
     * @return string The element HTML.
     */
    function winkElement(): string {
        ob_start();
        ?><wink-itinerary-button></wink-itinerary-button><?php
        $content = (string) ob_get_clean();

        if ( $this->isEditorContext() ) {
            return htmlspecialchars( $content );
        }
        return $content;
    }
}

if ( ! defined( 'WINK_TESTING' ) ) {
    $winkItinerary = new winkItinerary();
}
