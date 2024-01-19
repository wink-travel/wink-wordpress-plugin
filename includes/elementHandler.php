<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!array_key_exists('winkLoaderAlreadyEnqueued',$GLOBALS)) {
    $GLOBALS['winkLoaderAlreadyEnqueued'] = false;
}

class winkElements {
    protected $clientIdKey = 'winkClientId';
    protected $clientSecretKey = 'winkSecret';
    function __construct() {
        $this->pluginURL = trailingslashit( plugin_dir_url( __FILE__ ) );
        $this->imgURL = trailingslashit( dirname( plugin_dir_url( __FILE__ ) ) ) . 'img/';
        $this->environmentVal = get_option('winkEnvironment', 'production');
    }

    function coreFunction() {
        add_action('wp_footer',array($this,'coreComponent'));
    }
    function coreComponent() {
        if ($GLOBALS['winkLoaderAlreadyEnqueued'] == false) {
            $html = '';
            $clientId = get_option($this->clientIdKey, false);
            
            echo'<wink-app-loader config=\'{"clientId":"'.esc_html($clientId).'"}\'></wink-app-loader>';
            $GLOBALS['winkLoaderAlreadyEnqueued'] = true;
        }
        return $GLOBALS['winkLoaderAlreadyEnqueued'];
    }
}

require_once('elements/winklookup.php'); // Lookup element
require_once('elements/winkitinerary.php'); // Itinerary button element
require_once('elements/winkitineraryform.php'); // Itinerary form element
require_once('elements/winksearch.php'); // Search button element
require_once('elements/winkaccount.php'); // Account button element
require_once('elements/winkcontent.php'); // Content element

require_once('elements/wpbakery/vcElements.php'); // WPBakery Page Builder
require_once('elements/elementor/elementorWidgets.php'); // Elementor
require_once('elements/avada/fusionElements.php'); // Avada / Fusion Builder
