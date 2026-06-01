(function ($) {
	'use strict';

	if (typeof trendyolSyncMappingData === 'undefined') {
		return;
	}

	function showInitError(message) {
		var $wrap = $('.trendyol-sync-settings-wrap').first();

		if (!$wrap.length || !message || $wrap.find('.trendyol-sync-mapping-js-error').length) {
			return;
		}

		$wrap.prepend(
			'<div class="notice notice-error trendyol-sync-mapping-js-error"><p></p></div>'
		);
		$wrap.find('.trendyol-sync-mapping-js-error p').text(message);
	}

	if (!$.fn.selectWoo) {
		showInitError(trendyolSyncMappingData.selectWooMissing || '');
		return;
	}

	if (!trendyolSyncMappingData.catalogReady) {
		showInitError(trendyolSyncMappingData.catalogEmpty || '');
	}

	function buildAjaxConfig(type) {
		return {
			url: trendyolSyncMappingData.ajaxUrl,
			dataType: 'json',
			delay: 250,
			cache: true,
			data: function (params) {
				return {
					action: trendyolSyncMappingData.searchAction,
					nonce: trendyolSyncMappingData.nonce,
					type: type,
					term: params.term || '',
					page: params.page || 1
				};
			},
			transport: function (params, success, failure) {
				$.ajax({
					url: params.url,
					dataType: params.dataType,
					data: params.data,
					type: 'GET'
				})
					.done(success)
					.fail(failure);
			},
			processResults: function (response) {
				if (!response || !response.success || !response.data) {
					return { results: [] };
				}

				return {
					results: response.data.results || [],
					pagination: {
						more: !!(response.data.pagination && response.data.pagination.more)
					}
				};
			}
		};
	}

	function initCatalogSelect($select) {
		var type = $select.data('type');

		if (!type) {
			return;
		}

		var placeholder = $select.data('placeholder') || '';

		$select.selectWoo({
			allowClear: true,
			placeholder: placeholder,
			width: '100%',
			minimumInputLength: 0,
			dropdownParent: $(document.body),
			dropdownCssClass: 'trendyol-sync-select-dropdown',
			ajax: buildAjaxConfig(type),
			language: {
				noResults: function () {
					return trendyolSyncMappingData.noResults || 'Niciun rezultat';
				},
				searching: function () {
					return trendyolSyncMappingData.searching || 'Se caută…';
				}
			}
		});

		$select.on('select2:open', function () {
			var $search = $('.select2-container--open .select2-search__field');

			if ($search.length) {
				$search.trigger('input');
			}
		});
	}

	$(function () {
		$('.trendyol-sync-mapping-select').each(function () {
			initCatalogSelect($(this));
		});
	});
})(jQuery);
