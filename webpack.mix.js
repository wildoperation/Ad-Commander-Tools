const mix = require("laravel-mix");

mix
	.sourceMaps(false, "source-map")
	.js("src/js/export.js", "dist/js/export.js")
	.sass("src/scss/admin.scss", "dist/css/")
	.options({
		processCssUrls: false,
	});
