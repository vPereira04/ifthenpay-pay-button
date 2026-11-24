/* Adds the cup with its latte-art heart, the milk ripple, three steam curls and two coffee beans. */
(function () {
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-cf')) { return; }
			btn.setAttribute('data-cf', '1');
			var make = function (tag, cls, parent, text) {
				var node = document.createElement(tag);
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (text) { node.textContent = text; }
				(parent || btn).appendChild(node);
				return node;
			};
			var box = make('span', 'cf-box');
			make('i', 'cf-ht', box);
			make('i', 'cf-rp', box);
			make('i', 'cf-st', box);
			make('i', 'cf-st', box);
			make('i', 'cf-st', box);
			make('span', 'cf-bn cf-b1');
			make('span', 'cf-bn cf-b2');
		});
	});
})();