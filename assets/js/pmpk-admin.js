/**
 * UPI SmartPay for WooCommerce — Admin JavaScript
 *
 * Handles Approve / Reject button clicks on the order edit screen.
 * Requires: jQuery, window.pmpksamyAdminData (localised from PHP)
 *
 * @package PMPKSAMY_UPI_SmartPay
 */

/* global pmpksamyAdminData */
( function ( $ ) {
	'use strict';

	var cfg = window.pmpksamyAdminData || {};

	$( function () {
		initApproveButton();
		initRejectButton();
	} );

	// -----------------------------------------------------------------------
	// Approve
	// -----------------------------------------------------------------------
	function initApproveButton() {
		$( document ).on( 'click', '.pmpk-approve-btn', function () {
			var $btn    = $( this );
			var orderId = $btn.data( 'order-id' ) || window.pmpksamyCurrentOrderId;

			if ( ! orderId ) return;

			if ( ! window.confirm( cfg.confirmApprove || 'Approve this payment?' ) ) {
				return;
			}

			setButtonState( $btn, true, cfg.approving || 'Approving…' );

			$.post( cfg.ajaxUrl, {
				action:   'pmpksamy_admin_approve_payment',
				nonce:    cfg.nonce,
				order_id: orderId,
			} )
			.done( function ( response ) {
				if ( response.success ) {
					showToast( 'success', response.data.message );
					setTimeout( function () { window.location.reload(); }, 1200 );
				} else {
					showToast( 'error', response.data.message || 'Error approving payment.' );
					setButtonState( $btn, false, 'Approve Payment' );
				}
			} )
			.fail( function () {
				showToast( 'error', 'Request failed. Please try again.' );
				setButtonState( $btn, false, 'Approve Payment' );
			} );
		} );
	}

	// -----------------------------------------------------------------------
	// Reject
	// -----------------------------------------------------------------------
	function initRejectButton() {
		$( document ).on( 'click', '.pmpk-reject-btn', function () {
			var $btn    = $( this );
			var orderId = $btn.data( 'order-id' ) || window.pmpksamyCurrentOrderId;

			if ( ! orderId ) return;

			if ( ! window.confirm( cfg.confirmReject || 'Reject this payment?' ) ) {
				return;
			}

			var reason = window.prompt( cfg.reasonPrompt || 'Rejection reason (optional):' );
			if ( null === reason ) return;

			setButtonState( $btn, true, cfg.rejecting || 'Rejecting…' );

			$.post( cfg.ajaxUrl, {
				action:   'pmpksamy_admin_reject_payment',
				nonce:    cfg.nonce,
				order_id: orderId,
				reason:   reason,
			} )
			.done( function ( response ) {
				if ( response.success ) {
					showToast( 'success', response.data.message );
					setTimeout( function () { window.location.reload(); }, 1200 );
				} else {
					showToast( 'error', response.data.message || 'Error rejecting payment.' );
					setButtonState( $btn, false, 'Reject Payment' );
				}
			} )
			.fail( function () {
				showToast( 'error', 'Request failed. Please try again.' );
				setButtonState( $btn, false, 'Reject Payment' );
			} );
		} );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	function setButtonState( $btn, disabled, label ) {
		$btn.prop( 'disabled', disabled ).text( label );
	}

	function showToast( type, message ) {
		var $toast = $( '<div>', { class: 'pmpk-admin-toast ' + type, text: message } );
		$( 'body' ).append( $toast );
		setTimeout( function () {
			$toast.fadeOut( 300, function () { $( this ).remove(); } );
		}, 3500 );
	}

} )( jQuery );
