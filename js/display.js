(function ($) {
	"use strict";
	$(function () {
		// Place your public-facing JavaScript here
		createStoryJSss({
			type: "timeline",
			lang: "sv",
			hash_bookmark: true,
			width: "100%",
			height: "600",
			source: 'https://docs.google.com/spreadsheet/pub?key=0AptiQDIBlUsddENDRHBWZkZpd01MUEhDd1lWOUFXc0E&output=html',
			embed_id: 'my-timeline'
		});
	});
}(jQuery));