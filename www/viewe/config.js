(function () {

	var thresholdElm = document.getElementById('threshold');
	var dayTimePriceElm = document.getElementById('dayTimePrice');
	var morningTimePriceElm = document.getElementById('morningTimePrice');
	var nightTimePriceElm = document.getElementById('nightTimePrice');
	var dayTimeElm = document.getElementById('dayTime');
	var morningTimeElm = document.getElementById('morningTime');
	var eveningTimeElm = document.getElementById('eveningTime');
	var nightTimeElm = document.getElementById('nightTime');
	var sendButtonElm = document.getElementById('send');

	sendButtonElm.addEventListener('click', save, false);

	load();

	function load() {
		window.request('/read_config.php', 'GET', null, null, function (error, data) {
			if (error) {
				return console.error(error);
			}
			apply(data);
		});
	}

	function apply(data) {

		console.log(data);

		thresholdElm.value = data.threshold || 0;
		dayTimePriceElm.value = data.dayTimePrice || 0;
		morningTimePriceElm.value = data.morningTimePrice || 0;
		nightTimePriceElm.value = data.nightTimePrice || 0;
		dayTimeElm.value = data.dayTime || 0;
		morningTimeElm.value = data.morningTime || 0;
		eveningTimeElm.value = data.eveningTime || 0;
		nightTimeElm.value = data.nightTime || 0;
	}

	function save() {
		var post = {
			threshold: parseFloat(thresholdElm.value),
			dayTimePrice: parseFloat(dayTimePriceElm.value),
			morningTimePrice: parseFloat(morningTimePriceElm.value),
			nightTimePrice: parseFloat(nightTimePriceElm.value),
			dayTime: parseFloat(dayTimeElm.value),
			morningTime: parseFloat(morningTimeElm.value),
			nightTime: parseFloat(nightTimeElm.value),
			eveningTime: parseFloat(eveningTimeElm.value)
		};
		window.request('/save_config.php', 'POST', post, null, function (error, data) {
			if (error) {
				return console.error(error);
			}
			window.location.reload();
		});
	}

}());
