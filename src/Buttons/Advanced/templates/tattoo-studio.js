/* Draws a patch of skin where a tattoo machine outlines a heart, then shades it with hatching like a tattoo artist. */
(function () {
	var K = 'var(--ink)';
	var heart = 'M35 58C12 42 8 20 24 17C30 16 35 20 35 25C35 20 40 16 46 17C62 20 58 42 35 58Z';
	var zig = 'M33.5 54.5 L36.0 29.5 L38.0 26.8 L35.5 53.3 L37.4 51.7 L39.9 24.1 L41.9 23.7 L39.4 50.0 L41.3 48.2 L43.8 23.4 L45.8 23.0 L43.2 46.2 L45.2 44.3 L47.7 23.4 L49.6 23.9 L47.1 42.4 L49.1 40.4 L51.6 25.8 L53.5 28.0 L51.0 38.5 L53.0 36.5 L55.5 32.0';
	var machine = function () {
		return '<svg viewBox="0 0 1 1" xmlns="http://www.w3.org/2000/svg"><g transform="rotate(58) scale(.95)"><g transform="translate(-28 -56)">'
			+ '<path d="M28 56V49" stroke="#e6eaf2" stroke-width="1.500" stroke-linecap="round"/>'
			+ '<path d="M25.500 36H30.500L29.800 49H26.200Z" fill="url(#ttsl)" stroke="#0c0a09" stroke-width="1.100" stroke-linejoin="round"/>'
			+ '<path d="M25.800 40H30.200M26 43H30M26.200 46H29.800" stroke="#0c0a09" stroke-opacity=".5" stroke-width=".7"/>'
			+ '<rect x="23.500" y="28" width="9" height="9" rx="1.800" fill="#2a2420" stroke="#0c0a09" stroke-width="1.200"/>'
			+ '<path d="M25 30V35M28 30V35M31 30V35" stroke="#8d939f" stroke-width=".8"/>'
			+ '<path d="M17 28V9Q17 7 19 7H37Q39 7 39 9V28Z" fill="#34302c" stroke="#0c0a09" stroke-width="1.400" stroke-linejoin="round"/>'
			+ '<rect x="19" y="10" width="7" height="15" rx="2" fill="var(--a2)" stroke="#0c0a09" stroke-width="1"/><rect x="30" y="10" width="7" height="15" rx="2" fill="var(--a2)" stroke="#0c0a09" stroke-width="1"/>'
			+ '<path d="M19 13.500H26M19 17H26M19 20.500H26M30 13.500H37M30 17H37M30 20.500H37" stroke="#fff" stroke-opacity=".55" stroke-width=".9"/>'
			+ '<path d="M20.500 11.500V23M31.500 11.500V23" stroke="#fff" stroke-opacity=".4" stroke-width="1" stroke-linecap="round"/>'
			+ '<rect x="19" y="3" width="21" height="3.600" rx="1.400" fill="url(#ttsl)" stroke="#0c0a09" stroke-width="1"/>'
			+ '<circle cx="37" cy="4.800" r="1.600" fill="#0c0a09"/><path d="M23 6.500V9" stroke="#e6eaf2" stroke-width="1"/>'
			+ '<path d="M28 7Q28 -1 37 -4" fill="none" stroke="#0c0a09" stroke-width="2.200" stroke-linecap="round"/></g></g></svg>';
	};
	var art = '<svg class="tt-art" viewBox="0 0 70 70" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="ttsl" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#f1f3f8"/><stop offset=".5" stop-color="#b9c0cd"/><stop offset="1" stop-color="#7d8594"/></linearGradient>'
		+ '<clipPath id="tth"><path d="' + heart + '"/></clipPath>'
		+ '<linearGradient id="ttg" x1="0" y1="0" x2="1" y2="1"><stop offset=".25" stop-color="#000"/><stop offset=".62" stop-color="#bbb"/><stop offset="1" stop-color="#fff"/></linearGradient>'
		+ '<mask id="ttm" maskUnits="userSpaceOnUse" x="0" y="0" width="70" height="70"><rect width="70" height="70" fill="url(#ttg)"/></mask>'
		+ '</defs>'
		+ '<path d="' + heart + '" fill="none" stroke="#8a6aa8" stroke-opacity=".5" stroke-width="1.200" stroke-dasharray="2 2.500"/>'
		+ '<g class="tt-ink">'
		+ '<g clip-path="url(#tth)"><g mask="url(#ttm)"><path class="tt-sh" pathLength="1" d="' + zig + '" fill="none" stroke="' + K + '" stroke-width="1.500" stroke-linejoin="round" stroke-linecap="round"/></g>'
		+ '<g class="tt-dt" fill="' + K + '"><circle cx="40" cy="43" r=".7"/><circle cx="37.500" cy="40" r=".6"/><circle cx="44" cy="35" r=".7"/><circle cx="41" cy="31" r=".6"/><circle cx="47" cy="27" r=".7"/><circle cx="43" cy="26" r=".6"/><circle cx="36" cy="46" r=".6"/><circle cx="50" cy="24" r=".6"/></g></g>'
		+ '<path class="tt-ol" pathLength="1" d="' + heart + 'Z" fill="none" stroke="' + K + '" stroke-width="3.200" stroke-linecap="round" stroke-linejoin="round"/>'
		+ '</g>'
		+ '<path d="M20 26Q20 21 25.500 21" fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="2" stroke-linecap="round"/>'
		+ '<g fill="' + K + '"><path d="M10 10l1.200 2.800 2.800 1.200-2.800 1.200L10 18l-1.200-2.800L6 14l2.800-1.200Z"/><path d="M60 52l1 2.300 2.300 1-2.300 1-1 2.300-1-2.300-2.300-1 2.300-1Z"/></g>'
		+ '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-tt')) { return; }
			btn.setAttribute('data-tt', '1');
			var node = document.createElement('span');
			node.className = 'tt-skin';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art + '<span class="tt-m tt-m1"><span class="tt-mj">' + machine() + '</span></span><span class="tt-m tt-m2"><span class="tt-mj">' + machine() + '</span></span>';
			btn.appendChild(node);
		});
	});
})();
