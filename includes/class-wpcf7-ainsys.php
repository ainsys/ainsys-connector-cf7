<?php

namespace Ainsys\Connector\WPCF7;

if ( ! class_exists( 'WPCF7_Service' ) ) {
	return;
}

class WPCF7_Ainsys extends \WPCF7_Service {

	public function get_title() {
		return 'Ainsys';
	}

	public function get_categories() {
		return array( 'ainsys' );
	}

	public function icon() {
	}

	public function display( $action = '' ) {
		echo '<p>' . sprintf(
				esc_html( __( 'Ainsys позволяет интегрировать данные, поступающие через формы Contact Form 7 в вашу экосистему', 'contact-form-7' ) ),
				wpcf7_link(
					__( 'https://contactform7.com/recaptcha/', 'contact-form-7' ),
					__( 'reCAPTCHA (v3)', 'contact-form-7' )
				)
			) . '</p>';

		if ( $this->is_active() ) {
			echo sprintf(
				'<p class="dashicons-before dashicons-yes">%s</p>',
				esc_html( __( "Ainsys включен на этом сайте.", 'contact-form-7' ) )
			);
		}

//		if ( 'setup' == $action ) {
//			$this->display_setup();
//		} else {
//			echo sprintf(
//				'<p><a href="%1$s" class="button">%2$s</a></p>',
//				esc_url( $this->menu_page_url( 'action=setup' ) ),
//				esc_html( __( 'Setup Integration', 'contact-form-7' ) )
//			);
//		}
	}

	public function is_active() {
		return true;
	}

	private function display_setup() {
		$sitekey = $this->is_active() ? $this->get_sitekey() : '';
		$secret  = $this->is_active() ? $this->get_secret( $sitekey ) : '';

		?>
		<form method="post" action="<?php
		echo esc_url( $this->menu_page_url( 'action=setup' ) ); ?>">
			<?php
			wp_nonce_field( 'wpcf7-recaptcha-setup' ); ?>
			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row"><label for="sitekey"><?php
							echo esc_html( __( 'Site Key', 'contact-form-7' ) ); ?></label></th>
					<td><?php
						if ( $this->is_active() ) {
							echo esc_html( $sitekey );
							echo sprintf(
								'<input type="hidden" value="%1$s" id="sitekey" name="sitekey" />',
								esc_attr( $sitekey )
							);
						} else {
							echo sprintf(
								'<input type="text" aria-required="true" value="%1$s" id="sitekey" name="sitekey" class="regular-text code" />',
								esc_attr( $sitekey )
							);
						}
						?></td>
				</tr>
				<tr>
					<th scope="row"><label for="secret"><?php
							echo esc_html( __( 'Secret Key', 'contact-form-7' ) ); ?></label></th>
					<td><?php
						if ( $this->is_active() ) {
							echo esc_html( wpcf7_mask_password( $secret, 4, 4 ) );
							echo sprintf(
								'<input type="hidden" value="%1$s" id="secret" name="secret" />',
								esc_attr( $secret )
							);
						} else {
							echo sprintf(
								'<input type="text" aria-required="true" value="%1$s" id="secret" name="secret" class="regular-text code" />',
								esc_attr( $secret )
							);
						}
						?></td>
				</tr>
				</tbody>
			</table>
			<?php
			if ( $this->is_active() ) {
				if ( $this->get_global_sitekey() && $this->get_global_secret() ) {
					// nothing
				} else {
					submit_button(
						_x( 'Remove Keys', 'API keys', 'contact-form-7' ),
						'small', 'reset'
					);
				}
			} else {
				submit_button( __( 'Save Changes', 'contact-form-7' ) );
			}
			?>
		</form>
		<?php
	}

	protected function menu_page_url( $args = '' ) {
		$args = wp_parse_args( $args, array() );

		$url = menu_page_url( 'wpcf7-integration', false );
		$url = add_query_arg( array( 'service' => 'recaptcha' ), $url );

		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		return $url;
	}

}
