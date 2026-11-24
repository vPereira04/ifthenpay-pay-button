/* Draws a waffle cone with two scoops and a cherry that drops on top, squashing the scoop while a drip runs down. */
(function () {
	var K = '#4a2410';
	var cross = '';
	var i;
	for (i = -2; i < 7; i++) { cross += 'M' + (24 + i * 9) + ' 50L' + (60 + i * 9) + ' 110M' + (76 - i * 9) + ' 50L' + (40 - i * 9) + ' 110'; }
	var art = '<svg viewBox="0 -6 100 116" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<clipPath id="icc"><path d="M30 52L70 52L50 107Z"/></clipPath>'
		+ '<linearGradient id="icw" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(from var(--a1) h s calc(l + 26))"/><stop offset="1" stop-color="hsl(from var(--a1) h s calc(l + 6))"/></linearGradient>'
		+ '<radialGradient id="ic2" cx=".35" cy=".3" r=".9"><stop offset="0" stop-color="hsl(from var(--a3) h s calc(l + 16))"/><stop offset="1" stop-color="hsl(from var(--a3) h s calc(l - 8))"/></radialGradient>'
		+ '<radialGradient id="ic1" cx=".35" cy=".3" r=".9"><stop offset="0" stop-color="hsl(from var(--a2) h s calc(l + 16))"/><stop offset="1" stop-color="hsl(from var(--a2) h s calc(l - 8))"/></radialGradient>'
		+ '</defs>'
		// cone
		+ '<path d="M30 52L70 52L50 107Z" fill="url(#icw)"/>'
		+ '<g clip-path="url(#icc)"><path d="' + cross + '" stroke="' + K + '" stroke-opacity=".45" stroke-width="1.300"/></g>'
		+ '<path d="M30 52L70 52L50 107Z" fill="none" stroke="' + K + '" stroke-width="2.600" stroke-linejoin="round"/>'
		// bottom scoop with its skirt
		+ '<path d="M27 50A23 23 0 0 1 73 50Q73 57 68 57Q64 61 60 57Q56 62 52 57Q48 61 44 57Q40 62 36 57Q27 58 27 50Z" fill="url(#ic2)" stroke="' + K + '" stroke-width="2.600" stroke-linejoin="round"/>'
		+ '<path d="M33 44Q35 36 42 33" fill="none" stroke="#fff" stroke-opacity=".75" stroke-width="2.600" stroke-linecap="round"/>'
		// drip running off the skirt
		+ '<g transform="translate(37 56)"><rect class="ic-dp" x="-3" y="0" width="6" height="12" rx="3" fill="url(#ic2)" stroke="' + K + '" stroke-width="1.800"/></g>'
		+ '<g transform="translate(37 62)"><ellipse class="ic-dr" cx="0" cy="8" rx="2.200" ry="3" fill="hsl(from var(--a3) h s calc(l + 4))" stroke="' + K + '" stroke-width="1.200" opacity="0"/></g>'
		// top scoop
		+ '<g class="ic-s1"><path d="M33 33A17 17 0 0 1 67 33Q67 38 63 38Q59 42 55 38Q51 43 47 38Q43 42 39 38Q33 39 33 33Z" fill="url(#ic1)" stroke="' + K + '" stroke-width="2.600" stroke-linejoin="round"/>'
		+ '<path d="M38 27Q40 21 46 19" fill="none" stroke="#fff" stroke-opacity=".8" stroke-width="2.400" stroke-linecap="round"/>'
		+ '<g stroke-linecap="round" stroke-width="2.600"><path d="M54 26l3-1.500" stroke="#fff"/><path d="M60 31l1.500-3" stroke="#ffe27a"/><path d="M47 31l3 1" stroke="#7fd6ff"/><path d="M55 33l-2 2.500" stroke="#fff"/></g></g>'
		// cherry
		+ '<g class="ic-ch"><path d="M50 5Q51 -2 58 -4" fill="none" stroke="#3a7a2c" stroke-width="2" stroke-linecap="round"/><circle cx="50" cy="10" r="6" fill="#e2243b" stroke="' + K + '" stroke-width="2"/><circle cx="48" cy="8" r="1.800" fill="#fff" opacity=".85"/></g>'
		// impact sparkle
		+ '<g transform="translate(50 17)"><path class="ic-sp" d="M0 -9L2 -2L9 0L2 2L0 9L-2 2L-9 0L-2 -2Z" fill="#fff"/></g>'
		+ '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-ic')) { return; }
			btn.setAttribute('data-ic', '1');
			var node = document.createElement('span');
			node.className = 'ic-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
