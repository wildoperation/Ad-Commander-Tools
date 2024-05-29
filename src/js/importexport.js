jQuery(document).ready(function ($) {
	/**
	 * All submit buttons
	 */
	const $submit = $(".adcmdrdt-submit");
	if ($submit.length > 0) {
		const $form = $submit.closest("form");

		$form.on("submit", function (e) {
			$submit.attr("disabled", true);
			$(this)
				.find(".adcmdrdt-submit")
				.siblings(".adcmdr-loader")
				.css("display", "inline-block");
		});
	}

	/**
	 * Import bundle options
	 */
	const $bundle_opts = $('input[name="adcmdr_import_bundle_options[]"]');
	if ($bundle_opts.length > 0) {
		const $bundle_ads = $bundle_opts.filter('[value="ads"]');
		const $bundle_stats = $bundle_opts.filter('[value="stats"]');

		$bundle_ads.on("change", function () {
			if ($(this).is(":checked")) {
				$bundle_stats.attr("disabled", false);
			} else {
				$bundle_stats.attr("disabled", true);
				$bundle_stats.prop("checked", false);
			}
		});
	}

	/**
	 * Exported bundle list
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
				action: adcmdr_dt_impexp.actions.delete_bundle.action,
				security: adcmdr_dt_impexp.actions.delete_bundle.security,
				file: $li.data("file"),
			};

			$.post(adcmdr_dt_impexp.ajaxurl, data, function (response) {
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
