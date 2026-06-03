<?php
/**
 * Admin renderer: produce el HTML de los bloques visuales en la pantalla
 * de ajustes del gateway (cabecera con estado, separadores de sección y
 * panel de verificación). No procesa formularios ni guarda nada.
 *
 * Las funciones generate_* siguen la convención de WooCommerce
 * (generate_{type}_html) y se enganchan en el form table del gateway.
 *
 * @package Arnipay_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arnipay_Woo_AW_Admin_Renderer {

	private WC_Payment_Gateway $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway $gateway Gateway instance.
	 */
	public function __construct( WC_Payment_Gateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Render the configuration status hero at the top of the settings table.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data (title, description).
	 * @return string HTML markup.
	 */
	public function admin_header( string $key, array $data ): string {
		unset( $key );

		$client_ready  = ! empty( $this->gateway->get_option( 'client_id' ) ) && ! empty( $this->gateway->get_option( 'client_secret' ) );
		$webhook_ready = ! empty( $this->gateway->get_option( 'webhook_secret' ) );
		$enabled       = 'yes' === $this->gateway->get_option( 'enabled' );

		if ( $enabled && $client_ready && $webhook_ready ) {
			$status_class = 'is-ready';
			$status_label = __( 'Listo para recibir pagos', 'arnipay-woo' );
			$status_text  = __( 'El método está activo y las credenciales principales están cargadas.', 'arnipay-woo' );
		} elseif ( $client_ready || $webhook_ready || $enabled ) {
			$status_class = 'is-warning';
			$status_label = __( 'Configuración pendiente', 'arnipay-woo' );
			$status_text  = __( 'Completá las credenciales, el webhook y guardá los cambios antes de operar.', 'arnipay-woo' );
		} else {
			$status_class = 'is-muted';
			$status_label = __( 'Aún no configurado', 'arnipay-woo' );
			$status_text  = __( 'Ingresá tus credenciales de arnipay para habilitar la pasarela.', 'arnipay-woo' );
		}

		$webhook_url = method_exists( $this->gateway, 'get_webhook_url' )
			? (string) $this->gateway->get_webhook_url()
			: add_query_arg( 'wc-api', strtolower( get_class( $this->gateway ) ), home_url( '/' ) );

		ob_start();
		?>
		<tr class="arnipay-admin-header-row">
			<td colspan="2">
				<div class="arnipay-admin-shell">
					<div class="arnipay-admin-hero">
						<div class="arnipay-admin-brand">
							<div class="arnipay-admin-logo">arnipay</div>
							<span class="arnipay-admin-pill"><?php esc_html_e( 'WooCommerce', 'arnipay-woo' ); ?></span>
						</div>
						<h2><?php echo esc_html( $data['title'] ); ?></h2>
						<p><?php echo esc_html( $data['description'] ); ?></p>
					</div>

					<div class="arnipay-admin-status-card <?php echo esc_attr( $status_class ); ?>">
						<span class="arnipay-admin-status-dot" aria-hidden="true"></span>
						<div>
							<strong><?php echo esc_html( $status_label ); ?></strong>
							<small><?php echo esc_html( $status_text ); ?></small>
						</div>
					</div>

					<div class="arnipay-admin-steps" aria-label="<?php esc_attr_e( 'Pasos de configuración', 'arnipay-woo' ); ?>">
						<div class="arnipay-admin-step <?php echo $client_ready ? 'is-complete' : ''; ?>">
							<span>1</span>
							<div>
								<strong><?php esc_html_e( 'Cargar credenciales', 'arnipay-woo' ); ?></strong>
								<small><?php esc_html_e( 'Client ID y Client Secret.', 'arnipay-woo' ); ?></small>
							</div>
						</div>
						<div class="arnipay-admin-step <?php echo $webhook_ready ? 'is-complete' : ''; ?>">
							<span>2</span>
							<div>
								<strong><?php esc_html_e( 'Configurar webhook', 'arnipay-woo' ); ?></strong>
								<small><?php esc_html_e( 'Copiar URL y cargar Webhook Secret.', 'arnipay-woo' ); ?></small>
							</div>
						</div>
						<div class="arnipay-admin-step <?php echo $enabled ? 'is-complete' : ''; ?>">
							<span>3</span>
							<div>
								<strong><?php esc_html_e( 'Activar en checkout', 'arnipay-woo' ); ?></strong>
								<small><?php esc_html_e( 'Guardar y probar una compra.', 'arnipay-woo' ); ?></small>
							</div>
						</div>
					</div>

					<div class="arnipay-admin-webhook-preview">
						<span><?php esc_html_e( 'Webhook URL', 'arnipay-woo' ); ?></span>
						<code><?php echo esc_html( $webhook_url ); ?></code>
					</div>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a visual section title separator inside the settings table.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 * @return string HTML markup.
	 */
	public function section_title( string $key, array $data ): string {
		$field_key = $this->gateway->get_field_key( $key );

		ob_start();
		?>
		<tr class="arnipay-section-row">
			<td colspan="2">
				<div class="arnipay-section-card">
					<h3><?php echo esc_html( $data['title'] ); ?></h3>
					<?php if ( ! empty( $data['description'] ) ) : ?>
						<p><?php echo wp_kses_post( $data['description'] ); ?></p>
					<?php endif; ?>
				</div>
				<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" value="" />
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the verification panel with the two AJAX buttons.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 * @return string HTML markup.
	 */
	public function verification_panel( string $key, array $data ): string {
		$field_key = $this->gateway->get_field_key( $key );
		$nonce     = wp_create_nonce( 'arnipay_woo_aw_verify' );

		ob_start();
		?>
		<tr class="arnipay-verification-row">
			<td colspan="2">
				<div class="arnipay-verification-card">
					<div class="arnipay-verification-head">
						<div>
							<h3><?php echo esc_html( $data['title'] ); ?></h3>
							<p><?php echo esc_html( $data['description'] ); ?></p>
						</div>
						<span class="arnipay-admin-pill is-soft"><?php esc_html_e( 'Recomendado antes de operar', 'arnipay-woo' ); ?></span>
					</div>

					<div class="arnipay-verify-grid">
						<div class="arnipay-verify-box">
							<strong><?php esc_html_e( 'Credenciales de API', 'arnipay-woo' ); ?></strong>
							<small><?php esc_html_e( 'Comprueba conexión con arnipay usando el Client ID y Client Secret cargados.', 'arnipay-woo' ); ?></small>
							<div class="arnipay-verify-row">
								<button type="button"
									class="button button-primary arnipay-verify-btn"
									id="arnipay-verify-credentials"
									data-action="arnipay_woo_aw_verify_credentials"
									data-nonce="<?php echo esc_attr( $nonce ); ?>">
									<?php esc_html_e( 'Verificar credenciales', 'arnipay-woo' ); ?>
								</button>
								<span class="arnipay-verify-result" id="arnipay-verify-credentials-result"></span>
							</div>
						</div>

						<div class="arnipay-verify-box">
							<strong><?php esc_html_e( 'Webhook de confirmación', 'arnipay-woo' ); ?></strong>
							<small><?php esc_html_e( 'Envía un POST firmado a la URL pública para confirmar que el webhook responde correctamente.', 'arnipay-woo' ); ?></small>
							<div class="arnipay-verify-row">
								<button type="button"
									class="button arnipay-verify-btn"
									id="arnipay-verify-webhook"
									data-action="arnipay_woo_aw_verify_webhook"
									data-nonce="<?php echo esc_attr( $nonce ); ?>">
									<?php esc_html_e( 'Verificar webhook', 'arnipay-woo' ); ?>
								</button>
								<span class="arnipay-verify-result" id="arnipay-verify-webhook-result"></span>
							</div>
						</div>
					</div>

					<p class="arnipay-verification-note">
						<?php esc_html_e( 'Sugerencia: las credenciales pueden probarse antes de guardar. Para verificar el webhook, guardá primero los cambios porque la prueba usa la configuración activa del sitio.', 'arnipay-woo' ); ?>
					</p>
				</div>
				<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" value="" />
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * No-op validation used for the visual-only field types.
	 *
	 * Returned as an empty string so WC stores nothing for these placeholders.
	 *
	 * @return string
	 */
	public static function noop_field(): string {
		return '';
	}
}
