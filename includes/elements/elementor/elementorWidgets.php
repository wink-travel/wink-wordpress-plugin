<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class winkElementorWidgets extends winkElements {
    function __construct() {
        add_action('init', array($this, 'checkIfEnabled'));

        add_action( 'elementor/controls/controls_registered', [ $this, 'register_controls' ] );
    }
    public function register_controls() {
        require_once('elementorControls.php');
		$controls_manager = \Elementor\Plugin::$instance->controls_manager;
		$controls_manager->register_control( 'winkDefault', new winkDefault() );

	}
    function checkIfEnabled() {
        if( defined( 'ELEMENTOR_VERSION' ) ) {
            parent::__construct();
            add_action( 'elementor/widgets/widgets_registered', function() {


                require_once('winkaccount.php');
                require_once('winkitinerary.php');
                require_once('winklookup.php');
                require_once('winksearch.php');   
                require_once('winkcontent.php');
                require_once('winkitineraryform.php');          
                // Let Elementor know about our widget
                Elementor\Plugin::instance()->widgets_manager->register_widget_type( new elementorWinkAccount() );
                Elementor\Plugin::instance()->widgets_manager->register_widget_type( new elementorWinkitinerary() );
                Elementor\Plugin::instance()->widgets_manager->register_widget_type( new elementorWinkLookup() );
                Elementor\Plugin::instance()->widgets_manager->register_widget_type( new elementorWinkSearch() );
                Elementor\Plugin::instance()->widgets_manager->register_widget_type( new elementorWinkContent() );

            });
        }
    }
}

$winkElementorWidgets = new winkElementorWidgets();