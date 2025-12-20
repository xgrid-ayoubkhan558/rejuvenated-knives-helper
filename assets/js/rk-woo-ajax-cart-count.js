(function () {
	'use strict';

	var scheduled = false;
	var observer = null;

	function syncCartTotalToBody() {
		var dataEl = document.querySelector('.rk-woo-ajax-cart-count__cart-total-data');
		if (!dataEl || !document.body) {
			return;
		}

		var total = dataEl.getAttribute('data-woo-cart-total');
		if (total !== null) {
			document.body.setAttribute('data-woo-cart-total', total);
		}

		var subtotal = dataEl.getAttribute('data-woo-cart-subtotal');
		if (subtotal !== null) {
			document.body.setAttribute('data-woo-cart-subtotal', subtotal);
		}

		var currency = dataEl.getAttribute('data-woo-cart-currency');
		if (currency !== null) {
			document.body.setAttribute('data-woo-cart-currency', currency);
		}

		var minOrderValue = dataEl.getAttribute('data-woo-min-order-value');
		if (minOrderValue !== null) {
			document.body.setAttribute('data-woo-min-order-value', minOrderValue);
		}

		var meetsMinOrder = dataEl.getAttribute('data-woo-meets-min-order');
		if (meetsMinOrder !== null) {
			document.body.setAttribute('data-woo-meets-min-order', meetsMinOrder);
		}

		var minimumValueReached = dataEl.getAttribute('data-woo-minimum-value-reached');
		if (minimumValueReached !== null) {
			document.body.setAttribute('data-woo-minimum-value-reached', minimumValueReached);
		}
	}

	function scheduleSync() {
		if (scheduled) {
			return;
		}
		scheduled = true;
		window.requestAnimationFrame(function () {
			scheduled = false;
			syncCartTotalToBody();
		});
	}

	function ensureObserver() {
		if (!window.MutationObserver || !document.body || observer) {
			return;
		}

		observer = new MutationObserver(function () {
			scheduleSync();
		});

		observer.observe(document.body, { subtree: true, childList: true });
	}

	function init() {
		syncCartTotalToBody();
		ensureObserver();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
