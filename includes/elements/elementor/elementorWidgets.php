<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class elementorWidgets extends ikoTravelElements {
    function __construct() {
        add_action('init', array($this, 'checkIfEnabled'));

        add_action( 'elementor/controls/controls_registered', [ $this, 'register_controls' ] );
    }
    public function register_controls() {
        require_once('elementorControls.php');
		$controls_manager = \Elementor\Plugin::$instance->controls_manager;
		$controls_manager->register_control( 'ikoDefault', new ikoDefault() );

	}
    function checkIfEnabled() {
        if( defined( 'ELEMENTOR_VERSION' ) ) {
            parent::__construct();
            add_action( 'elementor/widgets/widgets_registered', function() {


                require_once('ikoaccount.php');
                require_once('ikoitinerary.php');
                require_once('ikolookup.php');
                require_once('ikosearch.php');   
                require_once('ikocontent.php');
                require_once('ikoitineraryform.php');          
                // Let Elementor know about our widget
                Elementor\Plugin::instance()->widgets_manager->register_widget_type( new elementorIkoAccount() );
                Elementor\Plugin::instance()->widgets_manager->register_widget_type( new elementorIkoitinerary() );
                Elementor\Plugin::instance()->widgets_manager->register_widget_type( new elementorIkoLookup() );
                Elementor\Plugin::instance()->widgets_manager->register_widget_type( new elementorIkoSearch() );
                Elementor\Plugin::instance()->widgets_manager->register_widget_type( new elementorIkoContent() );

            });
        }
    }
}

$elementorWidgets = new elementorWidgets();