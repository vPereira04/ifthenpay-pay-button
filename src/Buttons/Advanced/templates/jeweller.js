/* Draws a gold ring with a cut diamond that flashes through the colours of the rainbow, a glint and sparkles. */
(function () {
	var K = '#2a1a02', W = '#1b2a44';
	var star = function (x, y, s, cls) { return '<path class="jw-st ' + cls + '" transform="translate(' + x + ' ' + y + ') scale(' + s + ')" d="M0 -9L2.400 -2.400L9 0L2.400 2.400L0 9L-2.400 2.400L-9 0L-2.400 -2.400Z" fill="#fff" stroke="#bfe8ff" stroke-width=".8"/>'; };
	var f = function (pts, fill) { return '<path d="' + pts + 'Z" fill="' + fill + '" stroke="' + W + '" stroke-width=".9" stroke-linejoin="round"/>'; };
	var art = '<svg viewBox="0 0 112 100" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="jwg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(from var(--a1) h s calc(l + 34))"/><stop offset=".35" stop-color="hsl(from var(--a1) h s calc(l + 6))"/><stop offset=".7" stop-color="hsl(from var(--a1) h s calc(l - 18))"/><stop offset="1" stop-color="hsl(from var(--a1) h s calc(l + 22))"/></linearGradient>'
		+ '<clipPath id="jwc"><path d="M44 8H68L80 22L56 40L32 22Z"/></clipPath></defs>'
		// the band, front and shine
		+ '<circle cx="56" cy="70" r="27" fill="none" stroke="' + K + '" stroke-width="10.500"/><circle cx="56" cy="70" r="27" fill="none" stroke="url(#jwg)" stroke-width="8"/>'
		+ '<path d="M33 60A27 27 0 0 1 48 45" fill="none" stroke="#fff" stroke-opacity=".8" stroke-width="1.800" stroke-linecap="round"/><circle cx="56" cy="70" r="22.500" fill="none" stroke="#000" stroke-opacity=".25" stroke-width="1"/>'
		// the setting: four claws
		+ '<path d="M38 34L42 44H70L74 34L66 38H46Z" fill="url(#jwg)" stroke="' + K + '" stroke-width="1.300" stroke-linejoin="round"/>'
		// the diamond
		+ '<g class="jw-gm">'
		+ f('M44 8H68L80 22H32', '#f5fdff') + f('M32 22L44 8L50 22', '#bfe6fb') + f('M80 22L68 8L62 22', '#8fcdf2') + f('M44 8H68L62 22H50', '#ffffff')
		+ f('M32 22H50L56 40', '#a4d8f4') + f('M50 22H56L56 40', '#e4f6ff') + f('M56 22H62L56 40', '#7fc2ea') + f('M62 22H80L56 40', '#b6e1f8')
		+ '<g clip-path="url(#jwc)"><path class="jw-gl" d="M34 6L44 6L30 44L20 44Z" fill="#fff" opacity=".85"/></g>'
		+ '</g>'
		+ star(24, 18, 1.100, 'jw-s1') + star(90, 14, 0.9, 'jw-s2') + star(86, 46, 0.7, 'jw-s3') + star(18, 52, 0.8, 'jw-s4')
		+ '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-jw')) { return; }
			btn.setAttribute('data-jw', '1');
			var node = document.createElement('span');
			node.className = 'jw-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
