( function ( $ ) {
	'use strict';

	if ( typeof trendyolSyncAdmin === 'undefined' ) {
		return;
	}

	function bindAction( config ) {
		var $button = $( config.buttonSelector );
		var $status = $( config.statusSelector );

		if ( ! $button.length ) {
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
			setStatus( 'loading', config.loadingText );

			$.post( trendyolSyncAdmin.ajaxUrl, {
				action: config.action,
				nonce: config.nonce,
			} )
				.done( function ( response ) {
					if ( response && response.success ) {
						var msg =
							response.data && response.data.message
								? response.data.message
								: '';
						if (
							config.appendEnvironment &&
							response.data &&
							response.data.environment
						) {
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
					$button.prop( 'disabled', false ).text( config.buttonText );
				} );
		} );
	}

	bindAction( {
		buttonSelector: '#trendyol-check-connection',
		statusSelector: '#trendyol-connection-status',
		action: trendyolSyncAdmin.connection.action,
		nonce: trendyolSyncAdmin.connection.nonce,
		loadingText: trendyolSyncAdmin.i18n.checkingConnection,
		buttonText: trendyolSyncAdmin.i18n.connectionButton,
		appendEnvironment: true,
	} );

	bindAction( {
		buttonSelector: '#trendyol-sync-catalog',
		statusSelector: '#trendyol-catalog-status',
		action: trendyolSyncAdmin.catalog.action,
		nonce: trendyolSyncAdmin.catalog.nonce,
		loadingText: trendyolSyncAdmin.i18n.syncingCatalog,
		buttonText: trendyolSyncAdmin.i18n.catalogButton,
		appendEnvironment: false,
	} );
} )( jQuery );
