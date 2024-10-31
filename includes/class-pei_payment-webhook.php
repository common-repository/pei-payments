<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PEI_Payment_Webhook_Handler.
 *
 * Handles webhooks from Pei to always notify the charges.
 *
 * @since 1.0.0
 */
class PEI_Payment_Webhook_Handler extends WC_PEI_Payment_Gateway {
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	public function __construct() {

		add_action( 'woocommerce_api_pei_validate_order', array( $this, 'pei_payment_check_for_webhook' ) );
	}

	/**
	 * Check incoming requests for pei Webhook data and process them.
	 *
	 * @since 1.0.0
	 * @version 1.0.1
	 */
	public function pei_payment_check_for_webhook() {
		$wcppg = new WC_PEI_Payment_Gateway();
		
		$response = array(
			'code'    => 200,
			'message' => __('Recibido', 'pei-payment-gateway'),
		);		

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && ( 'POST' !== $_SERVER['REQUEST_METHOD'] )
			|| ! isset( $_GET['wc-api'] )
			|| ( 'pei_validate_order' !== $_GET['wc-api'] )
		) {
			return array(
				'code'    => 401,
				'message' => __('No autorizado', 'pei-payment-gateway'),
			);
		}

		$request_body    = file_get_contents( 'php://input' );
		$request_headers = array_change_key_case( $this->get_request_headers(), CASE_UPPER );

		$notification = json_decode( $request_body );
		$response['notification'] = $notification;
		$wcppg->pei_log('pei_payment_webhook: ' . json_encode($notification));
		$this->pei_payment_process_webhook_notification( $notification );

		echo wp_send_json($response);
		status_header( 200 );
		exit;
	}

	/**
	 * Gets the incoming request headers. Some servers are not using
	 * Apache and "getallheaders()" will not work so we may need to
	 * build our own headers.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	public function get_request_headers() {
		if ( ! function_exists( 'getallheaders' ) ) {
			$headers = array();

			foreach ( $_SERVER as $name => $value ) {
				if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
					$headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
				}
			}

			return $headers;
		} else {
			return getallheaders();
		}
	}

	/**
	 * Process checkout response from Pei
	 * and save it to DB
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param object $notification Pei response.
	 */
	public function pei_payment_process_webhook_notification( $notification ) {
		$wcppg = new WC_PEI_Payment_Gateway();
		$extra_data = $notification->customArray;
		$data_to_db = array();
		$order_id = 0;


		foreach ($extra_data as $key => $data) {
			if ( $data->key == 'orderId' ) {
				$order_id = (int) $data->value;
			}
		}

		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			$data_to_db = array(
				'user_id'		=> $order->get_user_id(),
				'amount'		=> (double) $order->get_total(),
				'order_id'		=> $order_id,
				'authcode'		=> isset( $notification->id ) ? $notification->id : '',
				'transactionid'	=> isset( $notification->referenceBank ) ? $notification->referenceBank : '',
				'error'			=> isset( $notification->error ) ? $notification->error : '',
				'status'		=> isset( $notification->status ) ? (int) $notification->status : 0,
			);

			$wcppg->pei_payment_save_to_db($data_to_db);
			$wcppg->pei_log('data_to_db_from_webhook: ' . json_encode($data_to_db));

			if ( $notification->status ) {
				$order_note = array(
					sprintf( '<strong>%s</strong>', __('Su pago ha sido aceptado y su orden se encuentra en proceso.', 'pei-payment-gateway') ),
					sprintf( '<strong>%s:</strong> %s', __('ID', 'pei-payment-gateway'), $notification->id ),
					sprintf( '<strong>%s:</strong> %s', __('Referencia del banco', 'pei-payment-gateway'), $notification->referenceBank )
				);
				$order->payment_complete( $notification->referenceBank );
				$order->add_order_note( implode( '<br>', $order_note ) );
			} else {
				$order->update_status( 'failed', __('Su pago fue rechazado y su orden ha fallado.', 'pei-payment-gateway') );
			}
		}

		// {"status":true,"customArray":[{"key":"orderId","value":"85"}],"id":"QkBPhJpQCp9Tmx1nqZ4z","referenceBank":"TFT0000000000000000083744","commerceId":42248,"_AsyncConfig":null}
	}

	/**
	 * Get webhook info
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function pei_payment_get_webhook_info() {
		$webhook_url = add_query_arg(
			array(
				'wc-api' => 'pei_validate_order',
			),
			trailingslashit( get_home_url() )
		);

		return array(
			'url'		=> $webhook_url,
			'message'	=> sprintf(esc_html__('El siguiente URL de webhook %1$s debes colocarlo en tu cuenta de pei Business en la sección de URL de notificaciones. Si tienes alguna duda puedes escribirnos a %2$s .', 'pei-payment-gateway'), '<strong style="background-color:#ddd;">' . esc_url( $webhook_url ) . '</strong>', '<a href="mailto:soportebusiness@go-pei.com" target="_blank">Soporte de Pei</a>')
		);
	}
}

new PEI_Payment_Webhook_Handler();
