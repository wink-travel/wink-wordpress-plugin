<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class fusionElements extends ikoTravelElements {
    function __construct() {
        add_action('init', array($this, 'checkIfEnabled'));
    }
    function checkIfEnabled() {
            parent::__construct();
            add_action( 'fusion_builder_before_init', array( $this, 'initElements' ));
        
    }
    function initElements() {
        if (function_exists('fusion_builder_map')) {
            $shortcodes = array();
            $shortcodes = apply_filters( 'ikoShortcodes', $shortcodes);
            foreach ($shortcodes as $key => $shortcodeData) {
                $params = $shortcodeData['params'];
                if (!empty($params)) {
                    foreach($params as $paramKey => $param) {
                        if (!empty($param['type']) && $param['type'] == 'dropdown') {
                            $params[$paramKey]['type'] = 'select';
                        }
                    }
                }
                fusion_builder_map( array(
                    "name" => $shortcodeData['name'],
                    "shortcode" => $shortcodeData['code'],
                    "class" => "",
                    "category" => esc_html__( "Content", $this->namespace),
                    "icon" => 'fusion-module-icon fusiona-widget',
                    "params" => $params
                ));
            }
        }
    }
}

$vsElements = new vcElements();
