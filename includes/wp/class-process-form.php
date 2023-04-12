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


	/**
	 * Sends posted data details to AINSYS.
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

		$data = array_merge( $this->get_utm_tags( $posted_data ), $this->get_service_data( $posted_data ) );

		$posted_fields = array_merge( $this->get_posted_data( $form_id, $posted_data ), $data );

		return array_merge(
			[
				'id'        => hexdec( crc32( $form_id . $posted_fields['email']['n0']['VALUE'] ) ),
				'form_id'   => $form_id,
				'form_name' => $this->get_form_name( $form_id ),
			],
			$posted_fields
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


	/**
	 * @param $posted_data
	 *
	 * @return array
	 */
	protected function get_utm_tags( $posted_data ): array {

		$utm_tags = [];
		foreach ( $posted_data as $key => $val ) {
			if ( false !== strpos( $key, 'utm_' ) ) {
				$utm_tags[ $key ] = $this->sanitize_data( $val );
			}
		}

		return $utm_tags;
	}


	/**
	 * @param $posted_data
	 *
	 * @return array
	 */
	protected function get_service_data( $posted_data ): array {

		$service_data = [];
		foreach ( $posted_data as $key => $val ) {
			if ( false !== strpos( $key, 'wpcf7_' ) ) {
				$service_data[ $key ] = $this->sanitize_data( $val );
			}
		}

		return $service_data;
	}


	/**
	 * @param $form_id
	 * @param $posted_data
	 *
	 * @return array
	 */
	protected function get_posted_data( $form_id, $posted_data ): array {

		$filtered_fields = [];

		$contact_form = WPCF7_ContactForm::get_instance( $form_id );
		$form_fields  = $contact_form->scan_form_tags();

		foreach ( $form_fields as $field ) {
			if ( 'text' === $field['basetype'] && false !== strpos( $field['name'], 'name' ) ) {

				$filtered_fields['key_name'] = $field['name'];
			}

			if ( false !== strpos( $field['basetype'], 'email' ) ) {

				$filtered_fields['key_email'] = $field['name'];
			}

			if ( ( 'text' === $field['basetype'] || 'tel' === $field['basetype'] )
			     && ( false !== strpos( $field['name'], 'tel' ) || false !== strpos( $field['name'], 'phone' ) )
			) {
				$filtered_fields['key_phone'] = $field['name'];
			}

		}

		$posted_fields = [];

		if ( ! empty( $filtered_fields['key_name'] ) ) {
			$posted_fields['name'] = $this->sanitize_data( $posted_data[ $filtered_fields['key_name'] ] );
		}

		if ( ! empty( $filtered_fields['key_email'] ) ) {
			$posted_fields['email'] = [
				[
					'VALUE'      => $this->sanitize_data( $posted_data[ $filtered_fields['key_email'] ] ),
					'VALUE_TYPE' => 'WORK',
				],
			];
		}

		if ( ! empty( $filtered_fields['key_phone'] ) ) {
			$posted_fields['phone'] = [
				[
					'VALUE'      => $this->sanitize_data( $posted_data[ $filtered_fields['key_phone'] ] ),
					'VALUE_TYPE' => 'WORK',
				],
			];
		}

		return $posted_fields;
	}


	/**
	 * @param $posted_data
	 *
	 * @return string
	 */
	protected function sanitize_data( $posted_data ): string {

		return sanitize_text_field( trim( $posted_data ) );
	}

}