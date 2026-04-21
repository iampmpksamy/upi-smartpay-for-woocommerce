<?php
/**
 * QR code generation helpers.
 *
 * Uses the Google Charts API to render QR codes without bundling a PHP image
 * library. A filter is provided so site owners can swap in a self-hosted QR
 * service for full data privacy.
 *
 * @package PMPKSAMY_UPI_SmartPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds UPI intent strings and returns QR image URLs.
 */
class PMPKSAMY_QR_Generator {

	/**
	 * Build a UPI payment intent string (upi://pay?...).
	 *
	 * @param string     $upi_id        Payee UPI VPA.
	 * @param string     $merchant_name Payee display name.
	 * @param float|null $amount        Transaction amount (null = open amount).
	 * @param string     $note          Short transaction note / order reference.
	 * @return string
	 */
	public static function get_upi_string( $upi_id, $merchant_name, $amount = null, $note = '' ) {
		$params = array(
			'pa' => sanitize_text_field( $upi_id ),
			'pn' => sanitize_text_field( $merchant_name ),
			'cu' => 'INR',
		);

		if ( null !== $amount && $amount > 0 ) {
			$params['am'] = number_format( (float) $amount, 2, '.', '' );
		}

		if ( $note ) {
			$params['tn'] = substr( sanitize_text_field( $note ), 0, 50 );
		}

		$query = http_build_query( $params, '', '&' );

		/**
		 * Filters the final UPI intent string.
		 *
		 * @param string $upi_string Full upi://pay?... string.
		 * @param array  $params     Query parameter array before encoding.
		 */
		return apply_filters( 'pmpksamy_upi_intent_string', 'upi://pay?' . $query, $params );
	}

	/**
	 * Return an <img> src URL for the QR code (Google Charts API).
	 *
	 * @param string $data UPI intent string to encode.
	 * @param int    $size Image dimension in pixels (square).
	 * @return string Absolute URL safe for use in src="".
	 */
	public static function get_qr_image_url( $data, $size = 256 ) {
		$size = max( 64, min( 600, absint( $size ) ) );

		$url = add_query_arg(
			array(
				'chs'  => $size . 'x' . $size,
				'cht'  => 'qr',
				'chl'  => rawurlencode( $data ),
				'choe' => 'UTF-8',
				'chld' => 'M|2',
			),
			'https://chart.googleapis.com/chart'
		);

		/**
		 * Filters the QR image URL.
		 * Replace with a self-hosted QR service for full data privacy.
		 *
		 * @param string $url  Generated QR image URL.
		 * @param string $data UPI intent string.
		 * @param int    $size Image size in pixels.
		 */
		return apply_filters( 'pmpksamy_qr_image_url', $url, $data, $size );
	}

	/**
	 * Build a UPI deep-link href for mobile "Pay Now" buttons.
	 *
	 * @param string $upi_id    Payee UPI VPA.
	 * @param string $merchant  Payee display name.
	 * @param float  $amount    Transaction amount.
	 * @param string $order_ref Order reference string.
	 * @return string
	 */
	public static function get_deep_link( $upi_id, $merchant, $amount, $order_ref = '' ) {
		$intent = self::get_upi_string( $upi_id, $merchant, $amount, $order_ref );
		return esc_url( $intent, array( 'upi' ) );
	}
}
