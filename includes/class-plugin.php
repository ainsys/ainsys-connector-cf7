<?php

namespace Ainsys\Connector\WPCF7;

use Ainsys\Connector\Master\DI_Container;
use Ainsys\Connector\Master\Plugin_Common;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\WPCF7\Settings\Admin_UI;
use Ainsys\Connector\WPCF7\WP\Process_Form;

class Plugin implements Hooked {

	use Plugin_Common;

	/**
	 * @var \Ainsys\Connector\Master\DI_Container
	 */
	public DI_Container $di_container;


	public function __construct() {

		$this->init_plugin_metadata();

		$this->di_container = DI_Container::get_instance();

		$this->components['process_form'] = $this->di_container->resolve( Process_Form::class );
		$this->components['admin_ui']     = $this->di_container->resolve( Admin_UI::class );

	}


	/**
	 * Links all logic to WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {

		foreach ( $this->components as $component ) {
			if ( $component instanceof Hooked ) {
				$component->init_hooks();
			}
		}
	}

}
