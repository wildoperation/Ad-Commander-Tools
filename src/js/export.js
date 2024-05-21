jQuery(document).ready(function ($) {
	/**
	 * Primary export button
	 */
	const $submit = $(".adcmdrdt-export-now");
	if ($submit.length > 0) {
		const $form = $submit.closest("form");

		$form.on("submit", function (e) {
			$submit.attr("disabled", true);
			$submit.siblings(".adcmdr-loader").css("display", "inline-block");
		});
	}

	/**
	 * Bundle list
	 */
	const $exported_list = $(".adcmdrdt-export-list");

	if ($exported_list.length > 0) {
		const $del = $exported_list.find(".adcmdrdt-del");

		$del.on("click", function (e) {
			e.preventDefault();

			const $this = $(this);
			const $li = $this.closest("li");

			$this.attr("disabled", true);
			$li.css("opacity", 0.5);

			const data = {
				action: adcmdr_dt_export.actions.delete_bundle.action,
				security: adcmdr_dt_export.actions.delete_bundle.security,
				file: $li.data("file"),
			};

			$.post(adcmdr_dt_export.ajaxurl, data, function (response) {
				if (response.success && response.data.action === "delete-bundle") {
					$li.remove();

					if ($exported_list.find("li").length <= 0) {
						$exported_list.closest("tr").remove();
					}
				}
			});
		});
	}
});
