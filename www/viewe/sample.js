var flat = [];
var values = [];
var key1 = 'Data 1';
var key2 = 'Data 2';
var range1 = [ 5, 5 ];
var range2 = [ 0, 10 ];
var color1 = '#ffbb4c';
var color2 = '#ff574c'; 
var labels = [];

function getData() {
	var data = {
		labels: [ 'x' ],
		flats: [ key1 ],
		values: [ key2 ]
	};
	for (var i = 0; i < 24; i++) {
		data.labels.push(i + 1);
		//data.flats.push(getRandomInt(range1[0], range1[1]));
		//data.values.push(getRandomInt(range2[0], range2[1]));
	}
	var colors = {};
	colors[key1] = color1;
	colors[key2] = color2;
	var sample = {
		x: 'x',
		colors: colors,
		columns: [
			data.labels,
			data.flats,
			data.values
		]
	};
	window.sample = sample;
}

function getBarData() {
	for (var i = 0, len = labels.length; i < len; i++) {
		values.push({
			label: labels[i],
			//value: getRandomInt(range1[0], range2[1])
		});	
	}
	var sample = [
		{
			key: key1,
			values: values,
			color: color1
		}
	];
	window.sample = sample;
}

function getPieData() {
	/*
	var value = getRandomInt(0, 100);
	var off = 100 - value;
	var sample = [
		{ label: '', value: value, color: '#ffc038' },
		{ label: 'none', value: off, color: '#fff' }
	];
	window.pieSample = sample;
	*/
}
