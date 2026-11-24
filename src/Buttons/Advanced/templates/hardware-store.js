/* Draws the block, the nail and a claw hammer that drives it flush in two strikes. */
(function () {
	var K = '#1d1006';
	var NX = 52, PX = 110, PY = 24.800, CY = 42.800;
	var grain = '';
	[[14, 72, 40], [18, 80, 60], [12, 84, 30], [50, 76, 28]].forEach(function (g) { grain += '<path d="M' + g[0] + ' ' + g[1] + 'h' + g[2] + '" stroke="#7a4518" stroke-opacity=".5" stroke-width="1" stroke-linecap="round"/>'; });
	var art = '<svg viewBox="0 -30 136 124" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="hwd" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e8b878"/><stop offset="1" stop-color="#a8672b"/></linearGradient>'
		+ '<linearGradient id="hws" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#f4f6f9"/><stop offset=".35" stop-color="#b9c0ca"/><stop offset=".75" stop-color="#6f7886"/><stop offset="1" stop-color="#a5adb9"/></linearGradient>'
		+ '<linearGradient id="hwh" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e6b06a"/><stop offset="1" stop-color="#a8672b"/></linearGradient>'
		+ '<linearGradient id="hwn" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#e6e9ee"/><stop offset=".5" stop-color="#8d96a3"/><stop offset="1" stop-color="#cfd5dd"/></linearGradient>'
		+ '<clipPath id="hwc"><rect x="-10" y="-40" width="160" height="104.500"/></clipPath></defs>'
		// the block: top face, front, side
		+ '<path d="M8 66L20 58H96L84 66Z" fill="#f0c98e" stroke="' + K + '" stroke-width="1.400" stroke-linejoin="round"/>'
		+ '<rect x="8" y="66" width="76" height="22" rx="2" fill="url(#hwd)" stroke="' + K + '" stroke-width="1.500"/>' + grain
		+ '<path d="M84 66L96 58V80L84 88Z" fill="#8c5222" stroke="' + K + '" stroke-width="1.400" stroke-linejoin="round"/>'
		// the nail: the clip is the wood's top, so it sinks into it; its head ends flush with the surface
		+ '<g clip-path="url(#hwc)"><g class="hw-nl"><rect x="' + (NX - 1.700) + '" y="' + (CY + 2) + '" width="3.400" height="30" fill="url(#hwn)" stroke="' + K + '" stroke-width="1"/>'
		+ '<ellipse cx="' + NX + '" cy="' + CY + '" rx="7" ry="2.800" fill="url(#hwn)" stroke="' + K + '" stroke-width="1.200"/><path d="M' + (NX - 4.500) + ' ' + (CY - 0.600) + 'H' + (NX + 2) + '" stroke="#fff" stroke-opacity=".8" stroke-width="1" stroke-linecap="round"/></g></g>'
		+ '<g transform="translate(' + NX + ' ' + (CY - 3) + ')"><g class="hw-sp hw-s1"><path d="M0 -9L2.400 -2.400L9 0L2.400 2.400L0 9L-2.400 2.400L-9 0L-2.400 -2.400Z" fill="#ffe27a" stroke="' + K + '" stroke-width="1" stroke-linejoin="round"/></g></g>'
		+ '<g transform="translate(' + NX + ' ' + (CY + 6) + ')"><g class="hw-sp hw-s2"><path d="M0 -9L2.400 -2.400L9 0L2.400 2.400L0 9L-2.400 2.400L-9 0L-2.400 -2.400Z" fill="#ffe27a" stroke="' + K + '" stroke-width="1" stroke-linejoin="round"/></g></g>'
		+ '<g transform="translate(' + NX + ' ' + (CY + 19) + ')"><g class="hw-fl"><path d="M0 -11L2.800 -2.800L11 0L2.800 2.800L0 11L-2.800 2.800L-11 0L-2.800 -2.800Z" fill="#fff" stroke="#ffd27a" stroke-width="1" stroke-linejoin="round"/></g></g>'
		// the hammer: the hand at (PX, PY), the handle points left when the face lands flat on the nail head
		+ '<g class="hw-arm"><g transform="translate(' + PX + ' ' + PY + ')"><g class="hw-hm">'
		+ '<rect x="-2" y="-4.200" width="56" height="8.400" rx="4" fill="url(#hwh)" stroke="' + K + '" stroke-width="1.400"/>'
		+ '<rect x="1" y="-5.200" width="34" height="10.400" rx="5" fill="var(--a2)" stroke="' + K + '" stroke-width="1.400"/><path d="M6 -2.400H31M6 2.600H31" stroke="#000" stroke-opacity=".28" stroke-width="1"/>'
		// the head: body, neck, polished face (down at impact), claw (up at impact)
		+ '<path d="M50 -10H66V9H50Z" fill="url(#hws)" stroke="' + K + '" stroke-width="1.500" stroke-linejoin="round"/>'
		+ '<path d="M52 -10V-12.500H64V-10Z" fill="url(#hws)" stroke="' + K + '" stroke-width="1.300" stroke-linejoin="round"/>'
		+ '<rect x="51.500" y="-15.500" width="13" height="3.600" rx="1" fill="#e9edf2" stroke="' + K + '" stroke-width="1.300"/>'
		+ '<path d="M50 9C49 19 53 27 61 32L65 29C60 23 62 15 66 9Z" fill="url(#hws)" stroke="' + K + '" stroke-width="1.400" stroke-linejoin="round"/><path d="M58 11L62 27" stroke="' + K + '" stroke-width="1" stroke-linecap="round"/>'
		+ '<path d="M52 -8V7" stroke="#fff" stroke-opacity=".75" stroke-width="1.300" stroke-linecap="round"/>'
		+ '</g></g></g></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-hw')) { return; }
			btn.setAttribute('data-hw', '1');
			var node = document.createElement('span');
			node.className = 'hw-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
