<?php
/**
 * Static helper utilities used across the plugin.
 *
 * @package PMPKSAMY_UPI_SmartPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility methods — no state, all static.
 */
class PMPKSAMY_Helper {

	/**
	 * Return a configured instance of the UPI gateway, or null when unavailable.
	 *
	 * @return PMPKSAMY_UPI_Gateway|null
	 */
	public static function get_gateway() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return null;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		return isset( $gateways[ PMPKSAMY_UPI_GATEWAY_ID ] )
			? $gateways[ PMPKSAMY_UPI_GATEWAY_ID ]
			: null;
	}

	/**
	 * Write a message to the WooCommerce log when debug mode is on.
	 *
	 * @param string $message Log message.
	 * @param string $level   WC_Log_Levels constant (debug, info, warning, error).
	 */
	public static function log( $message, $level = 'debug' ) {
		$gateway = self::get_gateway();

		if ( ! $gateway || 'yes' !== $gateway->get_option( 'debug_mode' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->log( $level, $message, array( 'source' => 'upi-smartpay' ) );
	}

	/**
	 * Parse the multi-line UPI IDs setting into a clean indexed array.
	 *
	 * @param string $raw Raw textarea value from settings.
	 * @return string[]
	 */
	public static function parse_upi_ids( $raw ) {
		$lines = explode( "\n", $raw );
		$ids   = array();

		foreach ( $lines as $line ) {
			$id = trim( $line );
			if ( $id && strpos( $id, '@' ) !== false ) {
				$ids[] = $id;
			}
		}

		return array_values( $ids );
	}

	/**
	 * Validate a UPI VPA (Virtual Payment Address).
	 *
	 * @param string $upi_id UPI ID to validate.
	 * @return bool
	 */
	public static function is_valid_upi_id( $upi_id ) {
		return (bool) preg_match( '/^[a-zA-Z0-9.\-_]+@[a-zA-Z0-9.\-_]+$/', $upi_id );
	}

	/**
	 * Absolute filesystem path to the private screenshot upload directory.
	 *
	 * @return string
	 */
	public static function get_screenshot_dir() {
		$upload = wp_upload_dir();
		return trailingslashit( $upload['basedir'] ) . 'pmpksamy-upi-screenshots';
	}

	/**
	 * Return a capability-checked admin AJAX URL for viewing a stored screenshot.
	 *
	 * @param string $filename Stored filename (basename only).
	 * @param int    $order_id WooCommerce order ID.
	 * @return string
	 */
	public static function get_screenshot_admin_url( $filename, $order_id ) {
		return add_query_arg(
			array(
				'action'   => 'pmpksamy_view_screenshot',
				'filename' => rawurlencode( basename( $filename ) ),
				'order_id' => absint( $order_id ),
				'nonce'    => wp_create_nonce( 'pmpksamy_view_screenshot_' . $order_id ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Brand signature HTML — used in admin footer and settings page.
	 *
	 * @param bool $show_icon Include the globe icon.
	 * @return string Safe HTML.
	 */
	public static function brand_signature( $show_icon = true ) {
		$icon = $show_icon ? '🌐 ' : '';
		return wp_kses(
			sprintf(
				/* translators: 1: developer website link, 2: social hub link */
				__( 'Developed by <a href="%1$s" target="_blank" rel="noopener noreferrer"><strong>Maalig (iampmpksamy)</strong></a> &middot; %2$s<a href="%3$s" target="_blank" rel="noopener noreferrer">iampmpksamy.social</a>', 'upi-smartpay' ),
				'https://www.pmpksamy.com',
				$icon,
				'https://www.iampmpksamy.social'
			),
			array(
				'a'      => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
				'strong' => array(),
			)
		);
	}
}
