/* Draws a small map where a plane flies a dotted route to a pin, leaving a solid line behind it, and the pin bounces on arrival. */
(function () {
	var K = '#0a2340';
	var plane = '<svg viewBox="-13 -13 26 26" xmlns="http://www.w3.org/2000/svg"><g stroke="' + K + '" stroke-width="1.300" stroke-linejoin="round">'
		+ '<path d="M-2 -2L-7 -11H-3.500L6 -2Z" fill="#fff"/><path d="M-2 2L-7 11H-3.500L6 2Z" fill="#fff"/>'
		+ '<path d="M-10 -1.500L-12.500 -6H-10L-6.500 -1.500Z" fill="#fff"/><path d="M-10 1.500L-12.500 6H-10L-6.500 1.500Z" fill="#fff"/>'
		+ '<path d="M-12 0Q-6 -2.800 8 -2.800Q13 -2.800 13 0Q13 2.800 8 2.800Q-6 2.800 -12 0Z" fill="#fff"/></g>'
		+ '<path d="M-5 -1.200H8" stroke="var(--a2)" stroke-width="1.600" stroke-linecap="round"/><ellipse cx="9" cy="0" rx="2.200" ry="1.300" fill="' + K + '" fill-opacity=".7"/></svg>';
	var art = '<svg viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg">'
		+ '<path d="M-4 40C6 34 18 38 22 48C26 58 18 66 8 68L-4 68Z" fill="var(--a3)" opacity=".9"/>'
		+ '<path d="M44 76C42 62 52 54 62 56C72 58 80 66 78 76Z" fill="var(--a3)" opacity=".9"/><path d="M30 2C36 -2 48 0 52 6C50 12 40 10 34 8Z" fill="var(--a3)" opacity=".8"/>'
		+ '<path d="M0 24H72M0 48H72M24 0V72M48 0V72" stroke="#fff" stroke-opacity=".35" stroke-width=".8"/>'
		+ '<path d="M10 58C6 38 28 20 56 28" fill="none" stroke="#0a2340" stroke-opacity=".55" stroke-width="1.800" stroke-dasharray="3 3.500" stroke-linecap="round"/>'
		+ '<path class="tv-tr" pathLength="1" d="M10 58C6 38 28 20 56 28" fill="none" stroke="#fff" stroke-width="2.600" stroke-linecap="round"/>'
		+ '<circle cx="10" cy="58" r="4" fill="#fff" stroke="' + K + '" stroke-width="1.800"/>'
		+ '<g transform="translate(56 28)"><circle class="tv-rp" cx="0" cy="0" r="6" fill="none" stroke="var(--a2)" stroke-width="2"/>'
		+ '<g class="tv-pin"><path d="M0 0C-8 -9 -8 -20 0 -20C8 -20 8 -9 0 0Z" fill="var(--a2)" stroke="' + K + '" stroke-width="1.900" stroke-linejoin="round"/><circle cx="0" cy="-12" r="3.200" fill="#fff" stroke="' + K + '" stroke-width="1.200"/></g></g>'
		+ '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-tv')) { return; }
			btn.setAttribute('data-tv', '1');
			var node = document.createElement('span');
			node.className = 'tv-win';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art + '<span class="tv-pl">' + plane + '</span>';
			btn.appendChild(node);
		});
	});
})();
