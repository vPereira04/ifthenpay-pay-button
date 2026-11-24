/* Draws a goal with its net and a football that flies into the back of it, making the net bulge. */
(function () {
	var K = '#08240f';
	var net = '';
	var i;
	// back net: a grid
	for (i = 0; i <= 8; i++) { net += '<path d="M' + (30 + i * 7.500) + ' 24V64" stroke="#fff" stroke-opacity=".55" stroke-width=".9"/>'; }
	for (i = 0; i <= 5; i++) { net += '<path d="M30 ' + (24 + i * 8) + 'H90" stroke="#fff" stroke-opacity=".55" stroke-width=".9"/>'; }
	// side nets: depth lines from the front posts to the back
	for (i = 0; i <= 4; i++) { net += '<path d="M14 ' + (14 + i * 14) + 'L30 ' + (24 + i * 10) + 'M106 ' + (14 + i * 14) + 'L90 ' + (24 + i * 10) + '" stroke="#fff" stroke-opacity=".45" stroke-width=".9"/>'; }
	// roof net
	for (i = 0; i <= 6; i++) { net += '<path d="M' + (14 + i * 15.300) + ' 14L' + (30 + i * 10) + ' 24" stroke="#fff" stroke-opacity=".4" stroke-width=".9"/>'; }
	// the ball: a clipped pattern so no line can leave the body, plus fixed light and shade
	var P = function (cx, cy, r, rot) { var d = '', k, a; for (k = 0; k < 5; k++) { a = (rot + k * 72) * Math.PI / 180; d += (k ? 'L' : 'M') + (cx + r * Math.cos(a)).toFixed(2) + ' ' + (cy + r * Math.sin(a)).toFixed(2); } return d + 'Z'; };
	var seams = '', outer = '', k, a, jx, jy, ox, oy, px, py, pr;
	for (k = 0; k < 5; k++) {
		a = (-90 + k * 72) * Math.PI / 180;
		px = 22 + 4.2 * Math.cos(a); py = 72 + 4.2 * Math.sin(a);
		jx = 22 + 7.6 * Math.cos(a); jy = 72 + 7.6 * Math.sin(a);
		seams += 'M' + px.toFixed(2) + ' ' + py.toFixed(2) + 'L' + jx.toFixed(2) + ' ' + jy.toFixed(2);
		pr = (-90 + k * 72 + 36) * Math.PI / 180;
		ox = 22 + 12.6 * Math.cos(pr); oy = 72 + 12.6 * Math.sin(pr);
		seams += 'M' + jx.toFixed(2) + ' ' + jy.toFixed(2) + 'L' + ox.toFixed(2) + ' ' + oy.toFixed(2);
		pr = (-90 + (k - 1) * 72 + 36) * Math.PI / 180;
		ox = 22 + 12.6 * Math.cos(pr); oy = 72 + 12.6 * Math.sin(pr);
		seams += 'M' + jx.toFixed(2) + ' ' + jy.toFixed(2) + 'L' + ox.toFixed(2) + ' ' + oy.toFixed(2);
		pr = (-90 + k * 72 + 36);
		ox = 22 + 12.6 * Math.cos(pr * Math.PI / 180); oy = 72 + 12.6 * Math.sin(pr * Math.PI / 180);
		outer += '<path d="' + P(ox, oy, 5, pr + 180) + '"/>';
	}
	var ball = '<g class="fb-bl">'
		+ '<g clip-path="url(#fbc)"><g class="fb-sp">'
		+ '<rect x="8" y="58" width="28" height="28" fill="url(#fbw)"/>'
		+ '<path d="' + seams + '" fill="none" stroke="#9aa1ae" stroke-width=".9" stroke-linecap="round"/>'
		+ '<g fill="#14161c">' + outer + '<path d="' + P(22, 72, 4.6, -90) + '"/></g>'
		+ '</g>'
		+ '<circle cx="22" cy="72" r="10.500" fill="url(#fbs)"/>'
		+ '</g>'
		+ '<circle cx="22" cy="72" r="10.200" fill="none" stroke="' + K + '" stroke-width="1.300"/>'
		+ '<ellipse cx="18.400" cy="66.800" rx="3.400" ry="2" transform="rotate(-35 18.400 66.800)" fill="#fff" opacity=".85"/>'
		+ '</g>';
	var art = '<svg viewBox="0 0 124 92" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<radialGradient id="fbw" cx=".38" cy=".32" r=".85"><stop offset="0" stop-color="#ffffff"/><stop offset=".7" stop-color="#e3e6ec"/><stop offset="1" stop-color="#aeb4c0"/></radialGradient>'
		+ '<radialGradient id="fbs" cx=".36" cy=".3" r=".8"><stop offset=".55" stop-color="#0a1430" stop-opacity="0"/><stop offset="1" stop-color="#0a1430" stop-opacity=".5"/></radialGradient>'
		+ '<clipPath id="fbc"><circle cx="22" cy="72" r="10.500"/></clipPath></defs>'
		+ '<ellipse cx="60" cy="78" rx="52" ry="5.500" fill="#000" opacity=".3"/>'
		// the back frame and net, which bulge
		+ '<g class="fb-net"><rect x="30" y="24" width="60" height="40" fill="#000" opacity=".25"/>' + net + '</g>'
		// the front frame: posts and crossbar
		+ '<path d="M14 76V14H106V76" fill="none" stroke="' + K + '" stroke-width="6.600" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 76V14H106V76" fill="none" stroke="#fff" stroke-width="4.200" stroke-linecap="round" stroke-linejoin="round"/>'
		+ '<path d="M16.500 70V16.500" stroke="#b9c0cc" stroke-width="1" stroke-linecap="round"/>'
		+ ball
		+ '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-fb')) { return; }
			btn.setAttribute('data-fb', '1');
			var node = document.createElement('span');
			node.className = 'fb-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
