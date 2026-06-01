(function ($) {
	'use strict';

	if (typeof trendyolSyncProductData === 'undefined') {
		return;
	}

	function buildBrandAjaxConfig() {
		return {
			url: trendyolSyncProductData.ajaxUrl,
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

		$select.on('select2:open', function () {
			var $search = $('.select2-container--open .select2-search__field');

			if ($search.length) {
				$search.trigger('input');
			}
		});
	}

	$(function () {
		initCategorySelect($('#_trendyol_category_id'));
		initBrandSelect($('#_trendyol_brand_id'));
	});
})(jQuery);
