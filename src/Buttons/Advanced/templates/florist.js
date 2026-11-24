/* Florist: a wrapped bouquet on the left, a butterfly on the right. */
(function () {
	var list = typeof scope !== 'undefined' && scope ? (scope.forEach ? Array.prototype.slice.call(scope) : [scope]) : [document];
	var S = 'stroke="hsl(from var(--a1) h calc(s - 4) calc(l - 4))" stroke-width="1.3" stroke-linejoin="round" stroke-linecap="round"';
	var G = 'stroke="hsl(from var(--a4) h s calc(l - 12))" stroke-width="1.2" stroke-linejoin="round" stroke-linecap="round"';
	var daisy = '';
	for (var i = 0; i < 8; i++) { daisy += '<ellipse cx="0" cy="-6.5" rx="3.2" ry="5.2" transform="rotate(' + (i * 45) + ')" fill="#fff" stroke="hsl(from var(--a1) h calc(s - 45) calc(l + 17))" stroke-width="1"/>'; }
	var bouquet = '<svg viewBox="0 0 80 86" xmlns="http://www.w3.org/2000/svg" width="80" height="86">' +
		'<g fill="none" stroke="var(--a4)" stroke-width="2.4" stroke-linecap="round"><path d="M40 58C40 46 40 36 40 28"/><path d="M38 58C30 48 24 40 21 32"/><path d="M42 58C50 48 56 40 59 32"/><path d="M36 58C30 50 30 38 31 22"/><path d="M44 58C50 50 52 36 51 20"/></g>' +
		'<path d="M38 52C28 52 20 46 18 38C28 38 36 42 38 52Z" fill="hsl(from var(--a4) h s calc(l + 23))" ' + G + '/><path d="M42 52C52 52 60 46 63 38C53 38 44 42 42 52Z" fill="hsl(from var(--a4) h s calc(l + 23))" ' + G + '/>' +
		'<g class="fl-b1"><circle cx="31" cy="20" r="4.4" fill="var(--a5)" stroke="hsl(from var(--a5) h calc(s - 50) calc(l - 34))" stroke-width="1.2"/><circle cx="28" cy="25" r="3.2" fill="hsl(from var(--a5) h s calc(l + 6))" stroke="hsl(from var(--a5) h calc(s - 50) calc(l - 34))" stroke-width="1.2"/></g>' +
		'<g class="fl-b2"><circle cx="51" cy="18" r="4.4" fill="var(--a5)" stroke="hsl(from var(--a5) h calc(s - 50) calc(l - 34))" stroke-width="1.2"/><circle cx="54" cy="23" r="3.2" fill="hsl(from var(--a5) h s calc(l + 6))" stroke="hsl(from var(--a5) h calc(s - 50) calc(l - 34))" stroke-width="1.2"/></g>' +
		'<g class="fl-f fl-f1"><path d="M13 32C11 22 15 17 15 14L20 20L25 13C25 16 29 22 27 32C25 37 15 37 13 32Z" fill="var(--a6)" stroke="hsl(from var(--a6) h calc(s - 6) calc(l - 27))" stroke-width="1.3" stroke-linejoin="round"/><path d="M20 20C18 25 18 31 20 36" fill="none" stroke="hsl(from var(--a6) h s calc(l + 28))" stroke-width="1.3" stroke-linecap="round"/></g>' +
		'<g class="fl-f fl-f2"><g transform="translate(60 27)">' + daisy + '<circle r="4" fill="hsl(from var(--a6) h s calc(l + 12))" stroke="hsl(from var(--a6) h calc(s - 14) calc(l - 23))" stroke-width="1.2"/></g></g>' +
		'<g class="fl-f fl-f3"><circle cx="40" cy="22" r="12.5" fill="hsl(from var(--a3) h s calc(l - 7))" ' + S + '/><path d="M40 22C37 18 43 15 45 19C47 24 40 28 35 24C31 20 35 13 41 13" fill="none" stroke="#fff0f3" stroke-width="1.4" stroke-linecap="round"/><path d="M33 29C37 33 45 33 48 28" fill="none" stroke="hsl(from var(--a1) h calc(s + 8) calc(l + 10))" stroke-width="1.2" stroke-linecap="round"/></g>' +
		'<path d="M11 47Q40 38 69 47L46 84L34 84Z" fill="hsl(from var(--a6) h calc(s - 24) calc(l + 24))" stroke="hsl(from var(--a6) h calc(s - 50) calc(l - 19))" stroke-width="1.4" stroke-linejoin="round"/><path d="M40 53L40 83M28 50L37 83M52 50L43 83" fill="none" stroke="hsl(from var(--a6) h calc(s - 48) calc(l + 10))" stroke-width="1"/>' +
		'<path d="M23 62Q40 69 57 62L55 69Q40 76 25 69Z" fill="hsl(from var(--a3) h s calc(l - 7))" ' + S + '/>' +
		'<g class="fl-bow"><path d="M40 66C32 58 26 64 30 70C33 73 38 69 40 66Z" fill="var(--a3)" ' + S + '/><path d="M40 66C48 58 54 64 50 70C47 73 42 69 40 66Z" fill="var(--a3)" ' + S + '/><circle cx="40" cy="67" r="2.8" fill="hsl(from var(--a3) h s calc(l - 7))" ' + S + '/></g></svg>';
	var butterfly = '<svg viewBox="0 0 44 40" xmlns="http://www.w3.org/2000/svg" width="44" height="40">' +
		'<g class="fl-wl"><path d="M22 20C14 4 3 6 4 15C5 22 14 24 22 20Z" fill="hsl(from var(--a2) h calc(s + 30) calc(l + 44))" stroke="var(--a2)" stroke-width="1.3" stroke-linejoin="round"/><path d="M22 21C14 22 6 28 10 34C15 38 21 29 22 21Z" fill="hsl(from var(--a5) h s calc(l + 5))" stroke="var(--a2)" stroke-width="1.3" stroke-linejoin="round"/><circle cx="11" cy="14" r="2.4" fill="#fff"/></g>' +
		'<g class="fl-wr"><path d="M22 20C30 4 41 6 40 15C39 22 30 24 22 20Z" fill="hsl(from var(--a2) h calc(s + 30) calc(l + 44))" stroke="var(--a2)" stroke-width="1.3" stroke-linejoin="round"/><path d="M22 21C30 22 38 28 34 34C29 38 23 29 22 21Z" fill="hsl(from var(--a5) h s calc(l + 5))" stroke="var(--a2)" stroke-width="1.3" stroke-linejoin="round"/><circle cx="33" cy="14" r="2.4" fill="#fff"/></g>' +
		'<ellipse cx="22" cy="21" rx="2" ry="8" fill="hsl(from var(--a5) h calc(s - 60) calc(l - 55))"/><path d="M21 13C19 8 17 6 15 6M23 13C25 8 27 6 29 6" fill="none" stroke="hsl(from var(--a5) h calc(s - 60) calc(l - 55))" stroke-width="1.1" stroke-linecap="round"/></svg>';
	var petal = '<svg viewBox="0 0 12 14" xmlns="http://www.w3.org/2000/svg" width="12" height="14"><path d="M6 1C11 4 11 11 6 13C1 11 1 4 6 1Z" fill="var(--a3)" stroke="hsl(from var(--a1) h calc(s - 4) calc(l - 4))" stroke-width="1.1"/></svg>';
	list.forEach(function (el) {
		if (!el || !el.querySelectorAll) { return; }
		var btns = el.matches && el.matches('.iftp-btn-pay__button') ? [el] : Array.prototype.slice.call(el.querySelectorAll('.iftp-btn-pay__button'));
		btns.forEach(function (btn) {
			if (btn.getAttribute('data-fl')) { return; }
			btn.setAttribute('data-fl', '1');
			var make = function (cls, html) {
				var n = document.createElement('span');
				n.className = cls; n.setAttribute('aria-hidden', 'true'); n.innerHTML = html || '';
				btn.appendChild(n); return n;
			};
			make('fl-gl');
			make('fl-bq', bouquet);
			make('fl-bf', butterfly);
			for (var k = 1; k <= 5; k++) { make('fl-dp fl-d' + k, petal); }
		});
	});
})();