(function () {

// c3.css change the colr of this class to match it c3-chart-arc path
var BASE_COLOR = '#f5b82b';
//var BASE_COLOR = '#fff';
//var BAR_COLOR = '#fbf7d3';
//var BAR_COLOR = '#f5b82b';
var BAR_COLOR = '#fff';
var BASE_SIZE = 200;
var RIM = 4;

var resizeFunctions = [];

// setup redraw on window resize
window.addEventListener('resize', function () {
	window.redrawPie();
}, false);

window.createPie = createPie;
window.numberCount = count;
window.redrawPie = function () {
	for (var i = 0, len = resizeFunctions.length; i < len; i++) {
		resizeFunctions[i]();
	}
};
window.lightingBolt = function (width, height) {
	var elm = document.createElement('div');
	lightingBolt(elm);
	window.setTimeout(function () {
		var svg = elm.querySelector('svg');
		svg.setAttribute('width', width + 'px');
		svg.setAttribute('height', height + 'px');
	}, 0);
	return elm;
};

// if _redraw is true, do not add resize function
function createPie(targetElm, size, value, cb, _redraw) {
	while (targetElm.firstChild) {
		targetElm.removeChild(targetElm.firstChild);
	}
	var elm = document.createElement('div');
	elm.style.opacity = 0;
	var offset = 4;
	var bs = BASE_SIZE + offset;
	//elm.style.transition = 'opacity 0.4s ease-out';
	elm.style.width = bs + 'px';
	elm.style.height = bs + 'px';
	elm.style.background = BASE_COLOR;
	elm.style.borderRadius = ((bs + RIM) / 2) + 'px';
	//elm.style.border = RIM + 'px solid #fbf7d3';
	//elm.style.border = RIM + 'px solid #fbf7d3';
	elm.style.border = RIM + 'px solid #fff';
	targetElm.appendChild(elm);
	var chartElm = document.createElement('div');
	chartElm.style.width = BASE_SIZE + 'px';
	chartElm.style.height = BASE_SIZE + 'px';
	chartElm.style.top = '-1px';
	chartElm.style.left = '2px';
	elm.appendChild(chartElm);
	var chart = createChart(chartElm, value);
	if (chart.flip) {
		chartElm.style.left = '-1.8px';
	}
	// adjust the pie chart element size
	window.setTimeout(function () {
		chartElm.style.transformOrigin = '51% 13.7%';
		if (chart.flip) {
			chartElm.style.transform = 'scale(-1.26, 1.26)';
		} else {
			chartElm.style.transform = 'scale(1.26, 1.26)';
		}
		// scale to match the instructed overall size
		window.setTimeout(function () {
			var scale = size / BASE_SIZE;
			elm.style.transform = 'scale(' + scale + ')';
			elm.style.opacity = 1;
		}, 0);
	}, 0);
	if (!_redraw) {
		resize(targetElm, size, value, cb);
	}
	var topElm = createTop(elm, BASE_SIZE);
	if (cb) {
		cb(null, topElm);
	}
}

function resize(targetElm, size, value, cb) {
	resizeFunctions.push(function () {
		createPie(targetElm, size, value, cb, true);
	});
}

// value is in percentage number
function createChart(elm, value) {
	// force disable interactiv functions
	elm.style.pointerEvents = 'none';
	// configurations
	var columns = [];
	value = value > 100 ? 100 : value;
	var pattern = [
		BASE_COLOR,
		BAR_COLOR
	];

	var s = parseInt(window.location.search.replace('?test=', ''));
	if (!isNaN(s)) {
		value = s;
		console.log(value / 100, 100 - value, (100 - value) - 50);
	}
	/*
	columns.push([ 'off', 100 - value ]);
	columns.push([ 'value', value ]);
	*/
	columns.push([ 'value', value ]);
	columns.push([ 'off', 100 - value ]);
	var conf = {
		bindto: elm,
		legend: { hide: true },
		tooltip: { show: false },
		color: {
			pattern: pattern
		},
		data: {
			type: 'pie',
			columns: columns
		},
		pie: {
			label: {
				format: function () {
					return '';
				}
			}
		}
	};
	var chart = c3.generate(conf);	
	// counter clockwise
	//chart.flip = value > 50 ? true : false;
	// clockwise
	chart.flip = value < 50 ? true : false;
	return chart;
}

function createTop(elm, size) {
	var w = size * 0.85;
	var rim = 3;
	var hack = 2;
	var offset = (rim % 2) ? 0.5 : 0;
	var top = document.createElement('div');
	top.style.width = w + 'px';
	top.style.height = w + 'px';
	top.style.borderRadius = ((w+rim) * 2) + 'px';
	top.style.position = 'relative';
	top.style.top = ( -1 * (((size - w) / 2) + rim + w + offset - (hack + 0.15)) ) + 'px';
	top.style.left = ((((size - w) / 2) - rim) + hack) + 'px';
	top.style.backgroundColor = '#fbf7d3';
	top.style.boxShadow = 'inset 0 0 0 4px #fefefc';
	top.style.border = (rim) + 'px solid ' + BASE_COLOR;
	elm.appendChild(top);
	return top;
}

function count(elm, suffix, val, prev) {
	if (!prev) {
		prev = 0;
	}
	var diff = val - prev;
	if (diff === 0) {
		return elm.textContent = elm + suffix;	
	}
	var step = 5;
	var mod = diff > 0 ? plus : minus;
	var op = function () {
		prev = mod(prev);
		elm.textContent = prev + suffix;
		if (prev === val) {
			return;
		}
		window.setTimeout(op, step);
	};
	window.setTimeout(op, step);
}

function plus(val) {
	return val + 1;
}

function minus(val) {
	return val - 1;
}

function lightingBolt(elm) {
	elm.innerHTML += '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" class="" pagealignment="none" x="0px" y="0px" width="130px" height="270px" viewBox="0 0 130 270" enable-background="new 0 0 130 270" xml:space="preserve"><defs></defs><g type="LAYER" name="workspace" id="workspace" locked="true"></g><g type="LAYER" name="Layer 01" id="Layer 01"><path transform="matrix(0.5068685591828757 -0.8620233545048972 0.8620233545048972 0.5068685591828757 19.88560817638404 120.5531087862949)" width="155.41825023386022" height="97.77326914228547" stroke-width="0.8792895453869604" stroke-miterlimit="3" stroke="#F0D784" fill="#F0D784" d="M1.4210854715202004e-13,0 L155.41825023386036,33.962742140008004 L32.77922881877984,38.85976080068758 L24.391407985440736,97.77326914228547 L1.4210854715202004e-13,0 Z "></path><path transform="matrix(-0.6194807674638299 0.7850118335047086 -0.7850118335047086 -0.6194807674638299 117.90820323469853 149.07427502257727)" width="164.6519759477373" height="94.6172110206337" stroke-width="0.8799116122609861" stroke-miterlimit="3" stroke="#F0D784" fill="#F0D784" d="M2.2737367544323206e-13,1.1368683772161603e-13 L164.65197594773753,15.992380135487224 L40.64176913729504,41.192591851724956 L38.3336594146852,94.61721102063382 L0.8614123814603545,1.0737257073158162 Z "></path></g></svg>';
}

}());
