/* Draws a porthole with a rocket in it: the flame flickers, the Earth drops away below and the stars streak past. */
(function () {
	var K = '#0a1030';
	var stars = '';
	var i, x, y, r = 7;
	var seed = [[12, 6], [30, 18], [76, 10], [88, 30], [20, 40], [60, 34], [82, 4], [8, 24], [92, 44], [42, 2]];
	for (i = -1; i < 3; i++) {
		seed.forEach(function (p) { stars += '<path d="M' + p[0] + ' ' + (p[1] + i * 40) + 'v3.500" stroke="#fff" stroke-width="1.200" stroke-linecap="round" opacity=".85"/>'; });
	}
	var art = '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="rkb" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#ffffff"/><stop offset=".6" stop-color="#dfe6f2"/><stop offset="1" stop-color="#9aa6bd"/></linearGradient>'
		+ '<radialGradient id="rke" cx=".4" cy=".2" r=".9"><stop offset="0" stop-color="hsl(from var(--a3) h s calc(l + 10))"/><stop offset="1" stop-color="hsl(from var(--a3) h s calc(l - 30))"/></radialGradient>'
		+ '</defs>'
		+ '<g class="rk-all">'
		+ '<g class="rk-st">' + stars + '</g>'
		+ '<g class="rk-er"><circle cx="50" cy="140" r="68" fill="url(#rke)"/><path d="M-6 104Q20 92 38 98Q30 108 10 108ZM60 92Q82 86 104 100Q90 110 66 104Z" fill="#6fcf7d" opacity=".85"/><circle cx="50" cy="140" r="68" fill="none" stroke="#fff" stroke-opacity=".45" stroke-width="2"/></g>'
		+ '<g class="rk-rk">'
		+ '<g class="rk-fl"><path d="M43 62Q50 100 57 62Z" fill="var(--a2)"/><path d="M46 62Q50 88 54 62Z" fill="#ffe27a"/></g>'
		+ '<path d="M41 52L28 70L41 64Z" fill="var(--a2)" stroke="' + K + '" stroke-width="2.200" stroke-linejoin="round"/>'
		+ '<path d="M59 52L72 70L59 64Z" fill="var(--a2)" stroke="' + K + '" stroke-width="2.200" stroke-linejoin="round"/>'
		+ '<path d="M50 10C62 22 64 42 61 62H39C36 42 38 22 50 10Z" fill="url(#rkb)" stroke="' + K + '" stroke-width="2.600" stroke-linejoin="round"/>'
		+ '<path d="M50 10C55 15 58 20 59 26H41C42 20 45 15 50 10Z" fill="var(--a2)" stroke="' + K + '" stroke-width="2" stroke-linejoin="round"/>'
		+ '<circle cx="50" cy="40" r="7" fill="url(#rke)" stroke="' + K + '" stroke-width="2.400"/><path d="M46 37Q47 34 50 34" fill="none" stroke="#fff" stroke-width="1.800" stroke-linecap="round"/>'
		+ '<path d="M39 56H61V62H39Z" fill="#59647d" stroke="' + K + '" stroke-width="1.800" stroke-linejoin="round"/>'
		+ '</g></g></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-rk')) { return; }
			btn.setAttribute('data-rk', '1');
			var node = document.createElement('span');
			node.className = 'rk-win';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
