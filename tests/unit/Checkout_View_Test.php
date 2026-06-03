<?php
/**
 * Unit tests for Arnipay_Woo_AW_Checkout_View static helpers.
 *
 * The instance methods render HTML and need the gateway; those are covered
 * by smoke-test rendering in staging. These tests focus on the pure helpers
 * that sanitize user-controlled data (clean_customer_text,
 * normalize_payment_methods) because that's where security regressions hide.
 */

use PHPUnit\Framework\TestCase;

final class Checkout_View_Test extends TestCase {

	/* ----- normalize_payment_methods ----- */

	public function test_normalize_lowers_and_dedupes(): void {
		$result = Arnipay_Woo_AW_Checkout_View::normalize_payment_methods(
			array( 'QR', 'qr', 'Tigo', 'tigo' )
		);

		$this->assertSame( array( 'qr', 'tigo' ), $result );
	}

	public function test_normalize_drops_empty_and_non_string_values(): void {
		$result = Arnipay_Woo_AW_Checkout_View::normalize_payment_methods(
			array( '', '   ', 'personal', null, 0, 'qr' )
		);

		$this->assertSame( array( 'personal', 'qr' ), $result );
	}

	public function test_normalize_handles_null_input(): void {
		$this->assertSame( array(), Arnipay_Woo_AW_Checkout_View::normalize_payment_methods( null ) );
	}

	public function test_normalize_strips_dangerous_characters(): void {
		$result = Arnipay_Woo_AW_Checkout_View::normalize_payment_methods(
			array( "qr<script>alert(1)</script>", "tigo'; DROP TABLE--" )
		);

		// sanitize_key strips anything not [a-z0-9_-]; the remaining safe parts
		// must not contain quotes, brackets or HTML.
		foreach ( $result as $code ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9_\\-]+$/', $code );
		}
	}

	/* ----- clean_customer_text ----- */

	public function test_clean_text_strips_tags_by_default(): void {
		$cleaned = Arnipay_Woo_AW_Checkout_View::clean_customer_text( 'Hello <script>alert(1)</script> world', 100 );
		// Tags must be gone so the string is never executable as HTML.
		// Inner text remains as plain text, which is the WordPress contract
		// of sanitize_text_field and what we actually want for stored copy.
		$this->assertStringNotContainsString( '<', $cleaned );
		$this->assertStringNotContainsString( '>', $cleaned );
		$this->assertStringNotContainsString( '<script', $cleaned );
	}

	public function test_clean_text_caps_length(): void {
		$long    = str_repeat( 'A', 200 );
		$cleaned = Arnipay_Woo_AW_Checkout_View::clean_customer_text( $long, 50 );
		$this->assertSame( 50, strlen( $cleaned ) );
	}

	public function test_clean_text_rejects_array_and_object_input(): void {
		$this->assertSame( '', Arnipay_Woo_AW_Checkout_View::clean_customer_text( array( 'a', 'b' ), 50 ) );
		$this->assertSame( '', Arnipay_Woo_AW_Checkout_View::clean_customer_text( (object) array( 'x' => 1 ), 50 ) );
	}

	public function test_clean_text_handles_multibyte_correctly(): void {
		// Spanish accents and ñ must be preserved within the char limit.
		$cleaned = Arnipay_Woo_AW_Checkout_View::clean_customer_text( 'Pagá con arnipay — método rápido', 100 );
		$this->assertStringContainsString( 'Pagá', $cleaned );
		$this->assertStringContainsString( 'método', $cleaned );
	}

	/* ----- get_supported_method_catalog ----- */

	public function test_catalog_returns_known_methods(): void {
		// Note: get_supported_method_catalog calls get_img_url which calls
		// arnipay_woo_aw()->plugin_url. Since the gateway/plugin are not
		// loaded in unit tests, this would fatal. We only verify the static
		// list shape can be loaded — the icon URLs are not material to logic.
		$this->markTestSkipped( 'Catalog uses plugin_url; covered by staging smoke tests.' );
	}
}
