<?php
/**
 * Test bootstrap: stubs WordPress and WooCommerce just enough to unit-test
 * the plugin's pure-domain classes in isolation.
 *
 * Why stubs: the classes under test are deliberately decoupled from the WP/WC
 * runtime. Loading the full WordPress codebase to test pure functions would
 * be slow and flaky; stubbing the handful of functions/classes they touch
 * gives fast, deterministic tests that catch real regressions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

/* ----- WordPress function stubs ----- */

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = (string) $str;
		// Strip tags, collapse whitespace, trim — same shape as WP's behavior.
		$str = strip_tags( $str );
		$str = preg_replace( '/[\r\n\t]+/', ' ', $str );
		$str = preg_replace( '/\s{2,}/', ' ', $str );
		return trim( $str );
	}
}

if ( ! function_exists( 'wc_format_decimal' ) ) {
	function wc_format_decimal( $value, $dp = '' ) {
		$value = str_replace( array( ',', ' ' ), array( '.', '' ), (string) $value );
		if ( '' === $dp || false === $dp ) {
			// Mirror WC: keep the integer part intact, trim only trailing
			// fractional zeros after a decimal point.
			if ( false === strpos( $value, '.' ) ) {
				return '' === $value ? '0' : $value;
			}
			$trimmed = rtrim( rtrim( $value, '0' ), '.' );
			return '' === $trimmed ? '0' : $trimmed;
		}
		return number_format( (float) $value, (int) $dp, '.', '' );
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	function wc_get_order( $id ) {
		return Test_WC_Order_Registry::get( (int) $id );
	}
}

if ( ! function_exists( 'wc_get_orders' ) ) {
	function wc_get_orders( $args ) {
		return Test_WC_Order_Registry::query( $args );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://shop.test' . $path;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $n ) {
		return abs( (int) $n );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		$str = (string) $str;
		$str = strip_tags( $str );
		return trim( $str );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	// Mirrors the relevant behaviour of WordPress's esc_html:
	// converts <, >, &, " and ' to their HTML entities so the result is
	// safe to embed inside an HTML text node.
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_string( $value ) ) {
			return stripslashes( $value );
		}
		return $value;
	}
}

/* ----- Minimal in-memory WC_Order ----- */

/**
 * In-memory order with the subset of WC_Order API the resolver actually uses.
 */
if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		public int $id;
		public string $payment_method = 'arnipay_woo_aw';
		public string $status         = 'pending';
		public string $total          = '0';
		public string $order_number   = '';
		public array $meta            = array();

		public function __construct( int $id = 0 ) {
			$this->id = $id;
		}

		public function get_id(): int {
			return $this->id;
		}

		public function get_payment_method(): string {
			return $this->payment_method;
		}

		public function get_status(): string {
			return $this->status;
		}

		public function get_total(): string {
			return $this->total;
		}

		public function get_order_number(): string {
			return '' !== $this->order_number ? $this->order_number : (string) $this->id;
		}

		public function get_meta( $key, $single = true ) {
			return $this->meta[ $key ] ?? '';
		}

		public function update_meta_data( $key, $value ): void {
			$this->meta[ $key ] = $value;
		}

		public function delete_meta_data( $key ): void {
			unset( $this->meta[ $key ] );
		}

		public function is_paid(): bool {
			return in_array( $this->status, array( 'processing', 'completed' ), true );
		}

		public function save(): void {
			// In-memory: nothing to persist.
		}

		public function save_meta_data(): void {
			// In-memory: nothing to persist.
		}
	}
}

/**
 * Registry for the in-memory orders. Tests register orders here and the
 * stubbed wc_get_order / wc_get_orders read from it.
 */
class Test_WC_Order_Registry {
	private static array $orders = array();

	public static function reset(): void {
		self::$orders = array();
	}

	public static function register( WC_Order $order ): void {
		self::$orders[ $order->get_id() ] = $order;
	}

	public static function get( int $id ): ?WC_Order {
		return self::$orders[ $id ] ?? null;
	}

	public static function query( array $args ): array {
		$results = array();
		foreach ( self::$orders as $order ) {
			if ( ! empty( $args['payment_method'] ) && $order->get_payment_method() !== $args['payment_method'] ) {
				continue;
			}
			if ( isset( $args['meta_key'], $args['meta_value'] ) ) {
				if ( (string) $order->get_meta( $args['meta_key'] ) !== (string) $args['meta_value'] ) {
					continue;
				}
			}
			$results[] = $order;
			if ( ! empty( $args['limit'] ) && count( $results ) >= (int) $args['limit'] ) {
				break;
			}
		}
		return $results;
	}
}

/* ----- Load the classes under test ----- */

require_once __DIR__ . '/../includes/domain/class-arnipay-woo-aw-order-resolver.php';
require_once __DIR__ . '/../includes/domain/class-arnipay-woo-aw-webhook-handler.php';
require_once __DIR__ . '/../includes/domain/class-arnipay-woo-aw-checkout-view.php';
require_once __DIR__ . '/../lib/vendor/geekwalletsrl/arnipay-sdk/src/Gateway/SignatureService.php';
require_once __DIR__ . '/../lib/vendor/geekwalletsrl/arnipay-sdk/src/Exception/GatewayException.php';
require_once __DIR__ . '/../lib/vendor/geekwalletsrl/arnipay-sdk/src/Gateway/Webhook.php';
