<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ikoDefault extends \Elementor\Base_Control {
	public $namespace;
	public function get_type() {
		return 'ikoDefault';
	}

	public function content_template() {
		$this->namespace = 'iko-travel';
		?>
		<div class="elementor-control-field">
			<div class="elementor-control-input-wrapper"><b><?= __( "This component does not require any configuration.", $this->namespace ); ?></b></div>
		</div>
		<div class="elementor-control-field">
			<div class="elementor-control-input-wrapper"><?=__( "Simply ensure that you have entered the correct Client-ID and Client-Secret ", $this->namespace ) . ' <a href="'.admin_url( '/customize.php?autofocus[section]=ikoTravel').'" title="'.__('iko.travel settings',$this->namespace).'" target="_blank">'.__('here',$this->namespace).'</a>'; ?> </div>
		</div>
		<?php
	}

}