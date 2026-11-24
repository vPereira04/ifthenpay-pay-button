/* Draws a boxing glove, a hanging punching bag and the POW burst between them. */
(function () {
	var R = 'hsl(from var(--a2) h s l)';
	var svg = '<svg viewBox="0 0 100 52" xmlns="http://www.w3.org/2000/svg">'
		+ '<g class="bx-bag"><path d="M83 -8V6" stroke="#fff" stroke-opacity=".7" stroke-width="1.600" stroke-dasharray="2 1.600"/>'
		+ '<rect x="73" y="5" width="20" height="40" rx="7" fill="' + R + '" stroke="#fff" stroke-width="1.800"/>'
		+ '<rect x="73" y="17" width="20" height="5" fill="#fff" fill-opacity=".85"/>'
		+ '<path d="M77 9V41" stroke="#fff" stroke-opacity=".35" stroke-width="2" stroke-linecap="round"/></g>'
		+ '<path class="bx-pow" d="M58 26L63 18L64 25L72 20L68 27L75 29L67 31L70 38L63 33L60 40L58 32L51 35L55 29Z" fill="#ffd21a" stroke="#fff" stroke-width="1.200" stroke-linejoin="round"/>'
		+ '<g class="bx-gl"><rect x="1" y="17" width="12" height="22" rx="3" fill="#fff" stroke="#fff" stroke-width="1.600"/><path d="M5 20V36" stroke="#0d0d10" stroke-opacity=".25" stroke-width="1.400"/>'
		+ '<path d="M11 15C11 7 19 5 29 5H42C54 5 58 13 58 23C58 34 52 40 42 40H25C16 40 11 36 11 30Z" fill="' + R + '" stroke="#fff" stroke-width="1.800" stroke-linejoin="round"/>'
		+ '<path d="M23 33C23 44 38 46 44 36" fill="hsl(from var(--a2) h s calc(l - 12))" stroke="#fff" stroke-width="1.800" stroke-linecap="round" stroke-linejoin="round"/>'
		+ '<path d="M47 9C51 14 51 20 47 25" fill="none" stroke="#000" stroke-opacity=".3" stroke-width="1.600" stroke-linecap="round"/>'
		+ '<path d="M18 11C22 8 28 8 33 9" fill="none" stroke="#fff" stroke-opacity=".7" stroke-width="2" stroke-linecap="round"/></g></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-bx')) { return; }
			btn.setAttribute('data-bx', '1');
			var n = document.createElement('span');
			n.className = 'bx-ic';
			n.setAttribute('aria-hidden', 'true');
			n.innerHTML = svg;
			btn.insertBefore(n, btn.firstChild);
		});
	});
})();
