<?php
/*
Plugin Name: Javaabu Woocommerce Plugin
Plugin URI: https://javaabu.com
Description: Javaabu Plugin for Woocommerce to add MVR and Transfer slip upload
Version: 1.0.0
Author: Javaabu Pvt Ltd
Author URI: https://javaabu.com
Text Domain: javaabu-woocommerce-plugin
 */

defined('ABSPATH') or die('You must be upto no good');

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function javaabu_woocommerce_add_to_gateways( $gateways ) {
    $gateways[] = 'JavaabuWoocommercePlugin';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'javaabu_woocommerce_add_to_gateways' );

add_filter('woocommerce_currency_symbol', 'add_mvr_currency_symbol', 11, 2);

function add_mvr_currency_symbol( $currency_symbol, $currency ) {
    switch( $currency ) {
        case 'MVR': $currency_symbol = 'MVR'; break;
    }
    return $currency_symbol;
}

add_action( 'plugins_loaded', 'javaabu_woocommerce_plugin_init', 11 );

function javaabu_woocommerce_plugin_init() {

    class JavaabuWoocommercePlugin extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->id                 = 'javaabu_woocommerce_gateway';
            $this->icon               = apply_filters('woocommerce_offline_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'Bank Transfer and Transfer Slip Upload', 'javaabu-woocommerce_plugin' );
            $this->method_description = __( 'The customer can upload payment transfer slip after confirming order.', 'javaabu-woocommerce_plugin' );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->account_number = $this->get_option( 'account_number');
            $this->account_name = $this->get_option( 'account_name');
            $this->bank = $this->get_option( 'bank');
            $this->site_key = $this->get_option( 'site_key');

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
            add_action( 'woocommerce_admin_order_data_after_shipping_address', [$this, 'add_proof_of_payment_details'] );

            add_filter( 'woocommerce_currencies', [$this, 'add_mvr_currency'] );

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }


        public function add_mvr_currency( $currencies ) {
            $currencies['MVR'] = __( 'Maldivian Rufiyya', 'woocommerce' );
            return $currencies;
        }


        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {

            $this->form_fields = apply_filters( 'wc_offline_form_fields', [

                'enabled' => [
                    'title'   => __( 'Enable/Disable', 'javaabu-woocommerce_plugin' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Bank Transfer and Transfer Slip Upload', 'javaabu-woocommerce_plugin' ),
                    'default' => 'yes'
                ],

                'title' => [
                    'title'       => __( 'Title', 'javaabu-woocommerce_plugin' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'javaabu-woocommerce_plugin' ),
                    'default'     => __( 'Bank Transfer and Transfer Slip Upload', 'javaabu-woocommerce_plugin' ),
                    'desc_tip'    => true,
                ],

                'description' => [
                    'title'       => __( 'Description', 'javaabu-woocommerce_plugin' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'javaabu-woocommerce_plugin' ),
                    'default'     => __( 'Transfer the order total amount to our Account and Upload the transfer slip', 'javaabu-woocommerce_plugin' ),
                    'desc_tip'    => true,
                ],

                'instructions' => [
                    'title'       => __( 'Instructions', 'javaabu-woocommerce_plugin' ),
                    'type'        => 'textarea',
                    'description' => __( 'We will verify your payment with in 12 hrs and get back to you.', 'javaabu-woocommerce_plugin' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ],

                'account_number' => [
                    'title'       => __( 'Account Number', 'javaabu-woocommerce_plugin' ),
                    'type'        => 'number',
                    'description' => __( 'Account Number to which transfers must be done.', 'javaabu-woocommerce_plugin' ),
                    'default'     => __( '7700000000001', 'javaabu-woocommerce_plugin' ),
                    'desc_tip'    => true,
                ],

                'account_name' => [
                    'title'       => __( 'Account Name', 'javaabu-woocommerce_plugin' ),
                    'type'        => 'text',
                    'description' => __( 'Account Name to which transfers must be done.', 'javaabu-woocommerce_plugin' ),
                    'default'     => __( 'Sample Account', 'javaabu-woocommerce_plugin' ),
                    'desc_tip'    => true,
                ],

                'bank' => [
                    'title'       => __( 'Bank Name', 'javaabu-woocommerce_plugin' ),
                    'type'        => 'text',
                    'description' => __( 'Name of the bank.', 'javaabu-woocommerce_plugin' ),
                    'default'     => __( 'Sample Bank', 'javaabu-woocommerce_plugin' ),
                    'desc_tip'    => true,
                ],

                'site_key' => [
                    'title'       => __( 'Website Key', 'javaabu-woocommerce_plugin' ),
                    'type'        => 'text',
                    'description' => __( 'Site Key given by Javaabu', 'javaabu-woocommerce_plugin' ),
                    'default'     => md5(rand(999, 9999999)),
                    'desc_tip'    => true,
                ],

                'site_logo' => [
                    'title'       => __( 'Website Logo Link', 'javaabu-woocommerce_plugin' ),
                    'type'        => 'text',
                    'description' => __( 'Website Logo to be used on the gateway page', 'javaabu-woocommerce_plugin' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ],

                'theme_color' => [
                    'title'       => __( 'Theme color', 'javaabu-woocommerce_plugin' ),
                    'type'        => 'text',
                    'description' => __( 'Theme color to be used in the gateway', 'javaabu-woocommerce_plugin' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ],
            ] );
        }


        /**
         * Encode secure number
         *
         * @return
         */
        private function encodeSecureNumber($order_id)
        {
            return md5($this->site_key.$order_id);
        }


        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {
            $secure_number = $this->encodeSecureNumber($order_id);
            $redirect = plugins_url('/javaabu-woocommerce-plugin/gateway/form.php');
            $redirect .= '?order_id='.$order_id.'&secure_number='.$secure_number;


            // Return redirect to gateway form
            return array(
                'result' 	=> 'success',
                'redirect'	=> $redirect
            );
        }

        /**
         * Verify if order is valid
         * @param int $order_id
         * @param string $secure_number
         *
         * @return bool
         */
        function verifyOrder($order_id, $secure_number) {

            if (get_post_meta($order_id, '_woo_javaabu_wc_uploaded_payment_proof', true) != null) {
                return false;
            }

            if (is_null($order_id) || is_null($secure_number)) {
                return false;
            }

            $secure_number_encoded = $this->encodeSecureNumber($order_id);

            return strcmp($secure_number_encoded, $secure_number) != -1;
        }


        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function make_onhold( $order_id ) {

            $order = wc_get_order( $order_id );

            // Mark as on-hold (we're awaiting the payment)
            $order->update_status( 'on-hold', __( 'Awaiting offline payment', 'javaabu-woocommerce_plugin' ) );

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            wp_redirect($this->get_return_url( $order ));
        }


        /**
         * Output for the order received page.
         */
        public function thankyou_page() {

            if ( $this->instructions ) {
                echo wpautop( wptexturize( $this->instructions ) );
            }

        }


        /**
         * upload proof of delivery
         *
         * @return
         */
        public function upload_proof_of_payment($order_id, $file)
        {
            $format_valid = $this->check_file_format($file);
            $size_valid = $this->check_file_size($file);
            $upload_filepath = $this->get_file_path($file, $order_id);

            if( $format_valid && $size_valid && copy( $file['tmp_name'], $upload_filepath ) ) {
                return update_post_meta( $order_id, '_woo_javaabu_wc_uploaded_payment_proof', $upload_filepath );
            } else {
                return false;
            }
        }


        /**
         * Check if file is valid format
         *
         * @param $file
         * @return bool
         */
        public function check_file_format($file)
        {
            $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

            return in_array($ext, $this->list_accepted_formats());
        }

        /**
         * Check if file size is below allowed file size
         *
         * @param $file
         * @return bool
         */
        public function check_file_size($file)
        {
            return $file["size"] < 5000000; //5MB
        }


        /**
         * get the file path to be moved
         *
         * @param $file
         * @return bool
         */
        public function get_file_path($file, $order_id)
        {
            $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
            $upload_dir = wp_upload_dir();
            $upload_dir = trailingslashit( $upload_dir['basedir'].'/proof-of-payment/' );
            wp_mkdir_p( $upload_dir );
            return  $upload_dir. $order_id.'.'.$ext;

        }


        /**
         * get list of accepted file formats
         *
         * @return array
         */
        public function list_accepted_formats()
        {
            return [
                'jpg',
                'png',
                'tiff',
                'jpeg',
                'bmp',
                'pdf',
            ];
        }

        public function add_proof_of_payment_details()
        {
            echo '<h3 style="margin: 10px 0px;">Proof of Payment</h3>';
            $proof = get_post_meta(get_the_ID(), '_woo_javaabu_wc_uploaded_payment_proof', true);

            if ($proof) {
                $url = $this->parseUrl($proof);
                    echo '<a href="'.$url.'" target="_blank"><img style="width: 50%" src="'.$url.'" alt="Proof of payment" /></a>';
            } else {
                echo '<p>No proof of payment uploaded</p>';
            }

        }

        /**
         * Convert the path to url
         * @param $path
         */
        private function parseUrl($path)
        {
            $file = explode('/wp-content', $path)[1];
            return home_url().'/wp-content/'.$file;
        }


        /**
         * get order return url
         *
         * @param $order_id
         * @return string
         */
        public function order_return_url($order_id)
        {
            $order = wc_get_order( $order_id );

            return $order->get_cancel_order_url();
        }

        /**
         * Get order total
         * 
         * @param $order_id
         * @return string
         */
        public function order_total($order_id)
        {
            return wc_get_order($order_id)->get_total();
        }

        /**
         * Get order total
         * 
         * @return null
         */
        public function email_instructions()
        {
            return null;
        }


    } // end class
}