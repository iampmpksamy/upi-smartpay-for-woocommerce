<?php
/**
 * Plugin Name:       UPI SmartPay for WooCommerce
 * Plugin URI:        https://www.pmpksamy.com/plugins/upi-smartpay
 * Description:       Smart UPI QR payment gateway for WooCommerce. Accept UPI payments with QR codes, multiple UPI IDs, and manual verification.
 * Version:           1.0.0
 * Author:            iampmpksamy
 * Author URI:        https://www.pmpksamy.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       upi-smartpay
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:   8.5
 *
 * @package PMPKSAMY_UPI_SmartPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Plugin constants
// ---------------------------------------------------------------------------
define( 'PMPKSAMY_UPI_VERSION',     '1.0.0' );
define( 'PMPKSAMY_UPI_FILE',        __FILE__ );
define( 'PMPKSAMY_UPI_DIR',         plugin_dir_path( __FILE__ ) );
define( 'PMPKSAMY_UPI_URL',         plugin_dir_url( __FILE__ ) );
define( 'PMPKSAMY_UPI_BASE',        plugin_basename( __FILE__ ) );
define( 'PMPKSAMY_UPI_GATEWAY_ID',  'pmpksamy_upi_smartpay' );

// ---------------------------------------------------------------------------
// Activation hook
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, 'pmpksamy_upi_activate' );

/**
 * Creates the private screenshot upload directory and validates WooCommerce.
 */
function pmpksamy_upi_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'UPI SmartPay requires WooCommerce to be installed and active.', 'upi-smartpay' ),
			esc_html__( 'Plugin Activation Error', 'upi-smartpay' ),
			array( 'back_link' => true )
		);
	}

	$upload     = wp_upload_dir();
	$target_dir = $upload['basedir'] . '/pmpksamy-upi-screenshots';

	if ( ! file_exists( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $target_dir . '/.htaccess', "Options -Indexes\ndeny from all\n" );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $target_dir . '/index.php', '<?php // Silence is golden.' . PHP_EOL );
	}

	update_option( 'pmpksamy_upi_version', PMPKSAMY_UPI_VERSION );
}

// ---------------------------------------------------------------------------
// Deactivation hook
// ---------------------------------------------------------------------------
register_deactivation_hook( __FILE__, 'pmpksamy_upi_deactivate' );

/**
 * Cleans up transient cache on deactivation.
 */
function pmpksamy_upi_deactivate() {
	delete_transient( 'pmpksamy_upi_cache' );
}

// ---------------------------------------------------------------------------
// Main plugin class (singleton)
// ---------------------------------------------------------------------------

/**
 * Core loader — bootstraps all subsystems after WooCommerce is confirmed present.
 */
final class PMPKSAMY_UPI_SmartPay {

	/** @var PMPKSAMY_UPI_SmartPay|null */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return PMPKSAMY_UPI_SmartPay
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Private constructor — use ::instance(). */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'boot' ), 0 );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
	}

	/** Declare compatibility with WooCommerce High-Performance Order Storage. */
	public function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				PMPKSAMY_UPI_FILE,
				true
			);
		}
	}

	/** Boot the plugin once all plugins (including WooCommerce) are loaded. */
	public function boot() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}

		$this->load_textdomain();
		$this->load_files();
		$this->register_hooks();
	}

	/** Load the plugin text domain for i18n. */
	private function load_textdomain() {
		load_plugin_textdomain(
			'upi-smartpay',
			false,
			dirname( PMPKSAMY_UPI_BASE ) . '/languages/'
		);
	}

	/** Require all class files. */
	private function load_files() {
		require_once PMPKSAMY_UPI_DIR . 'includes/class-pmpk-helper.php';
		require_once PMPKSAMY_UPI_DIR . 'includes/class-pmpk-qr-generator.php';
		require_once PMPKSAMY_UPI_DIR . 'includes/class-pmpk-gateway.php';
		require_once PMPKSAMY_UPI_DIR . 'includes/class-pmpk-ajax.php';
		require_once PMPKSAMY_UPI_DIR . 'public/class-pmpk-public.php';

		if ( is_admin() ) {
			require_once PMPKSAMY_UPI_DIR . 'admin/class-pmpk-admin.php';
		}
	}

	/** Wire up WooCommerce hooks and instantiate subsystems. */
	private function register_hooks() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );

		new PMPKSAMY_UPI_Ajax();
		new PMPKSAMY_UPI_Public();

		if ( is_admin() ) {
			new PMPKSAMY_UPI_Admin();
		}

		add_filter(
			'plugin_action_links_' . PMPKSAMY_UPI_BASE,
			array( $this, 'plugin_action_links' )
		);
	}

	/**
	 * Register PMPKSAMY_UPI_Gateway with WooCommerce.
	 *
	 * @param string[] $gateways Registered gateway class names.
	 * @return string[]
	 */
	public function register_gateway( $gateways ) {
		$gateways[] = 'PMPKSAMY_UPI_Gateway';
		return $gateways;
	}

	/**
	 * Add a "Settings" shortcut on the Plugins list page.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function plugin_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . PMPKSAMY_UPI_GATEWAY_ID );
		array_unshift(
			$links,
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'upi-smartpay' ) . '</a>'
		);
		return $links;
	}

	/** Show admin notice when WooCommerce is missing. */
	public function missing_woocommerce_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php esc_html_e( 'UPI SmartPay for WooCommerce requires WooCommerce to be installed and active.', 'upi-smartpay' ); ?>
			</p>
		</div>
		<?php
	}
}

// Boot.
PMPKSAMY_UPI_SmartPay::instance();
