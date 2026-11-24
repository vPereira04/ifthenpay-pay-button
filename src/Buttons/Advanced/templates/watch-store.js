/* Draws the chronograph and the strap's buckle. */
(function () {
	var rays = '', mins = '', marks = '', sub1 = '', sub2 = '', i;
	for (i = 0; i < 60; i++) { rays += '<path d="M50 50V15" stroke="hsl(from var(--a1) h calc(s - 13) calc(l + 17.8))" stroke-opacity=".08" stroke-width=".6" transform="rotate(' + (i * 6) + ' 50 50)"/>'; }
	for (i = 0; i < 12; i++) {
		var card = i % 3 === 0;
		marks += '<rect x="' + (card ? 48.500 : 49) + '" y="' + (card ? 16.500 : 17.500) + '" width="' + (card ? 3 : 2) + '" height="' + (card ? 8 : 6) + '" rx=".8" fill="url(#wtg)" stroke="hsl(from var(--a1) calc(h - 1.9) calc(s + 0.7) calc(l + 3.9))" stroke-width=".4" transform="rotate(' + (i * 30) + ' 50 50)"/>';
	}
	var tick = function (cx, n) {
		var out = '';
		for (var t = 0; t < n; t++) { out += '<path d="M' + cx + ' 42.500V44" stroke="hsl(from var(--a1) calc(h - 1.9) calc(s + 0.7) calc(l + 3.9))" stroke-width=".5" transform="rotate(' + (t * (360 / n)) + ' ' + cx + ' 50)"/>'; }
		return out;
	};
	var hand = function (cx, cls, tail) {
		return '<g class="' + cls + '"><path d="M' + cx + ' ' + (50 + tail) + 'V43.500" stroke="hsl(from var(--a2) h calc(s - 2.3) calc(l - 3.3))" stroke-width="1" stroke-linecap="round"/><circle cx="' + cx + '" cy="50" r="1.100" fill="hsl(from var(--a2) h calc(s - 2.3) calc(l - 3.3))"/></g>';
	};
	var head = '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="wtg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(from var(--a1) calc(h + 8.5) calc(s + 33.7) calc(l + 68.6))"/><stop offset=".4" stop-color="hsl(from var(--a1) calc(h + 2.8) calc(s + 0.7) calc(l + 37.5))"/><stop offset=".7" stop-color="hsl(from var(--a1) h calc(s + 6.7) calc(l + 17.5))"/><stop offset="1" stop-color="hsl(from var(--a1) calc(h + 5.7) calc(s + 14.7) calc(l + 53.7))"/></linearGradient>'
		+ '<radialGradient id="wtd" cx=".35" cy=".3" r=".9"><stop offset="0" stop-color="#fffaf0"/><stop offset=".7" stop-color="hsl(from var(--a1) calc(h + 3.8) calc(s - 17.5) calc(l + 66.5))"/><stop offset="1" stop-color="hsl(from var(--a1) calc(h + 2.9) calc(s - 30.8) calc(l + 52.2))"/></radialGradient>'
		+ '<clipPath id="wtc"><circle cx="50" cy="50" r="36"/></clipPath></defs>'
		+ '<rect x="92" y="43" width="8" height="14" rx="2.200" fill="url(#wtg)" stroke="hsl(from var(--a1) calc(h - 1.9) calc(s + 0.7) calc(l + 3.9))" stroke-width=".7"/><path d="M94.500 44V56M96.500 44V56M98.500 44V56" stroke="hsl(from var(--a1) calc(h - 1.9) calc(s + 0.7) calc(l + 3.9))" stroke-width=".6"/>'
		+ '<circle cx="50" cy="50" r="48" fill="url(#wtg)" stroke="var(--a1)" stroke-width=".8"/><circle cx="50" cy="50" r="45.500" fill="none" stroke="hsl(from var(--a1) calc(h + 8.1) calc(s + 33.7) calc(l + 71))" stroke-opacity=".7" stroke-width=".8"/>'
		+ '<circle cx="50" cy="50" r="43.500" fill="hsl(from var(--a1) calc(h - 5.4) calc(s - 45.1) calc(l - 11))"/>'
		+ '<g class="wt-bz"><circle cx="50" cy="50" r="40" fill="none" stroke="hsl(from var(--a1) calc(h + 2.2) calc(s + 4.4) calc(l + 53.1))" stroke-width="3.200" stroke-dasharray="0.9 3.340"/><path d="M50 8.500L47.800 13H52.200Z" fill="hsl(from var(--a1) calc(h + 2.2) calc(s + 4.4) calc(l + 53.1))"/></g>'
		+ '<circle cx="50" cy="50" r="36" fill="url(#wtd)"/>' + rays
		+ '<circle cx="50" cy="50" r="33" fill="none" stroke="hsl(from var(--a1) h calc(s - 13) calc(l + 17.8))" stroke-width=".5"/>' + marks
		+ '<circle cx="29" cy="50" r="9" fill="hsl(from var(--a1) calc(h + 4.1) calc(s - 9.4) calc(l + 69.8))" stroke="hsl(from var(--a1) h calc(s - 13) calc(l + 17.8))" stroke-width=".8"/>' + tick(29, 12)
		+ '<circle cx="71" cy="50" r="9" fill="hsl(from var(--a1) calc(h + 4.1) calc(s - 9.4) calc(l + 69.8))" stroke="hsl(from var(--a1) h calc(s - 13) calc(l + 17.8))" stroke-width=".8"/>' + tick(71, 12)
		+ hand(29, 'wt-s1', 5) + hand(71, 'wt-s2', 4)
		+ '<rect x="46" y="64" width="8" height="6" rx="1" fill="#fff" stroke="hsl(from var(--a1) calc(h - 1.9) calc(s + 0.7) calc(l + 3.9))" stroke-width=".6"/><path d="M48 66.500H52M48 68H51" stroke="#222" stroke-width=".7"/>'
		+ '<g class="wt-hr"><path d="M50 52L47 47.500L50 29L53 47.500Z" fill="url(#wtg)" stroke="var(--a1)" stroke-width=".6" stroke-linejoin="round"/></g>'
		+ '<g class="wt-mn"><path d="M50 53L48 48L50 19L52 48Z" fill="url(#wtg)" stroke="var(--a1)" stroke-width=".6" stroke-linejoin="round"/></g>'
		+ '<g class="wt-sec"><path d="M50 60V17" stroke="var(--a2)" stroke-width=".9" stroke-linecap="round"/><circle cx="50" cy="58" r="2" fill="var(--a2)"/></g>'
		+ '<circle cx="50" cy="50" r="3" fill="url(#wtg)" stroke="var(--a1)" stroke-width=".7"/><circle cx="50" cy="50" r="1" fill="var(--a2)"/>'
		+ '<g clip-path="url(#wtc)"><path class="wt-gl" d="M24 80L52 22H62L34 80Z" fill="#fff" opacity=".22"/></g></svg>';
	var buckle = '<svg viewBox="0 0 112 60" xmlns="http://www.w3.org/2000/svg">'
		+ '<circle cx="14" cy="30" r="3.800" fill="hsl(from var(--a1) calc(h - 18.5) calc(s + 1.7) calc(l - 12.5))"/><circle cx="14" cy="28.800" r="3.800" fill="none" stroke="hsl(from var(--a1) calc(h - 14.8) calc(s - 21.7) calc(l + 20.8))" stroke-opacity=".7" stroke-width=".8"/>'
		+ '<circle cx="36" cy="30" r="3.800" fill="hsl(from var(--a1) calc(h - 18.5) calc(s + 1.7) calc(l - 12.5))"/><circle cx="36" cy="28.800" r="3.800" fill="none" stroke="hsl(from var(--a1) calc(h - 14.8) calc(s - 21.7) calc(l + 20.8))" stroke-opacity=".7" stroke-width=".8"/>'
		+ '<rect x="52" y="3" width="44" height="54" rx="7" fill="none" stroke="hsl(from var(--a1) calc(h - 18.5) calc(s + 1.7) calc(l - 12.5))" stroke-width="8"/>'
		+ '<rect x="52" y="3" width="44" height="54" rx="7" fill="none" stroke="url(#wtg2)" stroke-width="5.200"/>'
		+ '<path d="M56 7H92" stroke="hsl(from var(--a1) calc(h + 9.1) calc(s + 33.7) calc(l + 73.1))" stroke-opacity=".8" stroke-width="1" stroke-linecap="round"/>'
		+ '<rect x="66" y="2" width="6" height="56" rx="2" fill="url(#wtg2)" stroke="var(--a1)" stroke-width=".8"/>'
		+ '<g class="wt-tg"><path d="M70 30H37" stroke="var(--a1)" stroke-width="4.400" stroke-linecap="round"/><path d="M70 30H37" stroke="url(#wtg2)" stroke-width="2.600" stroke-linecap="round"/></g>'
		+ '<defs><linearGradient id="wtg2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(from var(--a1) calc(h + 8.5) calc(s + 33.7) calc(l + 68.6))"/><stop offset=".4" stop-color="hsl(from var(--a1) calc(h + 2.8) calc(s + 0.7) calc(l + 37.5))"/><stop offset=".7" stop-color="hsl(from var(--a1) h calc(s + 6.7) calc(l + 17.5))"/><stop offset="1" stop-color="hsl(from var(--a1) calc(h + 5.7) calc(s + 14.7) calc(l + 53.7))"/></linearGradient></defs></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-wt')) { return; }
			btn.setAttribute('data-wt', '1');
			var make = function (tag, cls, html) {
				var node = document.createElement(tag);
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (html) { node.innerHTML = html; }
				btn.appendChild(node);
			};
			make('span', 'wt-bk', buckle);
			make('span', 'wt-hd', head);
			make('i', 'wt-sp');
			make('i', 'wt-bs');
		});
	});
})();