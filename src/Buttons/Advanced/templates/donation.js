/* Draws a glass donation jar with a coin slot: coins drop through it one after another, the pile grows and the heart pops. */
(function () {
	var K = '#06302a';
	var jar = 'M16 32H64V70Q64 82 52 82H28Q16 82 16 70Z';
	var heap = '';
	[[24, 76], [34, 76], [44, 76], [54, 76], [29, 70.500], [39, 70.500], [49, 70.500], [34, 65], [44, 65]].forEach(function (p) { heap += '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="5.600" fill="url(#djc)" stroke="#5a3a00" stroke-width="1.500"/>'; });
	var art = '<svg viewBox="0 0 76 84" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<radialGradient id="djc" cx=".35" cy=".3" r=".85"><stop offset="0" stop-color="hsl(from var(--a2) h s calc(l + 22))"/><stop offset=".6" stop-color="var(--a2)"/><stop offset="1" stop-color="hsl(from var(--a2) h s calc(l - 14))"/></radialGradient>'
		+ '<clipPath id="djj"><path d="' + jar + '"/></clipPath>'
		+ '</defs>'
		+ '<ellipse cx="40" cy="83" rx="26" ry="3" fill="#000" opacity=".25"/>'
		+ '<path d="' + jar + '" fill="#fff" fill-opacity=".22"/>'
		+ '<g clip-path="url(#djj)">' + heap + '</g>'
		+ '<g class="dj-all">' + '<g transform="translate(40 -6)"><g class="dj-cn dj-c0"><g class="dj-sp dj-s0"><circle r="6.200" fill="url(#djc)" stroke="#5a3a00" stroke-width="1.800"/><circle r="3.600" fill="none" stroke="#b8860b" stroke-width="1.200"/><path d="M-3 -3Q-1 -5 2 -5" fill="none" stroke="#fff" stroke-opacity=".8" stroke-width="1.100" stroke-linecap="round"/></g></g></g><g transform="translate(40 -6)"><g class="dj-cn dj-c1"><g class="dj-sp dj-s1"><circle r="6.200" fill="url(#djc)" stroke="#5a3a00" stroke-width="1.800"/><circle r="3.600" fill="none" stroke="#b8860b" stroke-width="1.200"/><path d="M-3 -3Q-1 -5 2 -5" fill="none" stroke="#fff" stroke-opacity=".8" stroke-width="1.100" stroke-linecap="round"/></g></g></g><g transform="translate(40 -6)"><g class="dj-cn dj-c2"><g class="dj-sp dj-s2"><circle r="6.200" fill="url(#djc)" stroke="#5a3a00" stroke-width="1.800"/><circle r="3.600" fill="none" stroke="#b8860b" stroke-width="1.200"/><path d="M-3 -3Q-1 -5 2 -5" fill="none" stroke="#fff" stroke-opacity=".8" stroke-width="1.100" stroke-linecap="round"/></g></g></g><g transform="translate(40 -6)"><g class="dj-cn dj-c3"><g class="dj-sp dj-s3"><circle r="6.200" fill="url(#djc)" stroke="#5a3a00" stroke-width="1.800"/><circle r="3.600" fill="none" stroke="#b8860b" stroke-width="1.200"/><path d="M-3 -3Q-1 -5 2 -5" fill="none" stroke="#fff" stroke-opacity=".8" stroke-width="1.100" stroke-linecap="round"/></g></g></g>' + '</g>'
		+ '<path d="' + jar + '" fill="none" stroke="' + K + '" stroke-width="2.800" stroke-linejoin="round"/>'
		+ '<path d="M21 40V66" stroke="#fff" stroke-opacity=".7" stroke-width="2.600" stroke-linecap="round"/>'
		+ '<rect x="12" y="20" width="56" height="13" rx="4.500" fill="hsl(from var(--a2) h s calc(l - 6))" stroke="' + K + '" stroke-width="2.600"/>'
		+ '<rect x="12" y="20" width="56" height="5" rx="3" fill="#fff" fill-opacity=".3"/>'
		+ '<rect x="29" y="24.500" width="22" height="4.500" rx="2.200" fill="' + K + '"/>'
		+ '<g transform="translate(28 50)"><g class="dj-hr"><path d="M0 7C-9 1 -9 -8 -3.500 -8C-1.500 -8 -.2 -7 0 -5.500C.2 -7 1.500 -8 3.500 -8C9 -8 9 1 0 7Z" fill="var(--a3)" stroke="#fff" stroke-width="1.800" stroke-linejoin="round"/></g></g>'
		+ '<g transform="translate(64 14)"><path class="dj-st" d="M0 -9L2 -2L9 0L2 2L0 9L-2 2L-9 0L-2 -2Z" fill="#fff" stroke="' + K + '" stroke-width=".8"/></g>'
		+ '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-dj')) { return; }
			btn.setAttribute('data-dj', '1');
			var node = document.createElement('span');
			node.className = 'dj-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
