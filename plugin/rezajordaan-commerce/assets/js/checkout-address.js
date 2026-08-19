(function ($) {
	"use strict";

	if (typeof $ === "undefined") {
		return;
	}

	var cfg = window.rjCommerceAddress || {};
	var cities = cfg.cities || {};
	var aliases = cfg.aliases || {};
	var i18n = cfg.i18n || {};

	function canonicalState(code) {
		if (!code) {
			return "";
		}

		var raw = String(code);
		var upper = raw.toUpperCase();

		if (Object.prototype.hasOwnProperty.call(cities, upper)) {
			return upper;
		}

		if (Object.prototype.hasOwnProperty.call(aliases, upper)) {
			return aliases[upper];
		}

		if (Object.prototype.hasOwnProperty.call(aliases, raw)) {
			return aliases[raw];
		}

		return upper;
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

	function cityValue($city, keepValue) {
		if (keepValue === false || keepValue === "") {
			return "";
		}

		if (typeof keepValue === "string") {
			return keepValue;
		}

		return $city.attr("data-selected") || $city.val() || "";
	}

	function setCityOptions($city, state, keepValue) {
		if (!$city.length) {
			return;
		}

		var list = cities[canonicalState(state)] || [];
		var current = cityValue($city, keepValue);
		var placeholder = list.length
			? i18n.chooseCity || "شهر را انتخاب کنید"
			: i18n.chooseProvince || "ابتدا استان را انتخاب کنید";
		var html = '<option value="">' + escapeHtml(placeholder) + "</option>";
		var i;

		for (i = 0; i < list.length; i += 1) {
			html += '<option value="' + escapeHtml(list[i]) + '">' + escapeHtml(list[i]) + "</option>";
		}

		if ($city.hasClass("select2-hidden-accessible") && $.fn.selectWoo) {
			$city.selectWoo("destroy");
		}

		$city.html(html);
		$city.prop("disabled", list.length === 0);

		if (current && list.indexOf(current) !== -1) {
			$city.val(current);
		} else {
			$city.val("");
		}

		$city.attr("data-selected", $city.val() || "");

		if ($.fn.selectWoo) {
			$city.selectWoo({
				width: "100%",
				placeholder: placeholder,
				dir: document.documentElement.dir || "rtl",
			});
		}
	}

	function syncCity($state, keepValue) {
		if (!$state.length) {
			return;
		}

		var $city = $("#" + prefixFromState($state) + "_city");
		setCityOptions($city, $state.val(), keepValue);
	}

	$(document.body).on("change", "#billing_state, #shipping_state", function () {
		var $state = $(this);
		var $city = $("#" + prefixFromState($state) + "_city");

		$city.removeAttr("data-selected");
		setCityOptions($city, $state.val(), "");
		$city.trigger("change");
	});

	$(document.body).on("country_to_state_changed", function () {
		$("#billing_state, #shipping_state").each(function () {
			syncCity($(this), true);
		});
	});

	$(function () {
		$("#billing_city, #shipping_city").each(function () {
			var $city = $(this);
			if ($city.val()) {
				$city.attr("data-selected", $city.val());
			}
		});

		$("#billing_state, #shipping_state").each(function () {
			syncCity($(this), true);
		});
	});
})(window.jQuery);
