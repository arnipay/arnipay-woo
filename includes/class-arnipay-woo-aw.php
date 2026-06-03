<?php
/**
 * Arnipay WooCommerce Integration Class.
 *
 * @package Arnipay_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Arnipay\Arnipay;

/**
 * Class Arnipay_Woo_AW
 *
 * Handles Arnipay API client initialization and configuration.
 */
class Arnipay_Woo_AW {

	/**
	 * Arnipay API client instance.
	 *
	 * @var Arnipay|null
	 */
	private static ?Arnipay $arnipay = null;

	/**
	 * Integration settings from WooCommerce.
	 *
	 * @var object|null
	 */
	private static ?object $settings = null;

	/**
	 * Get Arnipay API client instance.
	 *
	 * The Arnipay sandbox environment is not available yet, so the client
	 * always runs against production. SSL verification is left enabled
	 * (the SDK default for production).
	 *
	 * @return Arnipay|null Arnipay client instance or null if not configured.
	 */
	public static function get_instance(): ?Arnipay {

		if ( isset( self::$settings ) && isset( self::$arnipay ) ) {
			return self::$arnipay;
		}

		$settings = get_option( 'woocommerce_arnipay_woo_aw_settings', null );

		if ( ! is_array( $settings ) && ! is_object( $settings ) ) {
			return null;
		}

		self::$settings = (object) $settings;

		if ( ! isset( self::$settings->enabled ) || 'yes' !== self::$settings->enabled ) {
			return null;
		}

		$client_id     = isset( self::$settings->client_id ) ? (string) self::$settings->client_id : '';
		$client_secret = isset( self::$settings->client_secret ) ? (string) self::$settings->client_secret : '';

		if ( '' === $client_id || '' === $client_secret ) {
			return null;
		}

		// Sandbox is not available; always use the production environment.
		self::$arnipay = new Arnipay( $client_id, $client_secret, false );

		return self::$arnipay;
	}

	/**
	 * Build an Arnipay client from arbitrary credentials, bypassing the cache.
	 *
	 * Used by the admin "verify credentials" tool so it can test the values
	 * currently entered in the form, even before they are saved.
	 *
	 * @param string $client_id     Client ID to use.
	 * @param string $client_secret Client secret to use.
	 * @return Arnipay|null Client instance or null if either credential is empty.
	 */
	public static function build_client( string $client_id, string $client_secret ): ?Arnipay {
		$client_id     = trim( $client_id );
		$client_secret = trim( $client_secret );

		if ( '' === $client_id || '' === $client_secret ) {
			return null;
		}

		return new Arnipay( $client_id, $client_secret, false );
	}

	/**
	 * Reset the cached client and settings.
	 *
	 * Useful after settings are saved so a stale client is not reused.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$arnipay  = null;
		self::$settings = null;
	}

	/**
	 * Get available payment methods from Arnipay.
	 *
	 * The result is cached for 1 hour via a transient to avoid hammering
	 * the Arnipay API on repeated calls.
	 *
	 * @return array List of payment methods or empty array on failure.
	 */
	public static function get_payment_methods(): array {
		$cache_key = 'arnipay_woo_aw_payment_methods';
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$arnipay = self::get_instance();
		if ( ! $arnipay ) {
			return array();
		}

		try {
			$methods = $arnipay->getPaymentMethods();
			$methods = is_array( $methods ) ? $methods : array();

			set_transient( $cache_key, $methods, HOUR_IN_SECONDS );

			return $methods;
		} catch ( Exception $e ) {
			if ( function_exists( 'arnipay_woo_aw' ) ) {
				arnipay_woo_aw()->log( 'Error al obtener métodos de pago: ' . $e->getMessage() );
			}
			return array();
		}
	}
}
