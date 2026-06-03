<?php
/**
 * arnipay Gateway Additional Settings
 *
 * @package Arnipay_Woo
 */

$webhook_url = method_exists( $this, 'get_webhook_url' ) ? $this->get_webhook_url() : trailingslashit( get_bloginfo( 'url' ) ) . 'wc-api/' . strtolower( get_class( $this ) );

return array(
	'payments_section' => array(
		'title'       => __( 'Medios de pago', 'arnipay-woo' ),
		'type'        => 'arnipay_section_title',
		'description' => __( 'Elegí los métodos que querés ofrecer dentro de arnipay. Si no seleccionás ninguno, se mostrarán todos los medios disponibles.', 'arnipay-woo' ),
	),
	'payment_methods'  => array(
		'title'       => __( 'Métodos habilitados', 'arnipay-woo' ),
		'type'        => 'multiselect',
		'description' => __( 'El cliente verá estos métodos dentro del bloque de arnipay y deberá elegir uno antes de pagar.', 'arnipay-woo' ),
		'default'     => array(),
		'options'     => array(
			'personal' => 'Personal Pay',
			'qr'       => 'QR interoperable',
			'tigo'     => 'tigo money',
		),
		'desc_tip'    => false,
		'class'       => 'wc-enhanced-select arnipay-enhanced-select',
	),
	'webhook_section' => array(
		'title'       => __( 'Webhook de confirmación', 'arnipay-woo' ),
		'type'        => 'arnipay_section_title',
		'description' => __( 'Configurá esta URL en tu panel de arnipay para que WooCommerce reciba confirmaciones automáticas de pago.', 'arnipay-woo' ),
	),
	'webhook_url'     => array(
		'title'             => __( 'URL de notificación', 'arnipay-woo' ),
		'type'              => 'text',
		'description'       => __( 'Copiá esta URL y pegala en la sección de webhooks de tu panel de arnipay.', 'arnipay-woo' ),
		'default'           => $webhook_url,
		'desc_tip'          => false,
		'class'             => 'arnipay-readonly-url',
		'custom_attributes' => array(
			'readonly' => 'readonly',
		),
	),
	'webhook_secret'  => array(
		'title'       => __( 'Webhook Secret', 'arnipay-woo' ),
		'type'        => 'password',
		'description' => __( 'Clave usada para validar que las notificaciones recibidas fueron firmadas por arnipay.', 'arnipay-woo' ),
		'default'     => '',
		'desc_tip'    => false,
	),
);
