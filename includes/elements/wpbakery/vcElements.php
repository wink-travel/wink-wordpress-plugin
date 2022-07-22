<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class winkVCElements extends winkElements {
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
            vc_add_shortcode_param( 'winkText', array($this,'settingsText') );
        }
    }
    function settingsText( $settings, $value ) {
        return '';
    }
    function initElements() {
        if (function_exists('vc_map')) {
            $shortcodes = array();
            $shortcodes = apply_filters( 'winkShortcodes', $shortcodes);
            foreach ($shortcodes as $key => $shortcodeData) {
                $params = $shortcodeData['params'];
                if (empty($params)) {
                    $params = array(
                        array(
                            "type" => "winkText",
                            "class" => "",
                            "param_name" => "placeholder",
                            "value" => 1,
                            "heading" => esc_html__( "This component does not require any configuration.", $this->namespace ),
                            "description" => esc_html__( "Simply ensure that you have entered the correct Client-ID and Client-Secret ", $this->namespace ) . ' <a href="'.admin_url( '/customize.php?autofocus[section]=wink').'" title="'.esc_html__('WINK settings',$this->namespace).'" target="_blank">'.
                            esc_html__('here',$this->namespace).'</a> '
                        )
                    );
                }
                vc_map( array(
                    "name" => $shortcodeData['name'],
                    "base" => $shortcodeData['code'],
                    "class" => "",
                    "category" => esc_html__( "Content", $this->namespace),
                    "icon" => $this->imgURL.'logo.png',
                    "params" => $params
                ));
            }
        }
    }
}

$winkVCElements = new winkVCElements();
