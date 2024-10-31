<?php
/**
 * WS Connections
 */
class PEI_Connections {
	public function __construct() {
		$wcppg = new WC_PEI_Payment_Gateway();
		$this->pei_debug = ($wcppg->environment == 'sand') ? true : false;
		$this->pei_endpoint_url = $wcppg->settings['api_url_' . $wcppg->environment];
		$this->api_key = $wcppg->settings['api_key_' . $wcppg->environment];
		$this->business_id = $wcppg->settings['business_id_' . $wcppg->environment];
	}

	/**
	 * Get payment URL
	 * 
	 * @since 1.0.0
	 * @version 2.0.0
	 */
	public function pc_get_url( $order_id = 0, $params = array() ) {
		$status = 'ND1';
		$response = array(
			'response' => false
		);
		$args = array(
			'businessId' => (int) $this->business_id,
			'currency' => get_woocommerce_currency()
		);

		if ($order_id > 0) {
			$args['customData'][] = array(
				'key' => 'orderId',
				'value' => (int) $order_id
			);
			$args = wp_parse_args($params, $args);
		}

		$response_checkout = $this->pc_exec_func(array(
			'body' => json_encode($args)
		));

		$response_result = isset( $response_checkout['result'] ) ? $response_checkout['result'] : null;
		$response_error = isset( $response_checkout['error'] ) ? $response_checkout['error'] : null;

		if ( ! is_null( $response_result ) ) {
			$response = array(
				'response' => true,
				'pei_url' => $response_result['Url'],
				'expiration' => strtotime($response_result['Expiration']),
				'is_expired' => (strtotime($response_result['Expiration']) <= strtotime(date_i18n('Y-m-d h:i:s'))) ? true : false
			);
		} elseif ( ! is_null( $response_error ) ) {
			$response_error = $response_checkout['error'];
			$status = isset( $response_error['status'] ) ? $response_error['status'] : $response_checkout['response_code'];
			$message = ( isset( PEI_PROC()->responses[$status] ) ) ? PEI_PROC()->responses[$status] : PEI_PROC()->responses['ND1'] ;
			$response['message'] = sprintf('<strong>%s %s:</strong> %s', __('Error', 'pei-payment-gateway'), $status, esc_attr($message));
		} else {
			$response['response_checkout'] = json_encode( $response_checkout );
			$response['message'] = sprintf('<strong>%s %s:</strong> %s', __('Error', 'pei-payment-gateway'), $status, esc_attr( PEI_PROC()->responses[ $status ] ) );
		}

		return $response;
	}

	/**
	 * Authentication heades
	 */
	public function pc_get_authentication() {
		$user_agent = array(get_bloginfo('name'), PEI_PAYMENT_VERSION);
		return array(
			'Content-Type' => 'application/json',
			'X-Api-Key' => $this->api_key,
			'Cache-Control' => 'no-cache',
			'User-Agent' => implode('/', $user_agent)
		);
	}

	/**
	 * WS Pei Init
	 * $params: array - method/body/headers
	 * $function: string - Pei endpoint
	 */
	private function pc_exec_func($params = array(), $function = 'checkout') {
		$init_params = array(
			'method' => 'POST',
			'timeout' => 300,
			'sslverify' => false,
			'body' => array(),
			'headers' => $this->pc_get_authentication(),
		);
		$args = wp_parse_args($params, $init_params);
		$url = trailingslashit($this->pei_endpoint_url) . $function;

		if ($this->pei_debug) {
			$this->pc_log('Request: ' . $url . "\n" . json_encode(array(
				'args' => $args,
			)));
		}

		try {
			$remote_response = wp_safe_remote_post($url, $args);

			if ($this->pei_debug) {
				$this->pc_log('Response: ' . $url . "\n" . json_encode(array(
					'response' => $remote_response,
				)));
			}

			$response_code = wp_remote_retrieve_response_code($remote_response);
			$response = json_decode(wp_remote_retrieve_body($remote_response), true);

			if ($response_code != 200) {
				if ($this->pei_debug) {
					$this->pc_log('Response with error ' . $response_code . "\n" . ': ' . json_encode( $response ));
				}
				
				$response['response_code'] = $response_code;
			}
		} catch (Exception $e) {
			$response = array(
				'valid' => false,
				'response' => $e,
			);
		}

		return $response;
	}

	/**
	 * Log
	 */
	private function pc_log($log) {
		$wcppg = new WC_PEI_Payment_Gateway();
		$wcppg->pei_log($log);
	}
}

function pei_conn() {
	return new PEI_Connections();
}