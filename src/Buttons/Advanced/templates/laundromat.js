/* Draws the washing machine's porthole: chrome ring, water, a tumbling shirt, sock and towel, bubbles and the latch. */
(function () {
	var K = '#0e2347';
	var art = '<svg viewBox="0 0 104 104" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="lwc" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset=".35" stop-color="#b6bfcc"/><stop offset=".65" stop-color="#7d8798"/><stop offset="1" stop-color="#e3e8ef"/></linearGradient>'
		+ '<radialGradient id="lww" cx=".35" cy=".25" r=".95"><stop offset="0" stop-color="hsl(from var(--a1) h s calc(l + 38))"/><stop offset=".55" stop-color="hsl(from var(--a1) h s calc(l + 10))"/><stop offset="1" stop-color="hsl(from var(--a1) h s calc(l - 12))"/></radialGradient>'
		+ '<clipPath id="lwp"><circle cx="52" cy="52" r="35"/></clipPath></defs>'
		// latch on the right of the ring
		+ '<rect x="84" y="44" width="16" height="16" rx="4" fill="url(#lwc)" stroke="' + K + '" stroke-width="1.600"/><circle cx="92" cy="52" r="2.600" fill="#2b3547"/>'
		// chrome ring + dark gasket
		+ '<circle cx="52" cy="52" r="48" fill="' + K + '"/><circle cx="52" cy="52" r="47" fill="url(#lwc)" stroke="' + K + '" stroke-width="1.200"/><circle cx="52" cy="52" r="40.500" fill="#233044" stroke="' + K + '" stroke-width="1.500"/>'
		+ '<circle cx="52" cy="52" r="35" fill="url(#lww)"/>'
		+ '<g clip-path="url(#lwp)"><g class="lw-wv"><path d="M10 60Q22 54 34 60T58 60T82 60T106 60V100H10Z" fill="hsl(from var(--a1) h s calc(l + 28))" opacity=".55"/></g>'
		// tumbling laundry
		+ '<g class="lw-dr">'
		+ '<g transform="translate(34 36) rotate(-20)"><path d="M-13 -9L-6 -13Q0 -7 6 -13L13 -9L17 -2L10 1V14H-10V1L-17 -2Z" fill="#ff5a5f" stroke="' + K + '" stroke-width="1.400" stroke-linejoin="round"/></g>'
		+ '<g transform="translate(66 62) rotate(35)"><path d="M-5 -14H5V4L13 10Q15 16 9 16H-3Q-9 16 -9 10Z" fill="#ffd23f" stroke="' + K + '" stroke-width="1.400" stroke-linejoin="round"/><path d="M-5 -9H5" stroke="#fff" stroke-width="2"/></g>'
		+ '<g transform="translate(44 70) rotate(-8)"><rect x="-13" y="-8" width="26" height="16" rx="3" fill="#fff" stroke="' + K + '" stroke-width="1.400"/><path d="M-13 -3H13M-13 3H13" stroke="var(--a1)" stroke-width="2"/></g>'
		+ '<g transform="translate(62 32) rotate(15)"><circle r="6.500" fill="#b4f0c2" stroke="' + K + '" stroke-width="1.300"/><path d="M-3 -2Q0 2 3 -2" fill="none" stroke="' + K + '" stroke-width="1.100"/></g>'
		+ '</g>'
		+ '<circle class="lw-bu lw-b1" cx="40" cy="80" r="3.200" fill="#fff" fill-opacity=".8" stroke="#fff" stroke-width=".8"/><circle class="lw-bu lw-b2" cx="54" cy="84" r="2.500" fill="#fff" fill-opacity=".8" stroke="#fff" stroke-width=".8"/><circle class="lw-bu lw-b3" cx="66" cy="80" r="3.600" fill="#fff" fill-opacity=".8" stroke="#fff" stroke-width=".8"/>'
		+ '</g>'
		// glass shine
		+ '<path d="M28 40Q31 29 44 24" fill="none" stroke="#fff" stroke-width="4.200" stroke-linecap="round" opacity=".8"/><circle cx="26" cy="48" r="2" fill="#fff" opacity=".85"/>'
		+ '<circle cx="52" cy="52" r="35" fill="none" stroke="' + K + '" stroke-width="1.600"/>'
		+ '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-lw')) { return; }
			btn.setAttribute('data-lw', '1');
			var make = function (cls, html) {
				var node = document.createElement('span');
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (html) { node.innerHTML = html; }
				btn.appendChild(node);
			};
			make('lw-ic', art);
			make('lw-dial', '');
		});
	});
})();
