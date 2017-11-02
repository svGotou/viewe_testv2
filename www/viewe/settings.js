(function () {

	var maxBoard = 40;
	var ecocuteOnElm = document.getElementById('ecocute-on');
	var solarOnElm = document.getElementById('solar-on');
	var batteryOnElm = document.getElementById('battery-on');
	var ecocuteOffElm = document.getElementById('ecocute-off');
	var solarOffElm = document.getElementById('solar-off');
	var batteryOffElm = document.getElementById('battery-off');
	//var optionElmList = document.querySelectorAll('.optional');
	var boardElm = document.getElementById('board');
	var channelElms = getChannelElements();
	var saveButtonElm = document.getElementById('save-button');
	var doneButtonElm = document.getElementById('send');
	var uuidElm = document.getElementById('uuid');
	var uuidCopyElm = document.getElementById('uuid-copy');

	uuidCopyElm.addEventListener('click', copyUUID, false);
	saveButtonElm.addEventListener('click', save, false);
	doneButtonElm.addEventListener('click', save, false);

	loadUUID();
	load();
	setupChannleDropdown();

	function loadUUID() {
		window.request('/get_uuid.php', 'GET', null, null, function (error, uuid) {
			if (error) {
				return console.error(error);
			}
			uuidElm.value = uuid;
		});
	}

	function load() {
		window.request('/read_settings.php', 'GET', null, null, function (error, data) {
			if (error) {
				return console.error(error);
			}
			apply(data);
		});
	}

	function apply(data) {
		data.ecocute ? ecocuteOnElm.checked = true : ecocuteOffElm.checked = true;
		data.solar ? solarOnElm.checked = true : solarOffElm.checked = true;
		data.battery ? batteryOnElm.checked = true : batteryOffElm.checked = true;
		boardElm.value = data.board;
		for (var i = 0, len = channelElms.length; i < len; i++) {
			var ch = channelElms[i];
			ch.value = data.channels[i] || '';
		}
		controlChannelDropdown(boardElm.value);
	}

	function copyUUID() {
		uuidElm.select();
		try {
			document.execCommand('copy');
			alert('コピーしました。');
		} catch (error) {
			alert('右クリックでコピーしてください。');
		}
		uuidElm.blur();
	}

	function save() {
		var post = [
			boardElm.value
		];
		for (var i = 0, len = getChannelNumber(parseInt(boardElm.value)); i < len; i++) {
			var ch = channelElms[i];
			post.push(ch.value);
		}
		/*
		for (var i = 0, len = optionElmList.length; i < len; i++) {
			console.log(optionElmList[i].value);
			post.push(optionElmList[i].value);
		}
		*/
		post.push(ecocuteOnElm.checked ? 'YES' : 'NO');
		post.push(solarOnElm.checked ? 'YES' : 'NO');
		post.push(batteryOnElm.checked ? 'YES' : 'NO');
		window.request('/channelSave48A.php', 'POST', post, null, function (error, data) {
			if (error) {
				return console.error(error);
			}
			if (data.indexOf('NG') !== -1) {
				return console.error(data);
			}
			window.location.reload();
		});
	}

	function getChannelElements() {
		var list = [];
		for (var i = 1; i <= maxBoard; i++) {
			list.push(document.getElementById('ch' + i));
		}
		return list;
	}

	function getChannelNumber(board) {
		var list = [
			16,
			20,
			24,
			28,
			32,
			36,
			40
		];
		return list[board];
	}

	function setupChannleDropdown() {
		boardElm.addEventListener('change', function () {
			controlChannelDropdown(this.value);
		}, false);
	}

	function controlChannelDropdown(value) {
		var chNum = getChannelNumber(value);
		if (chNum === undefined) {
			// hmmm
			console.error('invalid value:', this.value);
			return;
		}
		for (var i = 1; i <= 40; i++) {
			if (i <= chNum) {
				document.getElementById('ch' + i).parentNode.style.display = 'inline-block';
			} else {
				document.getElementById('ch' + i).parentNode.style.display = 'none';
			}
		}
	}

}());
