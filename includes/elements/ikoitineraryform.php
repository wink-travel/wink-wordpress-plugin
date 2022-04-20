<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ikoItineraryForm extends ikoTravelElements {
    function __construct() {
        parent::__construct();
        $this->blockCode = 'ikoitineraryform';
        $this->blockName = esc_html__( "iko Itinerary Form", $this->namespace );
        add_action('init', array( $this,'gutenbergBlockRegistration' ) ); // Adding Gutenberg Block
        add_shortcode( $this->blockCode, array( $this,'blockHandler') );
        add_filter('ikoShortcodes',array( $this, 'shortcodeData') );
    }
    function shortcodeData($shortcodes) {
        $shortcodes[] = array(
            'code' => $this->blockCode,
            'name' => $this->blockName,
            'params' => array() 
        );
        return $shortcodes;
    }
    function blockHandler($atts) {
        $this->coreFunction();
        return $this->ikoTravelElement();
    }
    function ikoTravelElement() {
        ob_start();
        ?><iko-itinerary-form></iko-itinerary-form><?php
        $content = ob_get_contents();
        ob_end_clean();
        $isAdmin = false;
        if (!empty($_REQUEST['context']) && $_REQUEST['context'] == 'edit') {
            $isAdmin = true;
        }
        if (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'edit') {
            $isAdmin = true;
        }
        if (is_admin( ) || $isAdmin) { // in editor we should show the raw HTML to make it easier to click on
            return htmlspecialchars($content);
        }
        return $content;
    }

    function gutenbergBlockRegistration() {
        // Skip block registration if Gutenberg is not enabled/merged.
        if (!function_exists('register_block_type')) {
            return;
        }
        
        $dir = dirname(__FILE__);

        $gutenbergJS = $this->blockCode.'.js';
        wp_register_script('ikoTravelBlockRenderer_'.$this->blockCode, $this->pluginURL . 'elements/js/'.$gutenbergJS,
            array(
                'wp-blocks',
                'wp-i18n',
                'wp-element',
                'wp-components',
                'wp-editor'
            ),
            false
        );

        $jsData = array(
            'blockCat'  => $this->namespace.'-blocks',
            'imgURL'    => $this->imgURL,
            'mode'      => $this->environmentVal
        );

        wp_localize_script( 'ikoTravelBlockRenderer_'.$this->blockCode, 'ikoTravelData', $jsData );
        
        register_block_type('ikotravel-blocks/'.$this->blockCode, array(
            'editor_script' => 'ikoTravelBlockRenderer_'.$this->blockCode,
            'render_callback' => array($this,'blockHandler'),
            'attributes' => [
                // 'configurationId' => [
                //     'default' => '',
                //     'type' => 'string'
                // ]
            ],
            'category' => $this->namespace.'-blocks'
        ));
    }
}

$ikoItineraryForm = new ikoItineraryForm();
