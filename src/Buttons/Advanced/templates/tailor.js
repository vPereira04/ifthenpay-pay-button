/* Draws an embroidery hoop with a hole in denim, the needle and red-gold thread that darn it shut, and two rivets. */
(function () {
	var K = '#0a1530';
	var rows = '', cols = '';
	for (var i = 0; i < 11; i++) { rows += '<path d="M37 ' + (43 + i * 2.100) + 'H63" stroke="var(--a2)" stroke-width="1.300" stroke-linecap="round"/>'; }
	for (var k = 0; k < 12; k++) { cols += '<path d="M' + (37.500 + k * 2.200) + ' 42V65" stroke="hsl(from var(--a2) h s calc(l - 9))" stroke-width="1.300" stroke-dasharray="2.100 2.100" stroke-dashoffset="' + (k % 2 ? 0 : 2.100) + '"/>'; }
	var art = '<svg viewBox="0 0 108 108" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="tdw" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e9c48a"/><stop offset=".5" stop-color="#c58a45"/><stop offset="1" stop-color="#8a5226"/></linearGradient>'
		+ '<linearGradient id="tdn" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fafbfc"/><stop offset=".5" stop-color="#aeb6c1"/><stop offset="1" stop-color="#6e7785"/></linearGradient>'
		+ '<pattern id="tdt" width="5" height="5" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="5" height="5" fill="hsl(from var(--a1) h s calc(l + 7))"/><rect width="2" height="5" fill="hsl(from var(--a1) h s calc(l + 17))"/></pattern>'
		+ '<clipPath id="tdh"><rect class="td-rh" x="34" y="42" width="32" height="24"/></clipPath><clipPath id="tdv"><rect class="td-rv" x="34" y="42" width="32" height="24"/></clipPath>'
		+ '<clipPath id="tdf"><circle cx="50" cy="54" r="40"/></clipPath></defs>'
		// hoop: outer ring, fabric, inner ring, clamp screw
		+ '<circle cx="50" cy="54" r="47" fill="' + K + '"/><circle cx="50" cy="54" r="46" fill="url(#tdw)" stroke="' + K + '" stroke-width="1.200"/><circle cx="50" cy="54" r="40.500" fill="#7a4a22" stroke="' + K + '" stroke-width="1.200"/>'
		+ '<circle cx="50" cy="54" r="39" fill="url(#tdt)"/>'
		// the hole, frayed
		+ '<g clip-path="url(#tdf)">'
		+ '<path d="M41 51L44 46L50 44.500L56 46.500L60 50L59 57L55 61L48 62L43 59Z" fill="#071022" stroke="#16264a" stroke-width="1.600" stroke-linejoin="round"/>'
		+ '<path d="M41 51L37 50M44 46L42 42M56 46.500L58 42M60 50L64 48M59 57L63 59M55 61L56 65M43 59L40 63" stroke="hsl(from var(--a1) h s calc(l + 30))" stroke-width=".8" stroke-linecap="round"/>'
		+ '<g clip-path="url(#tdh)"><rect x="36" y="41" width="28" height="25" fill="url(#tdt)"/>' + rows + '</g><g clip-path="url(#tdv)">' + cols + '</g></g>'
		+ '<circle cx="50" cy="54" r="40.500" fill="none" stroke="#e9c48a" stroke-opacity=".7" stroke-width="1.200"/>'
		+ '<rect x="40" y="2" width="20" height="13" rx="3" fill="url(#tdn)" stroke="' + K + '" stroke-width="1.500"/><circle cx="50" cy="8.500" r="3.200" fill="#d9a441" stroke="' + K + '" stroke-width="1"/><path d="M48 8.500H52" stroke="' + K + '" stroke-width="1"/>'
		// the needle, with its thread, riding over the weave
		+ '<g class="td-ny"><g class="td-nx">'
		+ '<path d="M60 41Q72 32 86 38Q99 44 95 56" fill="none" stroke="var(--a2)" stroke-width="1.800" stroke-linecap="round"/><path d="M60 41Q72 32 86 38Q99 44 95 56" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width=".7" stroke-linecap="round"/>'
		+ '<path d="M22 41L60 38.500Q63 38.500 63 41Q63 43.500 60 43.500Z" fill="url(#tdn)" stroke="' + K + '" stroke-width="1.300" stroke-linejoin="round"/><ellipse cx="59" cy="41" rx="1.300" ry="1.700" fill="#071022"/>'
		+ '</g></g></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-td')) { return; }
			btn.setAttribute('data-td', '1');
			var make = function (cls, html) {
				var node = document.createElement('span');
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (html) { node.innerHTML = html; }
				btn.appendChild(node);
			};
			make('td-rt td-r1', '');
			make('td-rt td-r2', '');
			make('td-ic', art);
		});
	});
})();
