/* Draws a tennis racquet that swings through a served ball: the ball arcs across the clay, bounces and puffs dust. */
(function () {
	var K = '#0e1c3a';
	var strings = '';
	var i;
	for (i = 0; i <= 8; i++) { strings += 'M' + (17 + i * 3.800) + ' 4V44'; }
	for (i = 0; i <= 9; i++) { strings += 'M12 ' + (6 + i * 4) + 'H48'; }
	var racq = '<svg viewBox="0 0 60 78" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<clipPath id="tnc"><ellipse cx="30" cy="24" rx="17" ry="21"/></clipPath>'
		+ '<linearGradient id="tnf" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(from var(--a3) h s calc(l + 24))"/><stop offset="1" stop-color="hsl(from var(--a3) h s calc(l - 6))"/></linearGradient>'
		+ '</defs>'
		+ '<path d="M30 44V56" stroke="' + K + '" stroke-width="9" stroke-linecap="round"/>'
		+ '<path d="M30 44V56" stroke="url(#tnf)" stroke-width="5" stroke-linecap="round"/>'
		+ '<rect x="26" y="54" width="8" height="22" rx="3.500" fill="#2a2a32" stroke="' + K + '" stroke-width="2"/>'
		+ '<path d="M27 59L33 61M27 64L33 66M27 69L33 71" stroke="#fff" stroke-opacity=".55" stroke-width="1.200" stroke-linecap="round"/>'
		+ '<ellipse cx="30" cy="24" rx="17" ry="21" fill="#fff" fill-opacity=".18"/>'
		+ '<g clip-path="url(#tnc)" transform="translate(0 0)"><path d="' + strings + '" stroke="#fff" stroke-opacity=".85" stroke-width=".9"/></g>'
		+ '<ellipse cx="30" cy="24" rx="17" ry="21" fill="none" stroke="' + K + '" stroke-width="7.400"/>'
		+ '<ellipse cx="30" cy="24" rx="17" ry="21" fill="none" stroke="url(#tnf)" stroke-width="4.200"/>'
		+ '<path d="M18 14Q21 8 28 6" fill="none" stroke="#fff" stroke-opacity=".7" stroke-width="1.400" stroke-linecap="round"/>'
		+ '</svg>';
	var ball = '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><defs><radialGradient id="tnb" cx=".35" cy=".3" r=".85"><stop offset="0" stop-color="hsl(from var(--a2) h s calc(l + 18))"/><stop offset="1" stop-color="hsl(from var(--a2) h s calc(l - 12))"/></radialGradient></defs>'
		+ '<circle cx="10" cy="10" r="8.600" fill="url(#tnb)" stroke="' + K + '" stroke-width="1.600"/>'
		+ '<path d="M3.200 6.500Q10 10 3.200 13.800M16.800 6.500Q10 10 16.800 13.800" fill="none" stroke="#fff" stroke-width="1.500" stroke-linecap="round"/></svg>';
	var art = '<span class="tn-rq">' + racq + '</span><span class="tn-dust"></span><span class="tn-bx"><span class="tn-by" style="display:block">' + ball + '</span></span>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-tn')) { return; }
			btn.setAttribute('data-tn', '1');
			var node = document.createElement('span');
			node.className = 'tn-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
