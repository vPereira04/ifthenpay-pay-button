/* Builds the board: the grip face carries the label, the other face is the print with the trucks. */
(function () {
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-sk')) { return; }
			btn.setAttribute('data-sk', '1');
			var label = btn.querySelector('.iftp-btn-pay__label');
			var deck = document.createElement('span');
			deck.className = 'sk-deck';
			var up = document.createElement('span');
			up.className = 'sk-up';
			var bolts = '';
			for (var i = 1; i <= 8; i++) { bolts += '<i class="sk-bolt sk-b' + i + '" aria-hidden="true"></i>'; }
			up.innerHTML = bolts + '<i class="sk-st" aria-hidden="true"></i>';
			if (label) { up.insertBefore(label, up.firstChild); }
			else { up.insertAdjacentHTML('afterbegin', '<span class="iftp-btn-pay__label"></span>'); }
			var truck = function (cls) {
				return '<i class="sk-tr ' + cls + '"><i class="sk-ax"></i><i class="sk-bp"></i><i class="sk-wl sk-w1"></i><i class="sk-wl sk-w2"></i></i>';
			};
			var dn = document.createElement('span');
			dn.className = 'sk-dn';
			dn.setAttribute('aria-hidden', 'true');
			dn.innerHTML = '<i class="sk-ck sk-c1"></i><i class="sk-ck sk-c2"></i><span class="sk-gr"><i class="sk-bz"></i>SK8<i class="sk-bz"></i></span>' + truck('sk-t1') + truck('sk-t2');
			deck.appendChild(up);
			deck.appendChild(dn);
			btn.appendChild(deck);
			['sk-sh', 'sk-du sk-d1', 'sk-du sk-d2'].forEach(function (cls) {
				var n = document.createElement('i');
				n.className = cls;
				n.setAttribute('aria-hidden', 'true');
				btn.appendChild(n);
			});
		});
	});
})();