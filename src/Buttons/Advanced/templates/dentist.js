/* Adds the tooth, the foam, the toothbrush, the sparkles, the ring and the glint. */
(function () {
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-dn')) { return; }
			btn.setAttribute('data-dn', '1');
			var make = function (tag, cls, parent, text) {
				var node = document.createElement(tag);
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (text) { node.textContent = text; }
				(parent || btn).appendChild(node);
				return node;
			};
			var box = make('span', 'dn-box');
			make('i', 'dn-th', box);
			make('i', 'dn-fb', box);
			var bubs = [[16, 20, 6], [70, 26, 5], [22, 52, 5], [58, 54, 6], [44, 8, 4], [10, 38, 4], [68, 44, 4]];
			bubs.forEach(function (b, i) {
				var e = make('i', 'dn-bb', box);
				e.style.left = (b[0] - b[2]) + 'px';
				e.style.top = (b[1] - b[2]) + 'px';
				e.style.width = (b[2] * 2) + 'px';
				e.style.height = (b[2] * 2) + 'px';
				e.style.setProperty('--n', i);
			});
			make('i', 'dn-gl', box);
			make('i', 'dn-sp dn-s1', box);
			make('i', 'dn-sp dn-s2', box);
			make('i', 'dn-sp dn-s3', box);
			make('i', 'dn-rg', box);
			var bw = make('span', 'dn-bw', box);
			make('i', 'dn-br', bw);
		});
	});
})();