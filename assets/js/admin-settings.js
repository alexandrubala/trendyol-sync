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

	(function bindSyncAction() {
		var $button = $('#trendyol-start-sync');
		var $status = $('#trendyol-sync-status');
		var currentJobId = 0;

		if (!$button.length || typeof trendyolSyncAdmin.sync === 'undefined') {
			return;
		}

		function setStatus(type, message) {
			$status
				.removeClass('is-success is-error is-loading')
				.addClass(type ? 'is-' + type : '')
				.text(message || '');
		}

		function pollStatus() {
			$.post(trendyolSyncAdmin.ajaxUrl, {
				action: trendyolSyncAdmin.sync.statusAction,
				nonce: trendyolSyncAdmin.sync.nonce,
				job_id: currentJobId || 0
			}).done(function (response) {
				if (!response || !response.success || !response.data || !response.data.has_job) {
					setStatus('', trendyolSyncAdmin.i18n.statusIdle);
					return;
				}

				var data = response.data;
				currentJobId = data.job_id || currentJobId;
				var msg = 'Job #' + data.job_id + ': ' + data.status + ' (' + data.processed + '/' + data.total + ', ' + data.progress + '%)';
				setStatus('loading', msg);

				if (data.status === 'completed' || data.status === 'failed') {
					setStatus(data.status === 'completed' ? 'success' : 'error', msg);
					$button.prop('disabled', false).text(trendyolSyncAdmin.i18n.syncButton);
					return;
				}

				window.setTimeout(pollStatus, 3000);
			}).fail(function () {
				setStatus('error', '');
				$button.prop('disabled', false).text(trendyolSyncAdmin.i18n.syncButton);
			});
		}

		$button.on('click', function () {
			if ($button.prop('disabled')) {
				return;
			}

			$button.prop('disabled', true);
			setStatus('loading', trendyolSyncAdmin.i18n.startingSync);

			$.post(trendyolSyncAdmin.ajaxUrl, {
				action: trendyolSyncAdmin.sync.startAction,
				nonce: trendyolSyncAdmin.sync.nonce
			}).done(function (response) {
				if (!response || !response.success || !response.data) {
					setStatus('error', response && response.data && response.data.message ? response.data.message : '');
					$button.prop('disabled', false).text(trendyolSyncAdmin.i18n.syncButton);
					return;
				}

				currentJobId = response.data.job_id || 0;
				setStatus('success', response.data.message || '');
				window.setTimeout(pollStatus, 1500);
			}).fail(function (xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : '';
				setStatus('error', message);
				$button.prop('disabled', false).text(trendyolSyncAdmin.i18n.syncButton);
			});
		});
	})();
} )( jQuery );
