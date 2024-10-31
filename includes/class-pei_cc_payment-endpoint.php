<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PEI_CC_Payment_Endpoint.
 *
 * Handles the payment response
 *
 * @since 2.0.0
 */
class PEI_CC_Payment_Endpoint extends WC_PEI_Payment_Gateway {
	public $endpoint_slug;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->endpoint_slug = 'pei-payment-response';

		add_action( 'init', array( $this, 'pei_cc_payment_register_endpoint' ) );
		
		add_filter( 'the_content', array( $this, 'pei_the_content' ) );
	}

	/**
	 * Register endpoint & request payment form
	 * 
	 * TODO: Validate endpoint process response for guess users
	 */
	public function pei_cc_payment_register_endpoint() {
		global $wp;

		add_rewrite_endpoint( $this->endpoint_slug, EP_PAGES );
		flush_rewrite_rules();

		// Generate payment form
		if ( isset( $_GET['get_payment_form'] ) ) {
			$order_id = $_GET['get_payment_form'];
			$order = wc_get_order( $order_id );

			if ( isset( $_GET['order_key'] ) && $order->key_is_valid( $_GET['order_key'] ) ) {
				$pei_process_url = get_post_meta( $order_id, 'pei_process_url', true );
				$token = get_post_meta( $order_id, 'token', true );
		
				if ( $pei_process_url && $token ) {
					?>
					<p style="text-align: center;"><strong><?= __( 'Cargando formulario de pago...', 'wpcx' ) ?></strong></p>
					<form name="pei_form" action="<?= $pei_process_url ?>" method="post">
						<input type="hidden" name="TOKEN" value="<?= $token ?>" />
					</form>
					<script type="text/javascript">
						setTimeout( () => {
							document.pei_form.submit();
						}, 1500 );
					</script>
					<?php
					exit;
				}
			} else {
				$order->add_order_note( __( 'No se ha verificado su orden para continuar con el pago.', 'wpcx' ), 1 );
				wp_redirect( $order->get_checkout_order_received_url() );
				exit;
			}
		}
	}

	/**
	 * Process the response into the_content filter
	 * 
	 * @version 2.0.0
	 */
	public function pei_the_content( $template ) {
		global $wp, $wp_query;
		$wcppg = new WC_PEI_CC_Payment_Gateway();

		if ( get_query_var( $this->endpoint_slug ) ) {
			$iframe = $redirect_url = '';
			$query_vars = explode( '/', $wp->query_vars['pei-payment-response'] );
			$order_id = absint( $wp->query_vars['pei-payment-response'] );
			$order = wc_get_order( $order_id );
			$order_key = isset( $query_vars[1] ) ? $query_vars[1] : '';
			
			$temp_transaction_id = WC()->session->get( 'temp_transaction_id', '' );
			$processed_data = array();

			$status_code = isset( $_GET['STATUS'] ) ? esc_attr( $_GET['STATUS'] ) : '';
			$transaction_id = isset( $_GET['MERCHANT_OPERATION'] ) ? esc_attr( $_GET['MERCHANT_OPERATION'] ) : '';

			if ( !empty( $status_code ) && !empty( $transaction_id ) ) {
				$merchant = isset( $_GET['MERCHANT'] ) ? esc_attr( $_GET['MERCHANT'] ) : '';
				$auth_code = isset( $_GET['JID'] ) ? esc_attr( $_GET['JID'] ) : '';
				
				$order_note = '';
				// $redirect_url = $order->get_checkout_payment_url();
				// $button_text = __( 'Intentar pagar nuevamente', 'pei-payment-gateway' );
				$redirect_url = $order->get_checkout_order_received_url();
				$button_text = __( 'Ver el detalle de orden', 'pei-payment-gateway' );
	
				if ( $order->key_is_valid( $order_key ) ) {
					if ( $order_id > 0 ) {
						$is_error_code = ! in_array( $status_code, PEI_PROC()->success_codes );
						$processed_data = array(
							'user_id'		=> $order->get_user_id(),
							'amount'		=> ( double ) $order->get_total(),
							'order_id'		=> $order_id,
							'authcode'		=> $auth_code,
							'transactionid'	=> $transaction_id,
							'error'			=> $is_error_code ? ( isset( PEI_PROC()->process_responses[ $status_code ] ) ? PEI_PROC()->process_responses[ $status_code ] : PEI_PROC()->responses['ND3'] ) : '',
							'status'		=> $status_code,
						);
	
						if ( $is_error_code ) {
							$order_note = array(
								sprintf( '<strong>%s</strong>', __( 'Su pago fue rechazado y su orden ha fallado.', 'pei-payment-gateway' ) ),
								sprintf( '<strong>%s:</strong> %s', __( 'Referencia del banco', 'pei-payment-gateway' ), $transaction_id ),
								sprintf( '<strong>%s:</strong> %s', __( 'Código de error', 'pei-payment-gateway' ), $status_code )
							);
						} else {
							$order_note = array(
								sprintf( '<strong>%s</strong>', __( 'Su pago ha sido aceptado y su orden se encuentra en proceso.', 'pei-payment-gateway' ) ),
								sprintf( '<strong>%s:</strong> %s', __( 'Código de estado', 'pei-payment-gateway' ), $status_code ),
								sprintf( '<strong>%s:</strong> %s', __( 'Referencia del banco', 'pei-payment-gateway' ), $transaction_id )
							);
						}
		
						if ( $temp_transaction_id != $transaction_id ) {
							WC()->session->set( 'temp_transaction_id', $transaction_id );
							$wcppg->pei_cc_payment_save_to_db( $processed_data );
							$order->add_order_note( implode( '<br>', $order_note ) );
		
							if ( $is_error_code ) {
								$order->update_status( 'failed' );
							} else {
								$order->payment_complete( $transaction_id );
								wc_reduce_stock_levels( $order->get_id() );
							}
						}
					}
				}
				wc_add_notice( __( 'Error al procesar su pago. Intente nuevamente.', 'pei-payment-gateway' ) );
			} else {
				$form_generator_url = get_post_meta( $order_id, '_pei_cc_payment_form_generator_url', true );
				ob_start();
				?>
				<iframe src="<?= esc_url( $form_generator_url ) ?>" frameborder="0" style="height: 550px; max-width: 600px; width: 100%;"></iframe>
				<?php
				$iframe = ob_get_clean();
			}

			ob_start();
			?>
			<div class="woocommerce-content">
				<?php if ( ! empty( $iframe ) ) {
					echo $iframe;
				} elseif ( ! empty( $order_note ) ) { ?>
					<p><?= implode( '</p><p>', $order_note ) ?></p>
				<?php } else { ?>
					<p><?= __( 'Ocurrió algún problema al procesar su pago.', 'pei-payment-gateway' ) ?></p>
				<?php } ?>

				<?php if ( !empty( $redirect_url ) ) { ?>
					<a href="<?= esc_url( $redirect_url ) ?>" class="btn alt"><?= $button_text ?></a>
					<script>
						parent.postMessage( {
							url: '<?= $redirect_url ?>'
						} )
					</script>
				<?php } ?>
			</div>
			<script>
				window.addEventListener( 'message', ( e ) => {
					if ( e.data.url ) {
						window.location.href = e.data.url
					}
					
					console.log(e.data.url)
				} )
			</script>
			<?php
			return ob_get_clean();
		}

		return $template;
	}

	/**
	 * Get the payment response URL
	 * 
	 * @version 2.0.0
	 * 
	 * @param mixed $param	A formatted 'key' => 'value' array for params to be added to the URL
	 * via query string or string to append element to the URL to get via query_vars
	 * 
	 * @return string The formatted URL
	 */
	public function pei_cc_payment_response_url( $params = null ) {
		$base_url = wc_get_account_endpoint_url( $this->endpoint_slug );

		if ( !empty( $params ) ) {
			if ( is_array( $params ) ) {
				$formatted_url = add_query_arg( $params, $base_url );
				return esc_url( $formatted_url );
			} else {
				return esc_url( $base_url . $params );
			}
		}

		return false;
	}

	/**
	 * Get the payment form generator URL
	 * 
	 * @version 2.0.0
	 * 
	 * @param WC_Order $order
	 * 
	 * @return string Form generator URL
	 */
	public function pei_cc_payment_form_generator_url( $order ) {
		return add_query_arg( array( 'get_payment_form' => $order->get_id(), 'order_key' => $order->get_order_key() ), home_url() );
	}
}

new PEI_CC_Payment_Endpoint();
