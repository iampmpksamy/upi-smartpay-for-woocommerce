<?php
/**
 * AJAX action handlers — frontend customer interactions and admin actions.
 *
 * @package PMPKSAMY_UPI_SmartPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles all plugin AJAX endpoints.
 */
class PMPKSAMY_UPI_Ajax {

	public function __construct() {
		// Frontend (logged-in + guest).
		foreach ( array( 'pmpksamy_confirm_payment', 'pmpksamy_check_order_status', 'pmpksamy_upload_screenshot' ) as $action ) {
			add_action( 'wp_ajax_' . $action,        array( $this, $action ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, $action ) );
		}

		// Admin-only.
		add_action( 'wp_ajax_pmpksamy_admin_approve_payment', array( $this, 'pmpksamy_admin_approve_payment' ) );
		add_action( 'wp_ajax_pmpksamy_admin_reject_payment',  array( $this, 'pmpksamy_admin_reject_payment' ) );
		add_action( 'wp_ajax_pmpksamy_view_screenshot',       array( $this, 'pmpksamy_view_screenshot' ) );
	}

	// =======================================================================
	// Frontend: customer confirms payment
	// =======================================================================

	/** Record customer's "I Have Paid" confirmation with optional UTR. */
	public function pmpksamy_confirm_payment() {
		check_ajax_referer( 'pmpksamy_upi_nonce', 'nonce' );

		$order_id  = isset( $_POST['order_id'] )  ? absint( $_POST['order_id'] )                                   : 0;
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) )        : '';
		$utr       = isset( $_POST['utr'] )       ? sanitize_text_field( wp_unslash( $_POST['utr'] ) )              : '';

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'upi-smartpay' ) ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'upi-smartpay' ) ) );
		}

		if ( ! $this->can_access_order( $order, $order_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'upi-smartpay' ) ) );
		}

		if ( PMPKSAMY_UPI_GATEWAY_ID !== $order->get_payment_method() ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payment method for this order.', 'upi-smartpay' ) ) );
		}

		$current = $order->get_meta( '_pmpksamy_payment_status' );
		if ( in_array( $current, array( 'confirmed', 'approved' ), true ) ) {
			wp_send_json_success( array(
				'message' => __( 'Your payment confirmation has already been received.', 'upi-smartpay' ),
				'status'  => $current,
			) );
		}

		$order->update_meta_data( '_pmpksamy_payment_status', 'confirmed' );
		$order->update_meta_data( '_pmpksamy_confirmed_at',   current_time( 'mysql' ) );

		if ( $utr ) {
			$clean_utr = preg_replace( '/[^A-Za-z0-9]/', '', $utr );
			$order->update_meta_data( '_pmpksamy_utr_number', substr( $clean_utr, 0, 22 ) );
		}

		$order->save();

		$note = __( 'Customer confirmed UPI payment via the order page.', 'upi-smartpay' );
		if ( $utr ) {
			/* translators: %s: UTR / transaction reference number */
			$note .= ' ' . sprintf( __( 'UTR / Ref: %s', 'upi-smartpay' ), esc_html( $utr ) );
		}
		$order->add_order_note( $note );

		$this->notify_admin( $order );

		PMPKSAMY_Helper::log( 'Payment confirmed by customer for order #' . $order_id );

		wp_send_json_success( array(
			'message' => __( 'Thank you! Your payment confirmation has been recorded. We will verify and update your order shortly.', 'upi-smartpay' ),
			'status'  => 'confirmed',
		) );
	}

	// =======================================================================
	// Frontend: poll order status
	// =======================================================================

	/** Return the current order status and plugin payment status. */
	public function pmpksamy_check_order_status() {
		check_ajax_referer( 'pmpksamy_upi_nonce', 'nonce' );

		$order_id  = isset( $_POST['order_id'] )  ? absint( $_POST['order_id'] )                            : 0;
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'upi-smartpay' ) ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'upi-smartpay' ) ) );
		}

		if ( ! $this->can_access_order( $order, $order_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'upi-smartpay' ) ) );
		}

		wp_send_json_success( array(
			'order_status'   => $order->get_status(),
			'payment_status' => $order->get_meta( '_pmpksamy_payment_status' ),
			'status_label'   => wc_get_order_status_name( $order->get_status() ),
			'is_paid'        => $order->is_paid(),
		) );
	}

	// =======================================================================
	// Frontend: screenshot upload
	// =======================================================================

	/** Accept an image file upload and store it privately. */
	public function pmpksamy_upload_screenshot() {
		check_ajax_referer( 'pmpksamy_upi_nonce', 'nonce' );

		$order_id  = isset( $_POST['order_id'] )  ? absint( $_POST['order_id'] )                            : 0;
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'upi-smartpay' ) ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'upi-smartpay' ) ) );
		}

		if ( ! $this->can_access_order( $order, $order_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'upi-smartpay' ) ) );
		}

		$gateway = PMPKSAMY_Helper::get_gateway();
		if ( ! $gateway || 'yes' !== $gateway->get_option( 'allow_screenshot' ) ) {
			wp_send_json_error( array( 'message' => __( 'Screenshot upload is not enabled.', 'upi-smartpay' ) ) );
		}

		if ( empty( $_FILES['screenshot']['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was received.', 'upi-smartpay' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = $_FILES['screenshot'];

		$allowed_mime = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		$file_info    = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );

		if ( empty( $file_info['type'] ) || ! in_array( $file_info['type'], $allowed_mime, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.', 'upi-smartpay' ) ) );
		}

		if ( $file['size'] > 5 * MB_IN_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'File size exceeds the 5 MB limit.', 'upi-smartpay' ) ) );
		}

		$filename = 'order-' . $order_id . '-' . time() . '.' . $file_info['ext'];
		$target   = PMPKSAMY_Helper::get_screenshot_dir() . '/' . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			PMPKSAMY_Helper::log( 'Screenshot move_uploaded_file failed for order #' . $order_id, 'error' );
			wp_send_json_error( array( 'message' => __( 'Failed to save screenshot. Please try again.', 'upi-smartpay' ) ) );
		}

		$order->update_meta_data( '_pmpksamy_screenshot', $filename );
		$order->save();
		$order->add_order_note( __( 'Customer uploaded a payment screenshot.', 'upi-smartpay' ) );

		PMPKSAMY_Helper::log( 'Screenshot uploaded for order #' . $order_id );

		wp_send_json_success( array( 'message' => __( 'Screenshot uploaded successfully.', 'upi-smartpay' ) ) );
	}

	// =======================================================================
	// Admin: approve payment
	// =======================================================================

	/** Approve payment and transition order to Processing. */
	public function pmpksamy_admin_approve_payment() {
		check_ajax_referer( 'pmpksamy_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'upi-smartpay' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'upi-smartpay' ) ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'upi-smartpay' ) ) );
		}

		$order->payment_complete();
		$order->update_meta_data( '_pmpksamy_payment_status', 'approved' );
		$order->update_meta_data( '_pmpksamy_approved_at',    current_time( 'mysql' ) );
		$order->update_meta_data( '_pmpksamy_approved_by',    get_current_user_id() );
		$order->save();

		/* translators: %s: admin display name */
		$order->add_order_note( sprintf( __( 'UPI payment approved by %s.', 'upi-smartpay' ), wp_get_current_user()->display_name ) );

		PMPKSAMY_Helper::log( 'Order #' . $order_id . ' approved by user #' . get_current_user_id() );

		wp_send_json_success( array( 'message' => __( 'Payment approved. Order moved to Processing.', 'upi-smartpay' ) ) );
	}

	// =======================================================================
	// Admin: reject payment
	// =======================================================================

	/** Cancel the order and record a rejection reason. */
	public function pmpksamy_admin_reject_payment() {
		check_ajax_referer( 'pmpksamy_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'upi-smartpay' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] )                                        : 0;
		$reason   = isset( $_POST['reason'] )   ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) )           : '';

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'upi-smartpay' ) ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'upi-smartpay' ) ) );
		}

		$order->update_status( 'cancelled', __( 'UPI payment rejected by admin.', 'upi-smartpay' ) );
		$order->update_meta_data( '_pmpksamy_payment_status', 'rejected' );
		$order->save();

		$note = __( 'UPI payment rejected by admin.', 'upi-smartpay' );
		if ( $reason ) {
			/* translators: %s: rejection reason */
			$note .= ' ' . sprintf( __( 'Reason: %s', 'upi-smartpay' ), $reason );
		}
		$order->add_order_note( $note );

		PMPKSAMY_Helper::log( 'Order #' . $order_id . ' rejected. Reason: ' . $reason );

		wp_send_json_success( array( 'message' => __( 'Order cancelled and payment marked as rejected.', 'upi-smartpay' ) ) );
	}

	// =======================================================================
	// Admin: serve screenshot securely
	// =======================================================================

	/** Stream a stored screenshot to a capability-checked admin user. */
	public function pmpksamy_view_screenshot() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		check_ajax_referer( 'pmpksamy_view_screenshot_' . $order_id, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Access denied.', 'upi-smartpay' ), 403 );
		}

		$filename = isset( $_GET['filename'] ) ? sanitize_file_name( wp_unslash( $_GET['filename'] ) ) : '';

		if ( ! $filename || ! $order_id ) {
			wp_die( esc_html__( 'Invalid request.', 'upi-smartpay' ), 400 );
		}

		$file_path = PMPKSAMY_Helper::get_screenshot_dir() . '/' . $filename;

		if ( 0 !== strpos( $filename, 'order-' . $order_id . '-' ) ) {
			wp_die( esc_html__( 'File mismatch.', 'upi-smartpay' ), 403 );
		}

		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'File not found.', 'upi-smartpay' ), 404 );
		}

		$mime = mime_content_type( $file_path );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
			wp_die( esc_html__( 'Invalid file type.', 'upi-smartpay' ), 403 );
		}

		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $file_path );
		exit;
	}

	// =======================================================================
	// Private helpers
	// =======================================================================

	/**
	 * Determine whether the current request is authorised to access an order.
	 *
	 * @param WC_Order $order     Order to check.
	 * @param string   $order_key Order key from the request.
	 * @return bool
	 */
	private function can_access_order( $order, $order_key ) {
		if ( is_user_logged_in() ) {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				return true;
			}
			return (int) $order->get_customer_id() === get_current_user_id();
		}

		return $order->key_is_valid( $order_key );
	}

	/**
	 * Send an admin email when a customer confirms payment.
	 *
	 * @param WC_Order $order Confirmed order.
	 */
	private function notify_admin( $order ) {
		$admin_email = get_option( 'admin_email' );

		/* translators: 1: site name, 2: order number */
		$subject = sprintf(
			__( '[%1$s] UPI Payment Confirmed — Order #%2$s', 'upi-smartpay' ),
			get_bloginfo( 'name' ),
			$order->get_order_number()
		);

		/* translators: 1: order number, 2: admin order URL */
		$body = sprintf(
			__( "A customer has confirmed their UPI payment for Order #%1\$s.\n\nPlease verify and approve or reject from the order page:\n%2\$s", 'upi-smartpay' ),
			$order->get_order_number(),
			admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id() )
		);

		wp_mail( $admin_email, $subject, $body );
	}
}
