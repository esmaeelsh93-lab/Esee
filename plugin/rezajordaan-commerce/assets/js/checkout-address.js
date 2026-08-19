(function ($) {
	"use strict";

	if (typeof $ === "undefined") {
		return;
	}

	var filling = false;

	function readData() {
		if (window.rjCommerceAddress && window.rjCommerceAddress.cities) {
			return window.rjCommerceAddress;
		}

		var node = document.getElementById("rj-commerce-address-data");
		if (node && node.textContent) {
			try {
				return JSON.parse(node.textContent);
			} catch (error) {
				return {};
			}
		}

		return {};
	}

	function normalizeFa(text) {
		return String(text || "")
			.replace(/ي/g, "ی")
			.replace(/ك/g, "ک")
			.replace(/\u200c/g, " ")
			.replace(/\s+/g, " ")
			.trim();
	}

	function canonicalState(raw, labelText) {
		var cfg = readData();
		var cities = cfg.cities || {};
		var aliases = cfg.aliases || {};
		var labels = cfg.labels || {};
		var value = String(raw || "").trim();
		var upper = value.toUpperCase();
		var name = normalizeFa(labelText || value);

		if (cities[upper]) {
			return upper;
		}

		if (aliases[upper]) {
			return aliases[upper];
		}

		if (labels[value]) {
			return labels[value];
		}

		if (labels[name]) {
			return labels[name];
		}

		if (cities[value]) {
			return value;
		}

		return "";
	}

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;");
	}

	function prefixFromState($state) {
		var id = $state.attr("id") || $state.attr("name") || "";
		return id.indexOf("shipping") === 0 ? "shipping" : "billing";
	}

	function unwrapSelect2($el) {
		if (!$el.length) {
			return;
		}

		try {
			if ($.fn.selectWoo && $el.hasClass("select2-hidden-accessible")) {
				$el.selectWoo("destroy");
			} else if ($.fn.select2 && $el.hasClass("select2-hidden-accessible")) {
				$el.select2("destroy");
			}
		} catch (error) {
			// Keep going with a native select.
		}
	}

	function cityField($fromState) {
		var prefix = prefixFromState($fromState);
		var $city = $("#" + prefix + "_city");

		if (!$city.length) {
			$city = $fromState.closest("form, .woocommerce-billing-fields, .woocommerce-address-fields").find("[name='" + prefix + "_city']");
		}

		if ($city.length && $city.is("input")) {
			var $select = $("<select/>", {
				id: $city.attr("id") || prefix + "_city",
				name: $city.attr("name") || prefix + "_city",
				class: ($city.attr("class") || "") + " rj-city-select",
				autocomplete: "address-level2",
				required: true,
			});
			$city.replaceWith($select);
			$city = $select;
		}

		return $city;
	}

	function selectedStateLabel($state) {
		var $option = $state.find("option:selected");
		if ($option.length) {
			return $.trim($option.text());
		}

		return "";
	}

	function setCityOptions($city, stateCode, keepValue) {
		var cfg = readData();
		var cities = cfg.cities || {};
		var list = stateCode && cities[stateCode] ? cities[stateCode] : null;
		var placeholder = (cfg.i18n && cfg.i18n.chooseCity) || "شهر را انتخاب کنید";
		var current = "";
		var html;
		var i;

		if (!list || !list.length) {
			return false;
		}

		if (keepValue === false || keepValue === "") {
			current = "";
		} else if (typeof keepValue === "string") {
			current = keepValue;
		} else {
			current = $city.attr("data-selected") || $city.val() || "";
		}

		html = '<option value="">' + escapeHtml(placeholder) + "</option>";
		for (i = 0; i < list.length; i += 1) {
			html += '<option value="' + escapeHtml(list[i]) + '">' + escapeHtml(list[i]) + "</option>";
		}

		unwrapSelect2($city);
		$city.prop("disabled", false);
		$city.html(html);

		if (current && list.indexOf(current) !== -1) {
			$city.val(current);
		} else {
			$city.val("");
		}

		$city.attr("data-selected", $city.val() || "");
		return true;
	}

	function fillFromState($state, keepValue) {
		var $city;
		var code;

		if (filling || !$state.length) {
			return;
		}

		$city = cityField($state);
		if (!$city.length) {
			return;
		}

		code = canonicalState($state.val(), selectedStateLabel($state));
		if (!code) {
			unwrapSelect2($city);
			$city.prop("disabled", false);
			return;
		}

		filling = true;
		try {
			setCityOptions($city, code, keepValue);
		} finally {
			filling = false;
		}
	}

	$(document.body).on("change.rjCity select2:select.rjCity", "#billing_state, #shipping_state", function () {
		var $state = $(this);
		var $city = cityField($state);
		$city.removeAttr("data-selected");
		fillFromState($state, "");
	});

	$(document.body).on("country_to_state_changed.rjCity country_to_state_changing.rjCity", function () {
		window.setTimeout(function () {
			$("#billing_state, #shipping_state").each(function () {
				fillFromState($(this), true);
			});
		}, 0);
	});

	$(function () {
		$("#billing_city, #shipping_city").each(function () {
			var $city = $(this);
			if ($city.val()) {
				$city.attr("data-selected", $city.val());
			}
			unwrapSelect2($city);
			$city.prop("disabled", false);
		});

		$("#billing_state, #shipping_state").each(function () {
			fillFromState($(this), true);
		});
	});
})(window.jQuery);
