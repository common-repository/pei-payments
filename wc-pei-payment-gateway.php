<?php
/**
 * Plugin Name: Pagos con Multimoney Biz
 * Plugin URI:  https://www.go-pei.com/busines/woocommerce/
 * Description: Acepta pagos SINPE desde cualquier cuenta y sin compartir información innecesaria o privada. Visualiza reportes y sigue el paso de todos los pagos realizados por el botón de pago pei en tu sitio web.
 * Version:     2.0.1
 * Requires at least: 4.4
 * Tested up to: 6.0
 * WC requires at least: 3.0
 * WC tested up to: 6.5.1
 * Author:      Gente mas Gente, S.A.
 * Author URI:  https://www.grupogente.com/
 * Text Domain: pei-payment-gateway
 * Domain Path: /languages
 * License:     GPLv3
 */

if (!defined('ABSPATH')) exit;

if ( ! defined( 'PEI_PAYMENT_VERSION' ) ) {
	define( 'PEI_PAYMENT_VERSION', '2.0.1' );
}

if ( ! defined( 'PEI_PAYMENT_ID' ) ) {
	define( 'PEI_PAYMENT_ID', 'pei_payment' );
}

if ( ! defined( 'PEI_PAYMENT_PLUGIN_FILE' ) ) {
	define( 'PEI_PAYMENT_PLUGIN_FILE', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'PEI_PAYMENT_PLUGIN_PATH' ) ) {
	define( 'PEI_PAYMENT_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
}

if ( ! defined( 'PEI_PAYMENT_PLUGIN_URL' ) ) {
	define( 'PEI_PAYMENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'PEI_PAYMENT_PLUGIN_NAME' ) ) {
	define( 'PEI_PAYMENT_PLUGIN_NAME', 'Pagos con Multimoney Biz' );
}

/**
 * Load misc. and other files
 */
include PEI_PAYMENT_PLUGIN_PATH . '/includes/class-pei_payment-ws.php';
include PEI_PAYMENT_PLUGIN_PATH . '/includes/class-pei_cc_payment-ws.php';

/**
 * Check if WooCommerce is active
 */
function pei_payment_gateway_missing_wc_notice() {
	if ( current_user_can( 'activate_plugins' ) ) :
		if ( !class_exists( 'WooCommerce' ) ) :
			?>
			<div id="message" class="error">
				<p>
					<?php echo sprintf(
						__('%s requiere %sWooCommerce%s para ser instalado y activado.', 'pei-payment-gateway'),
						'<strong>' . PEI_PAYMENT_PLUGIN_NAME . '</strong>',
						'<a href="http://wordpress.org/plugins/woocommerce/" target="_blank" >',
						'</a>'
					); ?>
				</p>
			</div>
			<?php
		endif;
	endif;
}

/**
 * WebHook Notice
 */
function pei_payment_gateway_wc_webhook_notice() {
	$wcppw = new PEI_Payment_Webhook_Handler();
	$the_webhook = $wcppw->pei_payment_get_webhook_info();

	if ( ! isset( $the_webhook['message'] ) )
		return;
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<?php echo $the_webhook['message']; ?>
		</p>
	</div>
	<?php
}

/**
 * Pei Payment Gateway init
 * 
 * @since 1.0.0
 * @version 2.0.0
 */
function pei_payment_gateway_init() {
	if ( !in_array( 'woocommerce/woocommerce.php', get_option( 'active_plugins' ) ) ) {
		add_action( 'admin_notices', 'pei_payment_gateway_missing_wc_notice' );
		return;
	}

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	/** Init Payment Gateway **/
	include PEI_PAYMENT_PLUGIN_PATH . '/includes/class-pei_payment.php';
	include PEI_PAYMENT_PLUGIN_PATH . '/includes/class-pei_payment-webhook.php';
	include PEI_PAYMENT_PLUGIN_PATH . '/includes/class-pei_cc_payment.php';
	include PEI_PAYMENT_PLUGIN_PATH . '/includes/class-pei_cc_payment-endpoint.php';
	include PEI_PAYMENT_PLUGIN_PATH . '/includes/class-pei_payment-checkout-process.php';

	if ( ! class_exists( 'WC_PEI_Payment_Gateway' ) || ! class_exists( 'WC_PEI_CC_Payment_Gateway' ) ) return;

	function pei_payment_gateway_class($methods) {
		$methods[] = 'WC_PEI_Payment_Gateway';
		$methods[] = 'WC_PEI_CC_Payment_Gateway';

		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'pei_payment_gateway_class' );

	/** Load language **/
	load_plugin_textdomain( 'pei-payment-gateway', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	/** WebHook URL **/
	add_action( 'admin_notices', 'pei_payment_gateway_wc_webhook_notice' );

	/** Create Transaction table **/
	$site_option_version = get_site_option('pei_payment_gateway_version');
	if ( $site_option_version != PEI_PAYMENT_VERSION || empty( $site_option_version ) ) {
		pei_payment_gateway_install();
	}
	
	$site_update_table = get_site_option( 'pei_payment_table_transactions_updated' );
	if ( version_compare( PEI_PAYMENT_VERSION, '1.1', '>' ) && empty( $site_update_table ) ) {
		pei_payment_alter_transactions_table();
	}

	flush_rewrite_rules();
}
add_action( 'plugins_loaded', 'pei_payment_gateway_init' );

/**
 * Create table and save payment gateway version
 * Table to store all processed transactions for this payment gateway
 */
function pei_payment_gateway_install() {
	global $wpdb;

	$table_bitacora = $wpdb->prefix . 'pei_transactions';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS " . $table_bitacora . " (
		`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`user_id` int(11) NOT NULL DEFAULT '1',
		`amount` decimal(11,2) NOT NULL DEFAULT '0.00',
		`order_id` int(11) DEFAULT NULL,
		`authcode` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
		`transactionid` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
		`error` longtext COLLATE utf8_unicode_ci,
		`transaction_date` datetime DEFAULT NULL,
		`status` int(11) DEFAULT NULL,
		UNIQUE (`id`)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	update_option('pei_payment_gateway_version', PEI_PAYMENT_VERSION);
}

/**
 * Alter column status for previous installed plugins
 * 
 * @version 2.0.0
 */
function pei_payment_alter_transactions_table() {
	global $wpdb;

	$table_bitacora = $wpdb->prefix . 'pei_transactions';
	$sql = "ALTER TABLE " . $table_bitacora;
	$sql .= " ADD payment_method varchar(20) NULL DEFAULT NULL,";
	$sql .= " CHANGE COLUMN `status` `status` varchar(5) NULL DEFAULT NULL;";
	$result = $wpdb->query( $sql );

	if ( $result ) {
		update_option( 'pei_payment_table_transactions_updated', PEI_PAYMENT_VERSION );
	} else {
		update_option( 'pei_payment_table_transactions_updated_error', $sql );
	}
}

/**
 * Add setting links to WC
 */
function pei_payment_gateway_admin_submenu() {
	add_submenu_page(
		'woocommerce', __('Pagos con Pei', 'pei-payment-gateway'), __('Pagos con Pei', 'pei-payment-gateway'), 'manage_options', admin_url('admin.php?page=wc-settings&tab=checkout&section=' . PEI_PAYMENT_ID)
	);
	add_submenu_page(
		'woocommerce', PEI_PAYMENT_PLUGIN_NAME, PEI_PAYMENT_PLUGIN_NAME, 'manage_options', admin_url('admin.php?page=wc-settings&tab=checkout&section=pei_cc_payment')
	);
}
add_action( 'admin_menu', 'pei_payment_gateway_admin_submenu' );

/**
 * Add Settings action links
 */
function pei_payment_gateway_links($links) {
	$plugin_links = array(
		'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . PEI_PAYMENT_ID) . '">' . __('Ajustes', 'pei-payment-gateway') . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge($plugin_links, $links);    
}
add_filter( 'plugin_action_links_' . PEI_PAYMENT_PLUGIN_FILE, 'pei_payment_gateway_links' );
