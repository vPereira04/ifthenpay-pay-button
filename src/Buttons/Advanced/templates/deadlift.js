/* Adds the barbell, its shadow, the PR text and the chalk so the CSS can move them. */
(function () {
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-dl')) { return; }
			btn.setAttribute('data-dl', '1');
			var make = function (tag, cls, parent, text) {
				var node = document.createElement(tag);
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (text) { node.textContent = text; }
				parent.appendChild(node);
				return node;
			};
			make('span', 'dl-sh', btn);
			var rig = make('span', 'dl-rig', btn);
			make('i', 'dl-bar', rig);
			make('i', 'dl-a', rig);
			make('i', 'dl-b', rig);
			make('span', 'dl-win', btn, 'PR!');
			[-34, 0, 34].forEach(function (dx) { make('span', 'dl-ch', btn).style.setProperty('--dx', dx); });
		});
	});
})();