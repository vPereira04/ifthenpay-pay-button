/* Adds the printer, the page that prints, the scanner light and the four colour dots. */
(function () {
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-pr')) { return; }
			btn.setAttribute('data-pr', '1');
			var make = function (tag, cls, parent, text) {
				var node = document.createElement(tag);
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (text) { node.textContent = text; }
				(parent || btn).appendChild(node);
				return node;
			};
			var win = make('span', 'pr-win');
			var pap = make('i', 'pr-pap', win);
			make('i', 'pr-ink', pap);
			var ptr = make('span', 'pr-ptr');
			var gl = make('i', 'pr-gl', ptr);
			make('i', 'pr-sc', gl);
			make('i', 'pr-led', ptr);
			for (var d = 0; d < 4; d++) { make('i', 'pr-d'); }
		});
	});
})();