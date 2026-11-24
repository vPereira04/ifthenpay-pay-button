/* Draws a scored boule that rises from pale dough to a golden crust and steams. */
(function () {
	var K = '#4a2410';
	var body = 'M9 64C9 30 29 16 50 16C71 16 91 30 91 64Q91 72 83 72H17Q9 72 9 64Z';
	var cuts = 'M33 30L26 54M48 24L43 52M63 28L60 54';
	var steam = '';
	var i;
	for (i = 0; i < 3; i++) { steam += '<path class="bk-sm" d="M' + (32 + i * 18) + ' 10q-5 -5 0 -10t0 -10" fill="none" stroke="#a98a74" stroke-width="3.200" stroke-linecap="round" opacity="0"/>'; }
	var art = '<svg viewBox="0 -14 100 90" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="bkd" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="hsl(from var(--a2) h calc(s - 28) calc(l + 30))"/><stop offset="1" stop-color="hsl(from var(--a2) h calc(s - 28) calc(l + 18))"/></linearGradient>'
		+ '<radialGradient id="bkc" cx=".38" cy=".25" r=".95"><stop offset="0" stop-color="hsl(from var(--a2) h s calc(l + 14))"/><stop offset=".6" stop-color="var(--a2)"/><stop offset="1" stop-color="hsl(from var(--a2) h s calc(l - 16))"/></radialGradient>'
		+ '</defs>'
		+ '<ellipse cx="50" cy="74" rx="44" ry="4" fill="#000" opacity=".22"/>'
		+ '<g class="bk-lf">'
		+ '<path d="' + body + '" fill="url(#bkd)" stroke="' + K + '" stroke-width="3" stroke-linejoin="round"/>'
		+ '<g class="bk-bk"><path d="' + body + '" fill="url(#bkc)" stroke="' + K + '" stroke-width="3" stroke-linejoin="round"/></g>'
		+ '<g class="bk-sl"><path d="' + cuts + '" stroke="' + K + '" stroke-width="7" stroke-linecap="round"/><path d="' + cuts + '" stroke="hsl(from var(--a3) h s calc(l - 2))" stroke-width="4" stroke-linecap="round"/></g>'
		+ '<path d="M18 40Q24 28 36 23" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="3" stroke-linecap="round"/>'
		+ '<g fill="#fff" opacity=".75"><circle cx="72" cy="40" r="1.300"/><circle cx="78" cy="50" r="1.100"/><circle cx="68" cy="58" r="1.200"/><circle cx="20" cy="60" r="1.100"/></g>'
		+ '</g>' + steam + '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-bk')) { return; }
			btn.setAttribute('data-bk', '1');
			var node = document.createElement('span');
			node.className = 'bk-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
