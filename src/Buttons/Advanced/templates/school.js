/* Draws a slate where chalk writes an A+ and underlines it, the chalk following every stroke, with a chalk tray at the edge. */
(function () {
	var strokes = [['a','M16 56L34 12L52 56'],['b','M23 40H45'],['c','M62 38H82'],['d','M72 28V48'],['e','M10 66Q46 59 86 65']];
	var paths = '';
	strokes.forEach(function (s) { paths += '<path class="sc-s sc-s' + s[0] + '" pathLength="1" d="' + s[1] + '" fill="none" stroke="var(--a3)" stroke-width="4.200" stroke-linecap="round" stroke-linejoin="round" filter="url(#scf)"/>'; });
	var chalk = '<svg viewBox="0 0 1 1" xmlns="http://www.w3.org/2000/svg"><g transform="rotate(-38)"><rect x="-1" y="-4.500" width="26" height="9" rx="2.600" fill="#fbf8ee" stroke="#cfc9b6" stroke-width="1"/><path d="M4 -2.400H22" stroke="#fff" stroke-width="1.400" stroke-linecap="round" opacity=".8"/><rect x="-1" y="-4.500" width="6" height="9" rx="2.600" fill="#e9e4d2"/></g></svg>';
	var art = '<svg class="sc-art" viewBox="0 0 96 72" xmlns="http://www.w3.org/2000/svg"><defs><filter id="scf" filterUnits="userSpaceOnUse" x="-10" y="-10" width="120" height="100"><feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2" seed="4" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="2.600"/></filter></defs><g class="sc-mk">' + paths + '</g></svg>';
	var tray = '<svg viewBox="0 0 62 12" xmlns="http://www.w3.org/2000/svg"><rect x="0" y="5" width="62" height="5" rx="2" fill="#7a5530"/><rect x="0" y="5" width="62" height="2" rx="1" fill="#a67a4a"/>'
		+ '<rect x="6" y="1" width="20" height="5.500" rx="2.200" fill="#fbf8ee" stroke="#cfc9b6" stroke-width=".8"/><rect x="30" y="2" width="14" height="4.500" rx="1.800" fill="#ff9fbd" stroke="#e07fa0" stroke-width=".8"/>'
		+ '<rect x="48" y="0" width="12" height="6.500" rx="1.500" fill="#3b3f46"/><rect x="48" y="3.500" width="12" height="3" rx="1" fill="#e8c35a"/></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-sc')) { return; }
			btn.setAttribute('data-sc', '1');
			var node = document.createElement('span');
			node.className = 'sc-ic';
			node.setAttribute('aria-hidden', 'true');
			var chalks = '';
			strokes.forEach(function (s) { chalks += '<span class="sc-ch sc-c' + s[0] + '">' + chalk + '</span>'; });
			node.innerHTML = art + chalks;
			btn.appendChild(node);
			var tr = document.createElement('span');
			tr.className = 'sc-tray';
			tr.setAttribute('aria-hidden', 'true');
			tr.innerHTML = tray;
			btn.appendChild(tr);
		});
	});
})();
