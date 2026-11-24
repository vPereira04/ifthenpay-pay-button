/* Adds the black belt, its two tails, the red stamp and the shock ring. */
(function () {
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-jd')) { return; }
			btn.setAttribute('data-jd', '1');
			var make = function (tag, cls, parent, text) {
				var node = document.createElement(tag);
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (text) { node.textContent = text; }
				(parent || btn).appendChild(node);
				return node;
			};
			make('span', 'jd-tl');
			make('span', 'jd-tr');
			make('span', 'jd-bt');
			make('span', 'jd-sh');
			var st = make('span', 'jd-st');
			make('i', '', st, 'IPPON!');
		});
	});
})();