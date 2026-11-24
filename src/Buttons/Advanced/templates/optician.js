/* Adds the glasses, the glint and the face, and sets how far the glasses glide. */
(function () {
	var K = 'hsl(from var(--a3) h calc(s - 22) calc(l + 9))';
	var A = 'hsl(from var(--a2) h calc(s + 39) calc(l + 15))', B = 'hsl(from var(--a2) h calc(s + 39) calc(l + 30))', C = 'hsl(from var(--a2) h calc(s + 39) calc(l + 42))';
	var glasses = '<svg viewBox="0 0 54 26" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="opl" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".85"/><stop offset=".5" stop-color="' + B + '" stop-opacity=".45"/><stop offset="1" stop-color="' + A + '" stop-opacity=".5"/></linearGradient></defs>'
		+ '<g fill="none" stroke-linecap="round" stroke="' + K + '"><path d="M2 8L0 5M52 8l2-3" stroke-width="4.600"/><rect x="2.400" y="4.400" width="21" height="17" rx="7" stroke-width="5"/><rect x="30.600" y="4.400" width="21" height="17" rx="7" stroke-width="5"/><path d="M23.400 11Q27 7.600 30.600 11" stroke-width="4.600"/></g>'
		+ '<g fill="none" stroke-linecap="round" stroke="' + C + '" stroke-width="2.400"><path d="M2 8L0 5M52 8l2-3"/><rect x="2.400" y="4.400" width="21" height="17" rx="7" fill="url(#opl)"/><rect x="30.600" y="4.400" width="21" height="17" rx="7" fill="url(#opl)"/><path d="M23.400 11Q27 7.600 30.600 11"/><path d="M7 8.400Q9.400 6.800 13 7M35 8.400Q37.400 6.800 41 7" stroke="#fff" stroke-width="1.600" opacity=".9"/></g></svg>';
	var star = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 0c.8 6.500 4.500 10.500 12 12-7.500 1.500-11.200 5.500-12 12-.8-6.500-4.500-10.500-12-12C7.500 10.500 11.200 6.500 12 0z" fill="#fff" stroke="' + A + '" stroke-width="1"/></svg>';
	var face = '<svg viewBox="0 0 50 52" xmlns="http://www.w3.org/2000/svg">'
		+ '<circle cx="3.500" cy="29" r="4" fill="hsl(from var(--a1) h s l)" stroke="' + K + '" stroke-width="1.800"/><circle cx="46.500" cy="29" r="4" fill="hsl(from var(--a1) h s l)" stroke="' + K + '" stroke-width="1.800"/>'
		+ '<ellipse cx="25" cy="28" rx="21.500" ry="23" fill="hsl(from var(--a1) h s l)" stroke="' + K + '" stroke-width="2.200"/>'
		+ '<path d="M3.800 23C3 8 15 3.500 25 3.500C35 3.500 47 8 46.200 23C41 15.500 33 13 25 13C17 13 9 15.500 3.800 23Z" fill="hsl(from var(--a3) h calc(s - 37) calc(l + 17))" stroke="' + K + '" stroke-width="2" stroke-linejoin="round"/>'
		+ '<path d="M9 19.500L18 18.500M32 18.500L41 19.500" stroke="' + K + '" stroke-width="2" stroke-linecap="round"/>'
		+ '<g class="op-sq" stroke="' + K + '" stroke-width="2.400" stroke-linecap="round"><path d="M8.500 26.500H18.500M31.500 26.500H41.500"/></g>'
		+ '<g class="op-op"><circle cx="13.500" cy="26.500" r="3.200" fill="' + K + '"/><circle cx="36.500" cy="26.500" r="3.200" fill="' + K + '"/><circle cx="14.600" cy="25.300" r="1" fill="#fff"/><circle cx="37.600" cy="25.300" r="1" fill="#fff"/></g>'
		+ '<path d="M25 29Q22.500 35 26.500 36" fill="none" stroke="' + K + '" stroke-opacity=".7" stroke-width="1.800" stroke-linecap="round"/>'
		+ '<path class="op-m1" d="M19 43Q25 40.500 31 43" fill="none" stroke="' + K + '" stroke-width="2.200" stroke-linecap="round"/>'
		+ '<path class="op-m2" d="M17.500 40Q25 49 32.500 40" fill="none" stroke="' + K + '" stroke-width="2.200" stroke-linecap="round"/>'
		+ '<circle cx="12" cy="37" r="3" fill="hsl(from var(--a1) h s calc(l - 10))" fill-opacity=".35"/><circle cx="38" cy="37" r="3" fill="hsl(from var(--a1) h s calc(l - 10))" fill-opacity=".35"/></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-op')) { return; }
			btn.setAttribute('data-op', '1');
			var make = function (tag, cls, parent, html) {
				var node = document.createElement(tag);
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (html) { node.innerHTML = html; }
				(parent || btn).appendChild(node);
				return node;
			};
			make('span', 'op-fc', null, face);
			var g = make('span', 'op-g', null, glasses);
			make('i', 'op-gs', g, star);
			var aim = function () { btn.style.setProperty('--op-dx', Math.max(100, btn.offsetWidth - 84) + 'px'); };
			aim();
			if (window.ResizeObserver) { new ResizeObserver(aim).observe(btn); }
		});
	});
})();