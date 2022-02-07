<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class vcElements extends ikoTravelElements {
    function __construct() {
        add_action('init', array($this, 'checkIfEnabled'));
    }
    function checkIfEnabled() {
        if( defined( 'WPB_VC_VERSION' ) ) {
            parent::__construct();
            add_action( 'vc_before_init', array( $this, 'initVC' ));
            add_action( 'vc_before_init', array( $this, 'initElements' ));
        }
    }
    function initVC() {
        if (function_exists('vc_add_shortcode_param')) {
            vc_add_shortcode_param( 'ikoText', array($this,'settingsText') );
        }
    }
    function settingsText( $settings, $value ) {
        return '';
    }
    function initElements() {
        if (function_exists('vc_map')) {
            $shortcodes = array();
            $shortcodes = apply_filters( 'ikoShortcodes', $shortcodes);
            foreach ($shortcodes as $key => $shortcodeData) {
                $params = $shortcodeData['params'];
                if (empty($params)) {
                    $params = array(
                        array(
                            "type" => "ikoText",
                            "class" => "",
                            "param_name" => "placeholder",
                            "value" => 1,
                            "heading" => __( "This component does not require any configuration.", $this->namespace ),
                            "description" => __( "Simply ensure that you have entered the correct Client-ID and Client-Secret ", $this->namespace ) . ' <a href="'.admin_url( '/customize.php?autofocus[section]=ikoTravel').'" title="'.__('iko.travel settings',$this->namespace).'" target="_blank">'.
                            __('here',$this->namespace).'</a> '
                        )
                    );
                }
                vc_map( array(
                    "name" => $shortcodeData['name'],
                    "base" => $shortcodeData['code'],
                    "class" => "",
                    "category" => __( "Content", $this->namespace),
                    "icon" => $this->imgURL.'logo.png',
                    "params" => $params
                ));
            }
        }
    }
}

$vsElements = new vcElements();
