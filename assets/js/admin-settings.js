(() => {
	"use strict";

	document.querySelectorAll("[data-settings-tab]").forEach((button) => {
		button.addEventListener("click", () => {
			const target = button.dataset.settingsTab;
			document.querySelectorAll("[data-settings-tab]").forEach((item) => {
				item.classList.toggle("is-active", item === button);
			});
			document.querySelectorAll("[data-settings-panel]").forEach((panel) => {
				panel.classList.toggle("is-active", panel.dataset.settingsPanel === target);
			});
		});
	});

	document.querySelectorAll(".pc-admin-range input[type='range']").forEach((input) => {
		const output = input.parentElement?.querySelector("output");
		const unit = output?.textContent.replace(/[0-9]/g, "") || "";
		input.addEventListener("input", () => {
			if (output) output.textContent = `${input.value}${unit}`;
		});
	});

	document.querySelectorAll("[data-link-editor]").forEach((editor) => {
		const rows = editor.querySelector("[data-link-rows]");
		const template = editor.querySelector("[data-link-template]");
		const addButton = editor.querySelector("[data-add-link]");
		const limit = Number.parseInt(editor.dataset.limit || "10", 10);
		let index = rows?.children.length || 0;

		const refreshButton = () => {
			if (addButton && rows) addButton.disabled = rows.children.length >= limit;
		};

		addButton?.addEventListener("click", () => {
			if (!rows || !template || rows.children.length >= limit) return;
			const markup = template.innerHTML.replaceAll("__INDEX__", String(index));
			rows.insertAdjacentHTML("beforeend", markup);
			index += 1;
			refreshButton();
		});

		editor.addEventListener("click", (event) => {
			const removeButton = event.target.closest("[data-remove-link]");
			if (!removeButton) return;
			removeButton.closest(".pc-link-row")?.remove();
			refreshButton();
		});

		refreshButton();
	});
})();
