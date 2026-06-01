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

	function buildBrandAjaxConfig() {
		return {
			url: trendyolSyncMappingData.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			delay: 250,
			cache: true,
			data: function (params) {
				return {
					action: trendyolSyncMappingData.searchAction,
					nonce: trendyolSyncMappingData.nonce,
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
		var placeholder = $select.attr('data-placeholder') || '';
		var categories = trendyolSyncMappingData.categories || [];

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
		var placeholder = $select.attr('data-placeholder') || '';

		$select.selectWoo({
			allowClear: true,
			placeholder: placeholder,
			width: '100%',
			minimumInputLength: 0,
			dropdownParent: $(document.body),
			dropdownCssClass: 'trendyol-sync-select-dropdown',
			ajax: buildBrandAjaxConfig(),
			language: {
				noResults: function () {
					return trendyolSyncMappingData.noResults || 'Niciun rezultat';
				},
				searching: function () {
					return trendyolSyncMappingData.searching || 'Se caută…';
				}
			}
		});

		bindAjaxOpenLoad($select);
	}

	/**
	 * Încarcă prima pagină AJAX la deschidere (desktop + mobile; nu depinde de câmpul de căutare).
	 *
	 * @param {jQuery} $select Element select.
	 */
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

	function initSelect($select) {
		if ($select.data('trendyolSyncInited')) {
			return;
		}

		var type = $select.attr('data-type');

		if (!type) {
			return;
		}

		$select.data('trendyolSyncInited', true);

		if (type === 'category') {
			initCategorySelect($select);
			return;
		}

		if (type === 'brand') {
			initBrandSelect($select);
		}
	}

	$(function () {
		var $selects = $('.trendyol-sync-mapping-select');
		var deferCategoryInit = $selects.filter('[data-type="category"]').length > 10;

		$selects.each(function () {
			var $select = $(this);

			if (deferCategoryInit && $select.attr('data-type') === 'category') {
				$select.one('mousedown.trendyolSync', function () {
					initSelect($select);
				});
				return;
			}

			initSelect($select);
		});
	});
})(jQuery);
