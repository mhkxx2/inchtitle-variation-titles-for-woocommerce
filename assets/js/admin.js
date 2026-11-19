/**
 * InchTitle Variation Titles for WooCommerce - SaaS Enterprise Studio JavaScript
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		var $select = $('#inchtitle_vt_categories');

		// 1. Tab Navigation
		$('#inchtitle-saas-tabs-nav').on('click', '.inchtitle-saas-tab-btn', function (e) {
			e.preventDefault();
			var targetTab = $(this).data('tab');

			$('#inchtitle-saas-tabs-nav .inchtitle-saas-tab-btn').removeClass('active');
			$(this).addClass('active');

			$('.inchtitle-saas-tab-content').removeClass('active');
			$('#' + targetTab).addClass('active');

			// Save active tab in session storage or URL hash
			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, null, '#' + targetTab);
			}
		});

		// Check hash on load
		if (window.location.hash) {
			var hash = window.location.hash.substring(1);
			var $matchingTab = $('#inchtitle-saas-tabs-nav .inchtitle-saas-tab-btn[data-tab="' + hash + '"]');
			if ($matchingTab.length) {
				$matchingTab.trigger('click');
			}
		}

		// 2. SelectWoo / Select2 Initialization
		if ($select.length) {
			var placeholderText = (typeof inchTitleVtAdmin !== 'undefined' && inchTitleVtAdmin.placeholder) ? inchTitleVtAdmin.placeholder : 'Search categories...';

			if (typeof $.fn.selectWoo === 'function') {
				$select.selectWoo({
					placeholder: placeholderText,
					allowClear: true,
					width: '100%'
				});
			} else if (typeof $.fn.select2 === 'function') {
				$select.select2({
					placeholder: placeholderText,
					allowClear: true,
					width: '100%'
				});
			}
		}

		// 3. Quick Actions
		$('#inchtitle-btn-select-default').on('click', function (e) {
			e.preventDefault();
			if ($select.length) {
				var currentValues = $select.val() || [];
				if (currentValues.indexOf('81') === -1) {
					currentValues.push('81');
					$select.val(currentValues).trigger('change');
				}
			}
		});

		$('#inchtitle-btn-clear-all').on('click', function (e) {
			e.preventDefault();
			if ($select.length) {
				$select.val([]).trigger('change');
			}
		});

		// 4. Studio Sandbox & Simulator
		function escapeRegExp(string) {
			return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		}

		function updateSimulator() {
			var baseTitle = $.trim($('#inchtitle-sim-title').val()) || 'Samsung QN85F Neo QLED Cinema Scale, 50/55/65/75/85 UAE';
			var brand = $.trim($('#inchtitle-sim-brand').val()) || 'Samsung';
			var $activeChip = $('#inchtitle-sim-chips .inchtitle-saas-chip.active');
			var size = $activeChip.length ? $activeChip.data('size') : '';

			var $result = $('#inchtitle-sim-result');
			var $badge = $('#inchtitle-sim-selected-badge');
			var $origDisplay = $('#inchtitle-sim-orig-display');
			var $renderedDisplay = $('#inchtitle-sim-rendered-display');

			$origDisplay.text(baseTitle);

			if (!size) {
				$result.text(baseTitle);
				$badge.text('Original Restored');
				$renderedDisplay.text(baseTitle);
				return;
			}

			$badge.text('Selected: ' + size);

			var fullRenderedTitle = baseTitle;

			if (brand) {
				var brandRegex = new RegExp('\\b' + escapeRegExp(brand) + '\\b', 'i');
				if (brandRegex.test(baseTitle)) {
					var parts = baseTitle.split(brandRegex);
					var match = baseTitle.match(brandRegex);
					var matchedBrand = match ? match[0] : brand;

					fullRenderedTitle = baseTitle.replace(brandRegex, matchedBrand + ' ' + size);

					$result.html(
						$('<div>').text(parts[0]).html() +
						$('<div>').text(matchedBrand + ' ').html() +
						'<mark class="inchtitle-saas-mark">' + $('<div>').text(size).html() + '</mark> ' +
						$('<div>').text(parts.slice(1).join(matchedBrand)).html()
					);
					$renderedDisplay.text(fullRenderedTitle);
					return;
				} else {
					fullRenderedTitle = brand + ' ' + size + ' ' + baseTitle;
					$result.html(
						$('<div>').text(brand + ' ').html() +
						'<mark class="inchtitle-saas-mark">' + $('<div>').text(size).html() + '</mark> ' +
						$('<div>').text(baseTitle).html()
					);
					$renderedDisplay.text(fullRenderedTitle);
					return;
				}
			}

			fullRenderedTitle = size + ' ' + baseTitle;
			$result.html(
				'<mark class="inchtitle-saas-mark">' + $('<div>').text(size).html() + '</mark> ' +
				$('<div>').text(baseTitle).html()
			);
			$renderedDisplay.text(fullRenderedTitle);
		}

		// Preset Picker
		$('#inchtitle-preset-picker').on('change', function () {
			var $opt = $(this).find(':selected');
			var title = $opt.data('title');
			var brand = $opt.data('brand');

			if (title) {
				$('#inchtitle-sim-title').val(title);
			}
			if (brand) {
				$('#inchtitle-sim-brand').val(brand);
			}
			updateSimulator();
		});

		// Size chip selection
		$('#inchtitle-sim-chips').on('click', '.inchtitle-saas-chip', function (e) {
			e.preventDefault();
			$('#inchtitle-sim-chips .inchtitle-saas-chip').removeClass('active');
			$(this).addClass('active');
			updateSimulator();
		});

		// Inputs typing
		$('#inchtitle-sim-title, #inchtitle-sim-brand').on('input', function () {
			updateSimulator();
		});

		// Initialize simulator on page load
		updateSimulator();
	});
})(jQuery);
