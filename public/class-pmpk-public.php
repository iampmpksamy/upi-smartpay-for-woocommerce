<?php
/**
 * Frontend asset loader and public-facing hooks.
 *
 * @package PMPKSAMY_UPI_SmartPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages frontend scripts, styles, and the localised data object passed to JS.
 */
class PMPKSAMY_UPI_Public {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/** Enqueue scripts and styles on relevant WooCommerce pages only. */
	public function enqueue_assets() {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		wp_enqueue_style(
			'pmpksamy-upi-frontend',
			PMPKSAMY_UPI_URL . 'assets/css/pmpk-frontend.css',
			array(),
			PMPKSAMY_UPI_VERSION
		);

		wp_enqueue_script(
			'pmpksamy-upi-frontend',
			PMPKSAMY_UPI_URL . 'assets/js/pmpk-frontend.js',
			array( 'jquery' ),
			PMPKSAMY_UPI_VERSION,
			true
		);

		wp_localize_script( 'pmpksamy-upi-frontend', 'pmpksamyFrontendData', $this->build_js_data() );
	}

	// -----------------------------------------------------------------------
	// JS data object
	// -----------------------------------------------------------------------

	/**
	 * Build the data object available as window.pmpksamyFrontendData in JS.
	 *
	 * @return array
	 */
	private function build_js_data() {
		$order_id  = 0;
		$order_key = '';

		if ( is_wc_endpoint_url( 'order-received' ) ) {
			$order_id  = absint( get_query_var( 'order-received' ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		}

		return array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'pmpksamy_upi_nonce' ),
			'orderId'         => $order_id,
			'orderKey'        => $order_key,
			'pollInterval'    => 15000,
			'maxPollAttempts' => 40,
			'i18n'            => array(
				'copying'         => __( 'Copying…',                                                    'upi-smartpay' ),
				'copied'          => __( 'Copied!',                                                      'upi-smartpay' ),
				'copyFailed'      => __( 'Copy failed — please copy manually.',                          'upi-smartpay' ),
				'submitting'      => __( 'Submitting…',                                                  'upi-smartpay' ),
				'utrRequired'     => __( 'Please enter your UTR / transaction reference number.',         'upi-smartpay' ),
				'uploading'       => __( 'Uploading screenshot…',                                        'upi-smartpay' ),
				'uploadSuccess'   => __( 'Screenshot uploaded successfully.',                            'upi-smartpay' ),
				'statusApproved'  => __( '✓ Payment approved! Your order is confirmed.',                 'upi-smartpay' ),
				'statusConfirmed' => __( '⏳ Payment confirmed. Waiting for admin verification.',        'upi-smartpay' ),
				'statusRejected'  => __( '✕ Payment was rejected. Please contact us.',                  'upi-smartpay' ),
				'genericError'    => __( 'Something went wrong. Please try again or contact support.',   'upi-smartpay' ),
			),
		);
	}

	// -----------------------------------------------------------------------
	// Helper
	// -----------------------------------------------------------------------

	/**
	 * Only load assets on checkout or order-received (thank-you) pages.
	 *
	 * @return bool
	 */
	private function should_load_assets() {
		return (
			function_exists( 'is_checkout' ) &&
			( is_checkout() || is_wc_endpoint_url( 'order-received' ) )
		);
	}
}
