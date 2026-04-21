<?php
/**
 * Thank-you page UPI payment panel.
 *
 * Variables injected from PMPKSAMY_UPI_Gateway::render_thankyou_panel():
 *
 * @var WC_Order $order          Current order.
 * @var string   $upi_id         Assigned UPI VPA.
 * @var float    $amount         Order total.
 * @var string   $merchant       Merchant display name.
 * @var string   $intent_str     Full upi://pay?... string.
 * @var string   $qr_image_url   Google Charts QR image URL.
 * @var string   $deep_link      UPI deep-link URL for mobile button.
 * @var string   $instructions   Payment instruction text (newline-separated steps).
 * @var bool     $allow_ss       Whether screenshot upload is enabled.
 * @var string   $payment_status Current payment status (pending/confirmed/…).
 * @var string   $order_key      Order key for nonce-less guest verification.
 *
 * @package PMPKSAMY_UPI_SmartPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$order_id  = $order->get_id();
$formatted = $order->get_formatted_order_total();
?>
<div class="pmpk-upi-payment-panel" id="pmpk-upi-panel"
	 data-order-id="<?php echo esc_attr( $order_id ); ?>"
	 data-order-key="<?php echo esc_attr( $order_key ); ?>">

	<!-- =========================================================
	     Header
	     ========================================================= -->
	<div class="pmpk-panel-header">
		<div class="pmpk-panel-icon">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
				 stroke="currentColor" stroke-width="2" width="28" height="28" aria-hidden="true">
				<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
				<rect x="3" y="14" width="7" height="7" rx="1"/>
				<path d="M14 14h2v2h-2zm4 0h3v3h-3zm-4 4h3v3h-3zm4 2h3"/>
			</svg>
		</div>
		<div class="pmpk-panel-title">
			<h2><?php esc_html_e( 'Complete Your UPI Payment', 'upi-smartpay' ); ?></h2>
			<p class="pmpk-panel-subtitle">
				<?php esc_html_e( 'Scan the QR code or use the UPI ID below', 'upi-smartpay' ); ?>
			</p>
		</div>
	</div>

	<!-- =========================================================
	     Status bar (updated by JS polling)
	     ========================================================= -->
	<div class="pmpk-status-bar" id="pmpk-status-bar" role="status" aria-live="polite">
		<?php if ( 'confirmed' === $payment_status ) : ?>
			<span class="pmpk-status-confirmed">
				<?php esc_html_e( '⏳ Payment confirmed — awaiting admin verification.', 'upi-smartpay' ); ?>
			</span>
		<?php else : ?>
			<span class="pmpk-status-pending">
				<?php esc_html_e( '⌛ Waiting for your payment…', 'upi-smartpay' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<!-- =========================================================
	     Amount highlight
	     ========================================================= -->
	<div class="pmpk-amount-block">
		<span class="pmpk-amount-label"><?php esc_html_e( 'Amount to Pay', 'upi-smartpay' ); ?></span>
		<span class="pmpk-amount-value"><?php echo wp_kses_post( $formatted ); ?></span>
	</div>

	<!-- =========================================================
	     Main content grid: QR + details
	     ========================================================= -->
	<div class="pmpk-content-grid">

		<!-- QR Code -->
		<div class="pmpk-qr-wrapper">
			<div class="pmpk-qr-frame" id="pmpk-qr-frame" role="img"
				 aria-label="<?php esc_attr_e( 'UPI QR Code — tap to zoom', 'upi-smartpay' ); ?>">
				<img
					src="<?php echo esc_url( $qr_image_url ); ?>"
					alt="<?php esc_attr_e( 'UPI QR Code', 'upi-smartpay' ); ?>"
					class="pmpk-qr-image"
					width="256"
					height="256"
					loading="eager"
				/>
			</div>
			<p class="pmpk-qr-caption"><?php esc_html_e( 'Scan with any UPI app', 'upi-smartpay' ); ?></p>

			<!-- Mobile deep-link button -->
			<a href="<?php echo esc_url( $deep_link, array( 'upi' ) ); ?>"
			   class="pmpk-mobile-pay-btn pmpk-mobile-only"
			   aria-label="<?php esc_attr_e( 'Open in UPI App', 'upi-smartpay' ); ?>">
				<?php esc_html_e( 'Open UPI App', 'upi-smartpay' ); ?>
			</a>
		</div>

		<!-- UPI ID + Instructions -->
		<div class="pmpk-details-wrapper">

			<!-- UPI ID display -->
			<div class="pmpk-upi-id-block">
				<label class="pmpk-field-label" for="pmpk-upi-id-display">
					<?php esc_html_e( 'UPI ID', 'upi-smartpay' ); ?>
				</label>
				<div class="pmpk-upi-id-row">
					<input
						type="text"
						id="pmpk-upi-id-display"
						class="pmpk-upi-id-input"
						value="<?php echo esc_attr( $upi_id ); ?>"
						readonly
						aria-label="<?php esc_attr_e( 'UPI ID', 'upi-smartpay' ); ?>"
					/>
					<button type="button" class="pmpk-copy-btn" id="pmpk-copy-upi-id"
							data-value="<?php echo esc_attr( $upi_id ); ?>"
							title="<?php esc_attr_e( 'Copy UPI ID', 'upi-smartpay' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
							 fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
							<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
						</svg>
						<span><?php esc_html_e( 'Copy', 'upi-smartpay' ); ?></span>
					</button>
				</div>
			</div>

			<!-- Payment instructions -->
			<?php if ( $instructions ) : ?>
			<div class="pmpk-instructions-block">
				<h3 class="pmpk-instructions-heading"><?php esc_html_e( 'How to Pay', 'upi-smartpay' ); ?></h3>
				<div class="pmpk-instructions-text">
					<?php
					$steps = array_filter( explode( "\n", $instructions ) );
					if ( $steps ) {
						echo '<ol class="pmpk-steps-list">';
						foreach ( $steps as $step ) {
							$clean_step = trim( preg_replace( '/^\d+[.)]\s*/', '', $step ) );
							if ( $clean_step ) {
								echo '<li>' . esc_html( $clean_step ) . '</li>';
							}
						}
						echo '</ol>';
					}
					?>
				</div>
			</div>
			<?php endif; ?>

		</div>
	</div>

	<!-- =========================================================
	     Confirmation form
	     ========================================================= -->
	<?php if ( 'confirmed' !== $payment_status && 'approved' !== $payment_status ) : ?>
	<div class="pmpk-confirm-section" id="pmpk-confirm-section">
		<h3 class="pmpk-confirm-heading"><?php esc_html_e( 'Already Paid?', 'upi-smartpay' ); ?></h3>

		<div class="pmpk-utr-row">
			<label for="pmpk-utr-input" class="pmpk-field-label">
				<?php esc_html_e( 'UTR / Transaction Reference', 'upi-smartpay' ); ?>
				<span class="pmpk-required" aria-hidden="true">*</span>
			</label>
			<input
				type="text"
				id="pmpk-utr-input"
				class="pmpk-utr-input"
				placeholder="<?php esc_attr_e( 'Enter UTR or reference number', 'upi-smartpay' ); ?>"
				maxlength="22"
				autocomplete="off"
				aria-describedby="pmpk-utr-hint"
			/>
			<small id="pmpk-utr-hint" class="pmpk-field-hint">
				<?php esc_html_e( 'Find this in your UPI app under transaction details.', 'upi-smartpay' ); ?>
			</small>
		</div>

		<?php if ( $allow_ss ) : ?>
		<div class="pmpk-screenshot-row" id="pmpk-screenshot-row">
			<label for="pmpk-screenshot-input" class="pmpk-field-label">
				<?php esc_html_e( 'Payment Screenshot', 'upi-smartpay' ); ?>
				<span class="pmpk-optional"><?php esc_html_e( '(optional)', 'upi-smartpay' ); ?></span>
			</label>
			<div class="pmpk-file-drop-zone" id="pmpk-drop-zone" role="button" tabindex="0"
				 aria-label="<?php esc_attr_e( 'Click or drop a screenshot here', 'upi-smartpay' ); ?>">
				<input type="file" id="pmpk-screenshot-input" name="screenshot"
					   accept="image/jpeg,image/png,image/gif,image/webp"
					   class="pmpk-file-input" aria-hidden="true" tabindex="-1"/>
				<span class="pmpk-drop-icon" aria-hidden="true">📎</span>
				<span class="pmpk-drop-text"><?php esc_html_e( 'Click to choose or drag & drop', 'upi-smartpay' ); ?></span>
				<span class="pmpk-drop-meta"><?php esc_html_e( 'JPEG, PNG, WebP — max 5 MB', 'upi-smartpay' ); ?></span>
			</div>
			<div class="pmpk-file-preview" id="pmpk-file-preview" hidden></div>
			<div class="pmpk-upload-progress" id="pmpk-upload-progress" hidden
				 role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
				<div class="pmpk-progress-bar"></div>
			</div>
			<p class="pmpk-upload-message" id="pmpk-upload-message" aria-live="polite"></p>
		</div>
		<?php endif; ?>

		<button type="button" id="pmpk-confirm-btn" class="pmpk-confirm-btn">
			<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
				 fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
				<polyline points="20 6 9 17 4 12"/>
			</svg>
			<?php esc_html_e( 'I Have Paid', 'upi-smartpay' ); ?>
		</button>

		<p class="pmpk-confirm-message" id="pmpk-confirm-message" aria-live="assertive"></p>
	</div>
	<?php endif; ?>

	<!-- =========================================================
	     Post-confirmation notice
	     ========================================================= -->
	<?php if ( 'confirmed' === $payment_status ) : ?>
	<div class="pmpk-confirmed-notice" role="alert">
		<span class="pmpk-confirmed-icon" aria-hidden="true">✓</span>
		<div>
			<strong><?php esc_html_e( 'Payment Confirmed', 'upi-smartpay' ); ?></strong>
			<p><?php esc_html_e( 'We have received your confirmation and will verify it shortly. You will receive an email once your order is processed.', 'upi-smartpay' ); ?></p>
		</div>
	</div>
	<?php endif; ?>

	<!-- =========================================================
	     Branding footer
	     ========================================================= -->
	<div class="pmpk-panel-branding">
		<?php
		printf(
			/* translators: 1: plugin name link, 2: developer link */
			wp_kses(
				__( 'Powered by <a href="%1$s" target="_blank" rel="noopener noreferrer">UPI SmartPay</a> &middot; Developed by <a href="%2$s" target="_blank" rel="noopener noreferrer">Maalig (iampmpksamy)</a>', 'upi-smartpay' ),
				array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
			),
			'https://www.pmpksamy.com/plugins/upi-smartpay',
			'https://www.pmpksamy.com'
		);
		?>
	</div>

</div><!-- /.pmpk-upi-payment-panel -->
