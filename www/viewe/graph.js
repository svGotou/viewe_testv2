(function (){

var list = document.querySelectorAll('.graph') || [];

renderGraph();

function renderGraph() {
	for (var i = 0, len = list.length; i < len; i++) {
		createGraph(list[i]);
	}
}

// source: URL or JSON
function createGraph(context) {
	var type = getAtt(context, 'data-type');
	var source = getAtt(context, 'data-source');
	drawGraph(type, context, source);
}

function drawGraph(type, context, source) {
	var labels = getAtt(context, 'labels').split(',');
	var colors = getAtt(context, 'colors').split(',');
	var noBg = false;
	var fixed = getAtt(context, 'fixed');
	getDataFromSource(source, colors, labels, noBg, fixed, function (error, data) {
		if (error) {
			return console.error('failed to get data from source:', source, error);
		}
		var chart = new Chart(context, {
			type: type,
			data: {
				labels: labels,
				datasets: data
			}
		});	
	});
}


// TODO: this is just for demo
function getRandomInt(min, max) {
	return Math.floor(Math.random() * (max - min + 1) + min);
}

function createDataList(len) {
	var list = [];
	for (var i = 0; i < len; i++) {
		list.push(getRandomInt(0, 20));
	}
	return list;
}

function createFixedData(labels, fixed) {
	if (!fixed) {
		return null;
	}
	fixed = fixed.split(',');
	var color = fixed[0];
	var val = fixed[1];
	var label = fixed[2];
	var list = [];
	for (var i = 0, len = labels.length; i < len; i++) {
		list.push(val);
	}
	return {
		data: list,
		borderColor: color,
		backgroundColor: 'rgba(0,0,0,0)',
		borderWidth: 2,
		label: label
	};
}

function getDataFromSource(source, colors, labels, noBg, fixed, cb) {
	// TODO: get it from server by AJAX
	var data = [];
	// handle fixed data
	var fixedData = createFixedData(labels, fixed);
	if (fixedData) {
		data.push(fixedData);
	}
	// populate the data set
	for (var i = 0, len = colors.length; i < len; i++) {
		var rgb = hex2rgb(colors[i]).join(',');

		console.log(rgb);

		data.push({
			data: createDataList(labels.length),
			borderWidth: 3,
			borderColor: 'rgba(' + rgb + ',1)',
			backgroundColor: (noBg) ? 'rgba(0,0,0,0)' : 'rgba(' + rgb + ',0.4)',
			label: '‰ñ˜H:' + (i + 1)
		});
	}
	cb(null, data);
}

function getAtt(context, attName) {
	return context.getAttribute(attName);
}

function hex2rgb(hex) {
	hex = hex.replace('#', '');
	var splitPattern = hex.length === 3 ? /.{1,1}/g : /.{1,2}/g;
	var list = hex.match(splitPattern);
	for (var i = 0, len = list.length; i < len; i++) {
		list[i] = parseInt(list[i], 16);
	}
	return list;
}

}());
