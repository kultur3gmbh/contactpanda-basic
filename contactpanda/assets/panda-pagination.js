(function () {
	'use strict';

	document.querySelectorAll('select[data-panda-page-url]').forEach(function (picker) {
		picker.disabled = picker.options.length <= 1;
		picker.addEventListener('change', function () {
			var url = new URL(picker.dataset.pandaPageUrl, window.location.href);
			url.searchParams.set(picker.dataset.pandaPageArgument, picker.value);
			window.location.assign(url.href);
		});
	});
}());
