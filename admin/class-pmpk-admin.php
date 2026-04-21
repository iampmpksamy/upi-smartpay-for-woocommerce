<?php
/**
 * Admin-specific functionality: order meta box, column, and asset management.
 *
 * @package PMPKSAMY_UPI_SmartPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds UPI payment info to WooCommerce order screens and the orders list.
 */
class PMPKSAMY_UPI_Admin {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Order detail (HPOS-compatible).
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_order_meta_box' ), 10, 1 );

		// Orders list table — HPOS.
		add_filter( 'woocommerce_shop_order_list_table_columns',          array( $this, 'add_list_column' ) );
		add_action( 'woocommerce_shop_order_list_table_custom_column',    array( $this, 'render_list_column' ), 10, 2 );

		// Orders list — Legacy CPT fallback.
		add_filter( 'manage_edit-shop_order_columns',                     array( $this, 'add_list_column' ) );
		add_action( 'manage_shop_order_posts_custom_column',              array( $this, 'render_list_column_cpt' ), 10, 2 );

		// Inline JS data for the current order.
		add_action( 'admin_footer', array( $this, 'admin_js_data' ) );

		// Branded footer on every admin page.
		add_action( 'admin_footer_text', array( $this, 'admin_footer_branding' ) );
	}

	// -----------------------------------------------------------------------
	// Assets
	// -----------------------------------------------------------------------

	/**
	 * Enqueue styles and scripts on WooCommerce order-related admin screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$order_screens = array( 'woocommerce_page_wc-orders', 'post.php', 'edit.php' );

		if ( ! in_array( $hook, $order_screens, true ) ) {
			return;
		}

		wp_enqueue_style(
			'pmpksamy-upi-admin',
			PMPKSAMY_UPI_URL . 'assets/css/pmpk-admin.css',
			array(),
			PMPKSAMY_UPI_VERSION
		);

		wp_enqueue_script(
			'pmpksamy-upi-admin',
			PMPKSAMY_UPI_URL . 'assets/js/pmpk-admin.js',
			array( 'jquery' ),
			PMPKSAMY_UPI_VERSION,
			true
		);

		wp_localize_script( 'pmpksamy-upi-admin', 'pmpksamyAdminData', array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'pmpksamy_admin_nonce' ),
			'confirmApprove' => __( 'Approve this UPI payment and move the order to Processing?', 'upi-smartpay' ),
			'confirmReject'  => __( 'Reject this UPI payment and cancel the order?', 'upi-smartpay' ),
			'reasonPrompt'   => __( 'Optional: enter a rejection reason for the order notes.', 'upi-smartpay' ),
			'approving'      => __( 'Approving…', 'upi-smartpay' ),
			'rejecting'      => __( 'Rejecting…', 'upi-smartpay' ),
		) );
	}

	// -----------------------------------------------------------------------
	// Order detail meta box
	// -----------------------------------------------------------------------

	/**
	 * Render UPI payment details inside the order edit screen.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function render_order_meta_box( $order ) {
		if ( PMPKSAMY_UPI_GATEWAY_ID !== $order->get_payment_method() ) {
			return;
		}

		$upi_id         = $order->get_meta( '_pmpksamy_upi_id' );
		$payment_status = $order->get_meta( '_pmpksamy_payment_status' );
		$utr_number     = $order->get_meta( '_pmpksamy_utr_number' );
		$confirmed_at   = $order->get_meta( '_pmpksamy_confirmed_at' );
		$approved_at    = $order->get_meta( '_pmpksamy_approved_at' );
		$screenshot     = $order->get_meta( '_pmpksamy_screenshot' );
		$order_id       = $order->get_id();
		?>
		<div class="pmpksamy-admin-upi-box">
			<h4 class="pmpksamy-admin-upi-title">
				<?php esc_html_e( 'UPI SmartPay Details', 'upi-smartpay' ); ?>
				<?php if ( $payment_status ) : ?>
					<span class="pmpk-status-badge pmpk-status-<?php echo esc_attr( $payment_status ); ?>">
						<?php echo esc_html( $this->get_payment_status_label( $payment_status ) ); ?>
					</span>
				<?php endif; ?>
			</h4>

			<table class="pmpk-admin-meta-table">
				<tr>
					<th><?php esc_html_e( 'UPI ID', 'upi-smartpay' ); ?></th>
					<td><?php echo esc_html( $upi_id ?: '—' ); ?></td>
				</tr>
				<?php if ( $utr_number ) : ?>
				<tr>
					<th><?php esc_html_e( 'UTR / Ref #', 'upi-smartpay' ); ?></th>
					<td><code><?php echo esc_html( $utr_number ); ?></code></td>
				</tr>
				<?php endif; ?>
				<?php if ( $confirmed_at ) : ?>
				<tr>
					<th><?php esc_html_e( 'Confirmed At', 'upi-smartpay' ); ?></th>
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $confirmed_at ) ) ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( $approved_at ) : ?>
				<tr>
					<th><?php esc_html_e( 'Approved At', 'upi-smartpay' ); ?></th>
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $approved_at ) ) ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( $screenshot ) : ?>
				<tr>
					<th><?php esc_html_e( 'Screenshot', 'upi-smartpay' ); ?></th>
					<td>
						<a href="<?php echo esc_url( PMPKSAMY_Helper::get_screenshot_admin_url( $screenshot, $order_id ) ); ?>"
						   target="_blank" rel="noopener noreferrer" class="pmpk-screenshot-link">
							<?php esc_html_e( 'View Screenshot', 'upi-smartpay' ); ?>
						</a>
					</td>
				</tr>
				<?php endif; ?>
			</table>

			<?php if ( in_array( $payment_status, array( 'pending', 'confirmed' ), true ) ) : ?>
				<div class="pmpk-admin-actions">
					<button type="button" class="button button-primary pmpk-approve-btn"
							data-order-id="<?php echo esc_attr( $order_id ); ?>">
						<?php esc_html_e( 'Approve Payment', 'upi-smartpay' ); ?>
					</button>
					<button type="button" class="button pmpk-reject-btn"
							data-order-id="<?php echo esc_attr( $order_id ); ?>">
						<?php esc_html_e( 'Reject Payment', 'upi-smartpay' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// -----------------------------------------------------------------------
	// Orders list column
	// -----------------------------------------------------------------------

	/**
	 * Register a "UPI Status" column.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_list_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new['pmpksamy_upi_status'] = __( 'UPI Status', 'upi-smartpay' );
			}
		}
		return $new;
	}

	/**
	 * Render UPI Status column content (HPOS).
	 *
	 * @param string   $column Column key.
	 * @param WC_Order $order  Current order.
	 */
	public function render_list_column( $column, $order ) {
		if ( 'pmpksamy_upi_status' !== $column ) {
			return;
		}
		if ( PMPKSAMY_UPI_GATEWAY_ID !== $order->get_payment_method() ) {
			echo '—';
			return;
		}
		$this->render_status_badge( $order->get_meta( '_pmpksamy_payment_status' ) );
	}

	/**
	 * Render UPI Status column content (CPT legacy).
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post / order ID.
	 */
	public function render_list_column_cpt( $column, $post_id ) {
		if ( 'pmpksamy_upi_status' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( ! $order || PMPKSAMY_UPI_GATEWAY_ID !== $order->get_payment_method() ) {
			echo '—';
			return;
		}
		$this->render_status_badge( $order->get_meta( '_pmpksamy_payment_status' ) );
	}

	// -----------------------------------------------------------------------
	// Admin footer branding
	// -----------------------------------------------------------------------

	/**
	 * Append brand signature to the WordPress admin footer only on plugin pages.
	 *
	 * @param string $text Existing footer text.
	 * @return string
	 */
	public function admin_footer_branding( $text ) {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return $text;
		}

		// Show on gateway settings page.
		$is_gateway_screen = (
			isset( $_GET['section'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			PMPKSAMY_UPI_GATEWAY_ID === sanitize_key( wp_unslash( $_GET['section'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		if ( ! $is_gateway_screen ) {
			return $text;
		}

		return PMPKSAMY_Helper::brand_signature(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// -----------------------------------------------------------------------
	// JS data
	// -----------------------------------------------------------------------

	/** Inline the current order ID for the admin JS module. */
	public function admin_js_data() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_order_screen = (
			'woocommerce_page_wc-orders' === $screen->id ||
			'shop_order' === $screen->post_type
		);

		if ( ! $is_order_screen ) {
			return;
		}

		$order_id = 0;
		if ( isset( $_GET['id'] ) ) {
			$order_id = absint( $_GET['id'] );
		} elseif ( isset( $GLOBALS['post']->ID ) ) {
			$order_id = absint( $GLOBALS['post']->ID );
		}

		if ( ! $order_id ) {
			return;
		}
		?>
		<script>window.pmpksamyCurrentOrderId = <?php echo absint( $order_id ); ?>;</script>
		<?php
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Output a coloured status badge span.
	 *
	 * @param string $status Internal payment status string.
	 */
	private function render_status_badge( $status ) {
		if ( ! $status ) {
			echo '<span class="pmpk-status-badge pmpk-status-unknown">—</span>';
			return;
		}
		echo '<span class="pmpk-status-badge pmpk-status-' . esc_attr( $status ) . '">'
			. esc_html( $this->get_payment_status_label( $status ) )
			. '</span>';
	}

	/**
	 * Human-readable label for an internal payment status key.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function get_payment_status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Pending',   'upi-smartpay' ),
			'confirmed' => __( 'Confirmed', 'upi-smartpay' ),
			'approved'  => __( 'Approved',  'upi-smartpay' ),
			'rejected'  => __( 'Rejected',  'upi-smartpay' ),
		);
		return $labels[ $status ] ?? ucfirst( $status );
	}
}
