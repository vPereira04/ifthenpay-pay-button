/* Draws a net on a gym floor and a volleyball that is set up, spiked just over the net and bounces. */
(function () {
	var K = '#14233f';
	var mesh = '';
	var i;
	for (i = 0; i <= 3; i++) { mesh += 'M' + (47 + i * 2) + ' 44V62'; }
	for (i = 0; i <= 3; i++) { mesh += 'M47 ' + (44 + i * 6) + 'H53'; }
	var ball = '<g class="vb-ball"><g class="vb-sq"><g class="vb-spin">'
		+ '<g transform="scale(1.35)"><circle r="7" fill="#fff"/>'
		+ '<g clip-path="url(#vbc)"><path d="M-9 -4Q0 -9.500 9 -3" fill="none" stroke="var(--a2)" stroke-width="3.600"/><path d="M-9 2Q0 -3.500 9 3" fill="none" stroke="var(--a3)" stroke-width="3.600"/><path d="M-9 8Q0 2.500 9 8.500" fill="none" stroke="var(--a2)" stroke-width="3.600"/></g>'
		+ '</g></g><g transform="scale(1.35)"><circle r="7" fill="url(#vbs)"/><circle r="6.600" fill="none" stroke="' + K + '" stroke-width="1.400"/><ellipse cx="-2.400" cy="-3" rx="2" ry="1.200" transform="rotate(-35 -2.400 -3)" fill="#fff" opacity=".8"/></g></g></g>';
	var art = '<svg viewBox="0 0 102 72" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<clipPath id="vbc"><circle r="6.800"/></clipPath>'
		+ '<radialGradient id="vbs" cx=".35" cy=".3" r=".85"><stop offset=".55" stop-color="#0a1430" stop-opacity="0"/><stop offset="1" stop-color="#0a1430" stop-opacity=".4"/></radialGradient>'
		+ '</defs>'
		+ '<ellipse cx="50" cy="64" rx="46" ry="3.500" fill="#000" opacity=".16"/>'
		// the net: a post on the left, the mesh, a white tape on top
		+ '<g class="vb-net"><rect x="47" y="44" width="6" height="18" fill="' + K + '" fill-opacity=".35"/><path d="' + mesh + '" stroke="#fff" stroke-opacity=".8" stroke-width=".8" fill="none"/></g>'
		+ '<rect x="44.500" y="39.500" width="11" height="4.500" rx="1.500" fill="#fff" stroke="' + K + '" stroke-width="1.200"/>'
		+ '<rect x="44" y="38" width="3.600" height="28" rx="1.500" fill="#e8ecf4" stroke="' + K + '" stroke-width="1.400"/>'
		+ '<ellipse class="vb-ring" cx="82" cy="62" rx="9" ry="3" fill="none" stroke="#fff" stroke-width="1.800"/>'
		+ ball + '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-vb')) { return; }
			btn.setAttribute('data-vb', '1');
			var node = document.createElement('span');
			node.className = 'vb-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
