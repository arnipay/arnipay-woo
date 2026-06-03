<?php
/**
 * Checkout view: renderiza la sección de arnipay que el cliente ve dentro del
 * checkout de WooCommerce y maneja la selección del método específico.
 *
 * Responsabilidad acotada: HTML del bloque del checkout, validación de la
 * selección del cliente, catálogo de medios de pago soportados e íconos. No
 * conoce credenciales, no toca pedidos, no procesa pagos.
 *
 * @package Arnipay_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arnipay_Woo_AW_Checkout_View {

	/**
	 * Gateway instance — usado para leer ajustes (description, payment_methods).
	 *
	 * @var WC_Payment_Gateway
	 */
	private WC_Payment_Gateway $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway $gateway Instancia del gateway.
	 */
	public function __construct( WC_Payment_Gateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Sanitize and cap customer-facing text saved by the merchant.
	 *
	 * Static + public para que el gateway también la use sin duplicar.
	 *
	 * @param mixed $value     Raw text value.
	 * @param int   $max_chars Maximum characters to keep.
	 * @param bool  $textarea  Whether multiline text is allowed.
	 * @return string Sanitized text.
	 */
	public static function clean_customer_text( $value, int $max_chars, bool $textarea = false ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = '';
		}

		$value = $textarea
			? sanitize_textarea_field( wp_unslash( (string) $value ) )
			: sanitize_text_field( wp_unslash( (string) $value ) );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max_chars );
		}

		return substr( $value, 0, $max_chars );
	}

	/**
	 * Normalize payment method codes before sending them to Arnipay.
	 *
	 * @param array|null $methods Raw method list from settings.
	 * @return array Sanitized method codes.
	 */
	public static function normalize_payment_methods( ?array $methods ): array {
		$methods = array_filter(
			array_map(
				static function ( $method ): string {
					return sanitize_key( (string) $method );
				},
				(array) $methods
			)
		);

		return array_values( array_unique( $methods ) );
	}

	/**
	 * Build a versioned image URL to avoid stale browser caches after updates.
	 *
	 * @param string $filename Image filename inside assets/img.
	 * @return string Versioned asset URL.
	 */
	public static function get_img_url( string $filename ): string {
		$filename = ltrim( sanitize_file_name( $filename ), '/' );
		return add_query_arg( 'ver', ARNIPAY_WOO_AW_VERSION, arnipay_woo_aw()->plugin_url . 'assets/img/' . $filename );
	}

	/**
	 * Render the WooCommerce gateway icon (logo + method icons).
	 *
	 * @return string HTML markup.
	 */
	public function get_icon(): string {
		$labels = array(
			'personal' => 'Personal Pay',
			'qr'       => 'Código QR',
			'tigo'     => 'tigo money',
		);

		$icons = '<span class="arnipay-payments">';
		$icons .= sprintf(
			'<img src="%s" alt="%s" class="arnipay-pay-icon arnipay-pay-icon--arnipay" />',
			esc_url( self::get_img_url( 'pay-arnipay.svg' ) ),
			esc_attr__( 'arnipay', 'arnipay-woo' )
		);

		$payment_methods   = (array) $this->gateway->get_option( 'payment_methods', array() );
		$methods_for_icons = self::normalize_payment_methods( $payment_methods );
		if ( empty( $methods_for_icons ) ) {
			$methods_for_icons = array_keys( $labels );
		}

		foreach ( $methods_for_icons as $method ) {
			$method = sanitize_key( $method );
			if ( ! isset( $labels[ $method ] ) ) {
				continue;
			}

			$icons .= sprintf(
				'<img src="%s" alt="%s" class="arnipay-pay-icon arnipay-pay-icon--%s" />',
				esc_url( self::get_img_url( 'pay-' . $method . '.svg' ) ),
				esc_attr( $labels[ $method ] ),
				esc_attr( $method )
			);
		}

		$icons .= '</span>';

		return apply_filters( 'arnipay_woo_aw_icon_html', $icons, $this->gateway );
	}

	/**
	 * Render the checkout block shown when arnipay is selected.
	 *
	 * @return void
	 */
	public function render_payment_fields(): void {
		$methods     = $this->get_checkout_methods_for_display();
		$description = trim( (string) $this->gateway->get_option( 'description', '' ) );

		if ( '' === $description ) {
			$description = __( 'Seleccioná arnipay y elegí cómo querés pagar: Personal Pay, QR o tigo money.', 'arnipay-woo' );
		}

		echo '<div class="arnipay-method-selector" data-arnipay-method-selector>';
		echo '<div class="arnipay-method-selector__head">';
		echo '<h3>' . esc_html__( 'Elegí cómo querés pagar', 'arnipay-woo' ) . '</h3>';
		echo '<p>' . esc_html__( 'Seleccioná tu método de pago preferido', 'arnipay-woo' ) . '</p>';
		echo '</div>';

		echo '<div class="arnipay-method-selector__grid" role="radiogroup" aria-label="' . esc_attr__( 'Métodos de pago disponibles en arnipay', 'arnipay-woo' ) . '">';

		foreach ( $methods as $method ) {
			$field_id = 'arnipay_payment_method_' . sanitize_key( $method['code'] );
			echo '<div class="arnipay-method-option">';
			echo '<input type="radio" id="' . esc_attr( $field_id ) . '" name="arnipay_payment_method" value="' . esc_attr( $method['code'] ) . '" required />';
			echo '<label for="' . esc_attr( $field_id ) . '">';
			echo '<span class="arnipay-method-option__radio" aria-hidden="true"></span>';
			echo '<span class="arnipay-method-option__icon"><img src="' . esc_url( $method['icon'] ) . '" alt="' . esc_attr( $method['title'] ) . '" /></span>';
			echo '<span class="arnipay-method-option__text">';
			echo '<strong>' . esc_html( $method['title'] ) . '</strong>';
			echo '<small>' . esc_html( $method['description'] ) . '</small>';
			echo '</span>';
			echo '</label>';
			echo '</div>';
		}

		echo '</div>';

		echo '<div class="arnipay-method-selector__security">';
		echo '<span class="arnipay-method-selector__shield" aria-hidden="true">✓</span>';
		echo '<div><strong>' . esc_html__( 'Tus pagos están protegidos con encriptación avanzada.', 'arnipay-woo' ) . '</strong><br />';
		echo '<span>' . esc_html__( 'Seguros, rápidos y confiables.', 'arnipay-woo' ) . '</span></div>';
		echo '</div>';

		echo '<p class="arnipay-method-selector__note">' . esc_html( $description ) . '</p>';
		echo '</div>';
	}

	/**
	 * Validate the customer-selected payment method before creating the payment.
	 *
	 * @return bool True when the selected method is valid.
	 */
	public function validate_customer_selection(): bool {
		$selected = $this->get_selected_customer_method_from_request();

		if ( '' === $selected ) {
			wc_add_notice( __( 'Seleccioná cómo querés pagar dentro de arnipay.', 'arnipay-woo' ), 'error' );
			return false;
		}

		if ( ! in_array( $selected, $this->get_available_customer_method_codes(), true ) ) {
			wc_add_notice( __( 'El método seleccionado para arnipay no está disponible.', 'arnipay-woo' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Get the customer-selected method from the checkout request.
	 *
	 * @return string Sanitized method code or empty string.
	 */
	public function get_selected_customer_method_from_request(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates checkout nonce before calling the gateway.
		if ( ! isset( $_POST['arnipay_payment_method'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return sanitize_key( wp_unslash( $_POST['arnipay_payment_method'] ) );
	}

	/**
	 * Get available method codes for the checkout selector.
	 *
	 * @return array<int,string>
	 */
	public function get_available_customer_method_codes(): array {
		return array_map(
			static function ( array $method ): string {
				return (string) $method['code'];
			},
			$this->get_checkout_methods_for_display()
		);
	}

	/**
	 * Build the list of methods that will be shown in the informative layout.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_checkout_methods_for_display(): array {
		$payment_methods = (array) $this->gateway->get_option( 'payment_methods', array() );
		$selected        = self::normalize_payment_methods( $payment_methods );
		$all             = self::get_supported_method_catalog();

		if ( empty( $selected ) ) {
			return array_values( $all );
		}

		$methods = array();
		foreach ( $selected as $code ) {
			if ( isset( $all[ $code ] ) ) {
				$methods[] = $all[ $code ];
			}
		}

		return empty( $methods ) ? array_values( $all ) : $methods;
	}

	/**
	 * Supported payment methods metadata for the checkout selector.
	 *
	 * Static + public so other parts of the plugin can reuse the catalog.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_supported_method_catalog(): array {
		return array(
			'personal' => array(
				'code'        => 'personal',
				'title'       => 'Personal Pay',
				'description' => __( 'Pagá rápido desde tu billetera Personal Pay.', 'arnipay-woo' ),
				'icon'        => self::get_img_url( 'pay-personal.svg' ),
			),
			'qr'       => array(
				'code'        => 'qr',
				'title'       => 'QR',
				'description' => __( 'Escaneá y pagá desde cualquier app bancaria compatible.', 'arnipay-woo' ),
				'icon'        => self::get_img_url( 'pay-qr.svg' ),
			),
			'tigo'     => array(
				'code'        => 'tigo',
				'title'       => 'tigo money',
				'description' => __( 'Pagá de forma fácil y segura con tigo money.', 'arnipay-woo' ),
				'icon'        => self::get_img_url( 'pay-tigo.svg' ),
			),
		);
	}
}
