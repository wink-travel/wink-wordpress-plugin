<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class elementorWinkSearch extends \Elementor\Widget_Base {
	public function get_name() {
		return 'winksearch';
	}
	public function get_title() {
		return esc_html__( 'Wink Search', "wink2travel" );
	}
	public function get_icon() {
		return 'eicon-external-link-square';
	}
	public function get_categories() {
		return [ 'general' ];
	}
	protected function _register_controls() {

		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Wink Options', "wink2travel" )
			]
		);

		$this->add_control(
			'hey',
			[
				'label' => '',
				'type' => 'winkDefault',
				'placeholder' => ''
			]
		);

		$this->end_controls_section();

	}
	protected function render() {

		$settings = $this->get_settings_for_display();
		echo do_shortcode('[winksearch]');
		
	}
}
