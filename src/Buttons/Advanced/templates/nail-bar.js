/* Draws a fingertip with a bare almond nail and the polish brush that paints it in three strokes, then a shine. */
(function () {
	var K = '#4a1f1a';
	var nailPath = 'M32 40Q29 22 38 13Q46 8 54 13Q63 22 60 40Q46 46 32 40Z';
	var art = '<svg viewBox="0 0 92 92" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="nbs" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#f6d2b5"/><stop offset=".5" stop-color="#f1bf9c"/><stop offset="1" stop-color="#d99d78"/></linearGradient>'
		+ '<linearGradient id="nbp" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(from var(--a1) h s calc(l + 22))"/><stop offset=".5" stop-color="var(--a1)"/><stop offset="1" stop-color="hsl(from var(--a1) h s calc(l - 16))"/></linearGradient>'
		+ '<clipPath id="nbn"><path d="' + nailPath + '"/></clipPath>'
		+ '<clipPath id="nbc1"><rect class="nb-b1" x="28" y="8" width="13" height="40"/></clipPath><clipPath id="nbc2"><rect class="nb-b2" x="39" y="8" width="14" height="40"/></clipPath><clipPath id="nbc3"><rect class="nb-b3" x="51" y="8" width="13" height="40"/></clipPath></defs>'
		// the finger
		+ '<path d="M27 100V34Q27 6 46 6Q65 6 65 34V100Z" fill="url(#nbs)" stroke="' + K + '" stroke-width="1.600" stroke-linejoin="round"/>'
		+ '<path d="M30 56Q46 60 62 56M30 66Q46 70 62 66" fill="none" stroke="#c98a66" stroke-opacity=".6" stroke-width="1" stroke-linecap="round"/>'
		// the bare nail, then the polish going on in three bands
		+ '<path d="' + nailPath + '" fill="#f8dcd5" stroke="#c98a8a" stroke-width="1.100"/>'
		+ '<g clip-path="url(#nbn)"><g clip-path="url(#nbc1)"><rect x="26" y="6" width="40" height="44" fill="url(#nbp)"/></g><g clip-path="url(#nbc2)"><rect x="26" y="6" width="40" height="44" fill="url(#nbp)"/></g><g clip-path="url(#nbc3)"><rect x="26" y="6" width="40" height="44" fill="url(#nbp)"/></g>'
		+ '<path d="M37 34Q35 22 41 15" fill="none" stroke="#fff" stroke-opacity=".7" stroke-width="2.200" stroke-linecap="round"/>'
		+ '<g class="nb-sh"><path d="M28 6L36 6L26 48L18 48Z" fill="#fff" opacity=".55"/></g></g>'
		+ '<path d="' + nailPath + '" fill="none" stroke="' + K + '" stroke-opacity=".35" stroke-width="1"/>'
		// the brush: tip on the nail's base at rest, tilted like a hand holds it
		+ '<g class="nb-br"><g transform="translate(35.500 44) rotate(24)">'
		+ '<path d="M0 0Q-4 -2 -3.500 -8Q0 -13 3.500 -8Q4 -2 0 0Z" fill="url(#nbp)" stroke="' + K + '" stroke-width="1.200" stroke-linejoin="round"/>'
		+ '<rect x="-3.500" y="-1" width="7" height="12" fill="url(#nbp)" stroke="' + K + '" stroke-width="0"/>'
		+ '<rect x="-4" y="2" width="8" height="9" rx="1.500" fill="#d8dde5" stroke="' + K + '" stroke-width="1.200"/><path d="M-4 5H4M-4 8H4" stroke="#8e97a5" stroke-width=".8"/>'
		+ '<rect x="-4.500" y="11" width="9" height="40" rx="3.500" fill="#1d1722" stroke="' + K + '" stroke-width="1.200"/><path d="M-2.200 14V46" stroke="#fff" stroke-opacity=".45" stroke-width="1.200" stroke-linecap="round"/>'
		+ '</g></g></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-nb')) { return; }
			btn.setAttribute('data-nb', '1');
			var node = document.createElement('span');
			node.className = 'nb-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
