<?php
/**
 * Unit tests for Arnipay_Woo_AW_Order_Resolver.
 */

use PHPUnit\Framework\TestCase;

final class Order_Resolver_Test extends TestCase {

	private const GATEWAY_ID = 'arnipay_woo_aw';

	private Arnipay_Woo_AW_Order_Resolver $resolver;

	protected function setUp(): void {
		Test_WC_Order_Registry::reset();

		$reference_builder = function ( WC_Order $order ): string {
			// Deterministic builder matching the format the gateway emits.
			return 'ABC12-' . $order->get_id();
		};

		$this->resolver = new Arnipay_Woo_AW_Order_Resolver( self::GATEWAY_ID, $reference_builder );
	}

	private function make_order( int $id, array $meta = array(), string $status = 'pending', string $total = '0' ): WC_Order {
		$order                 = new WC_Order( $id );
		$order->payment_method = self::GATEWAY_ID;
		$order->status         = $status;
		$order->total          = $total;
		$order->meta           = $meta;
		Test_WC_Order_Registry::register( $order );
		return $order;
	}

	/* ----- identifier_equals ----- */

	public function test_identifier_equals_case_insensitive_by_default(): void {
		$this->assertTrue( Arnipay_Woo_AW_Order_Resolver::identifier_equals( 'ABC-123', 'abc-123' ) );
		$this->assertTrue( Arnipay_Woo_AW_Order_Resolver::identifier_equals( '  abc-123  ', 'ABC-123' ) );
	}

	public function test_identifier_equals_case_sensitive_when_requested(): void {
		$this->assertTrue( Arnipay_Woo_AW_Order_Resolver::identifier_equals( 'ABC-123', 'ABC-123', true ) );
		$this->assertFalse( Arnipay_Woo_AW_Order_Resolver::identifier_equals( 'ABC-123', 'abc-123', true ) );
	}

	public function test_identifier_equals_rejects_different_strings(): void {
		$this->assertFalse( Arnipay_Woo_AW_Order_Resolver::identifier_equals( 'abc', 'def' ) );
		$this->assertFalse( Arnipay_Woo_AW_Order_Resolver::identifier_equals( '', 'def' ) );
	}

	/* ----- get_order_from_reference ----- */

	public function test_resolves_order_by_reference_meta(): void {
		$order = $this->make_order( 42, array( '_arnipay_reference' => 'ABC12-42' ) );

		$found = $this->resolver->get_order_from_reference( 'ABC12-42' );

		$this->assertNotNull( $found );
		$this->assertSame( 42, $found->get_id() );
	}

	public function test_rejects_malformed_reference(): void {
		$this->assertNull( $this->resolver->get_order_from_reference( 'xx' ) );
		$this->assertNull( $this->resolver->get_order_from_reference( "ABC12-42' OR 1=1" ) );
		$this->assertNull( $this->resolver->get_order_from_reference( '../../../etc/passwd' ) );
		$this->assertNull( $this->resolver->get_order_from_reference( str_repeat( 'A', 100 ) ) );
	}

	public function test_fast_webhook_fallback_resolves_when_meta_missing(): void {
		// Order exists but has no _arnipay_reference yet (webhook arrived first).
		$this->make_order( 100 );

		$found = $this->resolver->get_order_from_reference( 'ABC12-100' );

		$this->assertNotNull( $found, 'reference_builder fallback should resolve the order' );
		$this->assertSame( 100, $found->get_id() );
	}

	public function test_fast_webhook_fallback_rejects_wrong_prefix(): void {
		$this->make_order( 100 );

		// Same order ID but a reference that would NOT have been generated
		// by this site — the case-sensitive hash_equals must reject it.
		$found = $this->resolver->get_order_from_reference( 'ZZZZZ-100' );

		$this->assertNull( $found );
	}

	public function test_legacy_order_format_supported(): void {
		$this->make_order( 7, array( '_arnipay_reference' => 'ORDER-7' ) );

		$found = $this->resolver->get_order_from_reference( 'ORDER-7' );

		$this->assertNotNull( $found );
		$this->assertSame( 7, $found->get_id() );
	}

	public function test_legacy_order_format_rejects_when_meta_mismatches(): void {
		// Order exists with a DIFFERENT stored reference — the legacy fallback
		// must not return it even though the ID matches.
		$this->make_order( 7, array( '_arnipay_reference' => 'ABC12-7' ) );

		$found = $this->resolver->get_order_from_reference( 'ORDER-7' );

		$this->assertNull( $found, 'must not return an order whose stored reference does not match' );
	}

	/* ----- get_order_from_event ----- */

	public function test_resolves_by_link_id_when_reference_missing(): void {
		$this->make_order( 9, array( '_arnipay_link_id' => 'lnk_ABC' ) );

		$found = $this->resolver->get_order_from_event(
			array(
				'event' => 'payment.completed',
				'data'  => array( 'link_id' => 'lnk_ABC' ),
			)
		);

		$this->assertNotNull( $found );
		$this->assertSame( 9, $found->get_id() );
	}

	public function test_resolves_by_lowercase_link_id_fallback(): void {
		$this->make_order( 9, array( '_arnipay_link_id_lc' => 'lnk_abc' ) );

		$found = $this->resolver->get_order_from_event(
			array(
				'event' => 'payment.completed',
				'data'  => array( 'link_id' => 'LNK_ABC' ),
			)
		);

		$this->assertNotNull( $found );
		$this->assertSame( 9, $found->get_id() );
	}

	public function test_returns_null_when_no_identifier_matches(): void {
		$this->make_order( 9, array( '_arnipay_link_id' => 'lnk_other' ) );

		$found = $this->resolver->get_order_from_event(
			array(
				'event' => 'payment.completed',
				'data'  => array( 'link_id' => 'lnk_nonexistent' ),
			)
		);

		$this->assertNull( $found );
	}

	/* ----- event_identifiers_match_order ----- */

	public function test_identifiers_match_when_stored_and_incoming_agree(): void {
		$order = $this->make_order(
			1,
			array(
				'_arnipay_reference' => 'ABC12-1',
				'_arnipay_link_id'   => 'lnk_X',
			)
		);

		$this->assertTrue( $this->resolver->event_identifiers_match_order( $order, 'ABC12-1', 'lnk_X', '' ) );
	}

	public function test_identifiers_reject_link_id_mismatch(): void {
		$order = $this->make_order( 1, array( '_arnipay_link_id' => 'lnk_X' ) );

		$this->assertFalse( $this->resolver->event_identifiers_match_order( $order, '', 'lnk_DIFFERENT', '' ) );
	}

	public function test_identifiers_ignore_payment_id_when_order_unpaid(): void {
		$order = $this->make_order( 1 ); // status=pending → not paid

		// payment_id should not be checked before the order is paid.
		$this->assertTrue( $this->resolver->event_identifiers_match_order( $order, '', '', 'pay_XYZ' ) );
	}

	public function test_link_id_case_insensitive_match_accepts_provider_casing(): void {
		$order = $this->make_order( 1, array( '_arnipay_link_id' => 'lnk_ABC' ) );

		$this->assertTrue( $this->resolver->event_identifiers_match_order( $order, '', 'LNK_abc', '' ) );
	}

	public function test_reference_match_is_case_sensitive(): void {
		$order = $this->make_order( 1, array( '_arnipay_reference' => 'ABC12-1' ) );

		// References are plugin-generated, must match exactly.
		$this->assertFalse( $this->resolver->event_identifiers_match_order( $order, 'abc12-1', '', '' ) );
	}

	/* ----- amount_matches ----- */

	public function test_amount_matches_exact_value(): void {
		$order = $this->make_order( 1, array( '_arnipay_expected_amount' => '150000' ) );

		$this->assertTrue(
			$this->resolver->amount_matches( $order, array( 'data' => array( 'amount' => '150000' ) ) )
		);
	}

	public function test_amount_matches_within_pyg_tolerance(): void {
		$order = $this->make_order( 1, array( '_arnipay_expected_amount' => '150000' ) );

		// 1-unit tolerance for rounding in PYG (no decimals).
		$this->assertTrue(
			$this->resolver->amount_matches( $order, array( 'data' => array( 'amount' => '150000.5' ) ) )
		);
	}

	public function test_amount_rejects_tampered_amount(): void {
		$order = $this->make_order( 1, array( '_arnipay_expected_amount' => '150000' ) );

		$this->assertFalse(
			$this->resolver->amount_matches( $order, array( 'data' => array( 'amount' => '1' ) ) )
		);
	}

	public function test_amount_rejects_missing_amount(): void {
		$order = $this->make_order( 1, array( '_arnipay_expected_amount' => '150000' ) );

		// Completed events MUST include amount; missing → manual review.
		$this->assertFalse( $this->resolver->amount_matches( $order, array( 'data' => array() ) ) );
		$this->assertFalse( $this->resolver->amount_matches( $order, array( 'data' => array( 'amount' => '' ) ) ) );
	}

	public function test_amount_falls_back_to_order_total_when_no_frozen_value(): void {
		$order = $this->make_order( 1, array(), 'pending', '999' );

		$this->assertTrue(
			$this->resolver->amount_matches( $order, array( 'data' => array( 'amount' => '999' ) ) )
		);
	}

	/* ----- build_dedupe_key ----- */

	public function test_dedupe_key_stable_for_same_identifiers(): void {
		$k1 = Arnipay_Woo_AW_Order_Resolver::build_dedupe_key(
			'payment.completed',
			array( 'reference' => 'ABC12-1', 'amount' => '150000' ),
			100,
			'sig'
		);
		$k2 = Arnipay_Woo_AW_Order_Resolver::build_dedupe_key(
			'payment.completed',
			array( 'reference' => 'ABC12-1', 'amount' => '150000' ),
			999, // different timestamp
			'different-sig'
		);

		$this->assertSame( $k1, $k2, 'retries of the same webhook must produce the same key' );
	}

	public function test_dedupe_key_changes_when_event_changes(): void {
		$completed = Arnipay_Woo_AW_Order_Resolver::build_dedupe_key(
			'payment.completed',
			array( 'reference' => 'ABC12-1' ),
			100,
			'sig'
		);
		$failed = Arnipay_Woo_AW_Order_Resolver::build_dedupe_key(
			'payment.failed',
			array( 'reference' => 'ABC12-1' ),
			100,
			'sig'
		);

		$this->assertNotSame( $completed, $failed );
	}

	public function test_dedupe_key_falls_back_to_timestamp_signature_without_identifiers(): void {
		$k1 = Arnipay_Woo_AW_Order_Resolver::build_dedupe_key( 'verification.ping', array(), 100, 'sig-A' );
		$k2 = Arnipay_Woo_AW_Order_Resolver::build_dedupe_key( 'verification.ping', array(), 100, 'sig-B' );

		// Different signature → different key when no identifiers are available.
		$this->assertNotSame( $k1, $k2 );
	}

	public function test_dedupe_key_is_sha256_hex(): void {
		$key = Arnipay_Woo_AW_Order_Resolver::build_dedupe_key(
			'payment.completed',
			array( 'reference' => 'ABC12-1' ),
			100,
			'sig'
		);

		$this->assertSame( 64, strlen( $key ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $key );
	}

	/* ----- remember_webhook ----- */

	public function test_remember_webhook_caps_at_20_entries(): void {
		$order = $this->make_order( 1 );

		for ( $i = 0; $i < 25; $i++ ) {
			$this->resolver->remember_webhook( $order, 'key-' . $i );
		}

		$processed = (array) $order->get_meta( '_arnipay_processed_webhooks' );

		$this->assertCount( 20, $processed );
		$this->assertSame( 'key-5', $processed[0], 'oldest keys should be evicted first' );
		$this->assertSame( 'key-24', $processed[19] );
	}

	public function test_remember_webhook_ignores_empty_key(): void {
		$order = $this->make_order( 1 );

		$this->resolver->remember_webhook( $order, '' );

		$this->assertSame( '', $order->get_meta( '_arnipay_processed_webhooks' ) );
	}

	public function test_remember_webhook_ignores_duplicates(): void {
		$order = $this->make_order( 1 );

		$this->resolver->remember_webhook( $order, 'key-A' );
		$this->resolver->remember_webhook( $order, 'key-A' );
		$this->resolver->remember_webhook( $order, 'key-A' );

		$this->assertCount( 1, $order->get_meta( '_arnipay_processed_webhooks' ) );
	}

	/* ----- store_event_identifiers ----- */

	public function test_store_event_identifiers_fills_empty_slots(): void {
		$order = $this->make_order( 1 );

		$this->resolver->store_event_identifiers( $order, 'ABC12-1', 'lnk_X', 'pay_Y', 'payment.completed' );

		$this->assertSame( 'ABC12-1', $order->get_meta( '_arnipay_reference' ) );
		$this->assertSame( 'lnk_X', $order->get_meta( '_arnipay_link_id' ) );
		$this->assertSame( 'lnk_x', $order->get_meta( '_arnipay_link_id_lc' ) );
		$this->assertSame( 'pay_Y', $order->get_meta( '_arnipay_payment_id' ) );
		$this->assertSame( 'pay_y', $order->get_meta( '_arnipay_payment_id_lc' ) );
	}

	public function test_store_event_identifiers_does_not_overwrite(): void {
		$order = $this->make_order(
			1,
			array(
				'_arnipay_reference' => 'EXISTING-REF',
				'_arnipay_link_id'   => 'existing_link',
			)
		);

		$this->resolver->store_event_identifiers( $order, 'NEW-REF', 'new_link', '', '' );

		$this->assertSame( 'EXISTING-REF', $order->get_meta( '_arnipay_reference' ) );
		$this->assertSame( 'existing_link', $order->get_meta( '_arnipay_link_id' ) );
	}

	public function test_payment_id_only_stored_on_completed_event(): void {
		$order = $this->make_order( 1 );

		$this->resolver->store_event_identifiers( $order, '', '', 'pay_Y', 'payment.failed' );

		$this->assertSame( '', $order->get_meta( '_arnipay_payment_id' ) );
	}
}
