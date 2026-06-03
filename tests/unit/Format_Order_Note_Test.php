<?php
/**
 * Unit tests for Arnipay_Woo_AW_Webhook_Handler::format_order_note_lines().
 *
 * The method is private because it is an implementation detail of the
 * handler, not part of its public contract. Reflection is the standard
 * PHPUnit pattern to cover private logic without changing visibility just
 * for testing. The behaviour matters because:
 *  - It writes directly to order notes shown to the merchant.
 *  - Its inputs include text translated via __() and values that originate
 *    in the webhook payload — we want to guarantee they cannot break out
 *    of the HTML context (XSS) even if upstream sanitization changes.
 */

use PHPUnit\Framework\TestCase;

final class Format_Order_Note_Test extends TestCase {

	private Arnipay_Woo_AW_Webhook_Handler $handler;
	private ReflectionMethod $method;

	protected function setUp(): void {
		$resolver = new Arnipay_Woo_AW_Order_Resolver( 'arnipay_woo_aw' );

		$this->handler = new Arnipay_Woo_AW_Webhook_Handler(
			'commerce-id',
			'webhook-secret',
			false,
			$resolver
		);

		// Reach into the private method once; reuse across tests.
		$this->method = new ReflectionMethod( Arnipay_Woo_AW_Webhook_Handler::class, 'format_order_note_lines' );
		$this->method->setAccessible( true );
	}

	private function format( array $lines ): string {
		return (string) $this->method->invoke( $this->handler, $lines );
	}

	/* ----- happy path ----- */

	public function test_joins_lines_with_br(): void {
		$result = $this->format( array( 'Pago confirmado.', 'Método: QR.', 'Referencia: ABC12-1.' ) );

		$this->assertSame( 'Pago confirmado.<br>Método: QR.<br>Referencia: ABC12-1.', $result );
	}

	public function test_single_line_input_has_no_br(): void {
		$result = $this->format( array( 'Solo una línea.' ) );

		$this->assertSame( 'Solo una línea.', $result );
		$this->assertStringNotContainsString( '<br>', $result );
	}

	/* ----- HTML escaping (the security-relevant part) ----- */

	public function test_escapes_script_tag_in_payload(): void {
		$result = $this->format( array( 'Método: <script>alert(1)</script>' ) );

		// The literal tag must be encoded; the user must never see it as
		// active HTML in the order notes timeline.
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( '</script>', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	public function test_escapes_quotes_and_ampersands(): void {
		$result = $this->format( array( 'Referencia: A"B&C\'D' ) );

		$this->assertStringNotContainsString( '"', $result, '" must be encoded' );
		$this->assertStringContainsString( '&quot;', $result );
		$this->assertStringContainsString( '&amp;', $result );
		// Single quote also encoded by ENT_QUOTES.
		$this->assertStringContainsString( '&#039;', $result );
	}

	public function test_br_separator_is_literal_not_escaped(): void {
		// The <br> that joins lines must come through as a tag, while any
		// <br> inside a line itself must be encoded. This guards against a
		// future refactor accidentally escaping the separator too.
		$result = $this->format( array( 'Línea 1', 'Línea 2 con <br> dentro' ) );

		$this->assertStringContainsString( 'Línea 1<br>Línea 2', $result, 'separator <br> must be a literal tag' );
		$this->assertStringContainsString( '&lt;br&gt;', $result, 'inline <br> must be encoded as text' );
	}

	/* ----- trim & filter ----- */

	public function test_trims_surrounding_whitespace(): void {
		$result = $this->format( array( '  Pago confirmado.  ', "\tMétodo: QR.\n" ) );

		$this->assertSame( 'Pago confirmado.<br>Método: QR.', $result );
	}

	public function test_filters_empty_strings(): void {
		$result = $this->format( array( 'Pago confirmado.', '', 'Método: QR.' ) );

		$this->assertSame( 'Pago confirmado.<br>Método: QR.', $result );
	}

	public function test_filters_whitespace_only_lines(): void {
		// A whitespace-only line trims down to '' and must be dropped — this
		// protects the "optional ID de pago" case where the caller might pass
		// a blank line in by accident.
		$result = $this->format( array( 'Pago confirmado.', '   ', "\t\n", 'Método: QR.' ) );

		$this->assertSame( 'Pago confirmado.<br>Método: QR.', $result );
	}

	public function test_empty_input_returns_empty_string(): void {
		$this->assertSame( '', $this->format( array() ) );
	}

	public function test_all_empty_input_returns_empty_string(): void {
		// Edge case: every line is filtered out — must not produce just "<br>".
		$this->assertSame( '', $this->format( array( '', '  ', "\n" ) ) );
	}

	/* ----- character preservation ----- */

	public function test_preserves_accented_characters(): void {
		// Spanish text with tildes and ñ must survive the escaping intact.
		$result = $this->format( array( 'Método: Tigo money', 'Referencia: pedido número 42' ) );

		$this->assertStringContainsString( 'Método', $result );
		$this->assertStringContainsString( 'número', $result );
	}

	/* ----- index normalization ----- */

	public function test_handles_non_sequential_input_keys(): void {
		// If the caller passes an associative or sparse array, the helper
		// must still produce a clean <br>-joined string in original order.
		$result = $this->format( array( 5 => 'Primero', 99 => 'Segundo', 'x' => 'Tercero' ) );

		$this->assertSame( 'Primero<br>Segundo<br>Tercero', $result );
	}

	/* ----- non-string inputs ----- */

	public function test_coerces_non_string_values(): void {
		// The trim closure casts to string, so integers and floats survive
		// as their textual representation. This covers the case where a
		// caller passes a payment_id that arrives typed as int from JSON.
		$result = $this->format( array( 'ID:', 12345, 'Monto:', 1500.50 ) );

		$this->assertStringContainsString( '12345', $result );
		$this->assertStringContainsString( '1500.5', $result );
	}
}
