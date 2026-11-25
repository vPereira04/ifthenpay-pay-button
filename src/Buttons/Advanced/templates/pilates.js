/* Adds the reformer icon; CSS slides the carriage and bends the legs. */
(function () {
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	var zig = 'M75 41';
	for (var i = 0; i < 14; i++) { zig += 'l2 ' + (i % 2 ? '1.8' : '-1.8'); }
	zig += 'L103 41';
	var art = '<svg class="pl-svg" viewBox="22 14.500 88 40" xmlns="http://www.w3.org/2000/svg" focusable="false">'
		+ '<rect class="pl-frame" x="22" y="45" width="88" height="4.500" rx="2.200"/>'
		+ '<rect class="pl-frame" x="27" y="49.500" width="4" height="4" rx="1"/><rect class="pl-frame" x="101" y="49.500" width="4" height="4" rx="1"/>'
		+ '<path class="pl-o" style="stroke-width:5.200" d="M103 44V27"/><path class="pl-post" d="M103 44V27"/><rect class="pl-bar" x="100.500" y="22" width="5" height="7" rx="2.500"/>'
		+ '<path class="pl-sp" d="' + zig + '"/>'
		+ '<g class="pl-car">'
		+ '<circle class="pl-wheel" cx="33" cy="43.500" r="2"/><circle class="pl-wheel" cx="67" cy="43.500" r="2"/>'
		+ '<rect class="pl-frame" x="27" y="39" width="46" height="4" rx="1.500"/>'
		+ '<rect class="pl-pad" x="25" y="35" width="50" height="4.500" rx="2.200"/>'
		+ '<rect class="pl-frame" x="31" y="33" width="10" height="2.400" rx="1.200"/>'
		+ '<path class="pl-o" style="stroke-width:8.600" d="M69 31.300H45"/><path class="pl-body" d="M69 31.300H45"/>'
		+ '<path class="pl-o" style="stroke-width:4.400" d="M47 32.600H62"/><path class="pl-arm" d="M47 32.600H62"/>'
		+ '<circle class="pl-bun pl-hair" cx="35" cy="25.600" r="2.700" stroke="#1a1411" stroke-width="1"/>'
		+ '<circle class="pl-hair" cx="39.500" cy="29.200" r="5.200" stroke="#1a1411" stroke-width="1"/>'
		+ '<circle class="pl-face" cx="40.600" cy="29.800" r="4.200"/>'
		+ '<g class="pl-thigh"><path class="pl-o" style="stroke-width:7.400" d="M69 31.300L83.03 26.00"/><path class="pl-th" d="M69 31.300L83.03 26.00"/>'
		+ '<g class="pl-shin"><path class="pl-o" style="stroke-width:7.400" d="M83.03 26.00L98 27"/><path class="pl-sh" d="M83.03 26.00L98 27"/><circle class="pl-knee" cx="83.03" cy="26.00" r="2.500"/></g></g>'
		+ '</g>'
		+ '<path class="pl-o" style="stroke-width:5.800" d="M98 27L100.200 24.600"/><path class="pl-foot" d="M98 27L100.200 24.600"/>'
		+ '</svg>';
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-pl')) { return; }
			btn.setAttribute('data-pl', '1');
			var box = document.createElement('span');
			box.className = 'pl-box';
			box.setAttribute('aria-hidden', 'true');
			box.innerHTML = art;
			btn.appendChild(box);
		});
	});
})();
