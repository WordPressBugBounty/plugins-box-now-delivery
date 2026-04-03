<?php
/*
Plugin Name: BOX NOW Delivery
Description: A Wordpress plugin from BOX NOW to integrate your eshop with our services.
Author: BOX NOW
Text Domain: box-now-delivery
Version: 3.2.0
License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin Version Constant
 */
define( 'BOX_NOW_DELIVERY_VERSION', '3.2.0' );

// Cancel order API call file
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-cancel-order.php';

// Include the box-now-delivery-print-order.php file
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-print-order.php';

// Include constants first
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-constants.php';

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    // Include custom shipping method file
    include(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-shipping-method.php');

    // Include admin page functions
    include(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-admin-page.php');

    function bndp_get_custom_cod_description_for_current_zone()
    {
        if (!function_exists('WC') || !WC()->customer) {
            return '';
        }

        $package = array(
            'destination' => array(
                'country' => WC()->customer->get_shipping_country(),
                'state' => WC()->customer->get_shipping_state(),
                'postcode' => WC()->customer->get_shipping_postcode(),
            ),
        );

        $shipping_zone = WC_Shipping_Zones::get_zone_matching_package($package);
        if (!$shipping_zone) {
            return '';
        }

        foreach ($shipping_zone->get_shipping_methods() as $shipping_method) {
            if ('box_now_delivery' !== $shipping_method->id) {
                continue;
            }

            $enable_custom_cod_description = $shipping_method->get_option('enable_custom_cod_description');
            $custom_cod_description = $shipping_method->get_option('custom_cod_description');

            if ('yes' === $enable_custom_cod_description && !empty($custom_cod_description)) {
                return wp_kses_post($custom_cod_description);
            }
        }

        return '';
    }

    /**
     * Enqueue scripts and styles for BOX NOW Delivery plugin.
     */
    function box_now_delivery_enqueue_scripts(){
        if (is_checkout() || is_order_received_page()) {
            $button_color = esc_attr(get_option('boxnow_button_color', '#6CD04E '));
            $button_text = esc_attr(get_option('boxnow_button_text', 'Pick a Locker'));
            $page = is_order_received_page() ? "thankyou_page" : "checkout";

            $settings = array(
                'partnerId' => esc_attr(get_option('boxnow_partner_id', '')),
                'embeddedIframe' => esc_attr(get_option('embedded_iframe', '')),
                'displayMode' => esc_attr(get_option('box_now_display_mode', 'popup')),
                'buttonColor' => $button_color,
                'buttonText' => $button_text,
                'lockerNotSelectedMessage' => esc_js(get_option('boxnow_locker_not_selected_message', 'Please select a locker first!')),
                'gps_option' => get_option('boxnow_gps_tracking', 'on'),
                'codTitle' => __('BOX NOW PAY ON THE GO!', 'box-now-delivery'),
                'codDescription' => bndp_get_custom_cod_description_for_current_zone(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('box-now-delivery-nonce'),
                'page' => $page
            );

            wp_enqueue_script('box-now-delivery-js', plugin_dir_url(__FILE__) . 'js/box-now-delivery.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('box-now-delivery-css', plugins_url('/css/box-now-delivery.css', __FILE__), array(), BOX_NOW_DELIVERY_VERSION);
            wp_localize_script('box-now-delivery-js', 'boxNowDeliverySettings', $settings);
            // If WooCommerce Blocks checkout is present, enqueue the Blocks-specific script
            if (boxnow_is_blocks_checkout()) {
                wp_enqueue_script('box-now-delivery-blocks-js', plugin_dir_url(__FILE__) . 'js/box-now-delivery-blocks.js', array('wp-data'), '1.0.0', true);
                wp_localize_script('box-now-delivery-blocks-js', 'boxNowDeliverySettings', $settings);
            }
        }
    }
    add_action('wp_enqueue_scripts', 'box_now_delivery_enqueue_scripts');

    // Detect Blocks checkout
    function boxnow_is_blocks_checkout() {
        if (!is_checkout()) {
            return false;
        }

        global $post;

        if (!$post instanceof WP_Post) {
            return false;
        }

        return has_block('woocommerce/checkout', $post);
    }

    /**
     * Enqueue data for WooCommerce Blocks checkout (ensures settings available to blocks context).
     */
    function bndp_add_boxnow_data_to_blocks() {

        if (!boxnow_is_blocks_checkout()) {
            return;
        }
        
        // Only proceed if Blocks is available
        if (!function_exists('wc_current_theme_is_fse_theme') || !wc_current_theme_is_fse_theme()) {
            // Still enqueue the script if blocks are used via shortcode in non-FSE themes
            if (boxnow_is_blocks_checkout()) {
                wp_enqueue_script('box-now-delivery-blocks-js', plugin_dir_url(__FILE__) . 'js/box-now-delivery-blocks.js', array('wp-data'), '1.0.0', true);
                $button_color = esc_attr(get_option('boxnow_button_color', '#6CD04E '));
                $button_text = esc_attr(get_option('boxnow_button_text', 'Pick a Locker'));
                $settings = array(
                    'partnerId' => esc_attr(get_option('boxnow_partner_id', '')),
                    'embeddedIframe' => esc_attr(get_option('embedded_iframe', '')),
                    'displayMode' => esc_attr(get_option('box_now_display_mode', 'popup')),
                    'buttonColor' => $button_color,
                    'buttonText' => $button_text,
                    'lockerNotSelectedMessage' => esc_js(get_option('boxnow_locker_not_selected_message', 'Please select a locker first!')),
                    'gps_option' => get_option('boxnow_gps_tracking', 'on'),
                    'codTitle' => __('BOX NOW PAY ON THE GO!', 'box-now-delivery'),
                    'codDescription' => bndp_get_custom_cod_description_for_current_zone(),
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('box-now-delivery-nonce'),
                );
                wp_localize_script('box-now-delivery-blocks-js', 'boxNowDeliverySettings', $settings);
            }
            return;
        }

        // FSE theme and blocks
        $button_color = esc_attr(get_option('boxnow_button_color', '#6CD04E '));
        $button_text = esc_attr(get_option('boxnow_button_text', 'Pick a Locker'));
        $settings = array(
            'partnerId' => esc_attr(get_option('boxnow_partner_id', '')),
            'embeddedIframe' => esc_attr(get_option('embedded_iframe', '')),
            'displayMode' => esc_attr(get_option('box_now_display_mode', 'popup')),
            'buttonColor' => $button_color,
            'buttonText' => $button_text,
            'lockerNotSelectedMessage' => esc_js(get_option('boxnow_locker_not_selected_message', 'Please select a locker first!')),
            'gps_option' => get_option('boxnow_gps_tracking', 'on'),
            'codTitle' => __('BOX NOW PAY ON THE GO!', 'box-now-delivery'),
            'codDescription' => bndp_get_custom_cod_description_for_current_zone(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('box-now-delivery-nonce'),
        );
        wp_enqueue_script('box-now-delivery-blocks-js', plugin_dir_url(__FILE__) . 'js/box-now-delivery-blocks.js', array('wp-data'), '1.0.0', true);
        wp_localize_script('box-now-delivery-blocks-js', 'boxNowDeliverySettings', $settings);
    }
    add_action('woocommerce_blocks_checkout_enqueue_data', 'bndp_add_boxnow_data_to_blocks');

    /**
     * Add custom field for Locker ID on checkout.
     *
     * @param array $fields Fields on the checkout.
     * @return array $fields Modified fields.
     */
    function bndp_box_now_delivery_custom_override_checkout_fields($fields)
    {

        $fields['billing']['_boxnow_locker_id'] = array(
                'label' => __('BOX NOW Locker ID', 'box-now-delivery'),
                'placeholder' => _x('BOX NOW Locker ID', 'placeholder', 'box-now-delivery'),
                'required' => false,
                'class' => array('boxnow-form-row-hidden', 'boxnow-locker-id-field'),
                'clear' => true
        );
        return $fields;
    }
    // Add a custom field to retrieve the Locker ID from the checkout page
    add_filter('woocommerce_checkout_fields', 'bndp_box_now_delivery_custom_override_checkout_fields');

    function bndp_box_now_delivery_checkout_nonce_field()
    {
        if (boxnow_is_blocks_checkout()) {
            return;
        }

        wp_nonce_field('bndp_boxnow_checkout', 'bndp_boxnow_checkout_nonce');
    }
    add_action('woocommerce_review_order_before_submit', 'bndp_box_now_delivery_checkout_nonce_field');

    /**
     * Hide the locker ID field on the checkout page.
     */
    function bndp_hide_box_now_delivery_locker_id_field()
    {
        if (is_checkout()) {
            ?>
            <script>
                jQuery(document).ready(function($) {
                    $('.boxnow-locker-id-field').hide();
                });
            </script>
            <?php
        }
    }
    add_action('wp_footer', 'bndp_hide_box_now_delivery_locker_id_field');


    /**
     * Remove the selected locker details from local storage when order placed
     */
    function bndp_check_order_received_page ()
    {
        if (is_order_received_page()) {
            ?>
            <script>
                jQuery(document).ready(function($) {
                    // delay to clear local storage at thank you page to make sure box_now_selected_locker is correctly saved
                    setTimeout(localStorage.removeItem("box_now_selected_locker"), 2000);
                });
            </script>
            <?php
        }
    }

    add_action('wp_footer', 'bndp_check_order_received_page');

    /* Display field value on the order edit page */
    add_action('woocommerce_admin_order_data_after_billing_address', 'bndp_box_now_delivery_checkout_field_display_admin_order_meta', 10, 1);

    /**
     * Display custom checkout field in the order edit page.
     *
     * @param WC_Order $order WooCommerce Order.
     */
    function bndp_box_now_delivery_checkout_field_display_admin_order_meta($order)
    {
        // Get the order shipping method
        $shipping_methods = $order->get_shipping_methods();
        $box_now_used = false;

        foreach ($shipping_methods as $shipping_method) {
            if ($shipping_method->get_method_id() == 'box_now_delivery') { // replace with your box now delivery method id
                $box_now_used = true;
                break;
            }
        }

        // Only proceed if BOX NOW Delivery was used
        if ($box_now_used) {

            $locker_id = $order->get_meta('_boxnow_locker_id');
            $warehouse_id = $order->get_meta('_selected_warehouse');

            /* get names for possible warehouses */
            $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/auth-sessions';
            $auth_args = array(
                'method' => 'POST',
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode(array(
                    'grant_type' => 'client_credentials',
                    'client_id' => get_option('boxnow_client_id', ''),
                    'client_secret' => get_option('boxnow_client_secret', '')
                ))
            );
            $response = wp_remote_post($api_url, $auth_args);
            $json = json_decode(wp_remote_retrieve_body($response), true);

            $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/origins';
            $origins_args = array(
                'method' => 'GET',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $json['access_token'],
                    'Content-Type' => 'application/json'
                )
            );
            $warehouses_json = wp_remote_get($api_url, $origins_args);
            $warehouses_list = json_decode(wp_remote_retrieve_body($warehouses_json), true);
            $warehouse_names = [];
            foreach ($warehouses_list['data'] as $warehouse) {
                $warehouse_names[$warehouse['id']] = $warehouse['name'];
            }

        ?>
            <style>
                .boxnow_data_column {
                    clear: both;
                    margin-top: 12px;
                }
                .boxnow_data_column .edit_address {
                    float: none;
                }
                .boxnow_data_column .edit_address .form-field {
                    margin: 0 0 8px;
                }
                .boxnow_data_column .edit_address input,
                .boxnow_data_column .edit_address select {
                    width: 100%;
                    max-width: 100%;
                    box-sizing: border-box;
                }
            </style>
            <div class="boxnow_data_column">
                <h4><?php echo esc_html__('BOX NOW Delivery', 'box-now-delivery'); ?><a href="#" class="edit_address"><?php echo esc_html__('Edit', 'box-now-delivery'); ?></a></h4>
                <div class="address">
                    <?php
                    echo '<p><strong>' . esc_html__('Locker ID', 'box-now-delivery') . ':</strong>' . esc_html($locker_id) . '</p>';
                    echo '<p><strong>' . esc_html__('Warehouse ID', 'box-now-delivery') . ':</strong>' . esc_html($warehouse_id) . ' - ' . esc_html($warehouse_names[$warehouse_id]) . '</p>';
                    ?>
                </div>
                <div class="edit_address">
                    <?php
                    woocommerce_wp_text_input(array(
                        'id' => '_boxnow_locker_id',
                        'label' => esc_html__('Locker ID', 'box-now-delivery'),
                        'wrapper_class' => '_boxnow_locker_id',
                        'value' => $order->get_meta('_boxnow_locker_id')
                    ));
                    echo '<a id="box_now_delivery_button" target="_blank" style=" background-color:#44d62d;
	border-radius:28px;
	border:1px solid #18ab29;
	display:inline-block;
	cursor:pointer;
	color:#ffffff;
	font-family:Arial;
	font-size:12px;
	padding:8px 12px;
    top: 23px;
	text-decoration:none;
	">Find a Locker</a></span>';
                    $warehouse_ids = explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')));
                    $warehouses_show = [];
                    foreach ($warehouse_ids as $id) {
                        $warehouses_show[$id] = $id . ' - ' . esc_html($warehouse_names[$id]);
                    }
                    $warehouse_options = array_combine($warehouse_ids, $warehouses_show);
                    woocommerce_wp_select(array('id' => '_selected_warehouse', 'label' => esc_html__('Warehouse ID', 'box-now-delivery'), 'wrapper_class' => '_selected_warehouse', 'options' => $warehouse_options));
                    wp_nonce_field('bndp_boxnow_admin_order_meta', 'bndp_boxnow_admin_order_meta_nonce');
                    ?>

                </div>
            </div>
        <?php
        }
    }

    /**
     * Save custom checkout fields in the order edit page.
     *
     * @param int $post_id The post ID.
     */
    function bndp_box_now_delivery_save_checkout_field_admin_order_meta($post_id)
    {
        $order = wc_get_order($post_id);
        $nonce = isset($_POST['bndp_boxnow_admin_order_meta_nonce']) ? sanitize_text_field(wp_unslash($_POST['bndp_boxnow_admin_order_meta_nonce'])) : '';

        // Ensure we have an order and the required POST data
        if (
            !$order ||
            !$nonce ||
            !wp_verify_nonce($nonce, 'bndp_boxnow_admin_order_meta') ||
            !isset($_POST['_boxnow_locker_id']) ||
            !isset($_POST['_selected_warehouse'])
        ) {
            return;
        }

        $locker_id = sanitize_text_field(wp_unslash($_POST['_boxnow_locker_id']));
        $selected_warehouse = sanitize_text_field(wp_unslash($_POST['_selected_warehouse']));

        $order->update_meta_data('_boxnow_locker_id', $locker_id);
        $order->update_meta_data('_selected_warehouse', $selected_warehouse);
        $order->save();
    }

    add_action('woocommerce_process_shop_order_meta', 'bndp_box_now_delivery_save_checkout_field_admin_order_meta');


    /**
     * Update the order meta with field value.
     *
     * @param int $order_id The order ID.
     */
    function bndp_box_now_delivery_checkout_field_update_order_meta($order)
    {
        $locker_id = '';
        $checkout_nonce = isset($_POST['bndp_boxnow_checkout_nonce']) ? sanitize_text_field(wp_unslash($_POST['bndp_boxnow_checkout_nonce'])) : '';
        $has_verified_checkout_post = $checkout_nonce && wp_verify_nonce($checkout_nonce, 'bndp_boxnow_checkout');

        // Attempt to get locker data from POST (JSON format)
        if ($has_verified_checkout_post && isset($_POST['box_now_selected_locker'])) {
            $locker_data_raw = sanitize_text_field(wp_unslash($_POST['box_now_selected_locker']));
            $locker_data = json_decode($locker_data_raw, true);
            if (is_array($locker_data) && !empty($locker_data['boxnowLockerId'])) {
                $locker_id = sanitize_text_field($locker_data['boxnowLockerId']);
            }
        }

        // Fallback: Try direct POST field
        if ($has_verified_checkout_post && empty($locker_id) && isset($_POST['_boxnow_locker_id'])) {
            $locker_id = sanitize_text_field(wp_unslash($_POST['_boxnow_locker_id']));
        }

        // Fallback: Try WooCommerce session
        if (empty($locker_id)) {
            $locker_id = WC()->session->get('boxnow_selected_locker_id');
        }

        // Save locker ID to order if available
        if (!empty($locker_id)) {
            $order->update_meta_data('_boxnow_locker_id', $locker_id);
        }

        // Save default warehouse if not already set
        if (!metadata_exists('post', $order->get_id(), '_selected_warehouse')) {
            $warehouse_ids = explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')));
            if (!empty($warehouse_ids[0])) {
                $order->update_meta_data('_selected_warehouse', $warehouse_ids[0]);
            }
        }

        // Commit meta data to order
        $order->save();
        WC()->session->set('boxnow_selected_locker_id', null);
    }
    // Classic/shortcode Checkout - Runs when the order object is created, before saving.
    add_action('woocommerce_checkout_create_order', 'bndp_box_now_delivery_checkout_field_update_order_meta');
    // Blocks Checkout - Final adjustments after order creation.
    add_action('woocommerce_store_api_checkout_order_processed', 'bndp_box_now_delivery_checkout_field_update_order_meta');

    /**
     * Save locker id from WooCommerce Blocks checkout request.
     */
    function bndp_box_now_delivery_blocks_checkout_update_order_meta($order, $request)
    {
        // Normalize request to array whether it's WP_REST_Request or array
        if (is_object($request) && class_exists('WP_REST_Request') && $request instanceof WP_REST_Request) {
            $req_data = $request->get_params();
        } else {
            $req_data = is_array($request) ? $request : array();
        }

        // Save locker id if provided in the request (do not depend on shipping method availability at this stage)
        $locker_id = '';
        if (!empty($req_data['extensions']['box-now-delivery']['_boxnow_locker_id'])) {
            $locker_id = sanitize_text_field($req_data['extensions']['box-now-delivery']['_boxnow_locker_id']);
        } elseif (!empty($req_data['_boxnow_locker_id'])) {
            $locker_id = sanitize_text_field($req_data['_boxnow_locker_id']);
        }

        // Fallback: pull from Woo session if available
        if (empty($locker_id) && function_exists('WC') && WC()->session) {
            $session_val = WC()->session->get('boxnow_selected_locker_id');
            if (!empty($session_val)) {
                $locker_id = sanitize_text_field($session_val);
            }
        }

        if (!empty($locker_id)) {
            $order->update_meta_data('_boxnow_locker_id', $locker_id);
        }

        if (!metadata_exists('post', $order->get_id(), '_selected_warehouse')) {
            $order->add_meta_data('_selected_warehouse', explode(',', str_replace(' ', '', get_option('boxnow_warehouse_id', '')))[0]);
        }

        // Clear session value after saving
        if (function_exists('WC') && WC()->session) {
            WC()->session->set('boxnow_selected_locker_id', null);
        }
        
        $order->save();
    }
    // Blocks Checkout - When order data is updated from Store API request (shipping method selection happens here).
    add_action('woocommerce_store_api_checkout_update_order_from_request', 'bndp_box_now_delivery_blocks_checkout_update_order_meta', 10, 2);
} else {

    /**
     * Display admin notice if WooCommerce is not active.
     */
    function bndp_box_now_delivery_admin_notice()
    {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html__('BOX NOW Delivery requires WooCommerce to be installed and active.', 'box-now-delivery'); ?></p>
        </div>
        <?php
    }

    add_action('admin_notices', 'bndp_box_now_delivery_admin_notice');
}

/**
 * Change Cash on delivery title to custom
 */
add_filter('woocommerce_gateway_title', 'bndp_change_cod_title_for_box_now_delivery', 20, 2);
add_filter('woocommerce_available_payment_gateways', 'bndp_box_now_delivery_adjust_cod_gateway_title', 20);

function bndp_is_box_now_delivery_selected()
{
    if (!function_exists('WC')) {
        return false;
    }

    $session = WC()->session;
    if ($session) {
        $chosen = $session->get('chosen_shipping_methods');
        if (is_array($chosen)) {
            foreach ($chosen as $method) {
                if (is_string($method) && strpos($method, 'box_now_delivery') === 0) {
                    return true;
                }
            }
        } elseif (is_string($chosen) && strpos($chosen, 'box_now_delivery') === 0) {
            return true;
        }

        $package0 = $session->get('shipping_for_package_0');
        if (is_array($package0) && !empty($package0['chosen_method'])) {
            $method = $package0['chosen_method'];
            if (is_string($method) && strpos($method, 'box_now_delivery') === 0) {
                return true;
            }
        }
    }

    $cart = WC()->cart;
    if ($cart && method_exists($cart, 'get_shipping_packages')) {
        $packages = $cart->get_shipping_packages();
        if (!empty($packages) && !empty($packages[0]['chosen_method'])) {
            $method = $packages[0]['chosen_method'];
            if (is_string($method) && strpos($method, 'box_now_delivery') === 0) {
                return true;
            }
        }
    }

    return false;
}

function bndp_box_now_delivery_adjust_cod_gateway_title($gateways)
{
    if (is_admin()) {
        return $gateways;
    }

    if (empty($gateways['cod'])) {
        return $gateways;
    }

    static $original_titles = array();
    $gateway = $gateways['cod'];
    $key = isset($gateway->id) ? $gateway->id : 'cod';

    if (!isset($original_titles[$key])) {
        $original_titles[$key] = isset($gateway->title) ? $gateway->title : '';
    }

    if (bndp_is_box_now_delivery_selected()) {
        $gateway->title = __('BOX NOW PAY ON THE GO!', 'box-now-delivery');
    } else {
        $gateway->title = $original_titles[$key];
    }

    $gateways['cod'] = $gateway;
    return $gateways;
}

function bndp_change_cod_title_for_box_now_delivery($title, $payment_id)
{
    if (!is_admin() && $payment_id === 'cod') {
        if (bndp_is_box_now_delivery_selected()) {
            $title = __('BOX NOW PAY ON THE GO!', 'box-now-delivery');
        }
    }

    return $title;
}

/*
* Send information to BOX NOW api and for sending an email to the customer with the voucher
*/
add_action('woocommerce_order_status_completed', 'boxnow_order_completed');

function boxnow_order_completed($order_id)
{
    // Check if the '_manual_status_change' transient is set
    if (get_transient('_manual_status_change')) {
        // Delete the transient
        delete_transient('_manual_status_change');
        // Return early
        return;
    }

    // Check if the Send voucher via email option is selected
    if (get_option('boxnow_voucher_option') !== 'email') {
        return;
    }

    $order = wc_get_order($order_id);

    if ($order->has_shipping_method('box_now_delivery')) {
        // Check if a voucher has already been created and return, 'yes' evaluates as true
        if ($order->get_meta('_voucher_created', true)) {
            return;
        }
        try {
            $prep_data = boxnow_prepare_data($order, true);
            $response = boxnow_order_completed_delivery_request($prep_data, $order->get_id(), 1);
        } catch (Exception $e) {
            // Since this is a hook based action that cannot show an alert dialog, add order note to inform user of the error
            if (isset($order)) {
                $order->add_order_note("BOX NOW: Error, unable to create voucher.", false);
            }
            return;
        }

        $response_data = json_decode($response, true);

        if (isset($response_data['parcels'][0]['id'])) {
            $voucher_number = $response_data['parcels'][0]['id'];
            $order = wc_get_order($order_id);
            
            // Add order note to inform user of BOX NOW voucher creation
            $order->add_order_note('BOX NOW voucher number created successfully: ' . $voucher_number, false);
            
            $order->update_meta_data('_boxnow_parcel_id', $voucher_number);
            // Set the flag to indicate that the voucher has been created, 'yes' evaluates as true
            $order->update_meta_data('_voucher_created', 'yes');
            $order->save();
        }
    }
}

/**
 * Create Delivery Request (exclusive use for boxnow_order_completed function)
 * @throws Exception
 */
function boxnow_order_completed_delivery_request($prep_data, $order_id, $num_vouchers)
{
    $access_token = boxnow_get_access_token();
    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/delivery-requests';
    $payment_method = $prep_data['payment_method'];
    $send_voucher_via_email = get_option('boxnow_voucher_option', 'button') === 'email';

    for ($i = 0; $i < $num_vouchers; $i++) {
        $item_data = [
                "value" => $prep_data['product_price'],
                "weight" => $prep_data['weight']
        ];

        if (isset($prep_data['compartment_sizes'])) {
            // if num_vouchers is 1, selects the FIRST element of the pre-calculated compartment sizes array prep_data['compartment_sizes']
            $item_data["compartmentSize"] = $prep_data['compartment_sizes'][0];
        }

        $items[] = $item_data;
    }

    $order = wc_get_order($order_id);
    // Get the billing address client email because shipping address does not have email
    $client_email = $order->get_billing_email();

    global $wp_version; 
    $additional_info =  "PHP " . phpversion() . ", WP "  . $wp_version . ", WC " . WC()->version . ", BNP " . BOX_NOW_DELIVERY_VERSION;
    $data = [
            "notifyOnAccepted" => $send_voucher_via_email ? get_option('boxnow_voucher_email', '') : '',
            "orderNumber" => bndp_create_order_number($order_id),
            "invoiceValue" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
            "paymentMode" => $payment_method === 'cod' ? "cod" : "prepaid",
            "amountToBeCollected" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
            "allowReturn" => boolval(get_option('boxnow_allow_returns', '1')),
            "origin" => [
                    "contactNumber" => get_option('boxnow_mobile_number', ''),
                    "contactEmail" => get_option('boxnow_voucher_email', ''),
                    "locationId" => $prep_data['selected_warehouse'],
            ],
            "destination" => [
                    "contactNumber" => $prep_data['phone'],
                    "contactEmail" => $client_email,
                    "contactName" => $prep_data['first_name'] . ' ' . $prep_data['last_name'],
                    "locationId" => $prep_data['locker_id'],
            ],
            "items" => $items,
            "additionalInformation" => $additional_info
    ];

    $response = wp_remote_post($api_url, [
            'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
    ]);

    if (is_wp_error($response)) {
        return $response->get_error_message();
    } else {
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);
        $response_id = $response_body['id'] ?? null;
        if ($response_id !== null && $response_code !== 401 && (string)$response_id !== '401') {
            $parcel_ids = [];
            foreach ($response_body['parcels'] as $parcel) {
                $parcel_ids[] = $parcel['id'];
            }
            $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
            $order->save();
        } else {
            throw new Exception('BOX NOW: Unable to create vouchers.' . json_encode($response_body));
        }
        return wp_remote_retrieve_body($response);
    }
}

// Create the order number using the order id as suffix, the undersscore character and a random 5digits integer as suffix
function bndp_create_order_number($order_id){
    $max_5_digit_number = pow(10, 5) - 1;
    $random_suffix = wp_rand(1, $max_5_digit_number);
    $order_number = $order_id . '_' . str_pad($random_suffix, 5, '0', STR_PAD_LEFT);;
    return $order_number;
}

/**
 * Determine the compartment size based on dimensions. 
 * Product weight is not checked here as BOX NOW shipping method already validates it during checkout
 * @throws Exception
 */
function boxnow_get_compartment_size($dimensions)
{
    // Check if all dimensions are either not set or equal to 0. Then return default compartment size (medium)
    if ((!isset($dimensions['length']) || $dimensions['length'] == 0) &&
        (!isset($dimensions['width']) || $dimensions['width'] == 0) &&
        (!isset($dimensions['height']) || $dimensions['height'] == 0)) {
        // Return the default compartment size
        return BOX_NOW_COMPARTMENT_MEDIUM;
    }

    // Get WooCommerce dimension unit
    $wc_dimensions_unit = get_option('woocommerce_dimension_unit'); // cm, mm, m, in, yd

    // Convert the product dimensions to the unit of measurement defined in BOX NOW shipping method settings
    try {
        $converted_length = bndp_convert_dimension_to_cm($dimensions['length'], $wc_dimensions_unit, BOX_NOW_DIMENSIONS_UNIT);
        $converted_width = bndp_convert_dimension_to_cm($dimensions['width'], $wc_dimensions_unit, BOX_NOW_DIMENSIONS_UNIT);
        $converted_height = bndp_convert_dimension_to_cm($dimensions['height'], $wc_dimensions_unit, BOX_NOW_DIMENSIONS_UNIT);
    } catch(InvalidArgumentException $e) {
        // Failed to convert the dimensions; treating the cart as containing oversized items.
        throw new Exception('BOX NOW: Unable to create voucher - Failed to convert product dimensions');
    }

    if ($converted_length <= BOX_NOW_LENGTH &&
        $converted_width <= BOX_NOW_WIDTH &&
        $converted_height <= BOX_NOW_SMALL_HEIGHT) {
        return BOX_NOW_COMPARTMENT_SMALL;
    }

    if ($converted_length <= BOX_NOW_LENGTH &&
        $converted_width <= BOX_NOW_WIDTH &&
        $converted_height <= BOX_NOW_MEDIUM_HEIGHT) {
        return BOX_NOW_COMPARTMENT_MEDIUM;
    }

    if ($converted_length <= BOX_NOW_LENGTH &&
        $converted_width <= BOX_NOW_WIDTH &&
        $converted_height <= BOX_NOW_LARGE_HEIGHT) {
        return BOX_NOW_COMPARTMENT_LARGE;
    }

    throw new Exception('BOX NOW: Unable to create voucher - Invalid product dimensions');
}

/**
 * @throws Exception
 */
function boxnow_prepare_data($order, bool $calculate_compartment_sizes = false)
{
    $order->save();

    // We need the shipping address for the voucher
    $prep_data = $order->get_address('shipping');

    // Fallback to billing name if shipping name is empty
    if (empty($prep_data['first_name']) && empty($prep_data['last_name'])) {
        $prep_data['first_name'] =  $order->get_billing_first_name();
        $prep_data['last_name'] = $order->get_billing_last_name();
    }

    foreach ($order->get_meta_data() as $data) {
        $meta_key = $data->key;
        $meta_value = $data->value;

        switch ($meta_key) {
            case get_option('boxnow-save-data-addressline1', ''):
                $prep_data['locker_addressline1'] = $meta_value;
                break;
            case get_option('boxnow-save-data-postalcode', ''):
                $prep_data['locker_postalcode'] = (int)$meta_value;
                break;
            case get_option('boxnow-save-data-addressline2', ''):
                $prep_data['locker_addressline2'] = $meta_value;
                break;
            case '_boxnow_locker_id':
                $prep_data['locker_id'] = $meta_value;
                break;
            case '_selected_warehouse':
                $prep_data['selected_warehouse'] = $meta_value;
                break;
        }
    }

    $prep_data['payment_method'] = $order->get_payment_method();
    $prep_data['order_total'] = $order->get_total();
    $prep_data['product_price'] = number_format(strval($order->get_subtotal()), 2, '.', '');
    
    // Only calculate compartment sizes if required(automatic voucher creation on order completion setting)
    if($calculate_compartment_sizes){
        $compartment_sizes = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            // Ensure the dimensions are valid float values. If not, consider them as 0.
            if (!$product || !is_object($product)) {
                $dimensions = [
                    'length' => 0,
                    'width' => 0,
                    'height' => 0
                ];
            } else {
                $length = $product->get_length();
                $width = $product->get_width();
                $height = $product->get_height();
                $dimensions = [
                    'length' => is_numeric($length) ? (float)$length : 0,
                    'width' => is_numeric($width) ? (float)$width : 0,
                    'height' => is_numeric($height) ? (float)$height : 0
                ];
            }

            $compartment_size = boxnow_get_compartment_size($dimensions);
            $quantity = $item->get_quantity();
            for ($i = 0; $i < $quantity; $i++) {
                $compartment_sizes[] = $compartment_size;
            }
        }
        $prep_data['compartment_sizes'] = $compartment_sizes;
    }

    // Ensure the country's prefix is not missing
    // Get the billing address client phone because shipping address does not have phone
    $client_phone = $order->get_billing_phone();
    $tel = $client_phone;

    if (substr($tel, 0, 1) != '+') {
        // If the phone starts with "00", replace "00" with "+"
        if (substr($tel, 0, 2) === '00') {
            $tel = '+' . substr($tel, 2);
        }
        // If the phone starts with the specified codes and has less than 9 digits, put "+357" in the beginning
        elseif (in_array(substr($tel, 0, 2), ['22', '23', '24', '25', '26', '96', '97', '98', '99']) && strlen(preg_replace('/[^\d]/', '', $tel)) < 9) {
            $tel = '+357' . preg_replace('/[^\d]/', '', $tel);
        }
        else {
            $tel = '+30' . preg_replace('/[^\d]/', '', $tel);
        }
    }
    $prep_data['phone'] = $tel;

    // Calculate the weight in kg and pass it
    $weight_kg = 0;
    $wc_weight_unit = get_option('woocommerce_weight_unit');    // kg, g, lbs, oz
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $quantity = $item->get_quantity();
        $product_weight = $product->get_weight();

        // Check if weight is not null and is a numeric value, else consider it as 0
        if (!is_null($product_weight) && is_numeric($product_weight)) {
            // Use only kg as weight unit in delivery requests  
            $product_weight = bndp_convert_weight($product_weight, $wc_weight_unit, 'kg');
            $weight_kg += floatval($product_weight) * $quantity;
        }
    }
    $prep_data['weight'] = $weight_kg;

    return $prep_data;
}

function boxnow_send_delivery_request($prep_data, $order_id, $num_vouchers, $compartment_sizes)
{
    $access_token = boxnow_get_access_token();
    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/delivery-requests';
    $payment_method = $prep_data['payment_method'];
    $send_voucher_via_email = get_option('boxnow_voucher_option', 'button') === 'email';

    // Prepare items array based on the number of vouchers
    $items = [];
    for ($i = 0; $i < $num_vouchers; $i++) {
        $items[] = [
                "value" => $prep_data['product_price'],
                "weight" => $prep_data['weight'],
                "compartmentSize" => $compartment_sizes
        ];
    }

    $order = wc_get_order($order_id);
    // Get the billing address client email because shipping address does not have email
    $client_email = $order->get_billing_email();

    global $wp_version;
    $additional_info =  "PHP " . phpversion() . ", WP "  . $wp_version . ", WC " . WC()->version . ", BNP " . BOX_NOW_DELIVERY_VERSION;
    $data = [
            "notifyOnAccepted" => $send_voucher_via_email ? get_option('boxnow_voucher_email', '') : '',
            "orderNumber" => bndp_create_order_number($order_id),
            "invoiceValue" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
            "paymentMode" => $payment_method === 'cod' ? "cod" : "prepaid",
            "amountToBeCollected" => $payment_method === 'cod' ? number_format($prep_data['order_total'], 2, '.', '') : "0",
            "allowReturn" => boolval(get_option('boxnow_allow_returns', '1')),
            "origin" => [
                    "contactNumber" => get_option('boxnow_mobile_number', ''),
                    "contactEmail" => get_option('boxnow_voucher_email', ''),
                    "locationId" => $prep_data['selected_warehouse'],
            ],
            "destination" => [
                    "contactNumber" => $prep_data['phone'],
                    "contactEmail" => $client_email,
                    "contactName" => $prep_data['first_name'] . ' ' . $prep_data['last_name'],
                    "locationId" => $prep_data['locker_id'],
            ],
            "items" => $items,
            "additionalInformation" => $additional_info
    ];

    $response = wp_remote_post($api_url, [
            'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
    ]);


    if (is_wp_error($response)) {
        return $response->get_error_message();
    } else {
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);
        $response_id = $response_body['id'] ?? null;
        if ($response_id !== null && $response_code !== 401 && (string)$response_id !== '401') {
            $parcel_ids = [];
            foreach ($response_body['parcels'] as $parcel) {
                $parcel_ids[] = $parcel['id'];
            }
            $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
            $order->save();
        } else {
            throw new Exception('BOX NOW: Unable to create vouchers.' . json_encode($response_body));
        }
        return wp_remote_retrieve_body($response);
    }
}

function boxnow_get_access_token()
{
    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/auth-sessions';
    $client_id = get_option('boxnow_client_id', '');
    $client_secret = get_option('boxnow_client_secret', '');

    $response = wp_remote_post($api_url, [
            'headers' => [
                    'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                    'grant_type' => 'client_credentials',
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
            ]),
    ]);

    if (is_wp_error($response)) {
        return $response->get_error_message();
    }

    $json = json_decode(wp_remote_retrieve_body($response), true);

    // Check if the 'access_token' key exists in the response
    if (isset($json['access_token'])) {
        return $json['access_token'];
    } else {
        // Handle the case where the 'access_token' key is not present
        return null;
    }
}

// Refresh the checkout page when the payment method changes
add_action('woocommerce_review_order_before_payment', 'boxnow_add_cod_payment_refresh_script');


// AJAX handler to store locker id in Woo session when selected on the checkout (works for guests too)
function boxnow_set_locker_handler()
{
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if (!$nonce || !wp_verify_nonce($nonce, 'box-now-delivery-nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'), 403);
    }

    $locker_id = isset($_POST['locker_id']) ? sanitize_text_field(wp_unslash($_POST['locker_id'])) : '';

    if ($locker_id) {
        WC()->session->set('boxnow_selected_locker_id', $locker_id);
        wp_send_json_success(array('message' => 'Locker ID saved to session'));
    } else {
        wp_send_json_error(array('message' => 'No locker ID provided'));
    }
}

// AJAX handler for locker selection
add_action('wp_ajax_boxnow_set_locker', 'boxnow_set_locker_handler');
add_action('wp_ajax_nopriv_boxnow_set_locker', 'boxnow_set_locker_handler');
add_action('wp_ajax_bndp_set_boxnow_locker', 'boxnow_set_locker_handler');
add_action('wp_ajax_nopriv_bndp_set_boxnow_locker', 'boxnow_set_locker_handler');


// AJAX handler to remove saved locker id from WooCommerce Session
function boxnow_clear_locker_handler()
{
    WC()->session->set('boxnow_selected_locker_id', null);
    wp_send_json_success(array('message' => 'Locker ID cleared from session'));
}

add_action('wp_ajax_bndp_clear_boxnow_locker', 'boxnow_clear_locker_handler');

// Print Vouchers section
function box_now_delivery_vouchers_input($order)
{
    // Get the order shipping method
    $shipping_methods = $order->get_shipping_methods();
    $box_now_used = false;

    foreach ($shipping_methods as $shipping_method) {
        if ($shipping_method->get_method_id() == 'box_now_delivery') {
            $box_now_used = true;
            break;
        }
    }

    // Only proceed if BOX NOW Delivery was used
    if ($box_now_used) {
        if (get_option('boxnow_voucher_option', 'button') === 'button') {
            // Get the maximum number of vouchers based on the order items
            $max_vouchers = 0;
            foreach ($order->get_items() as $item) {
                $max_vouchers += $item->get_quantity();
            }

            $parcel_ids = $order->get_meta('_boxnow_parcel_ids');
            $vouchers_created = $order->get_meta('_boxnow_vouchers_created');
            $button_disabled = $vouchers_created ? 'disabled' : '';

            // Get the parcel IDs for the current order and pass them to the JavaScript code
            if (!empty($parcel_ids)) {
                echo '<input type="hidden" id="box_now_parcel_ids" value="' . esc_attr(json_encode($parcel_ids ?: [])) . '">';
            }

            // Add the hidden input field for create_vouchers_enabled
            echo '<input type="hidden" id="create_vouchers_enabled" value="true" />';

            echo '<input type="hidden" id="max_vouchers" value="' . esc_attr($max_vouchers) . '">';

            if ($parcel_ids) {
                $links_html = '';
                foreach ($parcel_ids as $parcel_id) {
                    $links_html .= '<button data-parcel-id="' . $parcel_id . '"  class="parcel-id-link box-now-link">&#128196; ' . $parcel_id . '</button>';
                    $links_html .= '<button class="cancel-voucher-btn" data-order-id="' . $order->get_id() . '" style="color: white; background-color: red; border-radius: 4px; margin: 4px 0; border: none; cursor: pointer; padding: 6px 12px; font-size: 13px;">&#9664; Cancel Voucher</button><br>';
                    $tracking_url = 'https://t.boxnow.gr/?track=' . rawurlencode($parcel_id);
                    $links_html .= '<a class="box-now-track-btn" href="' . esc_url($tracking_url) . '" target="_blank" rel="noopener noreferrer" style="color: white; background-color: #f6a623; border-radius: 4px; margin: 4px 0; border: none; cursor: pointer; padding: 6px 12px;max-height:17px; min-width:103px; font-size: 13px; display: inline-block; text-decoration: none;">&#128230; Track Parcel</a><br>';
                }
            } else {
                $links_html = '';
            }
        ?>
            <div class="box-now-vouchers">
                <h4>Create BOX NOW Voucher(s)</h4>
                <p>Vouchers for this order (Max Vouchers: <span style="font-weight: bold; color: red;"><?php echo esc_html($max_vouchers); ?></span>)</p>
                <input type="hidden" id="box_now_order_id" value="<?php echo esc_attr($order->get_id()); ?>" />
                <input pattern="^[1-<?php echo esc_attr($max_vouchers); ?>]$" type="number" id="box_now_voucher_code" name="box_now_voucher_code" min="1" max="<?php echo esc_attr($max_vouchers); ?>" value="1" placeholder="Enter voucher quantity" style="width: 50%;" />
                <!-- Add buttons for each compartment size -->
                <div class="box-now-compartment-size-buttons" style="margin-top: 10px;">
                    <button type="button" id="box_now_create_voucher_small" class="button button-primary" data-compartment-size="small" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;">Create Vouchers (Small)</button>
                    <button type="button" id="box_now_create_voucher_medium" class="button button-primary" data-compartment-size="medium" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;">Create Vouchers (Medium)</button>
                    <button type="button" id="box_now_create_voucher_large" class="button button-primary" data-compartment-size="large" <?php echo esc_attr($button_disabled); ?> style="display: block; margin-bottom: 10px;">Create Vouchers (Large)</button>
                </div>
                <div id="box_now_voucher_link"><?php echo wp_kses_post($links_html); ?></div>
            </div>
            <?php
        }
    }
}
add_action('woocommerce_admin_order_data_after_shipping_address', 'box_now_delivery_vouchers_input', 10, 1);

function box_now_delivery_vouchers_js()
{
    // Enqueue your script here if you haven't already
    wp_enqueue_script('box-now-delivery-js', plugin_dir_url(__FILE__) . 'js/box-now-create-voucher.js', array('jquery'), '1.0', true);

    // Pass the nonce to your script
    wp_localize_script('box-now-delivery-js', 'myAjax', array(
            'nonce' => wp_create_nonce('box-now-delivery-nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
    ));
}
add_action('admin_enqueue_scripts', 'box_now_delivery_vouchers_js');

function boxnow_cancel_voucher_ajax_handler()
{
    // Verify nonce for security
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'box-now-delivery-nonce')) {
        wp_die('Invalid nonce');
    }

    // Check user capabilities
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Permission denied');
        return;
    }

    // Get order ID and parcel ID from the request
    $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
    $parcel_id = isset($_POST['parcel_id']) ? sanitize_text_field(wp_unslash($_POST['parcel_id'])) : '';

    // Check if the order ID is valid
    if ($order_id > 0 && $parcel_id) {
        // Get the order object
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Invalid order ID');
            return;
        }

        // Call the function to cancel the order on the BOX NOW API
        $api_cancellation_result = boxnow_send_cancellation_request($parcel_id);
        if ($api_cancellation_result === 'success') {
            // Call the function to cancel the order in WooCommerce
            boxnow_order_canceled($order_id, '', 'wc-boxnow-canceled', $order);

            // Remove the parcel_id from the parcel_ids array in the order metadata
            $parcel_ids = $order->get_meta('_boxnow_parcel_ids');
            if (($key = array_search($parcel_id, $parcel_ids)) !== false) {
                unset($parcel_ids[$key]);
                $parcel_ids = array_values($parcel_ids); // Reindex the array

                // Add order note to inform user of BOX NOW voucher cancellatioon
                $order->add_order_note('BOX NOW voucher cancelled successfully.', false);
                
                // Update the parcel_ids metadata only if the parcel ID was removed
                $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
                $order->save();
            }

            // Send a success response
            wp_send_json_success();
        } else {
            // Send an error response with the API error message
            wp_send_json_error("BOX NOW API cancellation failed: " . $api_cancellation_result);
        }
    } else {
        // Send an error response
        wp_send_json_error('Invalid order or parcel ID');
    }
}
add_action('wp_ajax_cancel_voucher', 'boxnow_cancel_voucher_ajax_handler');

function boxnow_create_box_now_vouchers_callback()
{
    // Check for the nonce
    check_ajax_referer('box-now-delivery-nonce', 'security');

    // Check user capabilities
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Permission denied');
        return;
    }

    if (!isset($_POST['order_id']) || !isset($_POST['voucher_quantity']) || !isset($_POST['compartment_size'])) {
        wp_send_json_error('Error: Missing required data - order_id, voucher_quantity, and compartment_size are required.');
    }

    $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
    $voucher_quantity = isset($_POST['voucher_quantity']) ? absint(wp_unslash($_POST['voucher_quantity'])) : 0;
    $compartment_size = isset($_POST['compartment_size']) ? absint(wp_unslash($_POST['compartment_size'])) : 0;

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Error: Order not found.');
    }

    try {
        $prep_data = boxnow_prepare_data($order);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }

    try {
        $delivery_request_response = boxnow_send_delivery_request($prep_data, $order_id, $voucher_quantity, $compartment_size);
        $response_body = json_decode($delivery_request_response, true);
        if (isset($response_body['id'])) {
            $parcel_ids = $order->get_meta('_boxnow_parcel_ids', true);
            if (!$parcel_ids) {
                $parcel_ids = [];
            }
            // Save the new parcel ids in the meta data
            foreach ($response_body['parcels'] as $parcel) {
                $parcel_ids[] = $parcel['id'];
            }
            $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
            $order->update_meta_data('_boxnow_vouchers_created', 1);
            $order->save();

            // Add order note to inform user of BOX NOW voucher number creation
            $order->add_order_note('BOX NOW voucher numbers created successfully: ' . implode(', ', $parcel_ids), false);

            // check if there are any parcel ids after the update
            $parcel_ids = $order->get_meta('_boxnow_parcel_ids', true);
            if (!$parcel_ids || count($parcel_ids) == 0) {
                throw new Exception('BOX NOW: No parcel ids available. API response: ' . json_encode($response_body));
            }
        } else {
            throw new Exception('BOX NOW: Unable to create vouchers. API response: ' . json_encode($response_body));
        }
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }

    if ($parcel_ids) {
        $new_parcel_ids = array_slice($parcel_ids, -$voucher_quantity); // Get the new parcel IDs
        wp_send_json_success(array('new_parcel_ids' => $new_parcel_ids));
    } else {
        throw new Exception('BOX NOW: Unable to create vouchers. API response: ' . json_encode($response_body));
    }
}
add_action('wp_ajax_create_box_now_vouchers', 'boxnow_create_box_now_vouchers_callback');

function boxnow_print_box_now_voucher_callback()
{
    // Check user capabilities
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Permission denied');
    }

    // Check for the nonce
    check_ajax_referer('box-now-delivery-nonce', 'security');

    if (!isset($_GET['parcel_id'])) {
        wp_die('Error boxnow_print_box_now_voucher_callback: Missing required parameter - parcel_id');
    }
    if (!isset($_GET['order_id'])) {
        wp_die('Error boxnow_print_box_now_voucher_callback: Missing required parameter - order_id');
    }

    $parcel_id = sanitize_text_field(wp_unslash($_GET['parcel_id']));
    $order_id = sanitize_text_field(wp_unslash($_GET['order_id']));

    // Make sure the order exists
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die('Error boxnow_print_box_now_voucher_callback: Order not found');
    }

    try {
        boxnow_print_voucher_pdf($parcel_id);
    } catch (Exception $e) {
        wp_die(
            esc_html(
                sprintf(
                    /* translators: %s: error message. */
                    __('Error: %s', 'box-now-delivery'),
                    $e->getMessage()
                )
            )
        );
    }

    exit();
}
add_action('wp_ajax_print_box_now_voucher', 'boxnow_print_box_now_voucher_callback');

/**
 * Add voucher email validation script to the admin footer.
 */
function boxnow_voucher_email_validation()
{
    if (is_admin()) { // Assuming this is only relevant in the admin area
        ?>
        <script>
            function isValidEmail(email) {
                const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(email.toLowerCase());
            }

            function displayEmailValidationMessage(message) {
                const messageContainer = document.getElementById('email_validation_message');
                messageContainer.textContent = message;
            }

            document.addEventListener('DOMContentLoaded', function() {
                const emailInput = document.querySelector('input[name="boxnow_voucher_email"]');

                if (emailInput) {
                    emailInput.addEventListener('input', function() {
                        if (!isValidEmail(emailInput.value)) {
                            displayEmailValidationMessage('Please use a valid email address!');
                        } else {
                            displayEmailValidationMessage('');
                        }
                    });
                } else {
                    console.warn("Email input element not found.");
                }
            });
        </script>
<?php
    }
}
add_action('admin_footer', 'boxnow_voucher_email_validation');

add_action('admin_enqueue_scripts', 'boxnow_load_jquery_in_admin');
function boxnow_load_jquery_in_admin()
{
    // Enqueue jQuery in the admin panel (although it's already included by default, it's fine to add it again)
    wp_enqueue_script('jquery');

    // Enqueue your custom JS script
    wp_enqueue_script(
        'box-now-delivery-admin-selector', // Handle for the script
        plugin_dir_url(__FILE__) . 'js/box-now-delivery-admin-selector.js', // Path to the JS file
        array('jquery'), // Dependencies (jQuery is included)
        BOX_NOW_DELIVERY_VERSION,
        true // Load script in the footer (recommended for performance)
    );
    $button_color = esc_attr(get_option('boxnow_button_color', '#6CD04E '));
    $button_text = esc_attr(get_option('boxnow_button_text', 'Pick a Locker'));

    wp_localize_script('box-now-delivery-js', 'boxNowDeliverySettings', array(
        'partnerId' => esc_attr(get_option('boxnow_partner_id', '')),
        'embeddedIframe' => esc_attr(get_option('embedded_iframe', '')),
        'displayMode' => esc_attr(get_option('box_now_display_mode', 'popup')),
        'buttonColor' => $button_color,
        'buttonText' => $button_text,
        'lockerNotSelectedMessage' => esc_js(get_option("boxnow_locker_not_selected_message", "Please select a locker first!")),
        'gps_option' => get_option('boxnow_gps_tracking', 'on')

    ));
}

/**
 * @throws InvalidArgumentException
 */
function bndp_convert_weight(float $value, string $from, string $to): float {
    // Normalize unit strings
    $from = strtolower($from);
    $to   = strtolower($to);

    // Conversion factors to kilograms
    $to_kg = [
        'kg'  => 1,
        'g'   => 0.001,
        'lbs' => 0.45359237,
        'oz'  => 0.028349523125,
    ];

    if (!isset($to_kg[$from]) || !isset($to_kg[$to])) {
        throw new InvalidArgumentException(
            sprintf(
                'BOX NOW: convert_weight() - Invalid weight unit "%1$s" or "%2$s".',
                sanitize_key($from),
                sanitize_key($to)
            )
        );
    }

    // Convert to kg, then to target unit
    $kg = $value * $to_kg[$from];
    return $kg / $to_kg[$to];
}

/**
 * @throws InvalidArgumentException
 */
function bndp_convert_dimension_to_cm(float $value, string $from, string $to): float {
    // Normalize units to lowercase
    $from = strtolower($from);
    $to   = strtolower($to);

    // Conversion factors to CENTIMETERS
    $to_cm = [
        'mm' => 0.1,        // 1 mm = 0.1 cm
        'cm' => 1,          // base unit
        'm'  => 100,        // 1 m = 100 cm
        'in' => 2.54,       // 1 inch = 2.54 cm
        'yd' => 91.44,      // 1 yard = 91.44 cm
        'ft' => 30.48,      // 1 foot = 30.48 cm
    ];

    if (!isset($to_cm[$from]) || !isset($to_cm[$to])) {
        throw new InvalidArgumentException(
            sprintf(
                'BOX NOW: convert_dimension_to_cm() - Invalid dimension unit "%1$s" or "%2$s".',
                sanitize_key($from),
                sanitize_key($to)
            )
        );
    }

    // Convert to CENTIMETERS, then to target unit
    $cms = $value * $to_cm[$from];
    // Format string to two decimals
    $result = number_format($cms/$to_cm[$to], 2, '.', '');
    return (float) $result;
}

/**
 * Display locker info or selection button on Thank You page
 * Enqueue JS for locker selection after payment
 * Add AJAX handler to update locker meta
 */

if (get_option('boxnow_thankyou_page', '1') == '1') {
// Show locker info or prompt on Thank You page 
add_action('woocommerce_thankyou', 'boxnow_thankyou_locker_ui', 20);
function boxnow_thankyou_locker_ui($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) return;

    $shipping_methods = $order->get_shipping_methods();
    $shipping_country = $order->get_shipping_country();
    $billing_country = $order->get_billing_country();

    if (!empty($shipping_methods)) {
        foreach ($shipping_methods as $method) {
            $carrier_name = $method->get_name();
            $carrier_id = $method->get_method_id();
            break;
        }
    } else {
        $carrier_id = 'No shipping method found';
    }

    if ($carrier_id === 'box_now_delivery') {
        $locker_id = $order->get_meta('_boxnow_locker_id');

        echo '<div class="boxnow-thankyou" style="margin:20px 0; padding:15px; border:1px solid #ddd; border-radius:8px; text-align:center;clear: both;">';

        if (!empty($locker_id)) {
            echo '<h3>Your BOX NOW Locker Selection</h3>';
            echo '<p><strong>Locker ID:</strong> ' . esc_html($locker_id) . '</p>';
            echo '<p>You’re all set! Your order will be delivered to the selected locker.</p>';
            echo '<a href="#" id="box_now_delivery_button" style="display:inline-block; margin-top:10px; padding:10px 20px; background:#44d62d; color:#fff; font-weight:bold; text-decoration:none; border-radius:5px;">Choose a different Locker</a>';
        } else {
            echo '<h3>Did you select a locker?</h3>';
            echo '<p>No locker is selected yet. Choose one now for fast delivery!</p>';
            echo '<a href="#" id="box_now_delivery_button" style="display:inline-block; margin-top:10px; padding:10px 20px; background:#44d62d; color:#fff; font-weight:bold; text-decoration:none; border-radius:5px;">Choose Locker</a>';

            // Modal HTML + JavaScript + CSS
            echo '
            <div id="boxnow-modal" class="boxnow-modal">
                <div class="boxnow-modal-content">
                    <span class="boxnow-close">&times;</span>
                    <h3>No Locker Selected</h3>
                    <p>Please choose a locker to complete your delivery.</p>
                    <a href="#" id="boxnow-modal-button" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#44d62d; color:#fff; font-weight:bold; text-decoration:none; border-radius:5px;">Choose Locker</a>
                </div>
            </div>

            <style>
                .boxnow-modal {
                    display: block;
                    position: fixed;
                    z-index: 1000;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    overflow: auto;
                    background-color: rgba(0, 0, 0, 0.5);
                }
                .boxnow-modal-content {
                    background-color: #fff;
                    margin: 10% auto;
                    padding: 20px;
                    border: 1px solid #ccc;
                    border-radius: 8px;
                    width: 90%;
                    max-width: 400px;
                    text-align: center;
                }
                .boxnow-close {
                    color: #aaa;
                    float: right;
                    font-size: 28px;
                    font-weight: bold;
                    cursor: pointer;
                }
                .boxnow-close:hover {
                    color: #000;
                }
            </style>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    var modal = document.getElementById("boxnow-modal");
                    var closeBtn = document.querySelector(".boxnow-close");
                    var modalBtn = document.getElementById("boxnow-modal-button");
                    var mainBtn = document.getElementById("box_now_delivery_button");

                    // Close modal on X
                    closeBtn.onclick = function() {
                        modal.style.display = "none";
                    };

                    // Clicking button in modal triggers main Choose Locker button
                    modalBtn.onclick = function(e) {
                        e.preventDefault();
                        modal.style.display = "none";
                        mainBtn.click(); // Simulate main button click
                    };

                    // Close modal when clicking outside the modal
                    window.onclick = function(event) {
                        if (event.target === modal) {
                            modal.style.display = "none";
                        }
                    };
                });
            </script>
            ';
        }

        echo '</div>';
        echo '<input type="hidden" id="carrier_name" value="' . esc_attr($carrier_name) . '">';
        echo '<input type="hidden" id="shipping_country" value="' . esc_attr($shipping_country) . '">';
        echo '<input type="hidden" id="billing_country" value="' . esc_attr($billing_country) . '">';
    }
}

add_action('wp_ajax_thankyou_php_boxnow', 'bndp_thankyou_php_boxnow');
add_action('wp_ajax_nopriv_thankyou_php_boxnow', 'bndp_thankyou_php_boxnow');


function bndp_thankyou_php_boxnow() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if (!$nonce || !wp_verify_nonce($nonce, 'box-now-delivery-nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce.'], 403);
    }

    $order_id_raw = isset($_POST['order_id']) ? sanitize_text_field(wp_unslash($_POST['order_id'])) : '';

    // Check if order_id is set and valid
    if ('' === $order_id_raw || !ctype_digit($order_id_raw)) {
        wp_send_json_error(['message' => 'No or invalid order ID found.']);
    }

    $order_key = isset($_POST['order_key']) ? sanitize_text_field(wp_unslash($_POST['order_key'])) : '';

    // Check if order_key is set
    if (empty($order_key)) {
        wp_send_json_error(['message' => 'No order key found.']);
    }

    $locker_id = isset($_POST['_boxnow_locker_id']) ? sanitize_text_field(wp_unslash($_POST['_boxnow_locker_id'])) : '';

    // Retrieve and sanitize POST data
    $order_id = (int) $order_id_raw;

    // Get the order object
    $order = wc_get_order($order_id);

    // Check if order exists
    if (!$order) {
        wp_send_json_error(['message' => 'Order not found.']);
    }

    // Check if locker_id is valid and order key matches
    if (!empty($locker_id) && ($order->get_order_key() === $order_key) && $order->get_id() === $order_id) {
        // Update locker ID in order meta
        $order->update_meta_data('_boxnow_locker_id', $locker_id);
        $order->save();
        // Verify if meta update is successful
        $verify = $order->get_meta('_boxnow_locker_id');
        if ($verify === $locker_id) {
            // Clear session value after saving
            WC()->session->set('boxnow_selected_locker_id', null);
            wp_send_json_success(['message' => 'Locker ID saved successfully.', 'saved_value' => $order_id]);
        } else {
            wp_send_json_error(['message' => 'Meta update failed. Value mismatch.', 'attempted' => $locker_id, 'actual' => $verify]);
        }
    } else {
        wp_send_json_error(['message' => 'No locker ID provided or invalid.']);
    }
}

    // Enqueue JS only on Thank You page
    add_action('wp_enqueue_scripts', function () {
        if (is_order_received_page()) {
            wp_enqueue_script('box-now-ty', plugin_dir_url(__FILE__) . 'js/box-now-ty.js', ['jquery'], '1.0', true);
            $settings = array(
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('box-now-delivery-nonce'),
                        'order_id' => get_query_var('order-received'));
            wp_localize_script('box-now-ty', 'thankyou_boxnow', $settings);
            
            // Force-display the 'select a locker' BOX NOW button on thank you page after every Blocks render 
            // (hotfix for certain themes like storefront that hide the button by default when using Blocks)
            wp_add_inline_script( 
                'wc-blocks-checkout', 
                "
                function forceShowCustomButton() {
                    const btn = document.getElementById('box_now_delivery_button');
                    if (btn) {
                        btn.style.display = 'inline-block';
                        btn.style.visibility = 'visible';
                        btn.style.opacity = '1';
                    }
                }

                document.addEventListener('DOMContentLoaded', forceShowCustomButton);

                // Key: Fired every time WC Blocks re-renders Thank You page
                document.addEventListener('wc-blocks-checkout-render', forceShowCustomButton);

                // Safety: In case Blocks re-renders outside the event
                setInterval(forceShowCustomButton, 200);
                "
            );
        }
    });

}
