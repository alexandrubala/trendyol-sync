( function ( $ ) {
	'use strict';

	var $button = $( '#trendyol-check-connection' );
	var $status = $( '#trendyol-connection-status' );

	if ( ! $button.length || typeof trendyolSyncAdmin === 'undefined' ) {
		return;
	}

	function setStatus( type, message ) {
		$status
			.removeClass( 'is-success is-error is-loading' )
			.addClass( type ? 'is-' + type : '' )
			.text( message || '' );
	}

	$button.on( 'click', function () {
		if ( $button.prop( 'disabled' ) ) {
			return;
		}

		$button.prop( 'disabled', true );
		setStatus( 'loading', trendyolSyncAdmin.i18n.checking );

		$.post( trendyolSyncAdmin.ajaxUrl, {
			action: trendyolSyncAdmin.action,
			nonce: trendyolSyncAdmin.nonce,
		} )
			.done( function ( response ) {
				if ( response && response.success ) {
					var msg = response.data && response.data.message ? response.data.message : '';
					if ( response.data && response.data.environment ) {
						msg += ' (' + response.data.environment + ')';
					}
					setStatus( 'success', msg );
				} else {
					var err =
						response && response.data && response.data.message
							? response.data.message
							: '';
					setStatus( 'error', err );
				}
			} )
			.fail( function ( xhr ) {
				var message =
					xhr.responseJSON &&
					xhr.responseJSON.data &&
					xhr.responseJSON.data.message
						? xhr.responseJSON.data.message
						: '';
				setStatus( 'error', message );
			} )
			.always( function () {
				$button.prop( 'disabled', false ).text( trendyolSyncAdmin.i18n.button );
			} );
	} );
} )( jQuery );
