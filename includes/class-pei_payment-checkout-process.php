<?php
/**
 * Pei Payment Gateway process class
 * 
 * @since 1.0.0
 * @version 2.0.0
 */
class PEI_Payment_Process {
	function __construct() {
		$this->responses = apply_filters( 'pei_payment_response_codes', array(
			'403'	=> __( 'Error en la autenticación.', 'pei-payment-gateway' ), // Missing Authentication Token
			'404'	=> __( 'No se encuentra el recurso solicitado.', 'pei-payment-gateway' ),
			'503'	=> __( 'El servicio no está disponible.', 'pei-payment-gateway' ),
			'5002'	=> __( 'El monto de la compra es inferior al monto mínimo permitido.', 'pei-payment-gateway' ), // 5000.00 Colones
			'5007'	=> __( 'Comercio no tiene una cuenta activa en la moneda elegida.', 'pei-payment-gateway' ), // Comercio no tiene una cta activa en esta moneda
			'ND1'	=> __( 'Error desconocido al intentar obtener la URL de pago de Pei.', 'pei-payment-gateway' ),
			'ND2'	=> __( 'Error desconocido al procesar el pago.', 'pei-payment-gateway' ),
			'ND3'	=> __( 'Error desconocido no identificado.', 'pei-payment-gateway' )
		) );

		$this->process_responses = apply_filters( 'pei_processing_response_codes', array(
			'000'	=> __( 'Pago exitoso', 'pei-payment-gateway' ),
			'010'	=> __( 'Autenticación exitosa', 'pei-payment-gateway' ),
			'011'	=> __( 'Error en la autenticación', 'pei-payment-gateway' ),
			'012'	=> __( 'Error al procesar el pago', 'pei-payment-gateway' ),
			'013'	=> __( 'Error en la autenticación', 'pei-payment-gateway' ),
			'101'	=> __( 'Error de tarjeta', 'pei-payment-gateway' ),
			'111'	=> __( 'Error de tarjeta', 'pei-payment-gateway' ),
			'121'	=> 'Card Error',
			'151'	=> __( 'Error al procesar el pago', 'pei-payment-gateway' ),
			'201'	=> 'Card Error',
			'251'	=> 'Payment Error',
			'500'	=> 'Not Finished',
			'501'	=> 'Error',
			'502'	=> 'Error',
			'503'	=> 'Error',
			'505'	=> 'Error checking',
			'550'	=> 'Pending Execution',
			'551'	=> 'Execution cancelled',
			'552'	=> 'Execution expired',
			'553'	=> 'Execution denied',
			'701'	=> 'Card Error',
			'702'	=> 'Card Error',
			'703'	=> 'Card Error',
			'731'	=> 'Blocked',
			'751'	=> 'Blocked',
			'752'	=> 'Blocked',
			'756'	=> 'Blocked',
			'757'	=> 'Blocked',
			'771'	=> 'Blocked',
			'776'	=> 'Blocked',
			'940'	=> 'COF Error',
			'950'	=> 'Error',
			'960'	=> 'Error',
			'970'	=> 'Error',
			'999'	=> 'Error'
		) );

		$this->success_codes = apply_filters( 'pei_response_success_codes', array( '000', '010' ) );

		$this->error_codes = apply_filters( 'pei_response_error_codes', array() );
	}
}

function pei_proc() {
	return new PEI_Payment_Process();
}