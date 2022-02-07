<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class elementorIkoAccount extends \Elementor\Widget_Base {
	protected $namespace = 'iko-travel';
	public function get_name() {
		return 'ikoaccount';
	}
	public function get_title() {
		return __( 'iko Account', $this->namespace );
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
				'label' => __( 'iko Options', $this->namespace )
			]
		);

		$this->add_control(
			'hey',
			[
				'label' => '',
				'type' => 'ikoDefault',
				'placeholder' => ''
			]
		);

		$this->end_controls_section();

	}
	protected function render() {

		$settings = $this->get_settings_for_display();
		echo do_shortcode('[ikoaccount]');
		
	}
}
