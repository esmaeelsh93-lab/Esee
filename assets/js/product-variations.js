(function ($) {
	"use strict";

	var colorMap = {
		صورتی: "#f4a6c8",
		قرمز: "#d64545",
		"آبی": "#5b8def",
		"آبی روشن": "#8ec5ff",
		"آبی کمرنگ": "#b8d9ff",
		"سبز": "#4caf7d",
		"زرد": "#f2c94c",
		"مشکی": "#1f1f1f",
		"سفید": "#f7f7f7",
		"خاکستری": "#9aa0a6",
		"بنفش": "#8b6fd6",
		"نارنجی": "#f2994a",
		"قهوه‌ای": "#8d6e63",
		"کرم": "#f5e6c8",
		"بژ": "#e8d8b8",
		pink: "#f4a6c8",
		red: "#d64545",
		blue: "#5b8def",
		green: "#4caf7d",
		yellow: "#f2c94c",
		black: "#1f1f1f",
		white: "#f7f7f7",
		grey: "#9aa0a6",
		gray: "#9aa0a6",
		purple: "#8b6fd6",
		orange: "#f2994a",
		brown: "#8d6e63",
		beige: "#e8d8b8",
	};

	function parseVariations($form) {
		var raw = $form.attr("data-product_variations");

		if (!raw || raw === "false") {
			return [];
		}

		try {
			return JSON.parse(raw);
		} catch (error) {
			return [];
		}
	}

	function getAttributeKey($select) {
		var name = $select.data("attribute_name") || $select.attr("name") || "";
		return name.replace(/^attribute_/, "");
	}

	function getCurrentSelections($form) {
		var selections = {};

		$form.find(".variations select").each(function () {
			var $select = $(this);
			var key = getAttributeKey($select);

			if (!key) {
				return;
			}

			selections[key] = $select.val() || "";
		});

		return selections;
	}

	function variationMatches(variation, attributeKey, optionValue, selections) {
		if (!variation.attributes) {
			return false;
		}

		var attrFull = "attribute_" + attributeKey;
		var variationValue = variation.attributes[attrFull];

		if (variationValue && variationValue !== optionValue) {
			return false;
		}

		for (var key in selections) {
			if (!Object.prototype.hasOwnProperty.call(selections, key) || key === attributeKey) {
				continue;
			}

			var selected = selections[key];

			if (!selected) {
				continue;
			}

			var otherValue = variation.attributes["attribute_" + key];

			if (otherValue && otherValue !== selected) {
				return false;
			}
		}

		return true;
	}

	function isOptionInStock(variations, attributeKey, optionValue, selections) {
		for (var index = 0; index < variations.length; index += 1) {
			var variation = variations[index];

			if (!variation.is_in_stock) {
				continue;
			}

			if (!variationMatches(variation, attributeKey, optionValue, selections)) {
				continue;
			}

			var attrFull = "attribute_" + attributeKey;
			var variationValue = variation.attributes[attrFull];

			if (!variationValue || variationValue === optionValue) {
				return true;
			}
		}

		return false;
	}

	function resolveColorSwatch(label) {
		var normalized = String(label || "")
			.trim()
			.toLowerCase()
			.replace(/\s+/g, " ");

		if (colorMap[label]) {
			return colorMap[label];
		}

		if (colorMap[normalized]) {
			return colorMap[normalized];
		}

		return "";
	}

	function syncSelectedStates($form) {
		$form.find(".rj-variation-swatches").each(function () {
			var $group = $(this);
			var attributeKey = $group.data("attribute");
			var $select = $form.find('select[name="attribute_' + attributeKey + '"]');
			var value = $select.val();

			$group.find(".rj-variation-option").each(function () {
				$(this).toggleClass("is-selected", $(this).data("value") === value);
			});
		});
	}

	function updateSwatchStates($form, variations) {
		var selections = getCurrentSelections($form);

		$form.find(".rj-variation-swatches").each(function () {
			var $group = $(this);
			var attributeKey = $group.data("attribute");

			$group.find(".rj-variation-option").each(function () {
				var $button = $(this);
				var value = $button.data("value");
				var inStock = isOptionInStock(variations, attributeKey, value, selections);

				$button.toggleClass("is-out-of-stock", !inStock);
				$button.attr("aria-disabled", inStock ? "false" : "true");
			});
		});
	}

	function buildSwatches($form, variations) {
		$form.addClass("rj-variations-enhanced");

		$form.find(".variations select").each(function () {
			var $select = $(this);
			var $cell = $select.closest("td.value");

			if ($cell.find(".rj-variation-swatches").length) {
				return;
			}

			var attributeKey = getAttributeKey($select);
			var label = $select.closest("tr").find("label").text().trim();
			var isColor = /color|رنگ|pa_color/i.test(attributeKey + " " + label);
			var $group = $('<div class="rj-variation-swatches" role="radiogroup"></div>');

			$group.attr("aria-label", label);
			$group.data("attribute", attributeKey);

			$select.find("option").each(function () {
				var value = this.value;
				var text = $(this).text();

				if (!value) {
					return;
				}

				var swatchColor = isColor ? resolveColorSwatch(text) : "";
				var $button = $('<button type="button" class="rj-variation-option"></button>');

				$button.attr("data-value", value);
				$button.attr("aria-label", text);

				if (isColor) {
					$button.addClass("is-color");
					$button.prepend('<span class="rj-variation-option__dot" aria-hidden="true"></span>');

					if (swatchColor) {
						$button.find(".rj-variation-option__dot").css("background-color", swatchColor);
					}
				}

				$button.append('<span class="rj-variation-option__text"></span>');
				$button.find(".rj-variation-option__text").text(text);

				if ($select.val() === value) {
					$button.addClass("is-selected");
				}

				$button.on("click", function () {
					if ($select.val() === value) {
						return;
					}

					$select.val(value).trigger("change");
				});

				$group.append($button);
			});

			$select.addClass("rj-variation-select-hidden");
			$cell.append($group);
		});

		$form.on(
			"woocommerce_update_variation_values found_variation reset_data hide_variation show_variation",
			function () {
				syncSelectedStates($form);
				updateSwatchStates($form, variations);
			}
		);

		$form.find(".variations select").on("change", function () {
			syncSelectedStates($form);
			updateSwatchStates($form, variations);
		});

		syncSelectedStates($form);
		updateSwatchStates($form, variations);
	}

	$(function () {
		$("form.variations_form").each(function () {
			var $form = $(this);
			var variations = parseVariations($form);

			if (!variations.length) {
				return;
			}

			buildSwatches($form, variations);
		});
	});
})(window.jQuery);
