/* Builds the title strip, the rest comment and the typing overlay, and runs the deploy sequence while the button is hovered or focused. */
(function () {
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-wd')) { return; }
			btn.setAttribute('data-wd', '1');
			var make = function (tag, cls, parent, text) {
				var node = document.createElement(tag);
				node.className = cls;
				node.setAttribute('aria-hidden', 'true');
				if (text) { node.textContent = text; }
				(parent || btn).appendChild(node);
				return node;
			};
			var bar = make('span', 'wd-b');
			make('i', 'wd-d', bar);
			make('i', 'wd-d', bar);
			make('i', 'wd-d', bar);
			make('i', 'wd-t', bar, '~/site - zsh');
			make('span', 'wd-rest', null, '# ready to ship');
			var ov = make('span', 'wd-ov');
			var l1 = make('span', 'wd-l wd-l1', ov);
			var l2 = make('span', 'wd-l wd-l2', ov);
			var t1 = document.createTextNode('');
			l1.appendChild(t1);
			var t2 = document.createTextNode('');
			l2.appendChild(t2);
			var cur = make('i', 'wd-k', l1);
			var cmd = 'npm run deploy';
			var timers = [];
			var later = function (fn, ms) { timers.push(setTimeout(fn, ms)); };
			var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			var stop = function () {
				timers.forEach(clearTimeout);
				timers = [];
				btn.classList.remove('wd-on');
				l2.className = 'wd-l wd-l2';
			};
			var bars = function (p) {
				var n = Math.round(p / 100 * 16);
				return '[' + new Array(n + 1).join('#') + new Array(17 - n).join('-') + '] ' + p + '%';
			};
			var run = function () {
				stop();
				if (reduce) { return; }
				btn.classList.add('wd-on');
				t1.nodeValue = '';
				t2.nodeValue = '';
				l1.appendChild(cur);
				var i;
				for (i = 1; i <= cmd.length; i++) {
					(function (n) { later(function () { t1.nodeValue = cmd.slice(0, n); }, 150 + n * 55); })(i);
				}
				var t0 = 150 + cmd.length * 55 + 250;
				later(function () { l2.className = 'wd-l wd-l2 wd-run'; l2.appendChild(cur); }, t0);
				for (i = 0; i <= 16; i++) {
					(function (n) { later(function () { t2.nodeValue = bars(Math.round(n / 16 * 100)); }, t0 + n * 120); })(i);
				}
				var t3 = t0 + 17 * 120 + 150;
				later(function () { l2.className = 'wd-l wd-l2 wd-live'; t2.nodeValue = '✓ Build ok · LIVE'; }, t3);
				later(run, t3 + 2200);
			};
			btn.addEventListener('mouseenter', run);
			btn.addEventListener('focus', run);
			btn.addEventListener('mouseleave', stop);
			btn.addEventListener('blur', stop);
		});
	});
})();