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

	function initCategorySelect($select) {
		var placeholder = $select.data('placeholder') || '';
		var categories = trendyolSyncMappingData.categories || [];

		if (!categories.length) {
			showInitError(trendyolSyncMappingData.catalogEmpty || '');
		}

		$select.selectWoo({
			allowClear: true,
			placeholder: placeholder,
			width: '100%',
			dropdownParent: $(document.body),
			dropdownCssClass: 'trendyol-sync-select-dropdown',
			data: categories,
			language: {
				noResults: function () {
					return trendyolSyncMappingData.noResults || 'Niciun rezultat';
				}
			}
		});
	}

	function initBrandSelect($select) {
		var placeholder = $select.data('placeholder') || '';

		$select.selectWoo({
			allowClear: true,
			placeholder: placeholder,
			width: '100%',
			minimumInputLength: 0,
			dropdownParent: $(document.body),
			dropdownCssClass: 'trendyol-sync-select-dropdown',
			ajax: buildAjaxConfig('brand'),
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

	function initSelect($select) {
		var type = $select.data('type');

		if (!type) {
			return;
		}

		if (type === 'category') {
			initCategorySelect($select);
			return;
		}

		if (type === 'brand') {
			initBrandSelect($select);
		}
	}

	$(function () {
		$('.trendyol-sync-mapping-select').each(function () {
			initSelect($(this));
		});
	});
})(jQuery);
