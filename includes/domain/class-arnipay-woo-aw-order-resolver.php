<?php
/**
 * Order resolution and reconciliation domain logic.
 *
 * Pure functions for looking up WooCommerce orders from arnipay webhook
 * identifiers, comparing identifiers safely, and persisting webhook results
 * idempotently. No HTTP, no rendering, no admin UI — just order data.
 *
 * @package Arnipay_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves WooCommerce orders from arnipay webhook data and maintains
 * webhook-related order metadata.
 */
class Arnipay_Woo_AW_Order_Resolver {

	/**
	 * Gateway ID used to filter order lookups.
	 *
	 * @var string
	 */
	private string $gateway_id;

	/**
	 * Optional logger callable. Receives a single string argument.
	 *
	 * @var callable|null
	 */
	private $logger;

	/**
	 * Callback that returns the expected reference for a given WC_Order.
	 *
	 * Used as a fallback when the meta lookup misses (e.g. very fast webhooks
	 * arriving before metadata is persisted). Receives a WC_Order and returns
	 * the reference string the gateway would have generated for it.
	 *
	 * @var callable|null
	 */
	private $reference_builder;

	/**
	 * Constructor.
	 *
	 * @param string        $gateway_id        WooCommerce gateway ID.
	 * @param callable|null $reference_builder Callable that returns the expected reference for a WC_Order.
	 * @param callable|null $logger            Optional logger callable.
	 */
	public function __construct( string $gateway_id, ?callable $reference_builder = null, ?callable $logger = null ) {
		$this->gateway_id        = $gateway_id;
		$this->reference_builder = $reference_builder;
		$this->logger            = $logger;
	}

	/**
	 * Resolve a WooCommerce order from a verified webhook event.
	 *
	 * Tries identifiers in order: reference, link_id, payment_id. Each lookup
	 * also tries the lowercase variant of the meta to tolerate provider
	 * casing changes.
	 *
	 * @param array $event Webhook event payload.
	 * @return WC_Order|null
	 */
	public function get_order_from_event( array $event ): ?WC_Order {
		$data = ( isset( $event['data'] ) && is_array( $event['data'] ) ) ? $event['data'] : array();

		$reference  = isset( $data['reference'] ) ? sanitize_text_field( (string) $data['reference'] ) : '';
		$link_id    = isset( $data['link_id'] ) ? sanitize_text_field( (string) $data['link_id'] ) : '';
		$payment_id = isset( $data['payment_id'] ) ? sanitize_text_field( (string) $data['payment_id'] ) : '';

		if ( '' !== $reference ) {
			$order = $this->get_order_from_reference( $reference );
			if ( $order instanceof WC_Order ) {
				return $order;
			}
		}

		if ( '' !== $link_id ) {
			$order = $this->get_order_by_meta( '_arnipay_link_id', $link_id );
			if ( ! $order instanceof WC_Order ) {
				$order = $this->get_order_by_meta( '_arnipay_link_id_lc', strtolower( $link_id ) );
			}
			if ( $order instanceof WC_Order ) {
				return $order;
			}
		}

		if ( '' !== $payment_id ) {
			$order = $this->get_order_by_meta( '_arnipay_payment_id', $payment_id );
			if ( ! $order instanceof WC_Order ) {
				$order = $this->get_order_by_meta( '_arnipay_payment_id_lc', strtolower( $payment_id ) );
			}
			if ( $order instanceof WC_Order ) {
				return $order;
			}
		}

		return null;
	}

	/**
	 * Resolve an order by reference string.
	 *
	 * Validates the reference format strictly, then looks up via meta. If the
	 * meta lookup misses, falls back to parsing the order ID from the
	 * structured reference and verifying it matches the expected reference for
	 * that order (using a constant-time comparison).
	 *
	 * @param string $reference Reference value (current or legacy format).
	 * @return WC_Order|null
	 */
	public function get_order_from_reference( string $reference ): ?WC_Order {
		$reference = sanitize_text_field( $reference );

		if ( ! preg_match( '/^[A-Za-z0-9\\-]{3,64}$/', $reference ) ) {
			return null;
		}

		$order = $this->get_order_by_meta( '_arnipay_reference', $reference );
		if ( $order instanceof WC_Order ) {
			return $order;
		}

		// Fast-webhook fallback: parse order ID from "PREFIX-id" or "PREFIX-id-num".
		if ( preg_match( '/^[A-Za-z0-9]{5}-([0-9]{1,20})(?:-[A-Za-z0-9\\-]{1,40})?$/', $reference, $matches ) ) {
			$order = wc_get_order( (int) $matches[1] );
			if ( $order instanceof WC_Order && $this->gateway_id === $order->get_payment_method() ) {
				if ( $this->reference_builder ) {
					$expected = (string) call_user_func( $this->reference_builder, $order );
					if ( '' !== $expected && hash_equals( $expected, $reference ) ) {
						return $order;
					}
				}
			}
		}

		// Legacy "ORDER-{id}" format compatibility.
		if ( preg_match( '/^ORDER-([A-Za-z0-9\\-]{1,40})$/', $reference ) ) {
			$order_id = (int) preg_replace( '/[^0-9]/', '', $reference );
			if ( $order_id > 0 ) {
				$order = wc_get_order( $order_id );
				if ( $order instanceof WC_Order && $this->gateway_id === $order->get_payment_method() ) {
					$stored_ref = (string) $order->get_meta( '_arnipay_reference', true );
					if ( '' === $stored_ref || $stored_ref === $reference ) {
						return $order;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Look up an order by a single meta key/value, restricted to this gateway.
	 *
	 * @param string $meta_key   Order meta key.
	 * @param string $meta_value Order meta value.
	 * @return WC_Order|null
	 */
	public function get_order_by_meta( string $meta_key, string $meta_value ): ?WC_Order {
		if ( '' === $meta_key || '' === $meta_value ) {
			return null;
		}

		$orders = wc_get_orders(
			array(
				'limit'          => 1,
				'meta_key'       => $meta_key,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'payment_method' => $this->gateway_id,
				'return'         => 'objects',
			)
		);

		if ( empty( $orders ) || ! $orders[0] instanceof WC_Order ) {
			return null;
		}

		return $orders[0];
	}

	/**
	 * Ensure signed webhook identifiers do not contradict the order metadata.
	 *
	 * Reference is compared case-sensitively (generated by this plugin); link
	 * and payment IDs are case-insensitive to tolerate provider UUID casing.
	 *
	 * @param WC_Order $order      Order resolved from the webhook.
	 * @param string   $reference  Incoming reference.
	 * @param string   $link_id    Incoming link ID.
	 * @param string   $payment_id Incoming payment ID.
	 * @return bool True when all non-empty identifiers are consistent.
	 */
	public function event_identifiers_match_order( WC_Order $order, string $reference, string $link_id, string $payment_id ): bool {
		$pairs = array(
			'_arnipay_reference'  => $reference,
			'_arnipay_link_id'    => $link_id,
			'_arnipay_payment_id' => $payment_id,
		);

		foreach ( $pairs as $meta_key => $incoming ) {
			// payment_id is only persisted on completed events, so before the
			// order is paid we have nothing to compare against.
			if ( '_arnipay_payment_id' === $meta_key && ! $order->is_paid() ) {
				continue;
			}

			$stored = (string) $order->get_meta( $meta_key, true );

			if ( '' !== $stored && '' !== $incoming && ! self::identifier_equals( $stored, $incoming, '_arnipay_reference' === $meta_key ) ) {
				if ( $this->logger ) {
					call_user_func( $this->logger, sprintf( 'Webhook rechazado: %s no coincide con la orden %d.', $meta_key, $order->get_id() ) );
				}
				return false;
			}
		}

		return true;
	}

	/**
	 * Compare two arnipay identifiers safely.
	 *
	 * Public + static so callers can use it without instantiating the resolver,
	 * and so it can be unit-tested directly without a WC_Order.
	 *
	 * @param string $stored         Stored identifier.
	 * @param string $incoming       Incoming identifier.
	 * @param bool   $case_sensitive Whether to compare as case-sensitive.
	 * @return bool
	 */
	public static function identifier_equals( string $stored, string $incoming, bool $case_sensitive = false ): bool {
		$stored   = trim( $stored );
		$incoming = trim( $incoming );

		if ( ! $case_sensitive ) {
			$stored   = strtolower( $stored );
			$incoming = strtolower( $incoming );
		}

		return hash_equals( $stored, $incoming );
	}

	/**
	 * Persist identifiers from a verified webhook on the order.
	 *
	 * Only fills empty slots — never overwrites an already-stored identifier.
	 * payment_id is only stored on `payment.completed` events.
	 *
	 * @param WC_Order $order      Order being processed.
	 * @param string   $reference  arnipay reference.
	 * @param string   $link_id    arnipay payment link ID.
	 * @param string   $payment_id arnipay payment/transaction ID.
	 * @param string   $event_type Webhook event type.
	 * @return void
	 */
	public function store_event_identifiers( WC_Order $order, string $reference, string $link_id, string $payment_id, string $event_type = '' ): void {
		$changed = false;

		if ( '' !== $reference && '' === (string) $order->get_meta( '_arnipay_reference', true ) ) {
			$order->update_meta_data( '_arnipay_reference', $reference );
			$changed = true;
		}

		if ( '' !== $link_id && '' === (string) $order->get_meta( '_arnipay_link_id', true ) ) {
			$order->update_meta_data( '_arnipay_link_id', $link_id );
			$order->update_meta_data( '_arnipay_link_id_lc', strtolower( $link_id ) );
			$changed = true;
		}

		if ( 'payment.completed' === $event_type && '' !== $payment_id && '' === (string) $order->get_meta( '_arnipay_payment_id', true ) ) {
			$order->update_meta_data( '_arnipay_payment_id', $payment_id );
			$order->update_meta_data( '_arnipay_payment_id_lc', strtolower( $payment_id ) );
			$changed = true;
		}

		if ( $changed ) {
			$order->save();
		}
	}

	/**
	 * Check whether the amount in a completed webhook matches the frozen amount.
	 *
	 * Completed payments are required to include an amount. PYG has no decimal
	 * digits; a 1-unit tolerance absorbs harmless rounding.
	 *
	 * @param WC_Order $order Order to compare against.
	 * @param array    $event Webhook event payload.
	 * @return bool True if the amount is present and matches.
	 */
	public function amount_matches( WC_Order $order, array $event ): bool {
		if ( ! isset( $event['data']['amount'] ) || '' === (string) $event['data']['amount'] ) {
			return false;
		}

		$reported      = (float) wc_format_decimal( (string) $event['data']['amount'] );
		$expected_meta = (string) $order->get_meta( '_arnipay_expected_amount', true );
		$expected      = '' !== $expected_meta
			? (float) wc_format_decimal( $expected_meta )
			: (float) wc_format_decimal( (string) $order->get_total() );

		return abs( $reported - $expected ) <= 1.0;
	}

	/**
	 * Remove the stored arnipay payment URL once the order is in a final state.
	 *
	 * @param WC_Order $order Order to clean up.
	 * @return void
	 */
	public function clear_payment_url( WC_Order $order ): void {
		if ( '' !== (string) $order->get_meta( '_arnipay_payment_url', true ) ) {
			$order->delete_meta_data( '_arnipay_payment_url' );
			$order->save();
		}
	}

	/**
	 * Record a processed idempotency key on the order, capped to 20 entries.
	 *
	 * @param WC_Order $order      Order being processed.
	 * @param string   $dedupe_key Idempotency key.
	 * @return void
	 */
	public function remember_webhook( WC_Order $order, string $dedupe_key ): void {
		if ( '' === $dedupe_key ) {
			return;
		}

		// WC returns '' (not array) when the meta is missing; (array)'' would
		// create a phantom [0 => ''] entry that pollutes the deduplication list.
		$raw       = $order->get_meta( '_arnipay_processed_webhooks', true );
		$processed = is_array( $raw ) ? $raw : array();

		if ( ! in_array( $dedupe_key, $processed, true ) ) {
			$processed[] = $dedupe_key;
			$processed   = array_slice( $processed, -20 );
			$order->update_meta_data( '_arnipay_processed_webhooks', $processed );
			$order->save();
		}
	}

	/**
	 * Build an idempotency key from signed webhook data.
	 *
	 * If any identifier is present, the key is derived from them so retries
	 * of the same webhook (different timestamps, same event) are detected as
	 * duplicates. Otherwise falls back to event+timestamp+signature.
	 *
	 * Public + static so it can be tested without a WC_Order or HTTP context.
	 *
	 * @param string $event_type Event type string.
	 * @param array  $data       Webhook data payload.
	 * @param int    $timestamp  Signed timestamp.
	 * @param string $signature  Webhook signature.
	 * @return string Idempotency key (64 hex chars).
	 */
	public static function build_dedupe_key( string $event_type, array $data, int $timestamp, string $signature ): string {
		$reference  = isset( $data['reference'] ) ? sanitize_text_field( (string) $data['reference'] ) : '';
		$link_id    = isset( $data['link_id'] ) ? sanitize_text_field( (string) $data['link_id'] ) : '';
		$payment_id = isset( $data['payment_id'] ) ? sanitize_text_field( (string) $data['payment_id'] ) : '';
		$status     = isset( $data['status'] ) ? sanitize_text_field( (string) $data['status'] ) : '';
		$amount     = isset( $data['amount'] ) ? wc_format_decimal( (string) $data['amount'] ) : '';

		if ( '' !== $payment_id || '' !== $link_id || '' !== $reference ) {
			return hash( 'sha256', implode( '|', array( $event_type, $payment_id, $link_id, $reference, $status, $amount ) ) );
		}

		return hash( 'sha256', $event_type . '|' . $timestamp . '|' . $signature );
	}
}
