/* Draws the ginger cat behind a goldfish bowl: the fish swims, bubbles rise and the cat's eyes follow it. */
(function () {
	var K = '#2a1408', ORANGE = '#f0a24a', LIGHT = '#fff1da';
	var art = '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><defs>'
		+ '<radialGradient id="pbw" cx=".4" cy=".25" r=".9"><stop offset="0" stop-color="#d6f6ff"/><stop offset=".55" stop-color="#7fd2ec"/><stop offset="1" stop-color="#3a9fc9"/></radialGradient>'
		+ '<linearGradient id="pbf" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffb347"/><stop offset="1" stop-color="#ff6a1a"/></linearGradient>'
		+ '<clipPath id="pbc"><circle cx="50" cy="68" r="29"/></clipPath></defs>'
		// the cat: ears, head, face
		+ '<g class="pb-ear" style="transform-origin:26px 22px"><path d="M20 30L24 6L42 20Z" fill="' + ORANGE + '" stroke="' + K + '" stroke-width="2" stroke-linejoin="round"/><path d="M25 24L26 14L34 20Z" fill="#ff9aa8"/></g>'
		+ '<path d="M80 30L76 6L58 20Z" fill="' + ORANGE + '" stroke="' + K + '" stroke-width="2" stroke-linejoin="round"/><path d="M75 24L74 14L66 20Z" fill="#ff9aa8"/>'
		+ '<ellipse cx="50" cy="38" rx="33" ry="27" fill="' + ORANGE + '" stroke="' + K + '" stroke-width="2"/>'
		+ '<path d="M50 12V22M40 14L42 23M60 14L58 23" stroke="#c9741f" stroke-width="2.600" stroke-linecap="round"/>'
		+ '<path d="M16 36L24 38M16 44L25 43M84 36L76 38M84 44L75 43" stroke="#c9741f" stroke-width="2.200" stroke-linecap="round"/>'
		+ '<ellipse cx="36" cy="34" rx="8.500" ry="9" fill="#e9ffd0" stroke="' + K + '" stroke-width="1.600"/><ellipse cx="64" cy="34" rx="8.500" ry="9" fill="#e9ffd0" stroke="' + K + '" stroke-width="1.600"/>'
		+ '<g class="pb-pp"><ellipse cx="36" cy="35" rx="3" ry="6.500" fill="' + K + '"/><ellipse cx="64" cy="35" rx="3" ry="6.500" fill="' + K + '"/><circle cx="34.800" cy="32" r="1.400" fill="#fff"/><circle cx="62.800" cy="32" r="1.400" fill="#fff"/></g>'
		+ '<path d="M46 45H54L50 49Z" fill="#ff7d94" stroke="' + K + '" stroke-width="1.200" stroke-linejoin="round"/>'
		// whiskers
		+ '<path d="M30 48L8 44M30 51L8 53M70 48L92 44M70 51L92 53" stroke="' + K + '" stroke-opacity=".7" stroke-width="1" stroke-linecap="round"/>'
		// the bowl: water, plant, gravel, fish, bubbles
		+ '<circle cx="50" cy="68" r="29" fill="url(#pbw)" opacity=".92"/>'
		+ '<g clip-path="url(#pbc)">'
		+ '<ellipse cx="50" cy="42" rx="30" ry="4.500" fill="#e9fbff" opacity=".5"/>'
		+ '<path class="pb-pl" d="M70 92Q64 80 70 72Q76 64 68 54" fill="none" stroke="#1f8a4c" stroke-width="3.200" stroke-linecap="round"/><path class="pb-pl" d="M76 92Q82 82 76 74" fill="none" stroke="#2fae66" stroke-width="2.600" stroke-linecap="round"/>'
		+ '<g class="pb-fish"><path d="M38 70Q50 60 62 70Q50 80 38 70Z" fill="url(#pbf)" stroke="' + K + '" stroke-width="1.500" stroke-linejoin="round"/><path d="M62 70L72 62L70 70L72 78Z" fill="#ff7a2a" stroke="' + K + '" stroke-width="1.400" stroke-linejoin="round"/><path d="M48 62Q52 56 56 63" fill="#ff8f3a" stroke="' + K + '" stroke-width="1.200"/><circle cx="43.500" cy="68" r="2.200" fill="#fff" stroke="' + K + '" stroke-width="1"/><circle cx="43" cy="68" r="1" fill="' + K + '"/></g>'
		+ '<circle class="pb-bu pb-b1" cx="40" cy="80" r="2.800" fill="#fff" stroke="#3a9fc9" stroke-width=".8"/><circle class="pb-bu pb-b2" cx="48" cy="84" r="2.200" fill="#fff" stroke="#3a9fc9" stroke-width=".8"/><circle class="pb-bu pb-b3" cx="58" cy="82" r="3.200" fill="#fff" stroke="#3a9fc9" stroke-width=".8"/>'
		+ '<path d="M22 88Q50 100 78 88V98H22Z" fill="#e8c88a"/><circle cx="32" cy="91" r="2.400" fill="#c98f5a"/><circle cx="44" cy="94" r="2.800" fill="#f0d7a8"/><circle cx="56" cy="93" r="2.400" fill="#d9a76e"/><circle cx="66" cy="90" r="2.200" fill="#c98f5a"/></g>'
		+ '<circle cx="50" cy="68" r="29" fill="none" stroke="#fff" stroke-opacity=".9" stroke-width="2.600"/><circle cx="50" cy="68" r="30.500" fill="none" stroke="' + K + '" stroke-opacity=".55" stroke-width="1.200"/>'
		+ '<path d="M30 56Q33 46 44 42" fill="none" stroke="#fff" stroke-width="3.600" stroke-linecap="round" opacity=".85"/><circle cx="29" cy="62" r="1.800" fill="#fff" opacity=".9"/>'
		+ '</svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-pb')) { return; }
			btn.setAttribute('data-pb', '1');
			var node = document.createElement('span');
			node.className = 'pb-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = art;
			btn.appendChild(node);
		});
	});
})();
