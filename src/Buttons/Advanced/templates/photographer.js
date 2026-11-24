/* Adds the camera, the photo, the focus corners and the flash, and sets how far the photo is tossed. */
(function () {
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-ph')) { return; }
			btn.setAttribute('data-ph', '1');
			var make = function (tag, cls, parent, text) {
				var node = document.createElement(tag);
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (text) { node.textContent = text; }
				(parent || btn).appendChild(node);
				return node;
			};
			make('span', 'ph-fl');
			make('span', 'ph-pic');
			make('span', 'ph-cam');
			make('span', 'ph-fb');
			make('span', 'ph-st');
			[0, 1, 2, 3].forEach(function () { make('i', 'ph-b'); });
		});
	});
})();