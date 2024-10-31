<?php
if (! defined('ABSPATH')) exit;

/**
 * Class Payment init
 * 
 * @since 1.0.0
 * @version 2.0.0
 */
class WC_PEI_Payment_Gateway extends WC_Payment_Gateway {
	public function __construct() {
		$this->logger = new WC_Logger();
		$this->id = PEI_PAYMENT_ID;
		$this->method_title = __('Pagos con Pei', 'pei-payment-gateway');
		$this->method_description = sprintf('%s Versión actual: %s', __('Configuraciones para Pagos con pei.', 'pei-payment-gateway'), PEI_PAYMENT_VERSION);

		// Bool. Can be set to true if you want payment fields to show on the checkout if doing a direct integration, which we are doing in this case
		$this->has_fields = true;

		// This defines your settings which are loaded with init_settings()
		$this->init_form_fields();
		$this->init_settings();

		// Turn these settings into variables
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		$this->is_sandbox = ($this->environment == 'sand') ? true : false;

		// Save settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		
		// Actions
		add_action( 'wp_head', array($this, 'pei_payment_styles') );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'pei_payment_wc_get_order_item_totals' ), 10, 3 );
		// add_filter( 'woocommerce_order_is_pending_statuses', array( $this, 'pei_payment_wc_order_is_pending_statuses' ) );

		// Add message to Thankyou page
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'pei_payment_wc_thankyou_order_received_text' ), 10, 2 );
	}

	public function init_form_fields() {
		$this->form_fields = require(PEI_PAYMENT_PLUGIN_PATH . '/includes/admin/pei_payment-settings.php');
	}

	/**
	 * Generate payment fields
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wptexturize( $this->description ) );
		}
	}

	/**
	 * Process payment from the Checkout
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order($order_id);
		$status = 'pending';


		/** Get Pei URL **/
		$formatted_name = ( !empty( trim( $order->get_formatted_shipping_full_name() ) ) ) ? $order->get_formatted_shipping_full_name() : $order->get_formatted_billing_full_name();
		$params = array(
			'description' => apply_filters('pei_payment_order_description', sprintf(__('Compra de %s en %s', 'pei-payment-gateway'), trim($formatted_name), get_bloginfo('name')), $order),
			'amount' => (int) $order->get_total(),
			'callback' => $order->get_checkout_order_received_url()
		);
		$response = PEI_CONN()->pc_get_url($order->get_id(), $params);

		if ( $response['response'] ) {
			$order->add_order_note( sprintf(__('Checkout iniciado: %s', 'pei-payment-gateway'), $this->method_title) );
			wc_reduce_stock_levels($order->get_id());
			// Remove cart
			WC()->cart->empty_cart();

			$response = array(
				'result'	=> 'success',
				'redirect'	=> esc_url($response['pei_url'])
			);
		} else {
			$this->pei_log('Response Error: ' . json_encode($response));

			if ( isset($response['message']) ) {
				$order->add_order_note( $response['message'], 1 );
				wc_add_notice( $response['message'], 'error' );
			}

			if ( isset($response['response_checkout']) ) {
				$this->pei_log('response_checkout: ' . $response['response_checkout']);
				$order->add_order_note( $response['response_checkout'] );
			}
			
			$response = array(
				'result' 	=> 'failure',
			);
		}

		// Set order status
		$order->update_status( $status );

		return $response;
	}

	/**
	 * Styles on Checkout order received
	 */
	public function pei_payment_styles() {
		if ( is_order_received_page() ) {
			?>
			<style type="text/css">
				/** Thankyou page **/
				.pei-message-wrapper {
					display: block;
					padding: 15px;
				}

				.pei-message-wrapper p {
					margin: 0;
					padding: 0;
				}

				.pei-pending {
					background: #fff3cd;
					border: 2px solid #ffeeba;
					color: #856404;
				}

				.pei-processing {
					background-color: #d4edda;
					border: 2px solid #c3e6cb;
					color: #155724;
				}
			</style>
			<?php
		}
	}

	/**
	 * Show transaction number to order items totals
	 * Validate get_payment_method to only shows when it is requiered
	 */
	public function pei_payment_wc_get_order_item_totals( $total_rows, $order, $tax_display ) {
		$transaction_id = $order->get_transaction_id();

		if ( $order->get_payment_method() != $this->id )
			return $total_rows;

		if ( !empty($transaction_id) ) {
			foreach ($total_rows as $key => $row) {
				$new_rows[$key] = $row;
				if ($key == 'payment_method') {
					$new_rows[$this->id] = array(
						'label' => apply_filters('pei_payment_transaction_id_label', __('Transacción:', 'pei-payment-gateway')),
						'value' => $transaction_id
					);
				}
			}
			$total_rows = $new_rows;
		}

		return $total_rows;
	}

	/**
	 * Thankyou page response
	 */
	public function pei_payment_wc_thankyou_order_received_text( $text, $order ) {
		if ( $order->get_payment_method() != $this->id )
			return $text;

		if ( $order->has_status( 'pending' ) ) {
			ob_start();
			?>
			<span class="pei-message-wrapper pei-pending"><?= __('La respuesta del pago de su orden se mantiene pendiente. Le notificaremos cuando el proceso sea completado.', 'pei-payment-gateway') ?></span>
			<?php
			$text = ob_get_clean();
		}

		if ( $order->has_status( 'processing' ) ) {
			ob_start();
			?>
			<span class="pei-message-wrapper pei-processing"><?= __('Su pago usando Pei ha sido recibido. Gracias.', 'pei-payment-gateway') ?></span>
			<?php
			$text = ob_get_clean();
		}

		return $text;
	}

	/**
	 * Save array of transaction to DB
	 */
	public function pei_payment_save_to_db( $data = array() ) {
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
				'order_id' => isset($data['order_id']) ? $data['order_id'] : '',
				'authcode' => isset($data['authcode']) ? $data['authcode'] : '',
				'transactionid' => isset($data['transactionid']) ? $data['transactionid'] : '',
				'error' => isset($data['error']) ? $data['error'] : '',
				'transaction_date' => date_i18n('Y-m-d H:i:s'),
				'status' => isset($data['status']) ? $data['status'] : '',
				'payment_method' => 'pei_payment',
			);
			$inserted_id = $wpdb->insert($table_transactions, $data);

			if (!$inserted_id) {
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
			$this->pei_log($error_message);
		}

		return $return;
	}

	/**
	 * Logger
	 */
	public function pei_log( $message ) {
		$this->logger->add( $this->id, $message . "\n" );
	}
}


/**
 * WC Stock management
 * Override the default WC manage stock options
 */
function pei_payment_override_wc_stock_management() {
	$wcppg = new WC_PEI_Payment_Gateway();
	if ( isset( $wcppg->pei_hold_order ) && $wcppg->pei_hold_order > 0 ) {
		update_option( 'woocommerce_hold_stock_minutes', $wcppg->pei_hold_order );
		update_option( 'woocommerce_manage_stock', 'yes' );
	}
}
add_action( 'init', 'pei_payment_override_wc_stock_management', 99 );
