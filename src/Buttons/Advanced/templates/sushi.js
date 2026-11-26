/* Draws the board, the maki, the salmon nigiri and the chopsticks. */
(function () {
	var salmon = 'var(--a2)';
	var fat = 'hsl(from var(--a2) h calc(s - 10) calc(l + 24))';
	var deep = 'hsl(from var(--a2) calc(h - 6) s calc(l - 14))';
	var wood = '#c98f52';
	var maki = '<circle cx="22" cy="31" r="14" fill="#0f1a14"/><circle cx="22" cy="31" r="11.500" fill="#fdfbf4"/>'
		+ '<circle cx="22" cy="31" r="11.500" fill="none" stroke="#e6e0cf" stroke-width=".6" stroke-dasharray="1.500 2.200"/>'
		+ '<circle cx="22" cy="31" r="6.500" fill="var(--a3)"/><circle cx="22" cy="31" r="3.800" fill="' + salmon + '"/>'
		+ '<path d="M12 22Q16 18 22 17.500" fill="none" stroke="#fff" stroke-opacity=".25" stroke-width="1.400" stroke-linecap="round"/>';
	var nigiri = '<rect x="48" y="30" width="40" height="15" rx="7.500" fill="#fdfbf4"/>'
		+ '<path d="M52 40H84" stroke="#e6e0cf" stroke-width="1" stroke-dasharray="1.500 2.200"/>'
		+ '<path d="M43 33Q44 21 68 20Q92 21 93 33Q68 38 43 33Z" fill="' + salmon + '" stroke="' + deep + '" stroke-width="1" stroke-linejoin="round"/>'
		+ '<path d="M50 30Q60 24 70 28M62 33Q74 26 86 31M56 25Q64 22 74 24" fill="none" stroke="' + fat + '" stroke-width="1.600" stroke-linecap="round" stroke-opacity=".85"/>';
	var star = '<path class="sh-st" d="M80 14L81.600 18.400L86 20L81.600 21.600L80 26L78.400 21.600L74 20L78.400 18.400Z" fill="#fff"/>';
	var sticks = '<g class="sh-b"><path d="M108 4L64 25" stroke="#2a1a12" stroke-width="3.800" stroke-linecap="round"/><path d="M108 4L64 25" stroke="#d9a35f" stroke-width="2.400" stroke-linecap="round"/><path d="M108 4L96 9.700" stroke="#b3202a" stroke-width="2.400" stroke-linecap="round"/></g>'
		+ '<path d="M108 9L72 33" stroke="#2a1a12" stroke-width="3.800" stroke-linecap="round"/><path d="M108 9L72 33" stroke="#e9bd7a" stroke-width="2.400" stroke-linecap="round"/><path d="M108 9L97 16.300" stroke="#b3202a" stroke-width="2.400" stroke-linecap="round"/>';
	var board = '<rect x="2" y="44" width="104" height="11" rx="4" fill="#5a3a1d"/><rect x="2" y="42" width="104" height="11" rx="4" fill="' + wood + '"/>'
		+ '<path d="M8 46H40M52 49H98" stroke="#a8733a" stroke-width="1" stroke-linecap="round"/>'
		+ '<ellipse cx="100" cy="41" rx="5" ry="3.500" fill="var(--a3)"/><path d="M97 39.500Q100 38 103 39.800" fill="none" stroke="#fff" stroke-opacity=".4" stroke-width="1" stroke-linecap="round"/>';
	var svg = '<svg viewBox="0 0 108 60" xmlns="http://www.w3.org/2000/svg">' + board + maki + '<g class="sh-lift">' + nigiri + sticks + star + '</g></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-sh')) { return; }
			btn.setAttribute('data-sh', '1');
			var node = document.createElement('span');
			node.className = 'sh-ic';
			node.setAttribute('aria-hidden', 'true');
			node.innerHTML = svg;
			btn.appendChild(node);
		});
	});
})();