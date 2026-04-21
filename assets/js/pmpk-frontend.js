/**
 * UPI SmartPay for WooCommerce — Frontend JavaScript
 *
 * Responsibilities:
 *  - Copy UPI ID to clipboard
 *  - QR code tap-to-zoom lightbox
 *  - Screenshot drag-and-drop upload
 *  - "I Have Paid" confirmation via AJAX
 *  - Periodic order status polling
 *
 * Requires: jQuery (loaded by WordPress), window.pmpksamyFrontendData
 *
 * @package PMPKSAMY_UPI_SmartPay
 */

/* global pmpksamyFrontendData */
( function ( $ ) {
	'use strict';

	var cfg  = window.pmpksamyFrontendData || {};
	var i18n = cfg.i18n || {};

	var state = {
		screenshotUploaded: false,
		pollTimer:          null,
		pollCount:          0,
		confirmed:          false,
	};

	// -----------------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------------
	$( function () {
		if ( ! $( '#pmpk-upi-panel' ).length ) {
			return;
		}

		initCopyButton();
		initQRLightbox();
		initDropZone();
		initConfirmButton();
		startPolling();
	} );

	// =======================================================================
	// Copy UPI ID
	// =======================================================================
	function initCopyButton() {
		var $btn = $( '#pmpk-copy-upi-id' );
		if ( ! $btn.length ) return;

		$btn.on( 'click', function () {
			var value = $btn.data( 'value' ) || $( '#pmpk-upi-id-display' ).val();
			if ( ! value ) return;

			$btn.find( 'span' ).text( i18n.copying || 'Copying…' );
			$btn.prop( 'disabled', true );

			copyToClipboard( value )
				.then( function () {
					$btn.addClass( 'pmpk-copied' );
					$btn.find( 'span' ).text( i18n.copied || 'Copied!' );
					setTimeout( function () {
						$btn.removeClass( 'pmpk-copied' );
						$btn.find( 'span' ).text( 'Copy' );
						$btn.prop( 'disabled', false );
					}, 2000 );
				} )
				.catch( function () {
					$btn.find( 'span' ).text( i18n.copyFailed || 'Copy failed' );
					$btn.prop( 'disabled', false );
				} );
		} );
	}

	/**
	 * Copy text using Clipboard API with textarea fallback.
	 *
	 * @param {string} text
	 * @returns {Promise}
	 */
	function copyToClipboard( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}

		return new Promise( function ( resolve, reject ) {
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0';
			document.body.appendChild( ta );
			ta.focus();
			ta.select();
			try {
				document.execCommand( 'copy' );
				document.body.removeChild( ta );
				resolve();
			} catch ( e ) {
				document.body.removeChild( ta );
				reject( e );
			}
		} );
	}

	// =======================================================================
	// QR lightbox
	// =======================================================================
	function initQRLightbox() {
		$( '#pmpk-qr-frame' ).on( 'click', function () {
			var src = $( this ).find( 'img' ).attr( 'src' );
			if ( ! src ) return;

			var $overlay = $( '<div>', { class: 'pmpk-qr-modal-overlay', role: 'dialog', 'aria-modal': 'true' } );
			var $inner   = $( '<div>', { class: 'pmpk-qr-modal-inner' } );

			$inner.append( $( '<img>', { src: src, alt: 'UPI QR Code' } ) );
			$overlay.append( $inner );
			$( 'body' ).append( $overlay );

			$overlay.on( 'click', function () { $overlay.remove(); } );

			$( document ).one( 'keydown.pmpkModal', function ( e ) {
				if ( 'Escape' === e.key ) {
					$overlay.remove();
					$( document ).off( 'keydown.pmpkModal' );
				}
			} );
		} );
	}

	// =======================================================================
	// Screenshot drag-and-drop
	// =======================================================================
	function initDropZone() {
		var $zone  = $( '#pmpk-drop-zone' );
		var $input = $( '#pmpk-screenshot-input' );

		if ( ! $zone.length ) return;

		$zone.on( 'keypress click', function ( e ) {
			if ( 'click' === e.type || 'Enter' === e.key || ' ' === e.key ) {
				$input.trigger( 'click' );
			}
		} );

		$input.on( 'click', function ( e ) { e.stopPropagation(); } );
		$input.on( 'change', function () { handleFileSelected( this.files ); } );

		$zone.on( 'dragover dragenter', function ( e ) {
			e.preventDefault();
			$zone.addClass( 'pmpk-dragover' );
		} );

		$zone.on( 'dragleave dragend', function () {
			$zone.removeClass( 'pmpk-dragover' );
		} );

		$zone.on( 'drop', function ( e ) {
			e.preventDefault();
			$zone.removeClass( 'pmpk-dragover' );
			var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
			if ( files && files.length ) {
				handleFileSelected( files );
			}
		} );
	}

	/**
	 * Preview and upload a chosen file.
	 *
	 * @param {FileList} files
	 */
	function handleFileSelected( files ) {
		if ( ! files || ! files[0] ) return;

		var file      = files[0];
		var $preview  = $( '#pmpk-file-preview' );
		var $msg      = $( '#pmpk-upload-message' );
		var $progress = $( '#pmpk-upload-progress' );
		var $bar      = $progress.find( '.pmpk-progress-bar' );

		// Client-side validation.
		var allowed = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
		if ( -1 === allowed.indexOf( file.type ) ) {
			showUploadMessage( $msg, 'error', 'Invalid file type. Only JPEG, PNG, GIF, WebP allowed.' );
			return;
		}
		if ( file.size > 5 * 1024 * 1024 ) {
			showUploadMessage( $msg, 'error', 'File size must not exceed 5 MB.' );
			return;
		}

		// Preview.
		var reader = new FileReader();
		reader.onload = function ( e ) {
			$preview.html( '<img src="' + e.target.result + '" alt="Preview" />' );
			$preview.prop( 'hidden', false );
		};
		reader.readAsDataURL( file );

		// Upload.
		var formData = new FormData();
		formData.append( 'action',     'pmpksamy_upload_screenshot' );
		formData.append( 'nonce',      cfg.nonce );
		formData.append( 'order_id',   cfg.orderId );
		formData.append( 'order_key',  cfg.orderKey );
		formData.append( 'screenshot', file );

		$progress.prop( 'hidden', false );
		$bar.css( 'width', '0%' );
		showUploadMessage( $msg, '', i18n.uploading || 'Uploading…' );

		$.ajax( {
			url:         cfg.ajaxUrl,
			type:        'POST',
			data:        formData,
			processData: false,
			contentType: false,
			xhr: function () {
				var xhr = new XMLHttpRequest();
				xhr.upload.addEventListener( 'progress', function ( e ) {
					if ( e.lengthComputable ) {
						$bar.css( 'width', Math.round( ( e.loaded / e.total ) * 100 ) + '%' );
					}
				} );
				return xhr;
			},
			success: function ( response ) {
				$progress.prop( 'hidden', true );
				if ( response.success ) {
					state.screenshotUploaded = true;
					showUploadMessage( $msg, 'success', response.data.message || i18n.uploadSuccess );
				} else {
					showUploadMessage( $msg, 'error', response.data.message || i18n.genericError );
				}
			},
			error: function () {
				$progress.prop( 'hidden', true );
				showUploadMessage( $msg, 'error', i18n.genericError );
			},
		} );
	}

	function showUploadMessage( $el, type, text ) {
		$el.removeClass( 'success error' );
		if ( type ) $el.addClass( type );
		$el.text( text );
	}

	// =======================================================================
	// "I Have Paid" confirmation
	// =======================================================================
	function initConfirmButton() {
		var $btn = $( '#pmpk-confirm-btn' );
		if ( ! $btn.length ) return;

		$btn.on( 'click', function () {
			var utr = $.trim( $( '#pmpk-utr-input' ).val() );

			if ( ! utr ) {
				setConfirmMessage( 'error', i18n.utrRequired || 'Please enter your UTR number.' );
				$( '#pmpk-utr-input' ).focus();
				return;
			}

			$btn.prop( 'disabled', true ).text( i18n.submitting || 'Submitting…' );
			setConfirmMessage( '', '' );

			$.post( cfg.ajaxUrl, {
				action:    'pmpksamy_confirm_payment',
				nonce:     cfg.nonce,
				order_id:  cfg.orderId,
				order_key: cfg.orderKey,
				utr:       utr,
			} )
			.done( function ( response ) {
				if ( response.success ) {
					state.confirmed = true;
					setConfirmMessage( 'success', response.data.message );
					$( '#pmpk-confirm-section' ).slideUp( 400 );
					updateStatusBar( 'confirmed' );
					setTimeout( function () { window.location.reload(); }, 4000 );
				} else {
					setConfirmMessage( 'error', response.data.message || i18n.genericError );
					$btn.prop( 'disabled', false ).text( 'I Have Paid' );
				}
			} )
			.fail( function () {
				setConfirmMessage( 'error', i18n.genericError );
				$btn.prop( 'disabled', false ).text( 'I Have Paid' );
			} );
		} );
	}

	function setConfirmMessage( type, text ) {
		var $msg = $( '#pmpk-confirm-message' );
		$msg.removeClass( 'success error' );
		if ( type ) $msg.addClass( type );
		$msg.text( text );
	}

	// =======================================================================
	// Order status polling
	// =======================================================================
	function startPolling() {
		if ( ! cfg.orderId || state.confirmed ) return;
		state.pollTimer = setInterval( pollStatus, cfg.pollInterval || 15000 );
	}

	function pollStatus() {
		state.pollCount++;

		if ( state.pollCount > ( cfg.maxPollAttempts || 40 ) ) {
			clearInterval( state.pollTimer );
			return;
		}

		$.post( cfg.ajaxUrl, {
			action:    'pmpksamy_check_order_status',
			nonce:     cfg.nonce,
			order_id:  cfg.orderId,
			order_key: cfg.orderKey,
		} )
		.done( function ( response ) {
			if ( ! response.success ) return;

			var data = response.data;
			updateStatusBar( data.payment_status, data.order_status );

			if ( data.is_paid || 'approved' === data.payment_status ) {
				clearInterval( state.pollTimer );
				window.location.reload();
			}

			if ( 'rejected' === data.payment_status ) {
				clearInterval( state.pollTimer );
			}
		} );
	}

	/**
	 * Update the visible status bar.
	 *
	 * @param {string} paymentStatus
	 */
	function updateStatusBar( paymentStatus ) {
		var $bar  = $( '#pmpk-status-bar' );
		var $span = $bar.find( 'span' );

		var messages = {
			pending:   i18n.statusPending   || '⌛ Waiting for your payment…',
			confirmed: i18n.statusConfirmed || '⏳ Payment confirmed — awaiting verification.',
			approved:  i18n.statusApproved  || '✓ Payment approved! Your order is confirmed.',
			rejected:  i18n.statusRejected  || '✕ Payment was rejected. Please contact us.',
		};

		var cssMap = {
			pending:   'pmpk-status-pending',
			confirmed: 'pmpk-status-confirmed',
			approved:  'pmpk-status-approved',
			rejected:  'pmpk-status-rejected',
		};

		if ( messages[ paymentStatus ] ) {
			$span.attr( 'class', cssMap[ paymentStatus ] || '' ).text( messages[ paymentStatus ] );
		}
	}

} )( jQuery );
