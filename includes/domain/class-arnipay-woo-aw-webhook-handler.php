<?php
/**
 * Webhook IPN handler — receives the signed event from arnipay, validates it,
 * resolves the corresponding order, and dispatches state changes through the
 * Order_Resolver.
 *
 * All HTTP-facing security checks (HTTP method, content length, HMAC
 * signature, anti-replay, client-id, idempotency, error response shape) live
 * here. Order state mutation is delegated to the resolver so the unit tests
 * can cover the reconciliation logic without a real WP runtime.
 *
 * @package Arnipay_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless-ish webhook handler. Constructed once per request by the gateway,
 * which injects the configured credentials and the resolver.
 */
class Arnipay_Woo_AW_Webhook_Handler {

	/**
	 * Maximum age (in seconds) accepted for a webhook timestamp.
	 *
	 * Webhooks older than this are rejected as possible replay attacks.
	 */
	public const WEBHOOK_MAX_AGE = 900;

	/**
	 * Maximum raw webhook body size accepted by this public endpoint.
	 *
	 * Limits low-cost DoS attempts that send very large unsigned payloads
	 * before the HMAC validation can run.
	 */
	public const WEBHOOK_MAX_BODY_BYTES = 262144;

	private string $client_id;
	private string $webhook_secret;
	private bool $debug;
	private Arnipay_Woo_AW_Order_Resolver $resolver;

	/**
	 * Constructor.
	 *
	 * @param string                        $client_id      Configured merchant client ID.
	 * @param string                        $webhook_secret Configured webhook secret.
	 * @param bool                          $debug          Whether debug logging is enabled.
	 * @param Arnipay_Woo_AW_Order_Resolver $resolver       Resolver instance.
	 */
	public function __construct( string $client_id, string $webhook_secret, bool $debug, Arnipay_Woo_AW_Order_Resolver $resolver ) {
		$this->client_id      = $client_id;
		$this->webhook_secret = $webhook_secret;
		$this->debug          = $debug;
		$this->resolver       = $resolver;
	}

	/**
	 * Entry point called from `woocommerce_api_*`.
	 *
	 * @return void
	 */
	public function handle(): void {
		// Reject any method other than POST. A webhook is always a POST.
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: '';

		if ( 'POST' !== $method ) {
			$this->respond( 'error', 'Método no permitido', 405 );
			return;
		}

		if ( '' === $this->webhook_secret ) {
			$this->respond( 'error', 'Webhook no configurado', 503 );
			return;
		}

		$content_length = isset( $_SERVER['CONTENT_LENGTH'] )
			? absint( sanitize_text_field( wp_unslash( $_SERVER['CONTENT_LENGTH'] ) ) )
			: 0;

		if ( $content_length > self::WEBHOOK_MAX_BODY_BYTES ) {
			$this->respond( 'error', 'Payload demasiado grande', 413 );
			return;
		}

		$webhook = new \Arnipay\Gateway\Webhook( $this->webhook_secret );

		try {
			$raw_body = file_get_contents( 'php://input' );
			$raw_body = ( false === $raw_body ) ? '' : $raw_body;

			if ( strlen( $raw_body ) > self::WEBHOOK_MAX_BODY_BYTES ) {
				$this->respond( 'error', 'Payload demasiado grande', 413 );
				return;
			}

			$captured = $webhook->captureRequest( null, $raw_body );
			$event    = $webhook->handleRequest( null, $raw_body );

			$timestamp = (int) ( $captured['timestamp'] ?? 0 );
			$now       = time();

			if ( $timestamp <= 0 || abs( $now - $timestamp ) > self::WEBHOOK_MAX_AGE ) {
				$this->log( sprintf( 'Webhook rechazado por timestamp fuera de rango (ts=%d, now=%d).', $timestamp, $now ) );
				$this->respond( 'error', 'Solicitud expirada', 401 );
				return;
			}

			$webhook_client_id = isset( $captured['clientId'] ) ? (string) $captured['clientId'] : '';
			if ( '' === $this->client_id || '' === $webhook_client_id || ! hash_equals( $this->client_id, $webhook_client_id ) ) {
				$this->log( 'Webhook rechazado: X-Client-ID no coincide con la configuración del comercio.' );
				$this->respond( 'error', 'Solicitud rechazada', 401 );
				return;
			}

			$data       = ( isset( $event['data'] ) && is_array( $event['data'] ) ) ? $event['data'] : array();
			$event_type = isset( $event['event'] ) ? sanitize_text_field( (string) $event['event'] ) : '';
			$reference  = isset( $data['reference'] ) ? sanitize_text_field( (string) $data['reference'] ) : '';
			$link_id    = isset( $data['link_id'] ) ? sanitize_text_field( (string) $data['link_id'] ) : '';
			$payment_id = isset( $data['payment_id'] ) ? sanitize_text_field( (string) $data['payment_id'] ) : '';

			$this->log(
				sprintf(
					'Webhook recibido: evento=%s, referencia=%s, link_id=%s, payment_id=%s',
					$event_type ?: 'N/A',
					$reference ?: 'N/A',
					$link_id ?: 'N/A',
					$payment_id ?: 'N/A'
				)
			);

			// Verification ping: signature already validated, just ack.
			if ( 'arnipay.verification' === $event_type ) {
				$this->respond( 'success', 'Webhook verificado', 200 );
				return;
			}

			$order = $this->resolver->get_order_from_event( $event );
			if ( ! $order ) {
				$this->log( 'Webhook sin orden asociada para los identificadores recibidos.' );
				$this->respond( 'error', 'Solicitud no procesable', 422 );
				return;
			}

			$dedupe_key = Arnipay_Woo_AW_Order_Resolver::build_dedupe_key(
				$event_type,
				$data,
				$timestamp,
				$captured['signature'] ?? ''
			);

			$raw_processed = $order->get_meta( '_arnipay_processed_webhooks', true );
			$processed     = is_array( $raw_processed ) ? $raw_processed : array();

			if ( in_array( $dedupe_key, $processed, true ) ) {
				$this->respond( 'success', 'Evento ya procesado', 200 );
				return;
			}

			// Never downgrade an already-paid order via late webhooks belonging
			// to a previous attempt. Ack to stop provider retries.
			if ( $order->is_paid() ) {
				if ( 'payment.completed' !== $event_type ) {
					$order->add_order_note(
						sprintf(
							/* translators: %s: webhook event name */
							__( 'arnipay notificó el evento %s, pero la orden ya estaba pagada. No se modificó el estado.', 'arnipay-woo' ),
							$event_type ?: 'N/A'
						)
					);
				}
				$this->resolver->remember_webhook( $order, $dedupe_key );
				$this->respond( 'success', 'OK', 200 );
				return;
			}

			if ( ! $this->resolver->event_identifiers_match_order( $order, $reference, $link_id, $payment_id ) ) {
				$order->add_order_note( __( 'arnipay envió identificadores que no coinciden con los datos guardados de la orden. Webhook rechazado para revisión manual.', 'arnipay-woo' ) );
				$this->respond( 'error', 'Solicitud no procesable', 422 );
				return;
			}

			$this->resolver->store_event_identifiers( $order, $reference, $link_id, $payment_id, $event_type );

			$payment_method = isset( $data['payment_method'] )
				? strtoupper( sanitize_text_field( (string) $data['payment_method'] ) )
				: 'N/A';
			$display_reference = $reference ?: ( $link_id ?: 'N/A' );

			$this->dispatch_event( $order, $event_type, $event, $payment_id, $payment_method, $display_reference, $dedupe_key );

			$this->respond( 'success', 'OK', 200 );
		} catch ( \Arnipay\Exception\GatewayException $e ) {
			$this->log( 'Error al procesar el webhook de arnipay: ' . $e->getMessage() );
			$code = (int) $e->getCode();
			$code = ( $code >= 400 && $code < 600 ) ? $code : 400;
			$this->respond( 'error', 'Solicitud rechazada', $code );
		} catch ( \Throwable $e ) {
			$this->log( 'Error inesperado en el webhook de arnipay: ' . $e->getMessage() );
			$this->respond( 'error', 'Error interno', 500 );
		}
	}

	/**
	 * Build a safe multi-line WooCommerce order note.
	 *
	 * WooCommerce order notes render safe HTML in the admin timeline; using
	 * explicit <br> separators keeps payment details readable across statuses.
	 *
	 * @param array<int,string> $lines Note lines.
	 * @return string Safe HTML note content.
	 */
	private function format_order_note_lines( array $lines ): string {
		$lines = array_values(
			array_filter(
				array_map(
					static function ( $line ): string {
						return trim( (string) $line );
					},
					$lines
				),
				static function ( string $line ): bool {
					return '' !== $line;
				}
			)
		);

		return implode( '<br>', array_map( 'esc_html', $lines ) );
	}

	/**
	 * Apply order state changes for a given verified event type.
	 *
	 * @param WC_Order $order             Order being processed.
	 * @param string   $event_type        Verified event type.
	 * @param array    $event             Full event payload.
	 * @param string   $payment_id        Sanitized payment ID.
	 * @param string   $payment_method    Display name for the payment method.
	 * @param string   $display_reference Reference shown in notes.
	 * @param string   $dedupe_key        Idempotency key.
	 * @return void
	 */
	private function dispatch_event( WC_Order $order, string $event_type, array $event, string $payment_id, string $payment_method, string $display_reference, string $dedupe_key ): void {
		switch ( $event_type ) {
			case 'payment.completed':
				if ( ! $this->resolver->amount_matches( $order, $event ) ) {
					$order->update_status(
						'on-hold',
						__( 'arnipay reportó un pago completado sin monto o con un monto distinto al esperado. Revisión manual requerida.', 'arnipay-woo' )
					);
					$this->resolver->remember_webhook( $order, $dedupe_key );
					return;
				}

				if ( '' !== $payment_id ) {
					$order->payment_complete( $payment_id );
				} else {
					// Reference/link are reconciliation values, not the arnipay payment ID.
					$order->payment_complete();
				}

				$note_lines = array(
					__( 'Pago confirmado por arnipay.', 'arnipay-woo' ),
					sprintf(
						/* translators: %s: payment method */
						__( 'Método: %s.', 'arnipay-woo' ),
						$payment_method
					),
					sprintf(
						/* translators: %s: reference/link */
						__( 'Referencia/link: %s.', 'arnipay-woo' ),
						$display_reference
					),
				);

				if ( '' !== $payment_id ) {
					$note_lines[] = sprintf(
						/* translators: %s: payment id */
						__( 'ID de pago: %s.', 'arnipay-woo' ),
						$payment_id
					);
				}

				$order->add_order_note( $this->format_order_note_lines( $note_lines ) );

				$this->resolver->clear_payment_url( $order );
				$this->resolver->remember_webhook( $order, $dedupe_key );
				return;

			case 'payment.failed':
				$order->update_status(
					'failed',
					$this->format_order_note_lines(
						array(
							__( 'Pago fallido en arnipay.', 'arnipay-woo' ),
							sprintf(
								/* translators: %s: payment method */
								__( 'Método: %s.', 'arnipay-woo' ),
								$payment_method
							),
							sprintf(
								/* translators: %s: reference/link */
								__( 'Referencia/link: %s.', 'arnipay-woo' ),
								$display_reference
							),
						)
					)
				);
				$this->resolver->clear_payment_url( $order );
				$this->resolver->remember_webhook( $order, $dedupe_key );
				return;

			case 'payment.cancelled':
			case 'payment.canceled':
				$order->update_status(
					'cancelled',
					$this->format_order_note_lines(
						array(
							__( 'Pago cancelado en arnipay.', 'arnipay-woo' ),
							sprintf(
								/* translators: %s: payment method */
								__( 'Método: %s.', 'arnipay-woo' ),
								$payment_method
							),
							sprintf(
								/* translators: %s: reference/link */
								__( 'Referencia/link: %s.', 'arnipay-woo' ),
								$display_reference
							),
						)
					)
				);
				$this->resolver->clear_payment_url( $order );
				$this->resolver->remember_webhook( $order, $dedupe_key );
				return;

			case 'payment.pending':
				if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed', 'cancelled', 'failed' ) ) ) {
					$order->update_status(
						'on-hold',
						$this->format_order_note_lines(
							array(
								__( 'Pago pendiente en arnipay.', 'arnipay-woo' ),
								sprintf(
									/* translators: %s: payment method */
									__( 'Método: %s.', 'arnipay-woo' ),
									$payment_method
								),
								sprintf(
									/* translators: %s: reference/link */
									__( 'Referencia/link: %s.', 'arnipay-woo' ),
									$display_reference
								),
							)
						)
					);
				} else {
					$order->add_order_note(
						$this->format_order_note_lines(
							array(
								__( 'arnipay notificó estado pendiente.', 'arnipay-woo' ),
								sprintf(
									/* translators: %s: reference/link */
									__( 'Referencia/link: %s.', 'arnipay-woo' ),
									$display_reference
								),
							)
						)
					);
				}
				$this->resolver->remember_webhook( $order, $dedupe_key );
				return;

			default:
				$this->log( 'Evento de webhook no manejado: ' . $event_type );
				return;
		}
	}

	/**
	 * Send a generic JSON response and stop execution.
	 *
	 * Generic messages avoid leaking which orders exist (anti-enumeration).
	 *
	 * @param string $status  'success' or 'error'.
	 * @param string $message Short, generic message (no sensitive data).
	 * @param int    $code    HTTP status code.
	 * @return void
	 */
	private function respond( string $status, string $message, int $code ): void {
		wp_send_json(
			array(
				'status'  => $status,
				'message' => $message,
			),
			$code
		);
	}

	/**
	 * Conditional debug logger.
	 *
	 * @param string $message Plain text only.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( $this->debug && function_exists( 'arnipay_woo_aw' ) ) {
			arnipay_woo_aw()->log( $message );
		}
	}
}
