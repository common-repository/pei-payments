<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$wcppw = new PEI_Payment_Webhook_Handler();
$the_webhook = $wcppw->pei_payment_get_webhook_info();
$production = __('Producción', 'pei-payment-gateway');
$sandbox = __('Sandbox', 'pei-payment-gateway');

$form_fields = array(
	'enabled' => array(
		'title'   => __( 'Habilitar / Deshabilitar', 'pei-payment-gateway' ),
		'type'    => 'checkbox',
		'label'   => __( 'Habilitar pasarela de pagos', 'pei-payment-gateway' ),
		'default' => 'no'
	),
	'title'			=> array(
		'title'		=> __( 'Título', 'pei-payment-gateway' ),
		'type'		=> 'text',
		'desc_tip'	=> __( 'Título de la pasarela a mostrar durando el proceso de finalización de pago.', 'pei-payment-gateway' ),
		'default'	=> __( 'Pagar con pei', 'pei-payment-gateway' ),
	),
	'description'	=> array(
		'title'		=> __( 'Descripción', 'pei-payment-gateway' ),
		'type'		=> 'textarea',
		'desc_tip'	=> __( 'Descripción del método de pago a mostrar durante el proceso de finalización de pago.', 'pei-payment-gateway' ),
		'default'	=> __('Usando sus cuentas IBAN registradas en pei.', 'pei-payment-gateway' ),
	),
	'webhook'			=> array(
		'title'			=> __( 'URL del Webhook', 'pei-payment-gateway' ),
		'type'			=> 'title',
		'description'	=> $the_webhook['message'],
	),
	'api_details'		=> array(
		'title'			=> __( 'Credenciales del API', 'pei-payment-gateway' ),
		'type'			=> 'title',
		'description'	=> __( 'Ingrese las credenciales del API de pei para procesar los pagos.', 'pei-payment-gateway' ),
	),
	'environment'		=> array(
		'title'			=> __( 'Ambiente', 'pei-payment-gateway' ),
		'type'			=> 'select',
		'default'		=> 'sandbox',
		'options'		=> array(
			'sand'		=> $sandbox,
			'prod'		=> $production
		),
		'desc_tip'		=> __( 'Elija el ambiente a utilizar para procesar el pago.', 'pei-payment-gateway' ),
	),
	'api_url_prod'		=> array(
		'title'			=> sprintf(__('URL del API en (%s)', 'pei-payment-gateway'), $production),
		'type'			=> 'text',
		'desc_tip'		=> sprintf(__('URL para conectar con el API de pei en %s.', 'pei-payment-gateway'), $production),
		'default'		=> 'https://apibiz.go-pei.com/v1/'
	),
	'business_id_prod'	=> array(
		'title'			=> sprintf(__('BusinessId (%s)', 'pei-payment-gateway'), $production),
		'type'			=> 'text',
		'desc_tip'		=> sprintf(__('Ingrese el BusinessId para %s proporcionado por pei.', 'pei-payment-gateway'), $production)
	),
	'api_key_prod'		=> array(
		'title'			=> sprintf(__('API Key (%s)', 'pei-payment-gateway'), $production),
		'type'			=> 'text',
		'desc_tip'		=> sprintf(__('Ingrese el API Key para %s proporcionado por pei..', 'pei-payment-gateway'), $production)
	),
	'api_url_sand'		=> array(
		'title'			=> sprintf(__('URL del API en (%s)', 'pei-payment-gateway'), $sandbox),
		'type'			=> 'text',
		'desc_tip'		=> sprintf(__('URL para conectar con el API de pei en %s.', 'pei-payment-gateway'), $sandbox),
		'default'		=> 'https://apibiz.gentepei.dev/v1/'
	),
	'business_id_sand'	=> array(
		'title'			=> sprintf(__('BusinessId (%s)', 'pei-payment-gateway'), $sandbox),
		'type'			=> 'text',
		'desc_tip'		=> sprintf(__('Ingrese el BusinessId para %s proporcionado por pei.', 'pei-payment-gateway'), $sandbox)
	),
	'api_key_sand'		=> array(
		'title'			=> sprintf(__('API Key (%s)', 'pei-payment-gateway'), $sandbox),
		'type'			=> 'text',
		'desc_tip'		=> sprintf(__('Ingrese el API Key para %s proporcionado por pei..', 'pei-payment-gateway'), $sandbox)
	),
	'order_process'		=> array(
		'title'			=> __( 'Procesamiento de órdenes', 'pei-payment-gateway' ),
		'type'			=> 'title',
		'description'	=> __( 'Administre el estado de las órdenes basado en la respuesta de pei.', 'pei-payment-gateway' ),
	),
	'pei_hold_order'	=> array(
		'title'			=> __('Retener órdenes (minutos)', 'pei-payment-gateway'),
		'type'			=> 'number',
		'description'	=> __('Retener órdenes (para órdenes Pendiente de pago) durante x minutos. Cuando se alcance el límite, las órdenes pendientes pasarán a Canceladas. Esta configuración sobreescribe las opciones de manejo de stock de WooCommerce, dejar vacío para usar la configuración predeterminada de WooCommerce.', 'pei-payment-gateway'),
		'default'		=> 60
	)
);

return apply_filters('pei_payment_settings', $form_fields);
