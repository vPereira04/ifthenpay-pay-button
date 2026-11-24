/* Draws a tumbler of red wine and a clay jug that tilts to pour it full, with a glint when it is done. */
(function () {
	var K = '#2a0d12';
	var art = '<svg viewBox="0 0 110 96" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<clipPath id="tcg"><path d="M23 42H51L47 86H27Z"/></clipPath>'
		+ '<linearGradient id="tcw" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="hsl(from var(--a3) h s calc(l + 10))"/><stop offset="1" stop-color="hsl(from var(--a3) h s calc(l - 8))"/></linearGradient>'
		+ '<linearGradient id="tcj" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="hsl(from var(--a2) h s calc(l + 14))"/><stop offset=".55" stop-color="var(--a2)"/><stop offset="1" stop-color="hsl(from var(--a2) h s calc(l - 14))"/></linearGradient>'
		+ '</defs>'
		+ '<ellipse cx="38" cy="88" rx="20" ry="3.500" fill="#000" opacity=".25"/>'
		// glass back, wine, then the glass front
		+ '<path d="M23 42H51L47 86H27Z" fill="#fff" fill-opacity=".35"/>'
		+ '<g clip-path="url(#tcg)"><rect class="tc-w" x="20" y="42" width="34" height="46" fill="url(#tcw)" style="translate:0 6px"/></g>'
		+ '<rect class="tc-st" x="43.500" y="38" width="3" height="48" fill="hsl(from var(--a3) h s calc(l + 4))"/>'
		+ '<path d="M23 42H51L47 86H27Z" fill="none" stroke="' + K + '" stroke-width="3" stroke-linejoin="round"/>'
		+ '<path d="M23 42H51L47 86H27Z" fill="none" stroke="#fff" stroke-opacity=".8" stroke-width="1.100" stroke-linejoin="round" transform="translate(0 0)"/>'
		+ '<path d="M29 48L31 80" stroke="#fff" stroke-opacity=".8" stroke-width="2.600" stroke-linecap="round"/>'
		+ '<path d="M23 86H51" stroke="' + K + '" stroke-width="3" stroke-linecap="round" opacity="0"/>'
		// the jug
		+ '<g class="tc-jug">'
		+ '<path d="M80 30Q94 30 94 45Q94 58 80 58" fill="none" stroke="' + K + '" stroke-width="9" stroke-linecap="round"/><path d="M80 30Q94 30 94 45Q94 58 80 58" fill="none" stroke="url(#tcj)" stroke-width="5" stroke-linecap="round"/>'
		+ '<path d="M62 22L53 20Q51 24 56 27L58 30V60Q58 70 68 70H72Q82 70 82 60V30Q82 24 76 22Z" fill="url(#tcj)" stroke="' + K + '" stroke-width="2.800" stroke-linejoin="round"/>'
		+ '<path d="M58 40H82V48H58Z" fill="var(--a1)" stroke="' + K + '" stroke-width="1.600"/>'
		+ '<path d="M58 44H82" stroke="#fff" stroke-width="1.400" stroke-dasharray="3 3"/>'
		+ '<path d="M62 54Q62 64 66 66" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="2.400" stroke-linecap="round"/>'
		+ '</g>'
		// glint
		+ '<g transform="translate(46 56)"><path class="tc-gl" d="M0 -9L2 -2L9 0L2 2L0 9L-2 2L-9 0L-2 -2Z" fill="#fff" stroke="' + K + '" stroke-width=".8"/></g>'
		+ '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-tc')) { return; }
			btn.setAttribute('data-tc', '1');
			var node = document.createElement('span');
			node.className = 'tc-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
