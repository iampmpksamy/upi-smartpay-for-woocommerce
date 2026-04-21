<?php
/**
 * WooCommerce Payment Gateway — UPI SmartPay.
 *
 * @package PMPKSAMY_UPI_SmartPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core gateway class.
 */
class PMPKSAMY_UPI_Gateway extends WC_Payment_Gateway {

	// -----------------------------------------------------------------------
	// Constructor
	// -----------------------------------------------------------------------

	public function __construct() {
		$this->id                 = PMPKSAMY_UPI_GATEWAY_ID;
		$this->has_fields         = true;
		$this->method_title       = __( 'UPI SmartPay', 'upi-smartpay' );
		$this->method_description = __( 'Accept UPI payments with QR code display and manual admin verification.', 'upi-smartpay' );
		$this->supports           = array( 'products' );

		$this->icon = apply_filters(
			'pmpksamy_upi_icon',
			PMPKSAMY_UPI_URL . 'assets/images/upi-logo.svg'
		);

		$this->init_form_fields();
		$this->init_settings();
		$this->map_settings();

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);

		add_action(
			'woocommerce_thankyou_' . $this->id,
			array( $this, 'render_thankyou_panel' )
		);

		add_action( 'woocommerce_email_before_order_table', array( $this, 'inject_email_instructions' ), 10, 3 );
	}

	// -----------------------------------------------------------------------
	// Settings — form fields
	// -----------------------------------------------------------------------

	/** Define admin settings fields. */
	public function init_form_fields() {
		$this->form_fields = array(

			'enabled' => array(
				'title'   => __( 'Enable / Disable', 'upi-smartpay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable UPI SmartPay', 'upi-smartpay' ),
				'default' => 'yes',
			),

			'title' => array(
				'title'             => __( 'Title', 'upi-smartpay' ),
				'type'              => 'text',
				'description'       => __( 'Payment method title shown to customers at checkout.', 'upi-smartpay' ),
				'default'           => __( 'Pay via UPI', 'upi-smartpay' ),
				'desc_tip'          => true,
				'custom_attributes' => array( 'maxlength' => '50' ),
			),

			'description' => array(
				'title'       => __( 'Description', 'upi-smartpay' ),
				'type'        => 'textarea',
				'description' => __( 'Brief description shown beneath the payment title at checkout.', 'upi-smartpay' ),
				'default'     => __( 'Pay securely using any UPI app — GPay, PhonePe, Paytm, or BHIM.', 'upi-smartpay' ),
				'desc_tip'    => true,
			),

			'merchant_name' => array(
				'title'             => __( 'Merchant Name', 'upi-smartpay' ),
				'type'              => 'text',
				'description'       => __( 'Name displayed inside UPI apps during the payment flow.', 'upi-smartpay' ),
				'default'           => get_bloginfo( 'name' ),
				'desc_tip'          => true,
				'custom_attributes' => array( 'maxlength' => '80' ),
			),

			'upi_ids' => array(
				'title'             => __( 'UPI IDs', 'upi-smartpay' ),
				'type'              => 'textarea',
				'description'       => __( 'One UPI VPA per line (e.g. yourname@upi).', 'upi-smartpay' ),
				'default'           => '',
				'custom_attributes' => array( 'rows' => '5', 'placeholder' => "merchant@okaxis\nbusiness@ybl" ),
			),

			'upi_selection_mode' => array(
				'title'       => __( 'UPI ID Selection Mode', 'upi-smartpay' ),
				'type'        => 'select',
				'description' => __( 'How to pick a UPI ID when multiple are configured.', 'upi-smartpay' ),
				'options'     => array(
					'random'   => __( 'Random — pick one at random each order', 'upi-smartpay' ),
					'priority' => __( 'Priority — always use the first entry', 'upi-smartpay' ),
				),
				'default'     => 'random',
				'desc_tip'    => true,
			),

			'payment_instructions' => array(
				'title'             => __( 'Payment Instructions', 'upi-smartpay' ),
				'type'              => 'textarea',
				'description'       => __( 'Step-by-step instructions shown to customers on the thank-you page.', 'upi-smartpay' ),
				'default'           => implode( "\n", array(
					__( '1. Open GPay, PhonePe, Paytm or BHIM on your phone.', 'upi-smartpay' ),
					__( '2. Scan the QR code or enter the UPI ID manually.', 'upi-smartpay' ),
					__( '3. Enter the exact order amount shown above.', 'upi-smartpay' ),
					__( '4. Complete the payment inside your UPI app.', 'upi-smartpay' ),
					__( '5. Note the UTR / Reference number from your app.', 'upi-smartpay' ),
					__( '6. Click the "I Have Paid" button below and enter the UTR.', 'upi-smartpay' ),
				) ),
				'custom_attributes' => array( 'rows' => '8' ),
			),

			'allow_screenshot' => array(
				'title'   => __( 'Screenshot Upload', 'upi-smartpay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Allow customers to upload a payment screenshot', 'upi-smartpay' ),
				'default' => 'yes',
			),

			'debug_mode' => array(
				'title'       => __( 'Debug Logging', 'upi-smartpay' ),
				'type'        => 'checkbox',
				'label'       => __( 'Write debug messages to WooCommerce → Status → Logs', 'upi-smartpay' ),
				'default'     => 'no',
				'description' => __( 'Disable in production to avoid logging sensitive data.', 'upi-smartpay' ),
				'desc_tip'    => true,
			),
		);
	}

	/** Pull option values into object properties for convenient access. */
	private function map_settings() {
		$this->enabled              = $this->get_option( 'enabled', 'yes' );
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->merchant_name        = $this->get_option( 'merchant_name', get_bloginfo( 'name' ) );
		$this->upi_selection_mode   = $this->get_option( 'upi_selection_mode', 'random' );
		$this->payment_instructions = $this->get_option( 'payment_instructions' );
		$this->allow_screenshot     = $this->get_option( 'allow_screenshot', 'yes' );
		$this->debug_mode           = $this->get_option( 'debug_mode', 'no' );
	}

	// -----------------------------------------------------------------------
	// Admin options — override to add branded header & footer
	// -----------------------------------------------------------------------

	/** Render the branded settings header, standard form, then branded footer. */
	public function admin_options() {
		$this->render_settings_header();
		parent::admin_options();
		$this->render_settings_footer();
	}

	/** Branded header shown at the top of the gateway settings page. */
	private function render_settings_header() {
		?>
		<div class="pmpksamy-settings-header">
			<div class="pmpksamy-header-identity">
				<img
					src="<?php echo esc_url( PMPKSAMY_UPI_URL . 'assets/images/upi-logo.svg' ); ?>"
					alt="<?php esc_attr_e( 'UPI SmartPay', 'upi-smartpay' ); ?>"
					width="44" height="44"
				/>
				<div>
					<h2><?php esc_html_e( 'UPI SmartPay for WooCommerce', 'upi-smartpay' ); ?></h2>
					<p><?php esc_html_e( 'Smart UPI QR payment gateway', 'upi-smartpay' ); ?></p>
				</div>
			</div>
			<div class="pmpksamy-header-links">
				<a href="https://www.pmpksamy.com" target="_blank" rel="noopener noreferrer">
					🌐 <?php esc_html_e( 'pmpksamy.com', 'upi-smartpay' ); ?>
				</a>
				<a href="https://www.iampmpksamy.social" target="_blank" rel="noopener noreferrer">
					📱 <?php esc_html_e( 'Social Hub', 'upi-smartpay' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/** Branded footer shown below the settings form. */
	private function render_settings_footer() {
		?>
		<div class="pmpksamy-settings-footer">
			<?php
			echo PMPKSAMY_Helper::brand_signature(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — method returns kses-safe HTML.
			?>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Checkout display
	// -----------------------------------------------------------------------

	/** Render extra content on the checkout payment method panel. */
	public function payment_fields() {
		if ( $this->description ) {
			echo '<p class="pmpk-checkout-description">' . wp_kses_post( $this->description ) . '</p>';
		}

		echo '<div class="pmpk-checkout-apps">';
		echo '<span class="pmpk-apps-label">' . esc_html__( 'Supported apps:', 'upi-smartpay' ) . '</span>';

		foreach ( array( 'GPay', 'PhonePe', 'Paytm', 'BHIM', 'Amazon Pay' ) as $app ) {
			echo '<span class="pmpk-app-badge">' . esc_html( $app ) . '</span>';
		}
		echo '</div>';
	}

	/** Always valid — no customer input collected at checkout. */
	public function validate_fields() {
		return true;
	}

	// -----------------------------------------------------------------------
	// Payment processing
	// -----------------------------------------------------------------------

	/**
	 * Called by WooCommerce on checkout form submission.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array{result: string, redirect: string}
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Order not found. Please try again.', 'upi-smartpay' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$upi_id = $this->select_upi_id();

		if ( empty( $upi_id ) ) {
			wc_add_notice(
				__( 'UPI payment is temporarily unavailable. Please choose another payment method.', 'upi-smartpay' ),
				'error'
			);
			PMPKSAMY_Helper::log( 'No valid UPI ID configured.', 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_status(
			'on-hold',
			__( 'Awaiting UPI payment confirmation from customer.', 'upi-smartpay' )
		);

		$order->update_meta_data( '_pmpksamy_upi_id',        sanitize_text_field( $upi_id ) );
		$order->update_meta_data( '_pmpksamy_payment_status', 'pending' );
		$order->save();

		wc_reduce_stock_levels( $order_id );
		WC()->cart->empty_cart();

		PMPKSAMY_Helper::log( 'Order #' . $order_id . ' placed. UPI ID: ' . $upi_id );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	// -----------------------------------------------------------------------
	// Thank-you page
	// -----------------------------------------------------------------------

	/**
	 * Render the UPI payment panel on the WooCommerce thank-you page.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function render_thankyou_panel( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( ! in_array( $order->get_status(), array( 'on-hold', 'pending' ), true ) ) {
			return;
		}

		$upi_id         = $order->get_meta( '_pmpksamy_upi_id' );
		$amount         = $order->get_total();
		$merchant       = $this->merchant_name ?: get_bloginfo( 'name' );
		$order_ref      = 'Order-' . $order->get_order_number();
		$intent_str     = PMPKSAMY_QR_Generator::get_upi_string( $upi_id, $merchant, $amount, $order_ref );
		$qr_image_url   = PMPKSAMY_QR_Generator::get_qr_image_url( $intent_str, 256 );
		$deep_link      = PMPKSAMY_QR_Generator::get_deep_link( $upi_id, $merchant, $amount, $order_ref );
		$instructions   = $this->payment_instructions;
		$allow_ss       = ( 'yes' === $this->allow_screenshot );
		$payment_status = $order->get_meta( '_pmpksamy_payment_status' );
		$order_key      = $order->get_order_key();

		include PMPKSAMY_UPI_DIR . 'public/templates/thankyou.php';
	}

	// -----------------------------------------------------------------------
	// Email instructions
	// -----------------------------------------------------------------------

	/**
	 * Inject UPI payment details into the on-hold customer email.
	 *
	 * @param WC_Order $order         Current order.
	 * @param bool     $sent_to_admin Whether this email goes to the admin.
	 * @param bool     $plain_text    Plain-text email mode.
	 */
	public function inject_email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin
			|| 'on-hold' !== $order->get_status()
			|| $this->id !== $order->get_payment_method()
		) {
			return;
		}

		$upi_id = $order->get_meta( '_pmpksamy_upi_id' );
		$amount = $order->get_formatted_order_total();

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'UPI Payment Details', 'upi-smartpay' ) . "\n";
			/* translators: UPI VPA */
			echo esc_html__( 'UPI ID: ', 'upi-smartpay' ) . esc_html( $upi_id ) . "\n";
			echo esc_html__( 'Amount: ', 'upi-smartpay' ) . wp_strip_all_tags( $amount ) . "\n\n";
			echo esc_html__( 'Open your UPI app, pay the exact amount, then click "I Have Paid" on the order page.', 'upi-smartpay' ) . "\n";
		} else {
			?>
			<div style="margin:20px 0;padding:16px;background:#f0f6ff;border-left:4px solid #1a73e8;border-radius:4px;">
				<h3 style="margin:0 0 10px;color:#1a73e8;"><?php esc_html_e( 'Complete Your UPI Payment', 'upi-smartpay' ); ?></h3>
				<p style="margin:4px 0;">
					<strong><?php esc_html_e( 'UPI ID:', 'upi-smartpay' ); ?></strong>
					<?php echo esc_html( $upi_id ); ?>
				</p>
				<p style="margin:4px 0;">
					<strong><?php esc_html_e( 'Amount:', 'upi-smartpay' ); ?></strong>
					<?php echo wp_kses_post( $amount ); ?>
				</p>
				<p style="margin:12px 0 0;font-size:13px;color:#555;">
					<?php esc_html_e( 'Open your UPI app, scan the QR or enter the UPI ID, pay the exact amount, then click "I Have Paid" on the order page.', 'upi-smartpay' ); ?>
				</p>
			</div>
			<?php
		}
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Pick one UPI ID from the configured list.
	 *
	 * @return string Empty string when none are configured.
	 */
	public function select_upi_id() {
		$raw = $this->get_option( 'upi_ids', '' );
		$ids = PMPKSAMY_Helper::parse_upi_ids( $raw );

		if ( empty( $ids ) ) {
			return '';
		}

		if ( 'random' === $this->upi_selection_mode ) {
			return $ids[ array_rand( $ids ) ];
		}

		return $ids[0];
	}
}
