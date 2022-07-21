<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class winkAccount extends winkElements {
    function __construct() {
        parent::__construct();
        $this->blockCode = 'winkaccount';
        $this->blockName = esc_html__( "wink Account", $this->namespace );
        add_action('init', array( $this,'gutenbergBlockRegistration' ) ); // Adding Gutenberg Block
        add_shortcode( $this->blockCode, array( $this,'blockHandler') );
        add_filter('winkShortcodes',array( $this, 'shortcodeData') );
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
        return $this->winkElement();
    }
    function winkElement() {
        ob_start();
        ?><iko-account-button></iko-account-button><?php
        $content = ob_get_contents();
        ob_end_clean();
        $isAdmin = false;
        if (!empty($_REQUEST['context']) && $_REQUEST['context'] == 'edit') {
            $isAdmin = true;
        }
        if (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'edit') {
            $isAdmin = true;
        }
        if (is_admin( ) || $isAdmin) {
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
        wp_register_script('winkBlockRenderer_'.$this->blockCode, $this->pluginURL . 'elements/js/'.$gutenbergJS,
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

        wp_localize_script( 'winkBlockRenderer_'.$this->blockCode, 'winkData', $jsData );
        
        register_block_type('wink-blocks/'.$this->blockCode, array(
            'editor_script' => 'winkBlockRenderer_'.$this->blockCode,
            'render_callback' => array($this,'blockHandler'),
            'attributes' => [],
            'category' => $this->namespace.'-blocks'
        ));
    }
}

$winkAccount = new winkAccount();
