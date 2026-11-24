/* Draws a story avatar with a gradient ring, a like heart that pops, hearts that float up and a notification badge that counts up. */
(function () {
	var K = '#3a1458';
	var heart = 'M0 7C-9 1 -9 -8 -3.500 -8C-1.500 -8 -.2 -7 0 -5.500C.2 -7 1.500 -8 3.500 -8C9 -8 9 1 0 7Z';
	var badge = function (cls, txt, w) {
		return '<g class="so-b ' + cls + '" transform="translate(62 64)"><g><rect x="' + (-w / 2) + '" y="-8.500" width="' + w + '" height="17" rx="8.500" fill="#ff2d55" stroke="#fff" stroke-width="2"/><text x="0" y="4.200" text-anchor="middle" font-family="ui-rounded,Segoe UI,system-ui,sans-serif" font-size="11" font-weight="900" fill="#fff">' + txt + '</text></g></g>';
	};
	var fl = function (cls, x, dx) { return '<g transform="translate(' + x + ' 30)"><g class="so-f ' + cls + '" style="--dx:' + dx + 'px"><path d="' + heart + '" fill="#ff2d55" stroke="#fff" stroke-width="1.600" stroke-linejoin="round" transform="scale(.8)"/></g></g>'; };
	var art = '<svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="sor" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stop-color="var(--a2)"/><stop offset=".5" stop-color="var(--a1)"/><stop offset="1" stop-color="var(--a3)"/></linearGradient>'
		+ '<linearGradient id="soa" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="hsl(from var(--a2) h s calc(l + 18))"/><stop offset="1" stop-color="hsl(from var(--a1) h s calc(l + 14))"/></linearGradient>'
		+ '<clipPath id="soc"><circle cx="40" cy="40" r="25"/></clipPath>'
		+ '</defs>'
		+ '<g class="so-ring"><circle cx="40" cy="40" r="33" fill="none" stroke="url(#sor)" stroke-width="5" stroke-dasharray="46 6" stroke-linecap="round"/></g>'
		+ '<circle cx="40" cy="40" r="28" fill="#fff"/>'
		+ '<g clip-path="url(#soc)"><rect x="15" y="15" width="50" height="50" fill="url(#soa)"/>'
		+ '<circle cx="40" cy="34" r="9" fill="#fff" stroke="' + K + '" stroke-width="2.200"/>'
		+ '<path d="M20 68C20 52 29 47 40 47C51 47 60 52 60 68Z" fill="#fff" stroke="' + K + '" stroke-width="2.200" stroke-linejoin="round"/></g>'
		+ '<circle cx="40" cy="40" r="25" fill="none" stroke="' + K + '" stroke-width="2.200"/>'
		+ fl('so-f1', 34, -6) + fl('so-f2', 46, 8) + fl('so-f3', 40, 0)
		+ '<g transform="translate(62 17)"><g class="so-hr"><path d="' + heart + '" fill="#ff2d55" stroke="#fff" stroke-width="2.200" stroke-linejoin="round" transform="scale(1.500)"/><path d="M-6 -6Q-8 -2 -5 0" fill="none" stroke="#fff" stroke-opacity=".7" stroke-width="1.600" stroke-linecap="round" transform="scale(1.100)"/></g></g>'
		+ badge('so-b1', '+1', 22) + badge('so-b2', '+12', 28) + badge('so-b3', '+99', 28)
		+ '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-so')) { return; }
			btn.setAttribute('data-so', '1');
			var node = document.createElement('span');
			node.className = 'so-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
