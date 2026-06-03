<?php
/**
 * End-to-end tests for the SDK signature/webhook layer combined with the
 * Order_Resolver. These exercise the same code paths the real webhook handler
 * uses, but without needing a full WordPress runtime.
 */

use PHPUnit\Framework\TestCase;
use Arnipay\Gateway\SignatureService;
use Arnipay\Gateway\Webhook;

final class Webhook_Pipeline_Test extends TestCase {

	private const SECRET    = 'whsec_test_pipeline';
	private const CLIENT_ID = 'commerce-uuid-A';
	private const URI       = '/?wc-api=arnipay_woo_aw_gateway';

	private SignatureService $sig;

	protected function setUp(): void {
		$this->sig = new SignatureService();
		Test_WC_Order_Registry::reset();
	}

	private function sign( array $event, int $timestamp ): array {
		$payload   = json_encode( $event );
		$signature = $this->sig->generate( 'POST', self::URI, $timestamp, self::CLIENT_ID, self::SECRET, $payload );
		return array( $payload, $signature );
	}

	private function validate( string $payload, string $signature, int $timestamp ): array {
		return ( new Webhook( self::SECRET ) )->processEvent(
			'POST',
			self::URI,
			(string) $timestamp,
			self::CLIENT_ID,
			$payload,
			$signature
		);
	}

	/* ----- Signature validation ----- */

	public function test_valid_signature_returns_parsed_event(): void {
		$event = array(
			'event'     => 'payment.completed',
			'timestamp' => gmdate( 'c' ),
			'data'      => array( 'reference' => 'ABC12-1', 'amount' => '150000' ),
		);
		$ts    = time();
		[ $payload, $sig ] = $this->sign( $event, $ts );

		$parsed = $this->validate( $payload, $sig, $ts );

		$this->assertSame( 'payment.completed', $parsed['event'] );
		$this->assertSame( 'ABC12-1', $parsed['data']['reference'] );
	}

	public function test_tampered_signature_rejected_with_401(): void {
		$ts                  = time();
		[ $payload, $sig ]   = $this->sign(
			array(
				'event'     => 'payment.completed',
				'timestamp' => gmdate( 'c' ),
				'data'      => array( 'reference' => 'ABC12-1', 'amount' => '150000' ),
			),
			$ts
		);

		try {
			$this->validate( $payload, 'deadbeef', $ts );
			$this->fail( 'tampered signature should throw' );
		} catch ( \Arnipay\Exception\GatewayException $e ) {
			$this->assertSame( 401, $e->getCode() );
		}
	}

	public function test_tampered_payload_rejected(): void {
		$ts                = time();
		[ $payload, $sig ] = $this->sign(
			array(
				'event'     => 'payment.completed',
				'timestamp' => gmdate( 'c' ),
				'data'      => array( 'reference' => 'ABC12-1', 'amount' => '150000' ),
			),
			$ts
		);

		// Same valid signature, but the body was changed by an attacker.
		$tampered = str_replace( '150000', '1', $payload );

		$this->expectException( \Arnipay\Exception\GatewayException::class );
		$this->validate( $tampered, $sig, $ts );
	}

	public function test_missing_event_or_data_rejected(): void {
		$ts                = time();
		$bad               = json_encode( array( 'foo' => 'bar' ) );
		$sig               = $this->sig->generate( 'POST', self::URI, $ts, self::CLIENT_ID, self::SECRET, $bad );

		$this->expectException( \Arnipay\Exception\GatewayException::class );
		$this->validate( $bad, $sig, $ts );
	}

	/* ----- Anti-replay (timestamp window) ----- */

	public function test_old_signed_webhook_must_be_rejected_by_anti_replay(): void {
		$ts                  = time() - 4000; // 66 minutes old
		[ $payload, $sig ]   = $this->sign(
			array(
				'event'     => 'payment.completed',
				'timestamp' => gmdate( 'c', $ts ),
				'data'      => array( 'reference' => 'ABC12-1', 'amount' => '150000' ),
			),
			$ts
		);

		// Signature itself is still cryptographically valid...
		$parsed = $this->validate( $payload, $sig, $ts );
		$this->assertSame( 'payment.completed', $parsed['event'] );

		// ...but the handler must reject anything older than WEBHOOK_MAX_AGE.
		$age_in_window = abs( time() - $ts ) <= Arnipay_Woo_AW_Webhook_Handler::WEBHOOK_MAX_AGE;
		$this->assertFalse(
			$age_in_window,
			'an old signed webhook must fall outside the anti-replay window'
		);
	}

	public function test_recent_signed_webhook_is_within_anti_replay_window(): void {
		$ts            = time() - 30;
		$age_in_window = abs( time() - $ts ) <= Arnipay_Woo_AW_Webhook_Handler::WEBHOOK_MAX_AGE;
		$this->assertTrue( $age_in_window );
	}

	/* ----- Idempotency via the resolver's build_dedupe_key ----- */

	public function test_two_retries_with_different_timestamps_have_same_dedupe_key(): void {
		$data = array( 'reference' => 'ABC12-1', 'amount' => '150000', 'payment_id' => 'pay_X' );

		$k1 = Arnipay_Woo_AW_Order_Resolver::build_dedupe_key( 'payment.completed', $data, 1000, 'sig-a' );
		$k2 = Arnipay_Woo_AW_Order_Resolver::build_dedupe_key( 'payment.completed', $data, 9999, 'sig-b' );

		$this->assertSame( $k1, $k2, 'arnipay retries must collapse into one' );
	}
}
