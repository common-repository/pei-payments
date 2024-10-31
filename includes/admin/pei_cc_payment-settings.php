<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$labels = array(
	'prod' => __( 'Producción', 'pei-payment-gateway' ),
	'sand' => __( 'Sandbox', 'pei-payment-gateway' ),
	'business_id' => __( 'Código/BusinessId', 'pei-payment-gateway' ),
	'api_key' => __( 'API Key', 'pei-payment-gateway' )
);

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
		'default'	=> PEI_PAYMENT_PLUGIN_NAME,
	),
	'description'	=> array(
		'title'		=> __( 'Descripción', 'pei-payment-gateway' ),
		'type'		=> 'textarea',
		'desc_tip'	=> __( 'Descripción del método de pago a mostrar durante el proceso de finalización de pago.', 'pei-payment-gateway' ),
		'default'	=> __('Pague seguro usando su tarjeta Visa o Mastercard.', 'pei-payment-gateway' ),
	),
	'api_details'		=> array(
		'title'			=> __( 'Credenciales del API', 'pei-payment-gateway' ),
		'type'			=> 'title',
		'description'	=> __( 'Ingrese las credenciales del API de Pei para procesar los pagos.', 'pei-payment-gateway' ),
	),
	'environment'		=> array(
		'title'			=> __( 'Ambiente', 'pei-payment-gateway' ),
		'type'			=> 'select',
		'default'		=> 'sandbox',
		'options'		=> array(
			'sand'		=> $labels['sand'],
			'prod'		=> $labels['prod']
		),
		'desc_tip'		=> __( 'Elija el ambiente a utilizar para procesar el pago.', 'pei-payment-gateway' ),
	),
	'business_id'		=> array(
		'title'			=> $labels['business_id'],
		'type'			=> 'text',
		'desc_tip'		=> sprintf( __( 'Ingrese el %s proporcionado por Pei.', 'pei-payment-gateway' ), $labels['business_id'] )
	),
	'api_key'			=> array(
		'title'			=> $labels['api_key'],
		'type'			=> 'text',
		'desc_tip'		=> sprintf( __( 'Ingrese el %s proporcionado por Pei.', 'pei-payment-gateway' ), $labels['api_key'] )
	)
);

return apply_filters( 'pei_payment_settings', $form_fields );