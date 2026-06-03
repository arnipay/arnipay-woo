<?php
/**
 * Verification service: maneja los botones del admin que validan que las
 * credenciales y el webhook estén configurados correctamente.
 *
 * Dos endpoints AJAX, ambos restringidos a usuarios con manage_woocommerce y
 * protegidos por nonce. Ningún dato sensible se devuelve en los mensajes de
 * error (anti-enumeración).
 *
 * @package Arnipay_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arnipay_Woo_AW_Verification_Service {

	private WC_Payment_Gateway $gateway;
	private bool $debug;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway $gateway Gateway instance — sólo para get_option/get_webhook_url.
	 * @param bool               $debug   Whether debug logging is on.
	 */
	public function __construct( WC_Payment_Gateway $gateway, bool $debug ) {
		$this->gateway = $gateway;
		$this->debug   = $debug;
	}

	/**
	 * Read a credential from the POSTed form (unsaved) or fall back to the
	 * stored option. Used so the admin can verify without saving first.
	 *
	 * @param string $posted_key Key in $_POST.
	 * @param string $option_key Key in the gateway settings.
	 * @return string
	 */
	private function resolve_credential( string $posted_key, string $option_key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked by caller.
		if ( isset( $_POST[ $posted_key ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = sanitize_text_field( wp_unslash( $_POST[ $posted_key ] ) );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return (string) $this->gateway->get_option( $option_key );
	}

	/**
	 * Verify common preconditions for both verification endpoints.
	 *
	 * Sends a JSON error and exits when the request is not authorized.
	 *
	 * @return void
	 */
	private function assert_admin_request(): void {
		check_ajax_referer( 'arnipay_woo_aw_verify', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'arnipay-woo' ) ),
				403
			);
		}
	}

	/**
	 * Conditional debug logger.
	 *
	 * @param string $message Plain text.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( $this->debug && function_exists( 'arnipay_woo_aw' ) ) {
			arnipay_woo_aw()->log( $message );
		}
	}

	/**
	 * AJAX endpoint: verify the API credentials.
	 *
	 * Hits arnipay's /payment_methods endpoint with the entered credentials.
	 *
	 * @return void
	 */
	public function ajax_verify_credentials(): void {
		$this->assert_admin_request();

		$client_id     = $this->resolve_credential( 'client_id', 'client_id' );
		$client_secret = $this->resolve_credential( 'client_secret', 'client_secret' );

		if ( '' === $client_id || '' === $client_secret ) {
			wp_send_json_error(
				array( 'message' => __( 'Completa el ID del Cliente y la Clave secreta antes de verificar.', 'arnipay-woo' ) )
			);
		}

		$arnipay = Arnipay_Woo_AW::build_client( $client_id, $client_secret );

		if ( ! $arnipay ) {
			wp_send_json_error(
				array( 'message' => __( 'No se pudieron preparar las credenciales.', 'arnipay-woo' ) )
			);
		}

		try {
			$methods = $arnipay->getPaymentMethods();
			$count   = is_array( $methods ) ? count( $methods ) : 0;

			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d: number of payment methods */
						__( 'Credenciales válidas. arnipay respondió correctamente (%d medios de pago disponibles).', 'arnipay-woo' ),
						$count
					),
				)
			);
		} catch ( \Arnipay\Exception\GatewayException $e ) {
			$code = (int) $e->getStatusCode();

			if ( 401 === $code || 403 === $code ) {
				$message = __( 'Las credenciales son incorrectas. Revisa el ID del Cliente y la Clave secreta.', 'arnipay-woo' );
			} else {
				$message = sprintf(
					/* translators: %d: HTTP status code */
					__( 'arnipay devolvió un error (código %d). Revisa la configuración o vuelve a intentar.', 'arnipay-woo' ),
					$code
				);
			}

			$this->log( 'Verificación de credenciales fallida: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => $message ) );
		} catch ( \Throwable $e ) {
			$this->log( 'Error inesperado al verificar credenciales: ' . $e->getMessage() );
			wp_send_json_error(
				array( 'message' => __( 'No se pudo contactar con arnipay. Revisa la conexión del servidor.', 'arnipay-woo' ) )
			);
		}
	}

	/**
	 * AJAX endpoint: verify the webhook configuration.
	 *
	 * Sends a signed POST to this site's public webhook endpoint using the
	 * saved Client ID and Webhook Secret to confirm the route, HMAC and
	 * verification handler work with the active config.
	 *
	 * @return void
	 */
	public function ajax_verify_webhook(): void {
		$this->assert_admin_request();

		$client_id      = trim( (string) $this->gateway->get_option( 'client_id' ) );
		$webhook_secret = trim( (string) $this->gateway->get_option( 'webhook_secret' ) );

		if ( '' === $client_id || '' === $webhook_secret ) {
			wp_send_json_error(
				array( 'message' => __( 'Guardá primero el Client ID y el Webhook Secret antes de verificar el webhook.', 'arnipay-woo' ) )
			);
		}

		// Warn if there are unsaved changes — the endpoint uses stored values.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked above.
		$posted_client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted_secret = isset( $_POST['webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) : '';

		if ( ( '' !== $posted_client_id && ! hash_equals( $client_id, $posted_client_id ) ) || ( '' !== $posted_secret && ! hash_equals( $webhook_secret, $posted_secret ) ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Hay cambios sin guardar en el Client ID o Webhook Secret. Guardá los cambios y luego volvé a verificar el webhook.', 'arnipay-woo' ) )
			);
		}

		$webhook_url = $this->get_webhook_url();
		$payload     = wp_json_encode(
			array(
				'event'     => 'arnipay.verification',
				'timestamp' => gmdate( 'c' ),
				'data'      => array(
					'reference' => 'ARNIPAY-VERIFICATION',
				),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		if ( ! is_string( $payload ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No se pudo preparar el payload de prueba del webhook.', 'arnipay-woo' ) )
			);
		}

		$timestamp = (string) time();
		$path      = wp_parse_url( $webhook_url, PHP_URL_PATH );
		$uri       = '/' . ltrim( is_string( $path ) ? $path : '/', '/' );
		$query     = wp_parse_url( $webhook_url, PHP_URL_QUERY );
		if ( $query ) {
			$uri .= '?' . $query;
		}

		try {
			$signature_service = new \Arnipay\Gateway\SignatureService();
			$signature         = $signature_service->generate(
				'POST',
				$uri,
				(int) $timestamp,
				$client_id,
				$webhook_secret,
				$payload
			);

			$response = wp_remote_post(
				$webhook_url,
				array(
					'timeout'     => 15,
					'redirection' => 0,
					'sslverify'   => true,
					'headers'     => array(
						'Content-Type' => 'application/json',
						'X-Client-ID'  => $client_id,
						'X-Timestamp'  => $timestamp,
						'X-Signature'  => $signature,
						'X-Webhook-ID' => 'verify-' . wp_generate_uuid4(),
					),
					'body'        => $payload,
				)
			);

			if ( is_wp_error( $response ) ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %s: error detail */
							__( 'No se pudo contactar la URL del webhook: %s', 'arnipay-woo' ),
							$response->get_error_message()
						),
					)
				);
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

			if ( $code >= 200 && $code < 300 ) {
				wp_send_json_success(
					array(
						'message' => __( 'Webhook verificado correctamente. La URL respondió a un POST firmado con la configuración activa del sitio.', 'arnipay-woo' ),
					)
				);
			}

			$remote_message = is_array( $body ) && isset( $body['message'] ) ? sanitize_text_field( (string) $body['message'] ) : '';
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: HTTP code, 2: remote message */
						__( 'El webhook respondió con código %1$d. %2$s', 'arnipay-woo' ),
						$code,
						$remote_message ?: __( 'Verifica que la URL pública, el Client ID y el Webhook Secret estén guardados correctamente.', 'arnipay-woo' )
					),
				)
			);
		} catch ( \Throwable $e ) {
			$this->log( 'Error al verificar el webhook: ' . $e->getMessage() );
			wp_send_json_error(
				array( 'message' => __( 'No se pudo completar la verificación del webhook.', 'arnipay-woo' ) )
			);
		}
	}

	/**
	 * Helper: webhook URL for the gateway (delegates to the gateway).
	 *
	 * @return string
	 */
	private function get_webhook_url(): string {
		if ( method_exists( $this->gateway, 'get_webhook_url' ) ) {
			return (string) $this->gateway->get_webhook_url();
		}
		return add_query_arg( 'wc-api', strtolower( get_class( $this->gateway ) ), home_url( '/' ) );
	}
}
