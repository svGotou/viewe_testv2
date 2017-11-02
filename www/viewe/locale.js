(function () {

	var lang = 'ja';
	var conversionMap = JSON.parse(JSON.stringify(window.__languages));

	window.changeLanguage = function (_lang) {
		if (window.__supportedLanguages.indexOf(_lang) === -1) {
			return;
		}
		lang = _lang;
		window.localStorage.setItem('_lang', lang);
		traverse(document.body);
	}; 

	window.setLanguage = function () {
		var _lang = window.localStorage.getItem('_lang');
		if (_lang) {
			lang = _lang;
		}
		window.changeLanguage(lang);
	};

	window.addEventListener('load', function () {
		var _lang = window.localStorage.getItem('_lang');
		if (_lang) {
			lang = _lang;
		}
		window.changeLanguage(lang);
	});
	
	function traverse(elm) {
		var children = elm.children;
		for (var i = 0, len = children.length; i < len; i++) {
			var child = children[i];
			if (child.children.length) {
				traverse(child);
				continue;
			}
			var tag = child.textContent.replace(/(\r|\t|\n)/g, '');
			if (conversionMap[tag] && conversionMap[tag][lang]) {
				child.textContent = conversionMap[tag][lang];
				conversionMap[conversionMap[tag][lang]] = conversionMap[tag];
			}
		}
	}
}());
