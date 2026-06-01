(function ($) {
	'use strict';

	if (typeof trendyolSyncProductData === 'undefined') {
		return;
	}

	function buildBrandAjaxConfig() {
		return {
			url: trendyolSyncProductData.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			delay: 250,
			cache: true,
			data: function (params) {
				return {
					action: trendyolSyncProductData.searchAction,
					nonce: trendyolSyncProductData.nonce,
					type: 'brand',
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
		};
	}

	function initCategorySelect($select) {
		if (!$select.length || !$.fn.selectWoo) {
			return;
		}

		var placeholder = $select.data('placeholder') || '';
		var categories = trendyolSyncProductData.categories || [];

		$select.selectWoo({
			allowClear: true,
			placeholder: placeholder,
			width: '100%',
			dropdownParent: $(document.body),
			dropdownCssClass: 'trendyol-sync-select-dropdown',
			data: categories
		});
	}

	function initBrandSelect($select) {
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
			ajax: buildBrandAjaxConfig()
		});

		bindAjaxOpenLoad($select);
	}

	function bindAjaxOpenLoad($select) {
		$select.on('select2:open', function () {
			var select2 = $select.data('select2');

			if (!select2 || !select2.dataAdapter || typeof select2.dataAdapter.query !== 'function') {
				return;
			}

			select2.dataAdapter.query(
				{
					term: '',
					page: 1
				},
				function () {}
			);
		});
	}

	$(function () {
		initCategorySelect($('#_trendyol_category_id'));
		initBrandSelect($('#_trendyol_brand_id'));
	});
})(jQuery);
