(function ($) {
	'use strict';

	if (typeof trendyolSyncMappingData === 'undefined' || !$.fn.selectWoo) {
		return;
	}

	function initSelect($select) {
		var type = $select.data('type');
		var placeholder = $select.data('placeholder') || '';

		if (!type) {
			return;
		}

		$select.selectWoo({
			allowClear: true,
			placeholder: placeholder,
			width: '100%',
			minimumInputLength: 0,
			dropdownParent: $(document.body),
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
				processResults: function (response, params) {
					params.page = params.page || 1;

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
			}
		});
	}

	$(function () {
		$('.trendyol-sync-mapping-select').each(function () {
			initSelect($(this));
		});
	});
})(jQuery);
