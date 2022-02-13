<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class elementorIkoContent extends \Elementor\Widget_Base {
	protected $namespace = 'iko-travel';
	public function get_name() {
		return 'ikocontent';
	}
	public function get_title() {
		return __( 'iko Content', $this->namespace );
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
		$shortcodes = array();
		$shortcodes = apply_filters( 'ikoShortcodes', $shortcodes);
		if (!empty($shortcodes['ikocontent'])) {
			$options = array();
			
			foreach($shortcodes['ikocontent']['params'][0]['value'] as $optionKey => $optionValue) {
				$options[$optionValue] = $optionKey;
			}
			$this->add_control(
				'layoutid',
				[
					'label' => 'Inventory',
					'type' => \Elementor\Controls_Manager::SELECT,
					'placeholder' => '',
					'options' => $options,
					'description' => __('Select any of your saved inventories. We strongly recommend to use this block only in full-width content areas and not in columns.', $this->namespace ),
				]
			);
		}

		$this->end_controls_section();

	}
	protected function render() {
		$settings = $this->get_settings_for_display();		
		echo do_shortcode('[ikocontent layoutid="'.$settings['layoutid'].'"]');
		
	}
}
