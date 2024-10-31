<?php
/**
 * WS Connections
 */
class PEI_CC_Payment_Connections {
	public function __construct() {
		$wcppg = new WC_PEI_CC_Payment_Gateway();
		$this->pei_debug = true; // $wcppg->is_sandbox;
		$this->api_key = $wcppg->pei_cc_payment_formatted_api_key(); // $wcppg->settings['api_key'];
		$this->business_id = $wcppg->pei_cc_payment_formatted_business_id(); // ->settings['business_id'];
		
		$this->acct_type = '03'; // "01" es no aplicable, "02" es crédito y "03" es débito
		$this->api_urls = apply_filters( 'pei_cc_payment_api_urls', array(
			'sand' => 'https://psp-sandbox.multimoney.com',
			'prod' => 'https://psp.multimoney.com', 
		) );
		$this->pei_endpoint_url = $this->api_urls[ $wcppg->environment ];
	}

	public function pc_get_url( $params = array() ) {
		$response = array(
			'response' => false
		);
		$client = '';

		$args = array(
			'MERCHANT' => $this->business_id,
			'AMOUNT' => (string) ( $params['amount'] * 100 ), // entero, sin decimales se agregan 00
			'CURRENCY' =>  $this->pc_get_currency(),
			'REDIRECT_URL' => $params['redirect'],
			'CONCEPT' => $params['description'],
			"PARAMS" => array(
				"SECURE_TYPE" => "SECURE",
				"AUTO_REDIRECT" => true
			),
			'AUTHENTICATION_PARAMS' => $this->pc_get_authentication( $params )
		);

		if ( is_user_logged_in() ) {
			$client = $params['user_email'];
			$args['CLIENT'] = $client;
		}
		
		// Get signature - $MERCHANT.$CLIENT.$AMOUNT.$CURRENCY.$REDIRECT_URL
		$payload = $args['MERCHANT'] . $client . $args['AMOUNT'] . $args['CURRENCY'] . $args['REDIRECT_URL'];
		$args['SIGNATURE'] = $this->pc_get_signature( $payload );
		$this->pc_log( 'pei_request: ' . json_encode( $args ) );

		$pei_response = $this->pc_exec_func( array(
			'body' => json_encode( $args )
		) );

		if ( isset( $pei_response['valid'] ) ) {
			if ( $pei_response['valid'] ) {
				$response = array(
					'response' => true,
					'pei_url' => isset( $pei_response['URL'] ) ? esc_url( $pei_response['URL'] ) : '',
					'merchant_op' => isset( $pei_response['MERCHANT_OPERATION'] ) ? esc_attr( $pei_response['MERCHANT_OPERATION'] ) : '',
					'token' => isset( $pei_response['TOKEN'] ) ? esc_attr( $pei_response['TOKEN'] ) : ''
				);
			} else {
				$error_code = $pei_response['response_code'];
				// CODE, DESCRIPTION, DEBUG_ID
				$status = isset( $pei_response['CODE'] ) ? $pei_response['CODE'] : $error_code;
				$message = isset( PEI_PROC()->responses[ $status ] ) ? PEI_PROC()->responses[ $status ] : PEI_PROC()->responses[ 'ND3' ] ;
				$response['message'] = sprintf( '<strong>%s %d:</strong> %s', __( 'Error', 'pei-payment-gateway' ), $status, esc_attr( $message ) );
			}
		} else {
			$response['message'] = sprintf( '<strong>%s:</strong> %s', __( 'Error', 'pei-payment-gateway' ), esc_attr( PEI_PROC()->responses['ND3'] ) );
		}

		return $response;
	}

	/**
	 * Authentication headers
	 * 
	 * @since 1.0.0
	 * @version 2.0.0
	 * 
	 * @return array
	 */
	public function pc_get_authentication( $params = array() ) {
		$language_info = explode( '-', get_bloginfo( 'language' ) );
		
		$auth_params = array(
			'ACCT_TYPE' => $this->acct_type,
			'BROWSER_ACCEPT_HEADER' => "application/json",
			'BROWSER_IP' => $this->pc_get_user_ip(),
			'BROWSER_LANGUAGE' => $language_info[0],
			'BROWSER_SCREEN_HEIGHT' => $params['iframe_height'],
			'BROWSER_SCREEN_WIDTH' => $params['iframe_width'],
			'BROWSER_TZ' => "1",
			'BROWSER_USER_AGENT' => wc_get_user_agent()
		);

		return $auth_params;
	}

	/**
	 * Get user IP based on WC_Geolocation class
	 * 
	 * @version 2.0.0
	 * 
	 * @return string IP
	 */
	private function pc_get_user_ip() {
		$wc_ip = WC_Geolocation::get_ip_address();
	
		return !empty( $wc_ip ) ? $wc_ip : '127.0.0.1';
	}

	/**
	 * WS Pei Init
	 * 
	 * @since 1.0.0
	 * @version 2.0.0
	 * 
	 * @param array $params		Method/body/headers
	 * @param string $function	String - Pei endpoint
	 */
	private function pc_exec_func($params = array(), $function = '/client/brw/token/request') {
		$init_params = array(
			'method' => 'POST',
			'timeout' => 300,
			'sslverify' => false,
			'body' => array(),
			'headers' => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json'
			)
		);
		$args = wp_parse_args( $params, $init_params );
		$url = trailingslashit( $this->pei_endpoint_url ) . $function;

		if ( $this->pei_debug ) {
			$this->pc_log( 'pei_body_request: ' . $url . "\n" . $args['body'] );
		}

		try {
			$remote_response = wp_safe_remote_request( $url, $args );
			$response_code = wp_remote_retrieve_response_code( $remote_response );
			$response = json_decode( wp_remote_retrieve_body( $remote_response ), true );

			if ( $this->pei_debug ) {
				$this->pc_log( 'pei_raw_response: ' . $url . "\n" . json_encode( array( 'response' => $remote_response ) ) );
			}
			
			if ( $response_code != 200 ) {
				if ( $this->pei_debug ) {
					$this->pc_log( 'pei_response_with_error ' . $response_code . "\n" . ': ' . json_encode( $response ) );
				}
				
				$response['valid'] = false;
				$response['response_code'] = $response_code;
			} else {
				$response['valid'] = true;
			}
		} catch ( Exception $e ) {
			$response = array(
				'valid' => false,
				'response_code' => 'ND1',
				'response' => $e,
			);
		}

		return $response;
	}

	/**
	 * Get currency code
	 * $this->currency_code = 840; // 840 = Dolares 188 = Colones
	 * 
	 * @return string	Currency code valid for Pei
	 */
	private function pc_get_currency() {
		$currencies = array(
			'usd' => '840',
			'crc' => '188'
		);
		$currency_code = strtolower( get_woocommerce_currency() );

		return isset( $currencies[ $currency_code ] ) ? $currencies[ $currency_code ] : 0;
	}

	/**
	 * Get Signature
	 * 
	 * @param string $payload
	 * 
	 * @version 2.0.0
	 */
	private function pc_get_signature( $payload ) {
		return hash_hmac( 'sha256', $payload,  hex2bin( $this->api_key ) );
	}

	/**
	 * Log
	 */
	private function pc_log($log) {
		$wcppg = new WC_PEI_Payment_Gateway();
		$wcppg->pei_log($log);
	}
}

function pei_cc_conn() {
	return new PEI_CC_Payment_Connections();
}