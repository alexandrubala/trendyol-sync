(function ($) {
	'use strict';

	if (typeof trendyolSyncProductData === 'undefined') {
		return;
	}

	function initAjaxSelect($select, type) {
		if (!$select.length || !$.fn.selectWoo) {
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
			ajax: {
				url: trendyolSyncProductData.ajaxUrl,
				dataType: 'json',
				delay: 250,
				cache: true,
				data: function (params) {
					return {
						action: trendyolSyncProductData.searchAction,
						nonce: trendyolSyncProductData.nonce,
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
		initAjaxSelect($('#_trendyol_brand_id'), 'brand');
		initAjaxSelect($('#_trendyol_category_id'), 'category');
	});
})(jQuery);
