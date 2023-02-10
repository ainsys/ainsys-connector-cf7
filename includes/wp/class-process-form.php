<?php

namespace Ainsys\Connector\WPCF7\WP;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\WP\Process;
use Ainsys\Connector\Master\Conditions;
use WPCF7_ContactForm;
use WPCF7_Submission;

class Process_Form extends Process implements Hooked {

	protected static string $entity = 'cf7';


	/**
	 * Initializes WordPress hooks for plugin/components.
	 *
	 * @return void
	 */
	public function init_hooks() {

		add_action( 'wpcf7_mail_sent', [ $this, 'process_update' ], 10, 1 );

	}


	public function on_wpcf7_submit( \WPCF7_ContactForm $wpcf7, $result = [] ) {

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

		$request_data = [
			'entity'  => [
				'id'   => time(),
				'name' => 'wpcf7',
			],
			'action'  => $request_action,
			'payload' => array_merge( [ 'id' => time() ], $_POST ),
		];

		try {
			$server_response = $this->core->curl_exec_func( $request_data );
		} catch ( \Exception $e ) {
			$server_response = 'Error: ' . $e->getMessage();
			$this->core->send_error_email( $server_response );
		}

		$this->logger->save( $form_id, $request_action, serialize( $request_data ), serialize( $server_response ), 0 );

		return $wpcf7;
	}


	/**
	 * Sends updated post details to AINSYS.
	 *
	 * @param  \WPCF7_ContactForm $contact_form
	 */
	public function process_update( WPCF7_ContactForm $contact_form ): void {

		self::$action = 'UPDATE';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return;
		}

		$form_id = $contact_form->id();

		$posted_data = WPCF7_Submission::get_instance()->get_posted_data();

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $form_id, $posted_data ),
			$form_id
		);

		$this->send_data( $form_id, $this->get_entity_name( $form_id ), self::$action, $fields );
	}


	/**
	 * Sends updated post details to AINSYS.
	 *
	 * @param $form_id
	 * @param $posted_data
	 *
	 * @return array
	 */
	public function process_checking( $form_id, $posted_data ): array {

		self::$action = 'CHECKING';

		if ( Conditions::has_entity_disable( self::$entity, self::$action ) ) {
			return [];
		}

		$fields = apply_filters(
			'ainsys_process_update_fields_' . self::$entity,
			$this->prepare_data( $form_id, $posted_data ),
			$form_id
		);

		return $this->send_data( $form_id, $this->get_entity_name( $form_id ), self::$action, $fields );
	}


	/**
	 * @param $form_id
	 * @param $posted_data
	 *
	 * @return array
	 */
	public function prepare_data( $form_id, $posted_data ): array {

		return array_merge(
			[
				'form_id'   => $form_id,
				'form_name' => $this->get_form_name( $form_id ),
			],
			$posted_data
		);

	}


	/**
	 * @param $form_id
	 *
	 * @return string
	 */
	protected function get_form_name( $form_id ): string {

		return get_post( $form_id )->post_name;
	}


	/**
	 * @param $form_id
	 *
	 * @return string
	 */
	protected function get_entity_name( $form_id ): string {

		return sprintf( '%s-%s-%s', self::$entity, $form_id, $this->get_form_name( $form_id ) );
	}

}