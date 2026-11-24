/* Draws a pizza with one slice that lifts away on stretching strands of melted cheese. */
(function () {
	var K = '#3a1606';
	var CX = 44, CY = 54, R = 34;
	var pt = function (a, rad) { var t = a * Math.PI / 180; return [CX + rad * Math.cos(t), CY + rad * Math.sin(t)]; };
	var A1 = -62, A2 = -8, U = -35;
	var P = function (a, rad) { var p = pt(a, rad); return p[0].toFixed(2) + ' ' + p[1].toFixed(2); };
	var pep = '', leaves = '', olives = '', blobs = '';
	[[-14, -12], [8, -19], [20, 2], [-3, 5], [-20, 12], [11, 19], [-12, -26], [24, -14], [0, -8]].forEach(function (c) {
		pep += '<g transform="translate(' + (CX + c[0]) + ' ' + (CY + c[1]) + ')"><circle r="6" fill="#c8312a" stroke="#6b130f" stroke-width="1"/><circle r="4.200" fill="#e04a3f"/><circle cx="-1.500" cy="-1" r=".9" fill="#8c1a14"/><circle cx="1.700" cy="1.200" r=".9" fill="#8c1a14"/></g>';
	});
	[[-24, -2, 30], [-6, -16, -20], [16, 12, 50], [-8, 20, 100]].forEach(function (c) {
		leaves += '<g transform="translate(' + (CX + c[0]) + ' ' + (CY + c[1]) + ') rotate(' + c[2] + ')"><path d="M0 -5.500Q6 0 0 5.500Q-6 0 0 -5.500Z" fill="var(--a3)" stroke="#12401c" stroke-width="1"/><path d="M0 -4.500V4.500" stroke="#12401c" stroke-width=".8"/></g>';
	});
	[[-6, -22], [18, -6], [-17, 24], [24, 20]].forEach(function (c) {
		olives += '<g transform="translate(' + (CX + c[0]) + ' ' + (CY + c[1]) + ')"><circle r="3.200" fill="#2a2430" stroke="#0c0910" stroke-width=".8"/><circle r="1.100" fill="#e9dfc4"/></g>';
	});
	[[-8, -3, 6], [10, -10, 5], [14, 14, 6], [-18, 2, 5], [2, 22, 5]].forEach(function (c) { blobs += '<circle cx="' + (CX + c[0]) + '" cy="' + (CY + c[1]) + '" r="' + c[2] + '" fill="hsl(from var(--a2) h s calc(l + 12))" opacity=".85"/>'; });
	var pizza = '<circle cx="' + CX + '" cy="' + CY + '" r="' + R + '" fill="url(#pzc)" stroke="' + K + '" stroke-width="1.600"/>'
		+ '<circle cx="' + CX + '" cy="' + CY + '" r="' + (R - 4.500) + '" fill="#c8312a" stroke="#8c1a14" stroke-width="1"/>'
		+ '<circle cx="' + CX + '" cy="' + CY + '" r="' + (R - 7) + '" fill="url(#pzh)"/>' + blobs + pep + olives + leaves
		+ '<path d="M' + P(-150, R - 1) + 'A' + (R - 1) + ' ' + (R - 1) + ' 0 0 1 ' + P(-110, R - 1) + '" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="1.600" stroke-linecap="round"/>';
	var rem = '<clipPath id="pzr"><path clip-rule="evenodd" d="M-30 -40H170V140H-30ZM' + CX + ' ' + CY + 'L' + P(A1, 70) + 'L' + P(U, 82) + 'L' + P(A2, 70) + 'Z"/></clipPath>';
	var sli = '<clipPath id="pzs"><path d="M' + CX + ' ' + CY + 'L' + P(A1, 70) + 'L' + P(U, 82) + 'L' + P(A2, 70) + 'Z"/></clipPath>';
	var strands = '';
	[[A1, 11], [A1, 20], [A1, 29], [A2, 11], [A2, 20], [A2, 29]].forEach(function (s) {
		var p = pt(s[0], s[1]);
		strands += '<g transform="translate(' + p[0].toFixed(2) + ' ' + p[1].toFixed(2) + ') rotate(' + U + ')"><path class="pz-st" d="M0 0Q.5 .3 1 0" fill="none" stroke="hsl(from var(--a2) h s calc(l + 6))" stroke-width="4.600" stroke-linecap="round" vector-effect="non-scaling-stroke"/><circle class="pz-bl" cx="0" cy="0" r="2.400" fill="hsl(from var(--a2) h s calc(l + 6))" stroke="hsl(from var(--a2) h s calc(l - 15))" stroke-width=".7"/></g>';
	});
	var art = '<svg viewBox="0 0 130 100" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<radialGradient id="pzc" cx=".4" cy=".35" r=".8"><stop offset="0" stop-color="#f0c27a"/><stop offset="1" stop-color="#c98a3d"/></radialGradient>'
		+ '<radialGradient id="pzh" cx=".4" cy=".35" r=".9"><stop offset="0" stop-color="hsl(from var(--a2) h s calc(l + 17))"/><stop offset="1" stop-color="hsl(from var(--a2) h s calc(l - 4))"/></radialGradient>' + rem + sli + '</defs>'
		+ '<g clip-path="url(#pzr)">' + pizza + '</g>'
		+ strands
		+ '<g class="pz-sl"><g clip-path="url(#pzs)">' + pizza + '</g></g>'
		+ '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-pz')) { return; }
			btn.setAttribute('data-pz', '1');
			var node = document.createElement('span');
			node.className = 'pz-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
