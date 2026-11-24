/* Draws the studio mic in its shock mount, its sound waves, the ON AIR sign and the equalizer. */
(function () {
	var K = '#0c0710';
	var grille = '';
	for (var g = 0; g < 9; g++) { grille += '<path d="M' + (31 + g * 4.250) + ' 10V62" stroke="#000" stroke-opacity=".4" stroke-width=".9"/>'; }
	for (var r = 0; r < 12; r++) { grille += '<path d="M29 ' + (13 + r * 4.200) + 'H67" stroke="#000" stroke-opacity=".4" stroke-width=".9"/>'; }
	var bands = '';
	for (var b = 0; b < 4; b++) { bands += '<path d="M' + (33 + b * 10) + ' 44L' + (28 + b * 12) + ' 74" stroke="#fff" stroke-opacity=".55" stroke-width="1.100"/>'; }
	var art = '<svg viewBox="0 0 96 108" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="pcm" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#6f7684"/><stop offset=".3" stop-color="#f3f5f8"/><stop offset=".55" stop-color="#b6bcc7"/><stop offset="1" stop-color="#555b68"/></linearGradient>'
		+ '<linearGradient id="pcg" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#1b1620"/><stop offset=".35" stop-color="#4a4352"/><stop offset=".65" stop-color="#2a2430"/><stop offset="1" stop-color="#15101a"/></linearGradient>'
		+ '<clipPath id="pcc"><rect x="29" y="8" width="38" height="56" rx="19"/></clipPath></defs>'
		// stand
		+ '<rect x="44" y="84" width="8" height="14" fill="url(#pcm)" stroke="' + K + '" stroke-width="1.400"/><ellipse cx="48" cy="101" rx="26" ry="5.500" fill="#2b2531" stroke="' + K + '" stroke-width="1.500"/><ellipse cx="48" cy="99.500" rx="22" ry="3.500" fill="url(#pcm)" opacity=".55"/>'
		// sound waves, behind the head
		+ '<path class="pc-wv pc-w1" d="M74 22Q84 38 74 54" fill="none" stroke="var(--a1)" stroke-width="2.600" stroke-linecap="round"/><path class="pc-wv pc-w2" d="M80 14Q95 38 80 62" fill="none" stroke="var(--a1)" stroke-width="2.600" stroke-linecap="round"/>'
		+ '<path class="pc-wv pc-w1" d="M22 22Q12 38 22 54" fill="none" stroke="var(--a1)" stroke-width="2.600" stroke-linecap="round"/><path class="pc-wv pc-w2" d="M16 14Q1 38 16 62" fill="none" stroke="var(--a1)" stroke-width="2.600" stroke-linecap="round"/>'
		// the cradle (yoke)
		+ '<path d="M13 30V72Q13 84 25 84H71Q83 84 83 72V30" fill="none" stroke="' + K + '" stroke-width="7.500" stroke-linecap="round"/><path d="M13 30V72Q13 84 25 84H71Q83 84 83 72V30" fill="none" stroke="url(#pcm)" stroke-width="4.600" stroke-linecap="round"/>'
		+ '<path d="M13 30V70" stroke="#fff" stroke-opacity=".6" stroke-width="1" transform="translate(-1.200 0)"/>'
		// the mic head
		+ '<g class="pc-hd">'
		// shock-mount elastics
		+ '<path d="M13 40L29 36M13 52L29 50M13 64L29 62M83 40L67 36M83 52L67 50M83 64L67 62" stroke="var(--a1)" stroke-width="1.600" stroke-linecap="round"/>'
		+ '<rect x="29" y="8" width="38" height="56" rx="19" fill="url(#pcg)" stroke="' + K + '" stroke-width="1.700"/>'
		+ '<g clip-path="url(#pcc)">' + grille + '<rect x="29" y="8" width="38" height="56" fill="url(#pcm)" opacity=".22"/><path d="M33 12Q33 40 36 60" stroke="#fff" stroke-opacity=".5" stroke-width="2" fill="none" stroke-linecap="round"/></g>'
		+ '<rect x="29" y="8" width="38" height="56" rx="19" fill="none" stroke="url(#pcm)" stroke-width="2"/>'
		+ '<rect x="27" y="56" width="42" height="9" rx="2.500" fill="url(#pcm)" stroke="' + K + '" stroke-width="1.300"/>'
		+ '<rect x="34" y="65" width="28" height="13" rx="3" fill="url(#pcm)" stroke="' + K + '" stroke-width="1.300"/><circle class="pc-rc" cx="48" cy="71.500" r="3" fill="var(--a1)"/>'
		+ '</g></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-pc')) { return; }
			btn.setAttribute('data-pc', '1');
			var make = function (tag, cls, html) {
				var node = document.createElement(tag);
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (html) { node.innerHTML = html; }
				btn.appendChild(node);
				return node;
			};
			make('span', 'pc-mic', art);
			make('span', 'pc-sign', 'ON AIR');
			var eq = make('span', 'pc-eq', '');
			for (var i = 0; i < 9; i++) {
				var bar = document.createElement('i');
				bar.style.setProperty('--n', i);
				eq.appendChild(bar);
			}
		});
	});
})();
