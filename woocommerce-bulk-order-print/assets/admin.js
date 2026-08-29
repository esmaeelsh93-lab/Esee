(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	ready(function () {
		var selectAll = document.getElementById('wbop-select-all');
		var form = document.getElementById('wbop-print-form');
		var warning = document.getElementById('wbop-print-warning');

		if (selectAll) {
			selectAll.addEventListener('change', function () {
				var boxes = document.querySelectorAll('.wbop-order-cb');
				for (var i = 0; i < boxes.length; i++) {
					boxes[i].checked = selectAll.checked;
				}
			});
		}

		if (form) {
			form.addEventListener('submit', function (event) {
				var checked = document.querySelectorAll('.wbop-order-cb:checked');
				if (!checked.length) {
					event.preventDefault();
					if (warning) {
						warning.hidden = false;
					} else {
						window.alert('لطفاً حداقل یک سفارش را انتخاب کنید.');
					}
					return false;
				}
				if (warning) {
					warning.hidden = true;
				}
				return true;
			});
		}
	});
})();
