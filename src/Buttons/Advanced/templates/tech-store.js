/* Builds the laptop that opens, boots and shows the tick, and the arrow. */
(function () {
	var laptop = '<i class="tc-gl"></i><span class="tc-lid"><span class="tc-scr"><i class="tc-wp"></i><i class="tc-sp"></i><i class="tc-ok"></i><i class="tc-sh"></i></span></span><span class="tc-base"><i class="tc-keys"></i><i class="tc-kg"></i></span>';
	var arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.600" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-tc')) { return; }
			btn.setAttribute('data-tc', '1');
			var make = function (cls, html) {
				var node = document.createElement('span');
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				node.innerHTML = html;
				btn.appendChild(node);
			};
			make('tc-ic', laptop);
			make('tc-go', arrow);
		});
	});
})();