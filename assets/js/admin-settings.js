/**
 * arnipay Admin Settings
 *
 * Mantiene el orden de selección en el campo multiselect de métodos de pago
 * y mejora la experiencia de configuración en el panel de WooCommerce.
 *
 * @package Arnipay_Woo
 */

jQuery(document).ready(function($) {
	'use strict';

	// Mantener el orden de selección en payment_methods.
	// Funciona tanto si Select2 está activo como si no: si Select2 no se
	// inicializa, el <select multiple> nativo se guarda igual sin que este
	// código interfiera (clave para no perder la selección).
	const $paymentMethods = $('#woocommerce_arnipay_woo_aw_payment_methods');

	function isSelect2Active($el) {
		return $el.length && $el.next('.select2-container').length > 0;
	}

	if ($paymentMethods.length) {
		let customOrder = $paymentMethods.val() || [];

		function reorderOptions() {
			const $options = $paymentMethods.find('option');
			const optionsMap = {};

			$options.each(function() {
				optionsMap[$(this).val()] = $(this);
			});

			$paymentMethods.empty();

			customOrder.forEach(function(value) {
				if (optionsMap[value]) {
					$paymentMethods.append(optionsMap[value]);
					delete optionsMap[value];
				}
			});

			for (const key in optionsMap) {
				if (Object.prototype.hasOwnProperty.call(optionsMap, key)) {
					$paymentMethods.append(optionsMap[key]);
				}
			}
		}

		// Re-leer el valor cuando Select2 se enganche (puede tardar).
		function refreshCustomOrder() {
			const current = $paymentMethods.val() || [];
			if (current.length) {
				customOrder = current.slice();
			}
		}

		// Sincronizar orden cuando cambia la selección, sin importar
		// si el evento es de Select2 o del select nativo.
		$paymentMethods.on('change', function() {
			refreshCustomOrder();
		});

		$paymentMethods.on('select2:select', function(e) {
			const selectedValue = e.params.data.id;
			if (customOrder.indexOf(selectedValue) === -1) {
				customOrder.push(selectedValue);
			}
			$paymentMethods.val(customOrder);
			reorderOptions();
			$paymentMethods.trigger('change.select2');
		});

		$paymentMethods.on('select2:unselect', function(e) {
			const unselectedValue = e.params.data.id;
			const index = customOrder.indexOf(unselectedValue);
			if (index > -1) {
				customOrder.splice(index, 1);
			}
			$paymentMethods.val(customOrder);
			reorderOptions();
			$paymentMethods.trigger('change.select2');
		});

		// Al enviar el formulario, solo forzar el orden si efectivamente
		// hay un orden personalizado válido. Si la lista vino vacía y el
		// usuario eligió opciones por Select2, ya están reflejadas. Si
		// Select2 no se aplicó nunca, no hacemos nada (el select nativo
		// se serializa por sí solo).
		$paymentMethods.closest('form').on('submit', function() {
			refreshCustomOrder();
			if (customOrder.length && isSelect2Active($paymentMethods)) {
				$paymentMethods.val(customOrder);
			}
		});

		// Reordenar solo si efectivamente hay un orden guardado.
		if (customOrder.length > 0) {
			reorderOptions();
		}
	}

	// Botón amigable para copiar la URL del webhook.
	const $webhookUrl = $('#woocommerce_arnipay_woo_aw_webhook_url');

	if ($webhookUrl.length && !$webhookUrl.parent().hasClass('arnipay-copy-wrap')) {
		$webhookUrl.wrap('<div class="arnipay-copy-wrap"></div>');
		const $copyBtn = $('<button type="button" class="button arnipay-copy-btn">Copiar URL</button>');
		const $feedback = $('<span class="arnipay-copy-feedback" aria-live="polite"></span>');

		$webhookUrl.after($copyBtn).after($feedback);

		$copyBtn.on('click', function() {
			const value = $webhookUrl.val() || '';

			function showCopied() {
				$feedback.text('Copiada');
				setTimeout(function() {
					$feedback.text('');
				}, 2200);
			}

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(value).then(showCopied).catch(function() {
					$webhookUrl.trigger('select');
					document.execCommand('copy');
					showCopied();
				});
			} else {
				$webhookUrl.trigger('select');
				document.execCommand('copy');
				showCopied();
			}
		});
	}

	/**
	 * Botones de verificación de configuración.
	 *
	 * Envían por AJAX los valores actuales del formulario para que el
	 * comercio pueda verificar antes de guardar.
	 */
	const ajaxUrl = (window.ajaxurl) ? window.ajaxurl : '/wp-admin/admin-ajax.php';

	function fieldValue(id) {
		const $el = $('#' + id);
		return $el.length ? $.trim($el.val() || '') : '';
	}

	$('.arnipay-verify-btn').on('click', function() {
		const $btn = $(this);
		const action = $btn.data('action');
		const nonce = $btn.data('nonce');
		const $result = $btn.siblings('.arnipay-verify-result');

		const data = {
			action: action,
			nonce: nonce,
			client_id: fieldValue('woocommerce_arnipay_woo_aw_client_id'),
			client_secret: fieldValue('woocommerce_arnipay_woo_aw_client_secret'),
			webhook_secret: fieldValue('woocommerce_arnipay_woo_aw_webhook_secret')
		};

		$btn.prop('disabled', true);
		$result
			.removeClass('arnipay-verify-ok arnipay-verify-fail')
			.addClass('arnipay-verify-loading')
			.text('Verificando...');

		$.post(ajaxUrl, data)
			.done(function(response) {
				if (response && response.success) {
					let msg = (response.data && response.data.message) ? response.data.message : 'Verificación correcta.';
					if (response.data && response.data.notice) {
						msg += ' (' + response.data.notice + ')';
					}
					$result
						.removeClass('arnipay-verify-loading arnipay-verify-fail')
						.addClass('arnipay-verify-ok')
						.text('\u2714 ' + msg);
				} else {
					const msg = (response && response.data && response.data.message)
						? response.data.message
						: 'La verificación falló.';
					$result
						.removeClass('arnipay-verify-loading arnipay-verify-ok')
						.addClass('arnipay-verify-fail')
						.text('\u2716 ' + msg);
				}
			})
			.fail(function() {
				$result
					.removeClass('arnipay-verify-loading arnipay-verify-ok')
					.addClass('arnipay-verify-fail')
					.text('\u2716 No se pudo completar la solicitud. Intenta nuevamente.');
			})
			.always(function() {
				$btn.prop('disabled', false);
			});
	});
});
