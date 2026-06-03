<?php
/**
 * Arnipay Plugin Main Class
 *
 * @package Arnipay_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class for Arnipay WooCommerce integration.
 */
class Arnipay_Woo_AW_Plugin {

	/**
	 * Absolute plugin path.
	 *
	 * @var string
	 */
	public string $plugin_path;

	/**
	 * Absolute plugin URL.
	 *
	 * @var string
	 */
	public string $plugin_url;

	/**
	 * Assets plugin URL.
	 *
	 * @var string
	 */
	public string $assets;

	/**
	 * Absolute path to plugin includes dir.
	 *
	 * @var string
	 */
	public string $includes_path;

	/**
	 * Absolute path to plugin lib dir.
	 *
	 * @var string
	 */
	public string $lib_path;

	/**
	 * Plugin bootstrap status.
	 *
	 * @var bool
	 */
	private bool $bootstrapped = false;

	/**
	 * WooCommerce Logger instance.
	 *
	 * @var WC_Logger
	 */
	public WC_Logger $logger;

	/**
	 * Constructor for the plugin.
	 *
	 * @param string $file    Plugin file path.
	 * @param string $version Plugin version.
	 */
	public function __construct(
		protected $file,
		protected $version
	) {
		$this->plugin_path = trailingslashit( plugin_dir_path( $this->file ) );
		$this->plugin_url = trailingslashit( plugin_dir_url( $this->file ) );
		$this->assets = $this->plugin_url . trailingslashit( 'assets' );
		$this->includes_path = $this->plugin_path . trailingslashit( 'includes' );
		$this->lib_path = $this->plugin_path . trailingslashit( 'lib' );
		$this->logger = new WC_Logger();
	}

	/**
	 * Initialize and run the Arnipay plugin.
	 *
	 * @throws Exception When plugin is already bootstrapped.
	 */
	public function run_arnipay(): void {
		try {
			if ( $this->bootstrapped ) {
				throw new Exception( 'arnipay for Woo can only be called once' );
			}
			$this->run();
			$this->bootstrapped = true;
		} catch ( Exception $e ) {
			if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
				add_action(
					'admin_notices',
					function () use ( $e ) {
						arnipay_woo_aw_notices( $e->getMessage() );
					}
				);
			}
		}
	}

	/**
	 * Load plugin dependencies and register hooks.
	 */
	private function run(): void {
		if ( ! class_exists( 'Arnipay\Gateway\Client' ) ) {
			require_once $this->lib_path . 'vendor/autoload.php';
		}

		if ( ! class_exists( 'Arnipay_Woo_AW' ) ) {
			require_once $this->includes_path . 'class-arnipay-woo-aw.php';
		}

		if ( ! class_exists( 'Arnipay_Woo_AW_Order_Resolver' ) ) {
			require_once $this->includes_path . 'domain/class-arnipay-woo-aw-order-resolver.php';
		}

		if ( ! class_exists( 'Arnipay_Woo_AW_Webhook_Handler' ) ) {
			require_once $this->includes_path . 'domain/class-arnipay-woo-aw-webhook-handler.php';
		}

		if ( ! class_exists( 'Arnipay_Woo_AW_Checkout_View' ) ) {
			require_once $this->includes_path . 'domain/class-arnipay-woo-aw-checkout-view.php';
		}

		if ( ! class_exists( 'Arnipay_Woo_AW_Admin_Renderer' ) ) {
			require_once $this->includes_path . 'domain/class-arnipay-woo-aw-admin-renderer.php';
		}

		if ( ! class_exists( 'Arnipay_Woo_AW_Verification_Service' ) ) {
			require_once $this->includes_path . 'domain/class-arnipay-woo-aw-verification-service.php';
		}

		require_once $this->includes_path . 'class-arnipay-woo-aw-gateway.php';

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), array( $this, 'plugin_action_links' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		$this->setup_update_checker();
	}

	/**
	 * Wire up the GitHub-based update checker.
	 *
	 * This makes the plugin appear in WordPress's normal "Plugin updates"
	 * UI whenever a newer GitHub release is published. The check is silent
	 * (cached for 12 hours by PUC) and the user only sees the standard
	 * WordPress update notice — no extra UI or settings to manage.
	 *
	 * If the library is not present (e.g. the lib/ folder was stripped),
	 * the plugin keeps working without auto-updates instead of failing.
	 *
	 * @return void
	 */
	private function setup_update_checker(): void {
		$loader = $this->lib_path . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

		if ( ! file_exists( $loader ) ) {
			return;
		}

		require_once $loader;

		// PUC v5 namespaces by version to avoid conflicts when several
		// plugins ship different PUC versions in the same WP install.
		$factory = '\\YahnisElsts\\PluginUpdateChecker\\v5p5\\PucFactory';

		if ( ! class_exists( $factory ) ) {
			return;
		}

		/*
		 * Public repository where releases are published. PUC reads this
		 * repo via the GitHub API and surfaces a new version in the
		 * WordPress "Plugin updates" UI whenever a release tagged with a
		 * higher version number is published on the tracked branch.
		 */
		$repo_url = 'https://github.com/arnipay/arnipay-woo/';

		$checker = $factory::buildUpdateChecker(
			$repo_url,
			$this->file,           // main plugin file
			'arnipay-woo'          // unique plugin slug
		);

		// Track the "main" branch — releases on that branch trigger updates.
		if ( method_exists( $checker, 'setBranch' ) ) {
			$checker->setBranch( 'main' );
		}

		/*
		 * Prefer release ZIP assets over the "Source code (zip)" GitHub
		 * generates automatically. When a release has a ZIP asset attached
		 * we use it; otherwise PUC falls back to the source-code zip. The
		 * .gitattributes export-ignore rules already strip tests/ and dev
		 * files from the auto-generated zip, so the fallback is also safe.
		 */
		if ( method_exists( $checker, 'getVcsApi' ) ) {
			$vcs_api = $checker->getVcsApi();
			if ( $vcs_api && method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
				$vcs_api->enableReleaseAssets();
			}
		}
	}

	/**
	 * Add Arnipay gateway to WooCommerce payment methods.
	 *
	 * @param array $methods Existing payment methods.
	 * @return array Updated payment methods.
	 */
	public function add_gateway( $methods ): array {
		$methods[] = 'Arnipay_Woo_AW_Gateway';
		return $methods;
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Updated plugin action links.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=arnipay_woo_aw' ) ),
			esc_html__( 'Configuraciones', 'arnipay-woo' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! function_exists( 'WC' ) || ! WC() || ! is_object( WC()->payment_gateways ) ) {
			return;
		}

		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( isset( $gateways[ ARNIPAY_WOO_AW_ID ] ) &&
			'yes' === $gateways[ ARNIPAY_WOO_AW_ID ]->enabled ) {
			wp_enqueue_style( ARNIPAY_WOO_AW_ID, $this->plugin_url . 'assets/css/arnipay.css', array(), $this->version, null );
			wp_enqueue_script( ARNIPAY_WOO_AW_ID . '-checkout', $this->plugin_url . 'assets/js/checkout.js', array( 'jquery' ), $this->version, true );
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		// Solo cargar en la página de configuración de WooCommerce > Pagos > Arnipay.
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		if ( ARNIPAY_WOO_AW_ID === $section ) {
			wp_enqueue_script(
				'arnipay-admin-settings',
				$this->plugin_url . 'assets/js/admin-settings.js',
				array( 'jquery', 'select2', 'wc-enhanced-select', 'jquery-ui-sortable' ),
				$this->version,
				true
			);

			wp_enqueue_style(
				'arnipay-admin-settings',
				$this->plugin_url . 'assets/css/admin-settings.css',
				array(),
				$this->version
			);
		}
	}


	/**
	 * Log a message to WooCommerce logs.
	 *
	 * Only plain strings are logged. Non-string values are reduced to their
	 * type instead of being dumped, to avoid leaking sensitive data (tokens,
	 * payloads, credentials) into log files.
	 *
	 * @param mixed $message Message to log.
	 */
	public function log( mixed $message ): void {
		if ( ! is_string( $message ) ) {
			$message = '[valor no textual omitido: ' . gettype( $message ) . ']';
		}

		// Cap the length so an attacker cannot flood the log via long input.
		if ( strlen( $message ) > 1000 ) {
			$message = substr( $message, 0, 1000 ) . '…';
		}

		$this->logger->add( 'arnipay_woo_aw', $message );
	}
}
