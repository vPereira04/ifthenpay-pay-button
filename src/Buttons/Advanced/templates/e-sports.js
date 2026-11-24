/* Draws the game pad: body, D-pad, sticks, light strip and four face buttons that light up in turn. */
(function () {
	var K = '#05060c';
	var btn = function (cx, cy, color, i, glyph) {
		return '<g class="es-bt" style="--i:' + i + ';color:' + color + '"><circle cx="' + cx + '" cy="' + cy + '" r="5.600" fill="' + color + '" stroke="' + K + '" stroke-width="1.100"/><circle cx="' + (cx - 1.500) + '" cy="' + (cy - 1.800) + '" r="1.800" fill="#fff" opacity=".6"/>' + glyph + '</g>';
	};
	var art = '<svg viewBox="0 0 100 62" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<linearGradient id="esb" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4a5068"/><stop offset=".5" stop-color="#2a2f44"/><stop offset="1" stop-color="#171a2a"/></linearGradient></defs>'
		+ '<g class="es-pad">'
		// shoulder bumpers
		+ '<path d="M22 12Q22 4 30 4H36V12Z" fill="#3a405a" stroke="' + K + '" stroke-width="1.300"/><path d="M78 12Q78 4 70 4H64V12Z" fill="#3a405a" stroke="' + K + '" stroke-width="1.300"/>'
		// body
		+ '<path d="M26 10H74Q94 10 97 34L94 54Q91 64 82 58L72 48H28L18 58Q9 64 6 54L3 34Q6 10 26 10Z" fill="url(#esb)" stroke="' + K + '" stroke-width="1.700" stroke-linejoin="round"/>'
		+ '<path d="M10 22Q14 14 26 13H74Q86 14 90 22" fill="none" stroke="#fff" stroke-opacity=".25" stroke-width="1.300" stroke-linecap="round"/>'
		+ '<rect class="es-lt" x="38" y="14.500" width="24" height="2.600" rx="1.300" fill="var(--a1)"/>'
		// d-pad
		+ '<path d="M23 21H29V26H34V32H29V37H23V32H18V26H23Z" fill="#0f1220" stroke="' + K + '" stroke-width="1" stroke-linejoin="round"/><path d="M24.500 22.500V35.500M19.500 29H32.500" stroke="#5a6288" stroke-width="1" stroke-linecap="round"/>'
		// sticks
		+ '<circle cx="38" cy="42" r="6.800" fill="#0f1220" stroke="' + K + '" stroke-width="1.200"/><circle cx="38" cy="42" r="4.400" fill="#3a405a" stroke="#6b7398" stroke-width=".8"/>'
		+ '<circle cx="62" cy="42" r="6.800" fill="#0f1220" stroke="' + K + '" stroke-width="1.200"/><circle cx="62" cy="42" r="4.400" fill="#3a405a" stroke="#6b7398" stroke-width=".8"/>'
		// centre button
		+ '<circle cx="50" cy="26" r="3.600" fill="var(--a2)" stroke="' + K + '" stroke-width="1"/><circle cx="50" cy="26" r="1.400" fill="#fff" opacity=".8"/>'
		// face buttons
		+ btn(77, 18.500, '#ffd23f', 0, '') + btn(84.500, 26, '#ff3b4e', 1, '') + btn(77, 33.500, '#3be37f', 2, '') + btn(69.500, 26, '#3b8bff', 3, '')
		+ '</g></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (b) {
			if (b.getAttribute('data-es')) { return; }
			b.setAttribute('data-es', '1');
			var node = document.createElement('span');
			node.className = 'es-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			b.appendChild(node);
		});
	});
})();
