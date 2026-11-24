/* Draws the platter, the record, the tonearm and the controls. */
(function () {
	var grooves = '';
	[[29.500, '40 9 22 18'], [26, '60 12 18 30'], [22.500, '24 14 46 8'], [19, '30 10 14 26']].forEach(function (g) {
		grooves += '<circle cx="44" cy="42" r="' + g[0] + '" fill="none" stroke="#fff" stroke-opacity=".13" stroke-width="1" stroke-dasharray="' + g[1] + '"/>';
	});
	var disc = '<g class="tt-disc">'
		+ '<circle cx="44" cy="42" r="38" fill="hsl(from var(--a1) calc(h - 13.6) calc(s - 18.7) calc(l - 77.6))"/><circle cx="44" cy="42" r="37" fill="url(#ttm)" stroke="#fff" stroke-opacity=".5" stroke-width=".6"/>'
		+ '<circle cx="44" cy="42" r="35.500" fill="none" stroke="#2a2d33" stroke-width="2.600" stroke-dasharray="1 3.100"/>'
		+ '<circle cx="44" cy="42" r="34" fill="#0d0d10"/><circle cx="44" cy="42" r="33" fill="#17171c"/>' + grooves
		+ '<circle cx="44" cy="42" r="13" fill="var(--a2)"/><circle cx="44" cy="42" r="13" fill="none" stroke="var(--a1)" stroke-width="1.200"/><circle cx="44" cy="42" r="10" fill="none" stroke="var(--a1)" stroke-opacity=".6" stroke-width=".6"/>'
		+ '<path d="M36 38H52M37 46H51" stroke="var(--a1)" stroke-width="1.200" stroke-linecap="round"/><circle cx="50" cy="35" r="1.500" fill="var(--a1)"/>'
		+ '<circle cx="44" cy="42" r="2.300" fill="url(#ttm)" stroke="#444" stroke-width=".6"/></g>';
	var sheen = '<path d="M44 42V8.500A33.500 33.500 0 0 1 70 20Z" fill="#fff" fill-opacity=".09"/><path d="M44 42V75.500A33.500 33.500 0 0 1 18 64Z" fill="#fff" fill-opacity=".09"/>';
	var arm = '<g class="tt-arm">'
		+ '<path d="M126 10L128 6.500" stroke="hsl(from var(--a1) calc(h - 9.3) calc(s - 63.8) calc(l - 81))" stroke-width="9" stroke-linecap="round"/><path d="M126 10L128 6.500" stroke="url(#ttm)" stroke-width="6" stroke-linecap="round"/>'
		+ '<circle cx="124" cy="17" r="10" fill="hsl(from var(--a1) calc(h - 9.3) calc(s - 63.8) calc(l - 81))"/><circle cx="124" cy="17" r="8.500" fill="url(#ttm)" stroke="#555" stroke-width=".6"/><circle cx="124" cy="17" r="3.400" fill="#2a2d33"/><circle cx="124" cy="17" r="1.300" fill="#c9cdd4"/>'
		+ '<path d="M124 17L105 70" stroke="hsl(from var(--a1) calc(h - 9.3) calc(s - 63.8) calc(l - 81))" stroke-width="4.400" stroke-linecap="round"/><path d="M124 17L105 70" stroke="url(#ttm)" stroke-width="2.200" stroke-linecap="round"/>'
		+ '<path d="M107.500 69L102.500 71L98.500 80.500L104.500 82.500L108 74Z" fill="hsl(from var(--a1) calc(h - 9.3) calc(s - 63.8) calc(l - 81))" stroke="hsl(from var(--a1) calc(h - 9.3) calc(s - 63.8) calc(l - 81))" stroke-width="1.200" stroke-linejoin="round"/><path d="M104.500 72L101.500 79" stroke="hsl(from var(--a1) h calc(s - 16.8) calc(l - 18.2))" stroke-width="1" stroke-linecap="round"/><circle cx="101.500" cy="82" r="1.500" fill="var(--a2)"/></g>';
	var rest = '<path d="M93 70Q93 84 104 82" fill="none" stroke="hsl(from var(--a1) calc(h - 9.3) calc(s - 63.8) calc(l - 81))" stroke-opacity=".5" stroke-width="2" stroke-linecap="round"/>';
	var svg = '<svg viewBox="0 0 150 84" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="ttm" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f4f5f8"/><stop offset=".5" stop-color="#aeb3bd"/><stop offset="1" stop-color="#d9dce2"/></linearGradient></defs>' + rest + disc + sheen + arm + '</svg>';
	var note = function (color) {
		return '<svg viewBox="0 0 14 18" xmlns="http://www.w3.org/2000/svg"><path d="M5 14V2L12 4V6L7 4.600V14" fill="' + color + '" stroke="' + color + '" stroke-width="1.100" stroke-linejoin="round"/><ellipse cx="3.600" cy="14.400" rx="3" ry="2.300" fill="' + color + '"/></svg>';
	};
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-tt')) { return; }
			btn.setAttribute('data-tt', '1');
			var make = function (cls, html) {
				var node = document.createElement('span');
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (html) { node.innerHTML = html; }
				btn.appendChild(node);
			};
			make('tt-ic', svg);
			make('tt-ctl', '<i class="tt-led"></i><i class="tt-sl"></i><i class="tt-kb"></i><i class="tt-btn"></i>');
			make('tt-nt tt-n1', note('var(--a1)'));
			make('tt-nt tt-n2', note('hsl(from var(--a1) calc(h - 2.1) calc(s - 14) calc(l - 21.4))'));
		});
	});
})();