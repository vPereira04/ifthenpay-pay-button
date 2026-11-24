/* Draws a small candlestick chart with a rising trend line and a live price dot. */
(function () {
	var G = 'var(--a1)', R = 'var(--a3)', K = 'hsl(from var(--a2) calc(h - 3.6) calc(s + 16) calc(l - 8.6))';
	var candle = function (i, x, wt, wb, bt, bb, col) {
		return '<g class="st-c st-c' + i + '"><path d="M' + (x + 4.500) + ' ' + wt + 'V' + wb + '" stroke="' + col + '" stroke-width="1.800" stroke-linecap="round"/>'
			+ '<rect x="' + x + '" y="' + bt + '" width="9" height="' + (bb - bt) + '" rx="1.600" fill="' + col + '" stroke="' + K + '" stroke-width="1"/></g>';
	};
	var chart = '<svg viewBox="0 0 88 46" xmlns="http://www.w3.org/2000/svg">'
		+ '<path d="M0 12H88M0 24H88M0 36H88" stroke="#fff" stroke-opacity=".08" stroke-width="1"/>'
		+ candle(1, 4, 24, 42, 28, 38, G) + candle(2, 20, 22, 38, 26, 34, R) + candle(3, 36, 16, 40, 22, 34, G) + candle(4, 52, 10, 32, 14, 28, G) + candle(5, 68, 5, 28, 8, 22, G)
		+ '<path class="st-ln" pathLength="100" d="M2 40L20 33L36 29L54 19L70 11L80 4" fill="none" stroke="hsl(from var(--a1) h calc(s + 9.4) calc(l + 39))" stroke-width="2.600" stroke-linecap="round" stroke-linejoin="round"/>'
		+ '<path class="st-ah" d="M86 0L74 2.500L83 11Z" fill="hsl(from var(--a1) h calc(s + 9.4) calc(l + 39))" stroke="' + K + '" stroke-width="1" stroke-linejoin="round"/>'
		+ '<circle class="st-pg" cx="76" cy="8" r="3" fill="none" stroke="' + G + '" stroke-width="1.600"/></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-st')) { return; }
			btn.setAttribute('data-st', '1');
			var n = document.createElement('span');
			n.className = 'st-ch';
			n.setAttribute('aria-hidden', 'true');
			n.innerHTML = chart;
			btn.appendChild(n);
		});
	});
})();