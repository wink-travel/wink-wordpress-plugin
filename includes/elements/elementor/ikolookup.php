<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class elementorIkoLookup extends \Elementor\Widget_Base {
	protected $namespace = 'iko-travel';
	public function get_name() {
		return 'ikolookup';
	}
	public function get_title() {
		return esc_html__( 'iko Lookup', $this->namespace );
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
				'label' => esc_html__( 'iko Options', $this->namespace )
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
		echo do_shortcode('[ikolookup]');
		
	}
}
