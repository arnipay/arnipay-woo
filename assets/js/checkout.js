/**
 * arnipay checkout button enhancement.
 * Adds a visual class to the final WooCommerce button when arnipay is selected.
 */
jQuery(function($) {
	'use strict';

	function syncArnipayButton() {
		const selected = $('input[name="payment_method"]:checked').val() === 'arnipay_woo_aw';
		$('#place_order').toggleClass('arnipay-final-order-button', selected);
	}

	$(document.body).on('updated_checkout payment_method_selected change', 'input[name="payment_method"]', syncArnipayButton);
	$(document.body).on('updated_checkout payment_method_selected', syncArnipayButton);
	syncArnipayButton();
});
