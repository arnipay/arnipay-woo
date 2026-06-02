<?php
/**
 * Plugin Name: arnipay for WooCommerce
 * Description: arnipay payment gateway for WooCommerce.
 * Version: 1.0.34
 * Author: arnipay
 * Author URI: https://arnipay.com.py
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * WC tested up to: 10.4
 * WC requires at least: 9.0
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 *
 * @package Arnipay_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ARNIPAY_WOO_AW_VERSION' ) ) {
	define( 'ARNIPAY_WOO_AW_VERSION', '1.0.34' );
}

if ( ! defined( 'ARNIPAY_WOO_AW_ID' ) ) {
	define( 'ARNIPAY_WOO_AW_ID', 'arnipay_woo_aw' );
}

add_action( 'plugins_loaded', 'arnipay_woo_init' );
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
		}
	}
);

/**
 * Initialize arnipay plugin.
 */
function arnipay_woo_init(): void {
	if ( ! arnipay_woo_aw_requirements() ) {
		return;
	}
	arnipay_woo_aw()->run_arnipay();
}

/**
 * Display admin notice.
 *
 * @param string $notice Notice message to display.
 */
function arnipay_woo_aw_notices( string $notice ): void {
	?>
	<div class="error notice">
		<p>
		<?php
			echo wp_kses(
				$notice,
				array(
					'a'    => array(
						'href'   => array(),
						'target' => array(),
					),
					'code' => array(),
					'br'   => array(),
				)
			);
		?>
		</p>
	</div>
	<?php
}

/**
 * Check if plugin requirements are met.
 *
 * @return bool True if requirements are met, false otherwise.
 */
function arnipay_woo_aw_requirements(): bool {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) || ! function_exists( 'get_woocommerce_currency' ) ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			add_action(
				'admin_notices',
				function () {
					arnipay_woo_aw_notices( __( 'arnipay requiere WooCommerce activo para funcionar.', 'arnipay-woo' ) );
				}
			);
		}
		return false;
	}

	if ( 'PYG' !== get_woocommerce_currency() ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			add_action(
				'admin_notices',
				function () {
					/* translators: %s: Link to WooCommerce currency settings */
					$currency_url = esc_url( admin_url( 'admin.php?page=wc-settings&tab=general#s2id_woocommerce_currency' ) );
					$currency     = __( 'arnipay solo funciona con la moneda PYG en WooCommerce. ', 'arnipay-woo' ) . sprintf( __( 'Click %s para establecer', 'arnipay-woo' ), '<a href="' . $currency_url . '">' . __( 'aquí', 'arnipay-woo' ) . '</a>' );
					arnipay_woo_aw_notices( $currency );
				}
			);
		}
		return false;
	}

	return true;
}

/**
 * Get the main plugin instance.
 *
 * @return Arnipay_Woo_AW_Plugin Plugin instance.
 */
function arnipay_woo_aw(): Arnipay_Woo_AW_Plugin {
	static $plugin;
	if ( ! isset( $plugin ) ) {
		require_once 'includes/class-arnipay-woo-aw-plugin.php';
		$plugin = new Arnipay_Woo_AW_Plugin( __FILE__, ARNIPAY_WOO_AW_VERSION );
	}
	return $plugin;
}

