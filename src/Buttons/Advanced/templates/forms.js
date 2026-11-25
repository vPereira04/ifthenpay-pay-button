/* Draws a clipboard form: three boxes that tick off one by one while a pencil writes. */
(function () {
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	var rows = '';
	[18, 30, 42].forEach(function (y, i) {
		rows += '<rect class="fm-cb" x="18" y="' + y + '" width="8" height="8" rx="2"/>'
			+ '<path class="fm-ln fm-l' + (i + 1) + '" d="M31 ' + (y + 4) + 'H52"/>'
			+ '<path class="fm-tk fm-t' + (i + 1) + '" d="M19.600 ' + (y + 4.200) + 'L21.800 ' + (y + 6.400) + 'L25 ' + (y + 1.600) + '"/>';
	});
	var art = '<svg class="fm-svg" viewBox="0 0 96 60" xmlns="http://www.w3.org/2000/svg">'
		+ '<g class="fm-sheet">'
		+ '<rect class="fm-shadow" x="13" y="7" width="50" height="52" rx="4"/>'
		+ '<rect class="fm-paper" x="10" y="4" width="50" height="52" rx="4"/>'
		+ '<rect class="fm-clip" x="27" y="0" width="16" height="9" rx="2.500"/><rect class="fm-hole" x="32" y="2.500" width="6" height="2.600" rx="1.300"/>'
		+ '<path class="fm-head" d="M17 13H44"/>' + rows
		+ '</g>'
		+ '<g class="fm-pen"><g transform="rotate(38 76 30)">'
		+ '<rect class="fm-pbody" x="73" y="8" width="6" height="30" rx="1.500"/><rect class="fm-pband" x="73" y="12" width="6" height="3"/>'
		+ '<path class="fm-ptip" d="M73 38H79L76 46Z"/><path class="fm-plead" d="M75.200 43.300H76.800L76 46Z"/>'
		+ '</g></g>'
		+ '</svg>';
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-fm')) { return; }
			btn.setAttribute('data-fm', '1');
			var box = document.createElement('span');
			box.className = 'fm-box';
			box.setAttribute('aria-hidden', 'true');
			box.innerHTML = art;
			btn.appendChild(box);
		});
	});
})();
