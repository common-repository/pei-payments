<?php
/**
 * CC Payment Gateway
 * 
 * @since 2.0.0
 */
class WC_PEI_CC_Payment_Gateway extends WC_Payment_Gateway {
	public function __construct()
	{
		$this->logger = new WC_Logger();
		$this->id = 'pei_cc_payment';
		$this->method_title = __( 'Pagos con tarjeta crédito/débito vía Multimoney', 'pei-payment-gateway' );
		$this->method_description = sprintf( '%s Versión actual: %s', __( 'Configuraciones para Pasarela de pagos Pei para aceptar pagos con tarjeta.', 'pei-payment-gateway' ), PEI_PAYMENT_VERSION );

		// Bool. Can be set to true if you want payment fields to show on the checkout if doing a direct integration, which we are doing in this case
		$this->has_fields = true;

		// This defines your settings which are loaded with init_settings()
		$this->init_form_fields();
		$this->init_settings();

		// Turn these settings into variables
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		$this->is_sandbox = ( $this->environment != 'prod' ) ? true : false;
		$this->accepted_cc = array( 'mastercard', 'visa' );

		// Save settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		
		// Actions
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'pei_cc_payment_wc_get_order_item_totals' ), 10, 3 );
	}

	public function init_form_fields()
	{
		$this->form_fields = require PEI_PAYMENT_PLUGIN_PATH . '/includes/admin/pei_cc_payment-settings.php';
	}

	/**
	 * Generate payment fields
	 */
	public function payment_fields()
	{
		if ( $this->description ) {
			echo wpautop( wptexturize( $this->description ) );
		}
		
		?>
		<input type="hidden" name="pei_iframe_height" value="0">
		<input type="hidden" name="pei_iframe_width" value="0">
		<script>
			jQuery( function( $ ) {
				$( 'input[name="pei_iframe_height"]' ).val( window.outerHeight )
				$( 'input[name="pei_iframe_width"]' ).val( window.outerWidth )
			} )
		</script>
		<?php
	}

	/**
	 * Process payment from the Checkout
	 * 
	 * @since 1.0.0
	 * @version 2.0.0
	 */
	public function process_payment( $order_id )
	{
		$order = wc_get_order($order_id);
		$status = 'pending';

		$iframe_height = isset( $_POST['pei_iframe_height'] ) ? $_POST['pei_iframe_height'] : 0;
		$iframe_width = isset( $_POST['pei_iframe_width'] ) ? $_POST['pei_iframe_width'] : 0;


		/** Get Pei URL **/
		$ppe = new PEI_CC_Payment_Endpoint();
		$formatted_name = ( !empty( trim( $order->get_formatted_shipping_full_name() ) ) ) ? $order->get_formatted_shipping_full_name() : $order->get_formatted_billing_full_name();
		$params = array(
			'description' => apply_filters( 'pei_payment_order_description', sprintf( __( 'Compra de %s en %s', 'pei-payment-gateway' ), trim( $formatted_name ), get_bloginfo( 'name' ) ), $order ),
			'amount' => $order->get_total(),
			'redirect' => $ppe->pei_cc_payment_response_url( sprintf( '%1$d/%2$s/', $order_id, $order->get_order_key() ) ),
			'user_email' => $order->get_billing_email(),
			'iframe_height' => $iframe_height,
			'iframe_width' => $iframe_width
		);
		$response = PEI_CC_CONN()->pc_get_url( $params );

		if ( $response['response'] ) {
			update_post_meta( $order_id, 'pei_process_url', $response['pei_url'] );
			update_post_meta( $order_id, 'merchant_operation', $response['merchant_op'] );
			update_post_meta( $order_id, 'token', $response['token'] );
			update_post_meta( $order_id, '_pei_cc_payment_form_generator_url', $ppe->pei_cc_payment_form_generator_url( $order ) );

			$order->add_order_note( sprintf( __( 'Checkout iniciado: %s', 'pei-payment-gateway' ), json_encode( array(
				'title' => $this->method_title,
				'merchant_operation' => $response['merchant_op'],
				'token' => $response['token']
			) ) ) );

			// Remove cart
			WC()->cart->empty_cart();

			$response = array(
				'result'	=> 'success',
				'redirect'	=> $ppe->pei_cc_payment_response_url( sprintf( '%1$d/%2$s/', $order_id, $order->get_order_key() ) )
			);
		} else {
			$response['business_id'] = $this->pei_cc_payment_formatted_business_id();
			$response['api_key'] = $this->pei_cc_payment_formatted_api_key();
			$this->pei_cc_log( 'response_error: ' . json_encode($response ) );

			if ( isset( $response['message'] ) ) {
				$order->add_order_note( $response['message'], 1 );
				wc_add_notice( $response['message'], 'error' );
			}
			
			$response = array(
				'result' => 'failure',
			);
		}

		// Set order status
		$order->update_status( $status );

		return $response;
	}

	/**
	 * Show transaction number to order items totals
	 * Validate get_payment_method to only shows when it is requiered
	 */
	public function pei_cc_payment_wc_get_order_item_totals( $total_rows, $order, $tax_display )
	{
		$transaction_id = $order->get_transaction_id();

		if ( $order->get_payment_method() != $this->id ) {
			return $total_rows;
		}

		if ( !empty( $transaction_id ) ) {
			foreach ( $total_rows as $key => $row ) {
				$new_rows[$key] = $row;

				if ( $key == 'payment_method' ) {
					$new_rows[$this->id] = array(
						'label' => apply_filters( 'pei_cc_payment_transaction_id_label', __( 'Transacción:', 'pei-payment-gateway' ) ),
						'value' => $transaction_id
					);
				}
			}
			$total_rows = $new_rows;
		}

		return $total_rows;
	}

	/**
	 * Save array of transaction to DB
	 */
	public function pei_cc_payment_save_to_db( $data = array() )
	{
		global $wpdb;
		$return = array(
			'response' => false
		);
		
		try {
			/* BEGIN Insert into database */
			$table_transactions = $wpdb->prefix . 'pei_transactions';
			$data = array(
				'user_id' => isset($data['user_id']) ? $data['user_id'] : get_current_user_id(),
				'amount' => isset($data['amount']) ? $data['amount'] : '',
				'authcode' => isset($data['authcode']) ? $data['authcode'] : '',
				'order_id' => isset($data['order_id']) ? $data['order_id'] : '',
				'transactionid' => isset($data['transactionid']) ? $data['transactionid'] : '',
				'error' => isset($data['error']) ? $data['error'] : '',
				'transaction_date' => date_i18n('Y-m-d H:i:s'),
				'status' => isset($data['status']) ? $data['status'] : '',
				'payment_method' => 'pei_cc_payment'
			);
			$inserted_id = $wpdb->insert( $table_transactions, $data );

			if ( ! $inserted_id ) {
				throw new Exception(__('Algo salió mal guardando la transacción en la tabla de control.', 'pei-payment-gateway'));
			} else {
				$return = array(
					'response' => true,
					'message' => __('Transacción guardada en la tabla de control.', 'pei-payment-gateway')
				);
			}
			$wpdb->flush();
			/* END Insert into database */
		} catch(Exception $e) {
			$error_message = $e->getMessage();
			$return['message'] = $error_message;
			$this->pei_cc_log( $error_message );
		}

		return $return;
	}

	/**
	 * Get formatted ID
	 * 
	 * @version 2.0.1
	 */
	public function pei_cc_payment_formatted_business_id()
	{
		$business_id = $this->business_id;
		
		if ( strlen( $business_id ) < 7 ) {
			return  'CR' . str_repeat( "0", 8 - strlen( $business_id ) ) . $business_id;
		}

		return $business_id;
	}

	/**
	 * Get formatted API Key
	 * 
	 * @version 2.0.1
	 * 
	 * @return string	API Key as hexadecimal
	 */
	public function pei_cc_payment_formatted_api_key()
	{
		return bin2hex( base64_decode( $this->api_key) );
	}

	/**
	 * Logger
	 */
	public function pei_cc_log( $message )
	{
		$this->logger->add( $this->id, $message . "\n" );
	}
}