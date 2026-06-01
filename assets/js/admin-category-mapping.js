(function ($) {
	'use strict';

	if (typeof trendyolSyncMappingData === 'undefined') {
		return;
	}

	function showInitError(message) {
		var $wrap = $('.trendyol-sync-settings-wrap').first();

		if (!$wrap.length || $wrap.find('.trendyol-sync-mapping-js-error').length) {
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

	function initSelect($select) {
		var type = $select.data('type');
		var placeholder = $select.data('placeholder') || trendyolSyncMappingData.emptyLabel || '';

		if (!type) {
			return;
		}

		$select.selectWoo({
			allowClear: true,
			placeholder: placeholder,
			width: '100%',
			minimumInputLength: 0,
			minimumResultsForSearch: 0,
			dropdownParent: $(document.body),
			dropdownCssClass: 'trendyol-sync-select-dropdown',
			ajax: {
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
			},
			language: {
				noResults: function () {
					return trendyolSyncMappingData.noResults || 'Niciun rezultat';
				},
				searching: function () {
					return trendyolSyncMappingData.searching || 'Se caută…';
				}
			}
		});
	}

	$(function () {
		$('.trendyol-sync-mapping-select').each(function () {
			initSelect($(this));
		});
	});
})(jQuery);
