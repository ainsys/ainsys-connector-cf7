<?php

namespace Ainsys\Connector\WPCF7;

use Ainsys\Connector\Master\Logger;
use Ainsys\Connector\Master\Plugin_Common;
use Ainsys\Connector\Master\Core;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\UTM_Handler;

class Plugin implements Hooked {

	use Plugin_Common;

	/**
	 * @var WPCF7_Ainsys;
	 */
	private $wpcf7_service;

	/**
	 * Injected dependency.
	 * @var UTM_Handler
	 */
	private $utm_handler;

	/**
	 * Injected dependency.
	 * @var Core
	 */
	private $core;

	/**
	 * @var Logger
	 */
	private $logger;

	public function __construct( Core $core, UTM_Handler $utm_handler, Logger $logger, WPCF7_Ainsys $wpcf7_service ) {

		$this->core        = $core;
		$this->utm_handler = $utm_handler;
		$this->logger      = $logger;

		$this->init_plugin_metadata();

		/**
		 * As it's used only inside this plugin and we'll not use it somewhere else just create it here,
		 * if we'd need to reuse it in some other plugin, we'd need to pass in DI container to it.// TODO think about it more.
		 */
		$this->wpcf7_service = new WPCF7_Ainsys();

	}


	/**
	 * Links all logic to WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {

		add_action( 'wpcf7_init', array( $this, 'register_service' ), 15, 0 );

		add_filter( 'wpcf7_form_hidden_fields', array( $this, 'add_hidden_fields' ), 100, 1 );

		add_action( 'wpcf7_submit', array( $this, 'on_wpcf7_submit' ), 10, 2 );

		add_filter( 'ainsys_status_list', array( $this, 'add_status_of_component' ), 10, 1 );

	}

	public function register_service() {
		if ( class_exists( '\WPCF7_Integration' ) ) {
			$integration = \WPCF7_Integration::get_instance();

			$integration->add_category(
				'ainsys',
				__( 'AINSYS', 'contact-form-7' )
			);

			$integration->add_service(
				'ainsys',
				$this->wpcf7_service
			);
		}

	}

	public function add_hidden_fields( $fields ) {
		$service = $this->wpcf7_service;

		if ( ! $service->is_active() ) {
			return $fields;
		}

		return array_merge(
			$fields,
			array(
				'_wpcf7_ainsys_referrer'   => $this->utm_handler::get_referer_url(),
				'_wpcf7_ainsys_user_agent' => $this->utm_handler::get_user_agent(),
				'_wpcf7_ainsys_ip'         => $this->utm_handler::get_my_ip(),
				'_wpcf7_ainsys_roistat'    => $this->utm_handler::get_roistat(),
			)
		);
	}

	public function on_wpcf7_submit( \WPCF7_ContactForm $wpcf7, $result = array() ) {

		if ( 'mail_sent' !== $result['status'] ) {
			return $wpcf7;
		}

		$form_id = $wpcf7->id();

		//      $fields = $wpcf7->scan_form_tags();
		//
		//      foreach ( $fields as $key => $field ) {
		//          if ( 'submit' === $field['basetype'] ) {
		//              unset( $fields[ $key ] );
		//          }
		//      }
		//
		//      $fields = array_values( $fields );

		$request_action = 'UPDATE';

		$request_data = array(
			'entity'  => array(
				'id'   => time(),
				'name' => 'wpcf7',
			),
			'action'  => $request_action,
			'payload' => array_merge( array( 'id' => time() ), $_POST ),
		);

		try {
			$server_response = $this->core->curl_exec_func( $request_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
			$this->core->send_error_email( $server_response );
		}

		$this->logger->save( $form_id, $request_action, serialize( $request_data ), serialize( $server_response ), 0 );

		return $wpcf7;
	}

	public function add_status_of_component( $status_items = array() ) {

		$status_items['wpcf7'] = array(
			'title'  => __( 'Contact Form 7', AINSYS_CONNECTOR_TEXTDOMAIN ), // phpcs:ignore
			'active' => $this->is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ),
		);

		return $status_items;
	}

}
