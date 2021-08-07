<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!array_key_exists('ikoTravelLoaderAlreadyEnqueued',$GLOBALS)) {
    $GLOBALS['ikoTravelLoaderAlreadyEnqueued'] = false;
}

class ikoTravelElements {
    protected $namespace = 'iko-travel';
    protected $clientIdKey = 'ikoTravelClientId';
    protected $clientSecretKey = 'ikoTravelSecret';
    function __construct() {
        // $this->namespace = 'iko-travel';
        $this->pluginURL = trailingslashit( plugin_dir_url( __FILE__ ) );
        $this->imgURL = trailingslashit( dirname( plugin_dir_url( __FILE__ ) ) ) . 'img/';
        $this->environmentVal = get_option('ikoEnvironment', false);
    }

    function coreFunction() {
        add_action('wp_footer',array($this,'coreComponent'));
    }
    function coreComponent() {
        if ($GLOBALS['ikoTravelLoaderAlreadyEnqueued'] == false) {
            $html = '';
            $clientId = get_option($this->clientIdKey, false);
            
            $html .= '<iko-app-loader config=\'{"client-id":"'.$clientId.'"}\'></iko-app-loader>';

            echo $html;
            $GLOBALS['ikoTravelLoaderAlreadyEnqueued'] = true;
        }
        return $GLOBALS['ikoTravelLoaderAlreadyEnqueued'];
    }
}

require_once('elements/ikolookup.php'); // Lookup element
require_once('elements/ikoitinerary.php'); // Itinerary button element
require_once('elements/ikosearch.php'); // Search button element
require_once('elements/ikoaccount.php'); // Account button element
require_once('elements/ikocontent.php'); // Content element
