/* Draws a runner from the side whose legs, knees and arms follow a real run cycle; speed lines trail behind. */
(function () {
	var K = '#0d1426';
	var SK = '#f2c29b';
	var SB = '#d49f78';
	var limb = function (x1, y1, x2, y2, col, w) {
		return '<path d="M' + x1 + ' ' + y1 + 'L' + x2 + ' ' + y2 + '" stroke="' + K + '" stroke-width="' + (w + 3) + '" stroke-linecap="round"/><path d="M' + x1 + ' ' + y1 + 'L' + x2 + ' ' + y2 + '" stroke="' + col + '" stroke-width="' + w + '" stroke-linecap="round"/>';
	};
	var shoe = function (back) {
		return '<path d="M40.500 72.500C40.500 70 43 69.500 45 70L47 71.500C51 72 55 74 55 77C55 78.800 53.500 79 52 79H41.500C40 79 39.500 78 40.500 76Z" fill="' + (back ? '#c9ccd6' : '#fff') + '" stroke="' + K + '" stroke-width="1.800" stroke-linejoin="round"/><path d="M40.500 77.300H54.300" stroke="var(--a3)" stroke-width="2"/>';
	};
	var arm = function (cls, col) {
		return '<g class="rn2-' + cls + 'a">' + limb(50, 24, 50, 35, col, 5.600) + '<g class="rn2-f' + cls + '">' + limb(50, 35, 50, 45, col, 5) + '<circle cx="50" cy="46" r="2.600" fill="' + col + '" stroke="' + K + '" stroke-width="1.400"/></g></g>';
	};
	var leg = function (cls, col, back) {
		return '<g class="rn2-l' + cls + '">' + limb(44, 46, 44, 61, col, 6.600) + '<g class="rn2-s' + cls + '">' + limb(44, 61, 44, 74, col, 5.800) + shoe(back) + '</g></g>';
	};
	var art = '<svg viewBox="0 0 100 80" xmlns="http://www.w3.org/2000/svg">'
		+ '<g class="rn2-sl rn2-s1"><path d="M4 28H26" stroke="#fff" stroke-width="2.600" stroke-linecap="round"/></g>'
		+ '<g class="rn2-sl rn2-s2"><path d="M0 42H22" stroke="#fff" stroke-width="2.600" stroke-linecap="round"/></g>'
		+ '<g class="rn2-sl rn2-s3"><path d="M6 56H24" stroke="#fff" stroke-width="2.600" stroke-linecap="round"/></g>'
		+ '<g class="rn2-bd">'
		+ arm('b', SB) + leg('b', SB, true)
		+ '<path d="M44 44L50 25" stroke="' + K + '" stroke-width="14" stroke-linecap="round"/><path d="M44 44L50 25" stroke="var(--a2)" stroke-width="10.800" stroke-linecap="round"/>'
		+ '<path d="M45.500 39L47.800 31" stroke="#fff" stroke-opacity=".55" stroke-width="2.400" stroke-linecap="round"/>'
		+ '<path d="M36 44Q36 40 44 40Q52 40 52 44L52 50Q52 53 44 53Q36 53 36 50Z" fill="var(--a3)" stroke="' + K + '" stroke-width="2" stroke-linejoin="round"/>'
		+ '<circle cx="54" cy="14.500" r="7" fill="' + SK + '" stroke="' + K + '" stroke-width="2.200"/>'
		+ '<path d="M47.500 13.500Q48 7.500 54 7.500Q60 7.500 61 12.500Q55 10 47.500 13.500Z" fill="#3a2418" stroke="' + K + '" stroke-width="1.200" stroke-linejoin="round"/>'
		+ '<path d="M47.300 13Q54 10.800 61.200 13" fill="none" stroke="var(--a3)" stroke-width="2.800" stroke-linecap="round"/>'
		+ '<circle cx="58.500" cy="16" r="1" fill="' + K + '"/>'
		+ '<path d="M47.500 12Q43 12 42 17" fill="none" stroke="var(--a3)" stroke-width="2.400" stroke-linecap="round"/>'
		+ leg('a', SK, false) + arm('a', SK)
		+ '</g></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-rn2')) { return; }
			btn.setAttribute('data-rn2', '1');
			var node = document.createElement('span');
			node.className = 'rn2-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
