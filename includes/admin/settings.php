<?php
/**
 * arnipay Gateway Settings Configuration
 *
 * @package Arnipay_Woo
 */

return array(
	'admin_header'      => array(
		'title'       => __( 'Panel de configuración de arnipay', 'arnipay-woo' ),
		'type'        => 'arnipay_admin_header',
		'description' => __( 'Conectá tu tienda WooCommerce con arnipay para cobrar en Paraguay mediante QR, tigo money, Personal Pay y otros medios habilitados en tu cuenta.', 'arnipay-woo' ),
	),
	'enabled'           => array(
		'title'       => __( 'Estado del método', 'arnipay-woo' ),
		'type'        => 'checkbox',
		'label'       => __( 'Activar arnipay en el checkout', 'arnipay-woo' ),
		'description' => __( 'Cuando está activo, tus clientes podrán elegir arnipay como pasarela de pago.', 'arnipay-woo' ),
		'default'     => 'no',
	),
	'checkout_section'  => array(
		'title'       => __( 'Experiencia en checkout', 'arnipay-woo' ),
		'type'        => 'arnipay_section_title',
		'description' => __( 'Definí cómo se mostrará arnipay al cliente durante el proceso de compra.', 'arnipay-woo' ),
	),
	'title'             => array(
		'title'       => __( 'Nombre visible', 'arnipay-woo' ),
		'type'        => 'text',
		'description' => __( 'Título que verá el cliente al seleccionar la pasarela de pago.', 'arnipay-woo' ),
		'default'     => __( 'arnipay', 'arnipay-woo' ),
		'desc_tip'    => true,
	),
	'description'       => array(
		'title'       => __( 'Descripción para el cliente', 'arnipay-woo' ),
		'type'        => 'textarea',
		'description' => __( 'Mensaje breve que se mostrará debajo del método de pago.', 'arnipay-woo' ),
		'default'     => __( 'Seleccioná arnipay y elegí cómo querés pagar: Personal Pay, QR o tigo money.', 'arnipay-woo' ),
		'desc_tip'    => true,
	),
	'credentials_title' => array(
		'title'       => __( 'Credenciales de acceso', 'arnipay-woo' ),
		'type'        => 'arnipay_section_title',
		'description' => __( 'Ingresá las credenciales de producción disponibles en tu panel de arnipay.', 'arnipay-woo' ),
	),
	'client_id'         => array(
		'title'       => __( 'Client ID', 'arnipay-woo' ),
		'type'        => 'text',
		'description' => __( 'Identificador público de tu comercio en arnipay.', 'arnipay-woo' ),
		'default'     => '',
		'desc_tip'    => false,
	),
	'client_secret'     => array(
		'title'       => __( 'Client Secret', 'arnipay-woo' ),
		'type'        => 'password',
		'description' => __( 'Clave secreta privada de tu comercio. No la compartas ni la pegues en tickets públicos.', 'arnipay-woo' ),
		'default'     => '',
		'desc_tip'    => false,
	),
	'advanced_title'    => array(
		'title'       => __( 'Diagnóstico', 'arnipay-woo' ),
		'type'        => 'arnipay_section_title',
		'description' => __( 'Opciones útiles para soporte técnico y resolución de incidencias.', 'arnipay-woo' ),
	),
	'debug'             => array(
		'title'       => __( 'Registro de depuración', 'arnipay-woo' ),
		'type'        => 'checkbox',
		'label'       => __( 'Guardar logs técnicos de arnipay', 'arnipay-woo' ),
		'default'     => 'no',
		/* translators: %s: path to WooCommerce logs */
		'description' => sprintf( __( 'Activar solo para diagnóstico. Los registros se revisan desde %s.', 'arnipay-woo' ), '<code>WooCommerce > Estado > Registros</code>' ),
	),
);
