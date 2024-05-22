const mix = require("laravel-mix");

mix
	.sourceMaps(false, "source-map")
	.js("src/js/importexport.js", "dist/js/importexport.js")
	.sass("src/scss/admin.scss", "dist/css/")
	.options({
		processCssUrls: false,
	});
