<?php
/**
 * @copyright Copyright (c) 2016 Green World FinTech Service Co., Ltd. (https://www.ecpay.com.tw)
 * @version 2.0.2106300
 *
 * Plugin Name: ECPay Payment for WooCommerce
 * Plugin URI: https://www.ecpay.com.tw
 * Description: ECPay Integration Payment Gateway for WooCommerce
 * Version: 2.0.2106300
 * Author: ECPay Green World FinTech Service Co., Ltd.
 * Author URI: https://www.ecpay.com.tw
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 3.1
 * WC tested up to: 4.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once(ABSPATH . 'wp-admin/includes/file.php');

/**
 * Required minimums and constants
 */
define( 'ECPAY_PAYMENT_MIN_PHP_VER', '5.6.0' );
define( 'ECPAY_PAYMENT_MIN_WC_VER', '3.1.0' );
define( 'ECPAY_PAYMENT_MAIN_FILE', __FILE__ );
define( 'ECPAY_PAYMENT_PLUGIN_VERSION', '2.0.2106300' );
define( 'ECPAY_PAYMENT_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'ECPAY_PAYMENT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

if ( ! class_exists( 'WC_Ecpay_Payment' ) )
{

    class WC_Ecpay_Payment {

        private static $instance;

        /**
         * Returns the *Singleton* instance of this class.
         *
         * @return Singleton The *Singleton* instance.
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Notices (array)
         * @var array
         */
        public $notices = array();

        protected function __construct() {
            add_action( 'admin_init', array( $this, 'check_environment' ) );
            add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
            add_action( 'plugins_loaded', array( $this, 'init' ) );
            add_action('wp_footer', array( $this, 'ecpay_integration_plugin_init_payment_method' ) );
        }

        /**
         * Init the plugin after plugins_loaded so environment variables are set.
         */
        public function init() {
            // Don't hook anything else in the plugin if we're in an incompatible environment
            if ( self::get_environment_warning() ) {
                return;
            }

            // Init the gateway itself
            $this->init_gateways();
        }

        /**
         * Allow this class and other classes to add slug keyed notices (to avoid duplication)
         */
        public function add_admin_notice( $slug, $class, $message ) {
            $this->notices[ $slug ] = array(
                'class'   => $class,
                'message' => $message,
            );
        }

        /**
         * check_environment
         */
        public function check_environment() {
            $environment_warning = self::get_environment_warning();

            if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
                $this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
            }
        }

        /**
         * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
         * found or false if the environment has no problems.
         */
        static function get_environment_warning() {

            if ( version_compare( phpversion(), ECPAY_PAYMENT_MIN_PHP_VER, '<=' ) ) {
                $message = __( '%1$sECPay Payment for WooCommerce Gateway%2$s - The minimum PHP version required for this plugin is %3$s. You are running %4$s.', 'ecpay' );

                return sprintf( $message, '<strong>', '</strong>', ECPAY_PAYMENT_MIN_PHP_VER, phpversion() );
            }

            if ( ! defined( 'WC_VERSION' ) ) {
                $message = __( '%1$sECPay Payment for WooCommerce Gateway%2$s requires WooCommerce to be activated to work.', 'ecpay' );

                return sprintf( $message, '<strong>', '</strong>' );
            }

            if ( version_compare( WC_VERSION, ECPAY_PAYMENT_MIN_WC_VER, '<=' ) ) {
                $message = __( '%1$sECPay Payment for WooCommerce Gateway%2$s - The minimum WooCommerce version required for this plugin is %3$s. You are running %4$s.', 'ecpay' );

                return sprintf( $message, ECPAY_PAYMENT_MIN_WC_VER, WC_VERSION );
            }

            if ( ! function_exists( 'curl_init' ) ) {
                $message = __( '%1$sECPay Payment for WooCommerce Gateway%2$s - cURL is not installed.', 'ecpay' );

                return sprintf( $message, '<strong>', '</strong>' );
            }

            return false;
        }

        /**
         * Display any notices we've collected thus far (e.g. for connection, disconnection)
         */
        public function admin_notices() {

            foreach ( (array) $this->notices as $notice_key => $notice ) {
                echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
                echo wp_kses( $notice['message'], array(
                    'a' => array(
                        'href' => array()
                    ),
                    'strong' => array(),
                ) );
                echo '</p></div>';
            }
        }

        /**
         *
         */
        public function init_gateways() {

            if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
                return;
            }

            if ( class_exists( 'WC_Payment_Gateway_CC' ) ) {
                include_once( dirname( __FILE__ ) . '/includes/ECPay.Payment.Integration.Shell.php' ); // 載入SDK
                include_once( dirname( __FILE__ ) . '/includes/ECPay.Payment.Html.php' );
                include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-ecpay.php' );
                include_once( dirname( __FILE__ ) . '/includes/class-wc-ecpay-apple-pay.php' );
                include_once( dirname( __FILE__ ) . '/includes/helpers/ECPayPaymentHelper.php' ); // 載入Helper
            }

            // 載入語系檔
            load_plugin_textdomain( 'ecpay', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

            add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
            add_filter( 'woocommerce_order_details_after_order_table', array( $this, 'order_details_payment_method' ), 10, 2 );
        }

        function order_details_payment_method($order)
        {
            $args = array(
                'post_id' => $order->get_id()
            );

            $comments = get_comments($args);

            $orderDetails = [];
            $search = [
                'Getting Code Result : (10100073)Get CVS Code Succeeded.',
                'Getting Code Result : (2)Get VirtualAccount Succeeded'
            ];
            if (is_array($comments)) {
                foreach ($comments as $comment) {
                    if (
                        (strpos($comment->comment_content, '(10100073)') && strpos($comment->comment_content, 'CVS')) ||
                        (strpos($comment->comment_content, '(2)') && strpos($comment->comment_content, 'ATM'))
                    ) {
                        $orderDetails = str_replace($search, '', $comment->comment_content);
                    }
                }
            }

            if (sizeof($orderDetails) > 0) {
                echo '
                    <h2 style="margin-top: 0px;padding-top: 0px;">' . __( 'Order note', 'ecpay' ) . '</h2>
                    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                        <tfoot>
                            <tr>
                                <th scope="row">' . __( 'Payment Method', 'ecpay' ) . ': </th>
                                <td>
                                    ' . esc_html(print_r($orderDetails, true)) . '
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                ';
            }
        }

        /**
         * Add the gateways to WooCommerce
         */
        public function add_gateways( $methods ) {
            $methods[] = 'WC_Gateway_Ecpay';
            $methods[] = 'WC_Gateway_Ecpay_DCA';
            return $methods;
        }

        public function ecpay_integration_plugin_init_payment_method() {
            ?>
            <script>
                (function(){
                    if (
                        document.getElementById("shipping_option") !== null &&
                        typeof document.getElementById("shipping_option") !== "undefined"
                    ) {
                        if (window.addEventListener) {
                            window.addEventListener('DOMContentLoaded', initPaymentMethod, false);
                        } else {
                            window.attachEvent('onload', initPaymentMethod);
                        }
                    }
                })();
                function initPaymentMethod() {
                    var e = document.getElementById("shipping_option");
                    var shipping = e.options[e.selectedIndex].value;
                    var payment = document.getElementsByName('payment_method');

                    if (
                        shipping == "HILIFE_Collection" ||
                        shipping == "FAMI_Collection" ||
                        shipping == "UNIMART_Collection"
                    ) {
                        var i;

                        for (i = 0; i< payment.length; i++) {
                            if (payment[i].id != 'payment_method_ecpay_shipping_pay') {
                                payment[i].style.display="none";

                                checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

                                if (checkclass == 0) {
                                    var x = document.getElementsByClassName(payment[i].id);
                                    x[0].style.display = "none";
                                } else {
                                    var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
                                    x[0].style.display = "none";
                                }
                            } else {
                                checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

                                if (checkclass == 0) {
                                    var x = document.getElementsByClassName(payment[i].id);
                                    x[0].style.display = "";
                                } else {
                                    var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
                                    x[0].style.display = "";
                                }
                            }
                        }
                        document.getElementById('payment_method_ecpay').checked = false;
                        document.getElementById('payment_method_ecpay_shipping_pay').checked = true;
                        document.getElementById('payment_method_ecpay_shipping_pay').style.display = '';
                    } else {
                        var i;
                        for (i = 0; i< payment.length; i++) {
                            if (payment[i].id != 'payment_method_ecpay_shipping_pay') {
                                payment[i].style.display="";

                                checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

                                if (checkclass == 0) {
                                    var x = document.getElementsByClassName(payment[i].id);
                                    x[0].style.display = "";
                                } else {
                                    var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
                                    x[0].style.display = "";
                                }
                            } else {
                                checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

                                if (checkclass == 0) {
                                    var x = document.getElementsByClassName(payment[i].id);
                                    x[0].style.display = "none";
                                } else {
                                    var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
                                    x[0].style.display = "none";
                                }

                                document.getElementById('payment_method_ecpay').checked = true;
                                document.getElementById('payment_method_ecpay_shipping_pay').checked = false;
                                document.getElementById('payment_method_ecpay_shipping_pay').style.display = "none";
                            }
                        }
                    }
                }
            </script>
            <?php
        }
    }

    $GLOBALS['wc_ecpay_payment'] = WC_Ecpay_Payment::get_instance();
}

?>