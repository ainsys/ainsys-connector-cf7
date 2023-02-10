<?php

namespace Ainsys\Connector\WPCF7\Settings;

use Ainsys\Connector\Master\Helper;
use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Plugin_Common;
use Ainsys\Connector\Master\Settings\Admin_UI_Entities_Checking;
use Ainsys\Connector\Master\Settings\Settings;
use Ainsys\Connector\Master\UTM_Handler;
use Ainsys\Connector\WPCF7\WP\Process_Form;
use WPCF7_ContactForm;

class Admin_UI implements Hooked {

	use Plugin_Common;

	static public string $entity = 'cf7';


	public function init_hooks() {

		add_filter( 'wpcf7_spam', '__return_false' );
		add_filter( 'ainsys_status_list', [ $this, 'add_status_of_component' ], 10, 1 );
		add_filter( 'ainsys_get_entities_list', [ $this, 'add_entity_to_list' ], 10, 1 );
		add_filter( 'ainsys_check_connection_request', [ $this, 'check_entity' ], 15, 3 );
		//add_action( 'wpcf7_init', array( $this, 'register_service' ), 15, 0 );

		add_filter( 'wpcf7_form_hidden_fields', [ $this, 'add_hidden_fields' ], 100, 1 );

	}


	public function add_entity_to_list( $entities_list ) {

		$entities_list[ self::$entity ] = __( 'CF7', AINSYS_CONNECTOR_TEXTDOMAIN );

		return $entities_list;

	}


	/**
	 * @param                                                               $result_entity
	 * @param                                                               $entity
	 * @param  \Ainsys\Connector\Master\Settings\Admin_UI_Entities_Checking $entities_checking
	 *
	 * @return mixed
	 */
	public function check_entity( $result_entity, $entity, Admin_UI_Entities_Checking $entities_checking ) {

		if ( $entity !== self::$entity ) {
			return $result_entity;
		}

		$result_test                     = $this->get_posted_data();
		$entities_checking->make_request = true;
		$result_entity                   = Settings::get_option( 'check_connection_entity' );

		return $entities_checking->get_result_entity( $result_test, $result_entity, $entity );

	}


	public function get_posted_data(): array {

		$forms = Helper::get_rand_posts( 'wpcf7_contact_form' );

		if ( empty( $forms ) ) {
			return [
				'request'  => __( 'Error: There is no data to check.', AINSYS_CONNECTOR_TEXTDOMAIN ),
				'response' => __( 'Error: There is no data to check.', AINSYS_CONNECTOR_TEXTDOMAIN ),
			];
		}

		$form_id = array_shift( $forms );

		$args = $this->get_form_fields( $form_id );

		return ( new Process_Form )->process_checking( (int) $form_id, $args );
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


	public function add_hidden_fields( $fields ): array {

		return array_merge(
			$fields,
			[
				'wpcf7_ainsys_referrer'   => UTM_Handler::get_referer_url(),
				'wpcf7_ainsys_user_agent' => UTM_Handler::get_user_agent(),
				'wpcf7_ainsys_ip'         => UTM_Handler::get_my_ip(),
				'wpcf7_ainsys_roistat'    => UTM_Handler::get_roistat(),
			]
		);
	}


	public function add_status_of_component( $status_items = [] ) {

		$status_items['wpcf7'] = [
			'title'   => __( 'Contact Form 7', AINSYS_CONNECTOR_TEXTDOMAIN ), // phpcs:ignore
			'slug'    => 'contact-form-7',
			'active'  => $this->is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ),
			'install' => $this->is_plugin_install( 'contact-form-7/wp-contact-form-7.php' ),
		];

		return $status_items;
	}


	/**
	 * @param $form_id
	 *
	 * @return string
	 */
	protected function get_url( $form_id ): string {

		$url_args = sprintf( '/contact-form-7/v1/contact-forms/%s/feedback', $form_id );

		if ( Helper::is_localhost() ) {
			$url = 'https://stage3.ainsys.a2hosted.com/wp-json' . $url_args;
		} else {
			$url = rest_url( $url_args );
		}

		return $url;
	}


	/**
	 * @param $form_id
	 *
	 * @return array
	 */
	protected function get_form_fields( $form_id ): array {

		$contact_form = WPCF7_ContactForm::get_instance( $form_id );
		$form_fields  = $contact_form->scan_form_tags();

		$filtered_fields = [];

		foreach ( $form_fields as $field ) {
			if ( false !== strpos( $field['type'], '*' ) ) {

				$filtered_fields[] = [
					'name'     => $field['name'],
					'basetype' => $field['basetype'],

				];
			}
			if ( $field['type'] === 'acceptance' ) {

				$filtered_fields[] = [
					'name'     => $field['name'],
					'basetype' => $field['basetype'],

				];
			}

		}

		$args = [];

		foreach ( $filtered_fields as $filtered_field ) {

			if ( $filtered_field['basetype'] === 'email' ) {
				$args[ $filtered_field['name'] ] = 'ainsys_test@check.com';

			} elseif ( $filtered_field['basetype'] === 'tel' ) {
				$args[ $filtered_field['name'] ] = '+44 7911 123456';

			} elseif ( false !== strpos( $filtered_field['name'], 'tel' ) || false !== strpos( $filtered_field['name'], 'phone' ) ) {
				$args[ $filtered_field['name'] ] = '+44 7911 123456';

			} elseif ( $filtered_field['basetype'] === 'text' ) {
				$args[ $filtered_field['name'] ] = 'ainsys_test text field';

			} elseif ( $filtered_field['basetype'] === 'textarea' ) {
				$args[ $filtered_field['name'] ] = 'ainsys_test textarea field';

			} elseif ( $filtered_field['basetype'] === 'acceptance' ) {
				$args[ $filtered_field['name'] ] = 'on';
			}
		}

		return $args;
	}


	/**
	 * @param  string $url
	 * @param  array  $args
	 *
	 * @return bool|string
	 */
	protected function remote_post( string $url, array $args ) {

		$curl = curl_init();

		curl_setopt_array( $curl, [
			CURLOPT_POST           => true,
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_POSTFIELDS     => $args,
			CURLOPT_HTTPHEADER     => [
				'user-agent: WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				'Content-Type: multipart/form-data',
			],
		] );

		$response = curl_exec( $curl );

		curl_close( $curl );

		return $response;
	}

}