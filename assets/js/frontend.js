/**
 * InchTitle Variation Titles for WooCommerce - Frontend Title Updater
 */
(function ($) {
	'use strict';

	if (typeof inchTitleVariationTitlesData === 'undefined') {
		return;
	}

	var data = inchTitleVariationTitlesData;
	var originalTitle = data.originalTitle || '';
	var brand = data.brand || '';
	var attrKey = data.attributeKey || 'attribute_pa_screen_size';
	var sizeMap = data.sizeMap || {};

	/**
	 * Escape regex special characters.
	 */
	function escapeRegExp(string) {
		return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}

	/**
	 * Normalize size string to "XX Inch" format.
	 */
	function formatSize(rawVal) {
		if (!rawVal) {
			return '';
		}

		var cleanVal = $.trim(rawVal);

		// Check sizeMap first
		if (sizeMap[cleanVal]) {
			return sizeMap[cleanVal];
		}
		if (sizeMap[cleanVal.toLowerCase()]) {
			return sizeMap[cleanVal.toLowerCase()];
		}

		// Regex formatting fallback (e.g. "55-inch" -> "55 Inch")
		var match = cleanVal.match(/^(\d+)\s*[-_]?\s*(?:inch|inches|in|\"|\')?$/i);
		if (match && match[1]) {
			return match[1] + ' Inch';
		}

		cleanVal = cleanVal.replace(/[-_]?\s*inch(es)?/gi, ' Inch');
		return cleanVal.charAt(0).toUpperCase() + cleanVal.slice(1);
	}

	/**
	 * Get title element.
	 */
	function getTitleElement() {
		var $el = $(data.titleSelector || 'h1.product_title.entry-title');
		if (!$el.length) {
			$el = $(data.fallbackSelector || '.product_title').first();
		}
		return $el;
	}

	/**
	 * Build new title with size inserted immediately after brand.
	 */
	function buildDynamicTitle(sizeStr) {
		var baseTitle = originalTitle;
		if (!baseTitle) {
			var $el = getTitleElement();
			baseTitle = $el.data('original-title') || $el.text().trim();
		}

		if (!sizeStr) {
			return baseTitle;
		}

		// If brand is present, find the first occurrence of the brand in the title
		if (brand) {
			var brandRegex = new RegExp('\\b' + escapeRegExp(brand) + '\\b', 'i');
			if (brandRegex.test(baseTitle)) {
				return baseTitle.replace(brandRegex, function (matched) {
					return matched + ' ' + sizeStr;
				});
			} else {
				// Brand not found as exact word in title, prepend brand + size or size
				return brand + ' ' + sizeStr + ' ' + baseTitle;
			}
		}

		// Fallback if no brand detected
		return sizeStr + ' ' + baseTitle;
	}

	/**
	 * Update visible H1 title.
	 */
	function updateTitle(sizeStr) {
		var $h1 = getTitleElement();
		if (!$h1.length) {
			return;
		}

		// Save original title if not saved
		if (!$h1.data('original-title')) {
			$h1.data('original-title', originalTitle || $h1.text().trim());
		}

		var newTitle = sizeStr ? buildDynamicTitle(sizeStr) : ($h1.data('original-title') || originalTitle);
		$h1.text(newTitle);
	}

	/**
	 * Restore original title.
	 */
	function restoreTitle() {
		var $h1 = getTitleElement();
		if (!$h1.length) {
			return;
		}
		var base = $h1.data('original-title') || originalTitle;
		if (base) {
			$h1.text(base);
		}
	}

	/**
	 * Extract selected size value from form or variation object.
	 */
	function getSelectedSize($form, variation) {
		var rawValue = '';

		// 1. From variation attributes if provided
		if (variation && variation.attributes) {
			if (variation.attributes[attrKey]) {
				rawValue = variation.attributes[attrKey];
			} else if (variation.attributes['pa_screen_size']) {
				rawValue = variation.attributes['pa_screen_size'];
			}
		}

		// 2. From select dropdown if not found
		if (!rawValue && $form && $form.length) {
			var $select = $form.find('select[name="' + attrKey + '"], select[name="attribute_' + attrKey + '"]');
			if ($select.length) {
				rawValue = $select.val();
			}
		}

		// 3. From active swatch if Woo Variation Swatches is used
		if (!rawValue && $form && $form.length) {
			var $activeSwatch = $form.find('.woo-variation-raw-select[name="' + attrKey + '"], .woo-variation-raw-select[data-attribute_name="' + attrKey + '"]');
			if ($activeSwatch.length) {
				rawValue = $activeSwatch.val();
			}
		}

		return rawValue ? formatSize(rawValue) : '';
	}

	/**
	 * Initialize event listeners on variation form.
	 */
	function init() {
		var $form = $('form.variations_form');
		if (!$form.length) {
			return;
		}

		var $h1 = getTitleElement();
		if ($h1.length && !$h1.data('original-title')) {
			$h1.data('original-title', originalTitle || $h1.text().trim());
		}

		// Event: found_variation
		$form.off('found_variation.inchTitleVt').on('found_variation.inchTitleVt', function (event, variation) {
			var formatted = getSelectedSize($(this), variation);
			if (formatted) {
				updateTitle(formatted);
			}
		});

		// Event: reset_data / hide_variation
		$form.off('reset_data.inchTitleVt hide_variation.inchTitleVt').on('reset_data.inchTitleVt hide_variation.inchTitleVt', function () {
			restoreTitle();
		});

		// Event: select change & variation change (Woo Swatches support)
		$form.off('change.inchTitleVt', 'select[name="' + attrKey + '"]').on('change.inchTitleVt', 'select[name="' + attrKey + '"]', function () {
			var val = $(this).val();
			if (val) {
				updateTitle(formatSize(val));
			} else {
				restoreTitle();
			}
		});

		$form.off('woocommerce_variation_has_changed.inchTitleVt').on('woocommerce_variation_has_changed.inchTitleVt', function () {
			var formatted = getSelectedSize($(this));
			if (formatted) {
				updateTitle(formatted);
			}
		});

		// Initial check on load (handles pre-selected attributes / URL params)
		var initialSize = getSelectedSize($form);
		if (initialSize) {
			updateTitle(initialSize);
		}
	}

	$(document).ready(function () {
		init();
		// Re-check after short delay in case swatches initialize asynchronously
		setTimeout(init, 100);
	});

})(jQuery);
