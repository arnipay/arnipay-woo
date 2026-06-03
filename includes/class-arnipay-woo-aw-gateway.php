<?php
/**
 * Arnipay Payment Gateway Class
 *
 * @package Arnipay_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Arnipay WooCommerce Payment Gateway
 *
 * Handles payment processing through Arnipay gateway.
 */
class Arnipay_Woo_AW_Gateway extends WC_Payment_Gateway {

	/**
	 * Arnipay Client ID.
	 *
	 * @var string|null
	 */
	protected ?string $client_id;

	/**
	 * Arnipay Client Secret.
	 *
	 * @var string|null
	 */
	protected ?string $client_secret;

	/**
	 * Enabled Payment Methods.
	 *
	 * @var array|null
	 */
	protected ?array $payment_methods;

	/**
	 * Arnipay Webhook Secret.
	 *
	 * @var string|null
	 */
	protected ?string $webhook_secret;

	/**
	 * Debug mode flag.
	 *
	 * @var string
	 */
	protected string $debug;

	/**
	 * Order resolver and reconciliation helper.
	 *
	 * Pure-domain class extracted from the gateway. All order lookups,
	 * identifier comparisons, amount checks and webhook bookkeeping are
	 * delegated to it. Tested independently in tests/unit/Order_Resolver_Test.
	 *
	 * @var Arnipay_Woo_AW_Order_Resolver
	 */
	protected Arnipay_Woo_AW_Order_Resolver $resolver;

	/**
	 * Checkout view renderer (extracted in 1.0.28).
	 *
	 * @var Arnipay_Woo_AW_Checkout_View
	 */
	protected Arnipay_Woo_AW_Checkout_View $checkout_view;

	/**
	 * Admin panel renderer (extracted in 1.0.28).
	 *
	 * @var Arnipay_Woo_AW_Admin_Renderer
	 */
	protected Arnipay_Woo_AW_Admin_Renderer $admin_renderer;

	/**
	 * Verification AJAX service (extracted in 1.0.28).
	 *
	 * @var Arnipay_Woo_AW_Verification_Service
	 */
	protected Arnipay_Woo_AW_Verification_Service $verification_service;


	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'arnipay_woo_aw';
		$this->icon               = arnipay_woo_aw()->plugin_url . 'assets/img/logo.svg';
		$this->has_fields         = true;
		$this->method_title       = 'arnipay';
		$this->method_description = 'Pago a través de arnipay';
		$this->order_button_text  = __( 'Pagar con arnipay', 'arnipay-woo' );
		$this->supports           = array(
			'products',
			'refunds',
		);
		$this->countries          = array(
			'PY',
		);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title           = Arnipay_Woo_AW_Checkout_View::clean_customer_text( $this->get_option( 'title', __( 'arnipay', 'arnipay-woo' ) ), 80 );
		$this->description     = Arnipay_Woo_AW_Checkout_View::clean_customer_text( $this->get_option( 'description', __( 'Seleccioná arnipay y elegí cómo querés pagar: Personal Pay, QR o tigo money.', 'arnipay-woo' ) ), 260, true );
		$this->enabled         = $this->get_option( 'enabled' );
		$this->client_id       = $this->get_option( 'client_id' );
		$this->client_secret   = $this->get_option( 'client_secret' );
		$this->payment_methods = (array) $this->get_option( 'payment_methods', array() );
		$this->webhook_secret  = $this->get_option( 'webhook_secret' );
		$this->debug           = $this->get_option( 'debug' );
		$this->order_button_text = __( 'Pagar con arnipay', 'arnipay-woo' );

		// Wire up the order resolver. It needs the gateway ID for ownership
		// checks and a callback to recompute the expected reference for an
		// order when meta is missing (very-fast-webhook fallback).
		$this->resolver = new Arnipay_Woo_AW_Order_Resolver(
			$this->id,
			function ( WC_Order $order ): string {
				return $this->build_order_reference( $order );
			},
			'yes' === $this->debug
				? static function ( string $message ): void { arnipay_woo_aw()->log( $message ); }
				: null
		);

		// Delegations extracted in 1.0.28. The gateway keeps thin wrappers
		// for the WC-registered callbacks (generate_*_html, ajax_verify_*,
		// payment_fields, validate_fields, get_icon) so existing hooks keep
		// firing — they all forward to these helpers.
		$this->checkout_view        = new Arnipay_Woo_AW_Checkout_View( $this );
		$this->admin_renderer       = new Arnipay_Woo_AW_Admin_Renderer( $this );
		$this->verification_service = new Arnipay_Woo_AW_Verification_Service( $this, 'yes' === $this->debug );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( 'Arnipay_Woo_AW', 'reset' ) );
		add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'confirmation_ipn' ) );

		// AJAX handlers for the admin verification buttons.
		add_action( 'wp_ajax_arnipay_woo_aw_verify_credentials', array( $this, 'ajax_verify_credentials' ) );
		add_action( 'wp_ajax_arnipay_woo_aw_verify_webhook', array( $this, 'ajax_verify_webhook' ) );

		// The customer is redirected directly to arnipay. Webhooks are still the
		// only trusted source for changing the WooCommerce order state.

		// Filters.
		add_filter( 'woocommerce_settings_api_form_fields_' . $this->id, array( $this, 'add_additional_settings' ) );
	}

	/**
	 * Get the public webhook (IPN) URL for this site.
	 *
	 * This is the URL the merchant must register in their Arnipay panel.
	 *
	 * @return string Absolute webhook URL.
	 */
	public function get_webhook_url(): string {
		return add_query_arg( 'wc-api', strtolower( get_class( $this ) ), home_url( '/' ) );
	}

	/**
	 * Initialize gateway form fields.
	 */
	public function init_form_fields(): void {
		$this->form_fields = require __DIR__ . '/admin/settings.php';
	}

	/**
	 * Check if gateway needs setup.
	 *
	 * @return bool True if it needs setup, false otherwise.
	 */
	public function needs_setup(): bool {
		return ! $this->is_available();
	}

	/**
	 * Check if this gateway is available based on configuration.
	 *
	 * @return bool True if available, false otherwise.
	 */
	public function is_available(): bool {
		if ( ! parent::is_available() ||
			! $this->client_id ||
			! $this->client_secret ||
			! $this->webhook_secret ) {
			return false;
		}
		return true;
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array Result with redirect URL.
	 */
	public function process_payment( $order_id ): array {
		$fail = array(
			'result'   => 'fail',
			'redirect' => '',
		);

		// Use wc_get_order() for HPOS compatibility instead of new WC_Order().
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			wc_add_notice( __( 'Error al procesar el pago, por favor intente nuevamente.', 'arnipay-woo' ), 'error' );
			return $fail;
		}

		$arnipay = Arnipay_Woo_AW::get_instance();

		if ( ! $arnipay ) {
			wc_add_notice( __( 'Error al procesar el pago, por favor intente nuevamente.', 'arnipay-woo' ), 'error' );
			return $fail;
		}

		// Sanity check: arnipay cannot process a zero or negative amount.
		$order_total = (float) $order->get_total();
		if ( $order_total <= 0 ) {
			wc_add_notice( __( 'El monto del pedido no es válido para arnipay.', 'arnipay-woo' ), 'error' );
			return $fail;
		}

		$selected_customer_method = $this->checkout_view->get_selected_customer_method_from_request();
		if ( '' === $selected_customer_method || ! in_array( $selected_customer_method, $this->checkout_view->get_available_customer_method_codes(), true ) ) {
			wc_add_notice( __( 'Seleccioná un método de pago válido dentro de arnipay.', 'arnipay-woo' ), 'error' );
			return $fail;
		}

		$reference       = $this->build_order_reference( $order );
		$url             = '';
		$link_id         = '';
		$previous_status = $order->get_status();
		$status_changed  = false;

		/*
		 * Prepare the order BEFORE creating the payment link, but only through
		 * metadata/status operations that cannot later overwrite a webhook result.
		 *
		 * This fixes both race windows:
		 *  - The webhook can resolve the order immediately by reference.
		 *  - If the webhook completes the order while the HTTP request is still
		 *    returning, the code below never saves a stale WC_Order object over it.
		 */
		$order->delete_meta_data( '_arnipay_payment_id' );
		$order->delete_meta_data( '_arnipay_payment_id_lc' );
		$order->delete_meta_data( '_arnipay_link_id' );
		$order->delete_meta_data( '_arnipay_link_id_lc' );
		$order->delete_meta_data( '_arnipay_payment_url' );
		$order->delete_meta_data( '_arnipay_processed_webhooks' );
		$order->update_meta_data( '_arnipay_reference', $reference );
		$order->update_meta_data( '_arnipay_expected_amount', wc_format_decimal( $order_total ) );
		$order->update_meta_data( '_arnipay_selected_method', $selected_customer_method );
		$order->save_meta_data();

		// If this is a retry from failed/cancelled/on-hold, move it to pending
		// before arnipay can send any webhook. Do not touch status after the link
		// is created, because a fast webhook may already have completed the order.
		if ( ! $order->is_paid() && ! $order->has_status( 'pending' ) ) {
			$order->update_status( 'pending', __( 'Esperando confirmación de pago de arnipay.', 'arnipay-woo' ) );
			$status_changed = true;
		}

		try {
			$builder = $arnipay->payment()
				->title( "Pedido #{$order->get_order_number()}" )
				->amount( $order_total )
				->description( "Pago del pedido #{$order->get_order_number()} en " . get_bloginfo( 'name' ) )
				->reference( $reference )
				->redirect( $order->get_checkout_order_received_url(), wc_get_checkout_url() );

			// Restrict arnipay checkout to the real method selected by the customer.
			$builder->allow( array( $selected_customer_method ) );

			$link    = $builder->create();
			$url     = isset( $link['url'] ) ? esc_url_raw( (string) $link['url'] ) : '';
			$link_id = isset( $link['id'] ) ? sanitize_text_field( (string) $link['id'] ) : '';
		} catch ( \Arnipay\Exception\GatewayException $e ) {
			$this->maybe_restore_status_after_payment_link_failure( $order_id, $previous_status, $status_changed );
			if ( 'yes' === $this->debug ) {
				arnipay_woo_aw()->log( 'Error al crear la transacción en arnipay: ' . $e->getMessage() );
			}
			wc_add_notice( __( 'Error al procesar el pago, por favor intente nuevamente.', 'arnipay-woo' ), 'error' );
			return $fail;
		} catch ( \Throwable $e ) {
			$this->maybe_restore_status_after_payment_link_failure( $order_id, $previous_status, $status_changed );
			if ( 'yes' === $this->debug ) {
				arnipay_woo_aw()->log( 'Error inesperado al crear la transacción: ' . $e->getMessage() );
			}
			wc_add_notice( __( 'Error al procesar el pago, por favor intente nuevamente.', 'arnipay-woo' ), 'error' );
			return $fail;
		}

		if ( empty( $url ) ) {
			$this->maybe_restore_status_after_payment_link_failure( $order_id, $previous_status, $status_changed );
			wc_add_notice( __( 'Error al procesar el pago, por favor intente nuevamente.', 'arnipay-woo' ), 'error' );
			return $fail;
		}

		/*
		 * Reload the order after the HTTP call. A very fast webhook may have already
		 * completed it. From this point on, only save metadata; never call save() on
		 * the stale order and never downgrade the status to pending.
		 */
		$fresh_order = wc_get_order( $order_id );
		if ( $fresh_order instanceof WC_Order ) {
			if ( '' !== $link_id ) {
				$fresh_order->update_meta_data( '_arnipay_link_id', $link_id );
				$fresh_order->update_meta_data( '_arnipay_link_id_lc', strtolower( $link_id ) );
			}

			if ( ! $fresh_order->is_paid() ) {
				$fresh_order->update_meta_data( '_arnipay_payment_url', esc_url_raw( $url ) );
			} else {
				$fresh_order->delete_meta_data( '_arnipay_payment_url' );
			}

			$fresh_order->save_meta_data();
		}

		/*
		 * Redirect the customer directly to the secure arnipay checkout page.
		 * This avoids popup/new-window blockers and keeps the sensitive payment
		 * experience hosted by the gateway instead of inside the merchant store.
		 */
		return array(
			'result'   => 'success',
			'redirect' => esc_url_raw( $url ),
		);
	}

	/**
	 * Restore a previous order status when creating the arnipay link fails.
	 *
	 * The status may have been moved to pending before the provider request so a
	 * webhook can never be overwritten later. If the request itself fails, restore
	 * the previous non-paid state to avoid leaving a failed/cancelled retry as
	 * pending without a valid payment URL.
	 *
	 * @param int    $order_id        WooCommerce order ID.
	 * @param string $previous_status Previous order status without wc- prefix.
	 * @param bool   $status_changed  Whether this attempt changed the status.
	 * @return void
	 */
	private function maybe_restore_status_after_payment_link_failure( int $order_id, string $previous_status, bool $status_changed ): void {
		if ( ! $status_changed || '' === $previous_status || 'pending' === $previous_status ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || $order->is_paid() ) {
			return;
		}

		$order->update_status(
			$previous_status,
			__( 'No se pudo iniciar el pago en arnipay. Se restauró el estado anterior de la orden.', 'arnipay-woo' )
		);
	}

	/**
	 * Get the payment method icon HTML (delegated to Checkout_View).
	 *
	 * @return string HTML markup.
	 */
	public function get_icon(): string {
		return $this->checkout_view->get_icon();
	}

	/**
	 * Render the checkout payment fields (delegated to Checkout_View).
	 *
	 * @return void
	 */
	public function payment_fields(): void {
		$this->checkout_view->render_payment_fields();
	}

	/**
	 * Validate the customer-selected method (delegated to Checkout_View).
	 *
	 * @return bool
	 */
	public function validate_fields(): bool {
		return $this->checkout_view->validate_customer_selection();
	}

	/*
	 * Checkout view helpers (get_img_url, normalize_payment_methods,
	 * clean_customer_text, get_selected_customer_method_from_request,
	 * get_available_customer_method_codes, get_checkout_methods_for_display,
	 * get_supported_method_catalog) were extracted to
	 * Arnipay_Woo_AW_Checkout_View in 1.0.28. Callers inside this class use
	 * the static helpers Arnipay_Woo_AW_Checkout_View::normalize_payment_methods
	 * and ::clean_customer_text directly.
	 */



	/**
	 * Build a merchant-scoped reference for an order.
	 *
	 * The prefix is derived from the Client ID, Client Secret and site URL using
	 * HMAC-SHA256. This avoids exposing credentials while making the reference
	 * different for each commerce. The order number is kept for traceability.
	 *
	 * Example: A7F3K-123-546234
	 *
	 * @param WC_Order $order Order being paid.
	 * @return string Merchant-scoped payment reference.
	 */
	private function build_order_reference( WC_Order $order ): string {
		$order_id     = (string) absint( $order->get_id() );
		$order_number = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $order->get_order_number() );

		if ( '' === $order_number || $order_number === $order_id ) {
			return $this->build_reference_prefix() . '-' . $order_id;
		}

		// Include the internal order ID to guarantee uniqueness even if a store
		// uses a custom order-number plugin that can repeat display numbers.
		return $this->build_reference_prefix() . '-' . $order_id . '-' . substr( $order_number, 0, 40 );
	}

	/**
	 * Build a stable 5-character merchant prefix from configured credentials.
	 *
	 * The secret is used as an HMAC key and is never written to the reference.
	 * Five base36 characters give a compact merchant prefix while the order
	 * number still makes every payment reference unique inside the store.
	 *
	 * @return string Uppercase alphanumeric merchant prefix.
	 */
	private function build_reference_prefix(): string {
		$client_id     = trim( (string) $this->client_id );
		$client_secret = trim( (string) $this->client_secret );

		if ( '' === $client_id || '' === $client_secret ) {
			return 'ARNPY';
		}

		$hash     = hash_hmac( 'sha256', $client_id . '|' . home_url( '/' ), $client_secret, true );
		$alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$prefix   = '';

		for ( $i = 0; $i < 5; $i++ ) {
			$prefix .= $alphabet[ ord( $hash[ $i ] ) % strlen( $alphabet ) ];
		}

		return $prefix;
	}


	/**
	 * Handle Arnipay webhook IPN confirmation.
	 *
	 * Thin delegation to Arnipay_Woo_AW_Webhook_Handler. Every security check
	 * (method, signature, anti-replay, client-id, idempotency) lives in the
	 * handler so it can be reasoned about and tested in isolation.
	 *
	 * @return void
	 */
	public function confirmation_ipn(): void {
		$handler = new Arnipay_Woo_AW_Webhook_Handler(
			(string) $this->client_id,
			(string) $this->webhook_secret,
			'yes' === $this->debug,
			$this->resolver
		);
		$handler->handle();
	}


	/*
	 * Webhook reconciliation methods (build_dedupe_key, get_order_from_event,
	 * get_order_from_reference, get_order_by_meta, event_identifiers_match_order,
	 * arnipay_identifier_equals, store_event_identifiers, amount_matches,
	 * clear_payment_url, remember_webhook) were extracted to
	 * Arnipay_Woo_AW_Order_Resolver — see includes/domain/ and the unit tests
	 * in tests/unit/Order_Resolver_Test. Callers delegate through $this->resolver.
	 */


	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'arnipay_refund', __( 'Orden no encontrada.', 'arnipay-woo' ) );
		}

		if ( null !== $amount ) {
			$refund_amount = (float) wc_format_decimal( (string) $amount );
			$order_total   = (float) wc_format_decimal( (string) $order->get_total() );

			if ( $refund_amount > 0 && $refund_amount + 1 < $order_total ) {
				return new WP_Error(
					'arnipay_refund_partial',
					__( 'El reembolso automático parcial no está soportado por esta integración. Procesa el reembolso parcial manualmente desde arnipay o solicita el reembolso total.', 'arnipay-woo' )
				);
			}
		}

		$transaction_id = (string) $order->get_meta( '_arnipay_payment_id', true );

		if ( '' === $transaction_id ) {
			$transaction_id = (string) $order->get_transaction_id();
		}

		$link_id   = (string) $order->get_meta( '_arnipay_link_id', true );
		$reference = (string) $order->get_meta( '_arnipay_reference', true );

		if ( '' === $transaction_id || 0 === strpos( $transaction_id, 'ORDER-' ) || ( '' !== $reference && hash_equals( $reference, $transaction_id ) ) || ( '' !== $link_id && hash_equals( $link_id, $transaction_id ) ) ) {
			return new WP_Error( 'arnipay_refund', __( 'No hay un ID de pago de arnipay válido asociado a esta orden. Revisa la orden o procesa el reembolso manualmente desde arnipay.', 'arnipay-woo' ) );
		}

		$arnipay = Arnipay_Woo_AW::get_instance();

		if ( ! $arnipay ) {
			return new WP_Error( 'arnipay_refund', __( 'No se pudo conectar con arnipay.', 'arnipay-woo' ) );
		}

		$reason = Arnipay_Woo_AW_Checkout_View::clean_customer_text( $reason, 180 );

		try {
			$arnipay->transaction()->reverse( (string) $transaction_id, $reason ?: null );
			$order->add_order_note(
				sprintf(
					/* translators: %s: refund reason */
					__( 'Reembolso solicitado a arnipay. Motivo: %s', 'arnipay-woo' ),
					$reason ?: __( 'no especificado', 'arnipay-woo' )
				)
			);
			return true;
		} catch ( \Throwable $e ) {
			if ( 'yes' === $this->debug ) {
				arnipay_woo_aw()->log( 'Error al reembolsar en arnipay: ' . $e->getMessage() );
			}
			return new WP_Error( 'arnipay_refund', __( 'Error al procesar el reembolso en arnipay.', 'arnipay-woo' ) );
		}
	}

	/**
	 * Add additional settings to the existing settings array.
	 *
	 * @since 1.0.0
	 * @param array $settings Existing settings array.
	 * @return array Modified settings array.
	 */
	public function add_additional_settings( array $settings ): array {
		$additional_settings = array();

		if ( empty( $this->settings ) ) {
			return $settings;
		}

		$additional_settings = require __DIR__ . '/admin/other_settings.php';

		$settings = array_merge( $settings, $additional_settings );

		// Always reflect the real webhook URL, never a stale saved value.
		if ( isset( $settings['webhook_url'] ) ) {
			$settings['webhook_url']['default'] = $this->get_webhook_url();
		}

		// Append the verification panel after the webhook section.
		$settings['verification'] = array(
			'title'       => __( 'Verificación de la configuración', 'arnipay-woo' ),
			'type'        => 'arnipay_verification',
			'description' => __( 'Comprueba que las credenciales y el webhook estén correctamente configurados antes de recibir pagos reales.', 'arnipay-woo' ),
		);

		return apply_filters( 'arnipay_woo_aw_payment_settings', $settings );
	}

	/**
	 * Render the admin header (delegated to Admin_Renderer).
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 * @return string HTML markup.
	 */
	public function generate_arnipay_admin_header_html( string $key, array $data ): string {
		return $this->admin_renderer->admin_header( $key, $data );
	}

	/**
	 * Render a section title separator (delegated to Admin_Renderer).
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 * @return string HTML markup.
	 */
	public function generate_arnipay_section_title_html( string $key, array $data ): string {
		return $this->admin_renderer->section_title( $key, $data );
	}

	/**
	 * Render the verification panel (delegated to Admin_Renderer).
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 * @return string HTML markup.
	 */
	public function generate_arnipay_verification_html( string $key, array $data ): string {
		return $this->admin_renderer->verification_panel( $key, $data );
	}

	/**
	 * No-op validation for the visual-only section title field.
	 *
	 * @return string
	 */
	public function validate_arnipay_section_title_field( string $key, $value ): string {
		return Arnipay_Woo_AW_Admin_Renderer::noop_field();
	}

	/**
	 * No-op validation for the visual-only admin header field.
	 *
	 * @return string
	 */
	public function validate_arnipay_admin_header_field( string $key, $value ): string {
		return Arnipay_Woo_AW_Admin_Renderer::noop_field();
	}

	/**
	 * No-op validation for the visual-only verification field.
	 *
	 * @return string
	 */
	public function validate_arnipay_verification_field( string $key, $value ): string {
		return Arnipay_Woo_AW_Admin_Renderer::noop_field();
	}


	/**
	 * AJAX: verify the API credentials (delegated to Verification_Service).
	 *
	 * @return void
	 */
	public function ajax_verify_credentials(): void {
		$this->verification_service->ajax_verify_credentials();
	}

	/**
	 * AJAX: verify the webhook configuration (delegated to Verification_Service).
	 *
	 * @return void
	 */
	public function ajax_verify_webhook(): void {
		$this->verification_service->ajax_verify_webhook();
	}


}
