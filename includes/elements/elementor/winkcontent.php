<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class elementorWinkContent extends \Elementor\Widget_Base {
	public function get_name() {
		return 'winkcontent';
	}
	public function get_title() {
		return __( 'wink Content', "wink" );
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
				'label' => esc_html__( 'wink Options', "wink" )
			]
		);
		$shortcodes = array();
		$shortcodes = apply_filters( 'winkShortcodes', $shortcodes);
		if (!empty($shortcodes['winkcontent'])) {
			$options = array();
			
			foreach($shortcodes['winkcontent']['params'][0]['value'] as $optionKey => $optionValue) {
				$options[$optionValue] = $optionKey;
			}
			$this->add_control(
				'layoutid',
				[
					'label' => 'Inventory',
					'type' => \Elementor\Controls_Manager::SELECT,
					'placeholder' => '',
					'options' => $options,
					'description' => esc_html__('Select any of your saved inventories. We strongly recommend to use this block only in full-width content areas and not in columns.', "wink" ),
				]
			);
		}

		$this->end_controls_section();

	}
	protected function render() {
		$settings = $this->get_settings_for_display();		
		echo wp_kses(do_shortcode('[winkcontent layoutid="'.esc_html($settings['layoutid']).'"]'));
		
	}
}
