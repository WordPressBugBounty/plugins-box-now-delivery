<?php
/*
Plugin Name: BOX NOW Delivery
Description: A Wordpress plugin from BOX NOW to integrate your eshop with our services.
Author: BOX NOW
Text Domain: box-now-delivery
Version: 3.0.2
*/

// Cancel order API call file
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-cancel-order.php';

// Include the box-now-delivery-print-order.php file
require_once plugin_dir_path(__FILE__) . 'includes/box-now-delivery-print-order.php';

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    // Include custom shipping method file
    include(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-shipping-method.php');

    // Include admin page functions
    include(plugin_dir_path(__FILE__) . 'includes/box-now-delivery-admin-page.php');

    /**
     * Enqueue scripts and styles for Box Now Delivery plugin.
     */
    function box_now_delivery_enqueue_scripts()
    {
        if (is_checkout() || is_order_received_page()) {
            $button_color = esc_attr(get_option('boxnow_button_color', '#6CD04E '));
            $button_text = esc_attr(get_option('boxnow_button_text', 'Pick a Locker'));
            $page = is_order_received_page() ? "thankyou_page" : "checkout";

            wp_enqueue_script('box-now-delivery-js', plugin_dir_url(__FILE__) . 'js/box-now-delivery.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('box-now-delivery-css', plugins_url('/css/box-now-delivery.css', __FILE__));

            $settings = array(
                'partnerId' => esc_attr(get_option('boxnow_partner_id', '')),
                'embeddedIframe' => esc_attr(get_option('embedded_iframe', '')),
                'displayMode' => esc_attr(get_option('box_now_display_mode', 'popup')),
                'buttonColor' => $button_color,
                'buttonText' => $button_text,
                'lockerNotSelectedMessage' => esc_js(get_option('boxnow_locker_not_selected_message', 'Please select a locker first!')),
                'gps_option' => get_option('boxnow_gps_tracking', 'on'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'page' => $page
            );

            wp_localize_script('box-now-delivery-js', 'boxNowDeliverySettings', $settings);

            // If WooCommerce Blocks checkout is present, enqueue the Blocks-specific script
            if (wp_script_is('wc-blocks-checkout', 'registered')) {
                wp_enqueue_script('box-now-delivery-blocks-js', plugin_dir_url(__FILE__) . 'js/box-now-delivery-blocks.js', array(), '1.0.0', true);
                wp_localize_script('box-now-delivery-blocks-js', 'boxNowDeliverySettings', $settings);
            }
        }
    }
    add_action('wp_enqueue_scripts', 'box_now_delivery_enqueue_scripts');

    /**
     * Enqueue data for WooCommerce Blocks checkout (ensures settings available to blocks context).
     */
    function bndp_add_boxnow_data_to_blocks() {
        // Only proceed if Blocks is available
        if (!function_exists('wc_current_theme_is_fse_theme') || !wc_current_theme_is_fse_theme()) {
            // Still enqueue the script if blocks are used via shortcode in non-FSE themes
            if (wp_script_is('wc-blocks-checkout', 'registered')) {
                wp_enqueue_script('box-now-delivery-blocks-js', plugin_dir_url(__FILE__) . 'js/box-now-delivery-blocks.js', array(), '1.0.0', true);
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
            'ajaxUrl' => admin_url('admin-ajax.php'),
        );
        wp_enqueue_script('box-now-delivery-blocks-js', plugin_dir_url(__FILE__) . 'js/box-now-delivery-blocks.js', array(), '1.0.0', true);
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
                'label' => __('BOX NOW Locker ID', 'woocommerce'),
                'placeholder' => _x('BOX NOW Locker ID', 'placeholder', 'woocommerce'),
                'required' => false,
                'class' => array('boxnow-form-row-hidden', 'boxnow-locker-id-field'),
                'clear' => true
        );
        return $fields;
    }
    // Add a custom field to retrieve the Locker ID from the checkout page
    add_filter('woocommerce_checkout_fields', 'bndp_box_now_delivery_custom_override_checkout_fields');

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
    function check_order_received_page ()
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

    add_action('wp_footer', 'check_order_received_page');

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

        // Only proceed if Box Now Delivery was used
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
            <div class="boxnow_data_column">
                <h4><?php echo esc_html__('box-now-delivery', 'woocommerce'); ?><a href="#" class="edit_address"><?php echo esc_html__('Edit', 'woocommerce'); ?></a></h4>
                <div class="address">
                    <?php
                    echo '<p><strong>' . esc_html__('Locker ID') . ':</strong>' . esc_html($locker_id) . '</p>';
                    echo '<p><strong>' . esc_html__('Warehouse ID') . ':</strong>' . esc_html($warehouse_id) . ' - ' . esc_html($warehouse_names[$warehouse_id]) . '</p>';
                    ?>
                </div>
                <div class="edit_address">
                    <?php
                    woocommerce_wp_text_input(array(
                        'id' => '_boxnow_locker_id',
                        'label' => esc_html__('Locker ID'),
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
                    woocommerce_wp_select(array('id' => '_selected_warehouse', 'label' => esc_html__('Warehouse ID'), 'wrapper_class' => '_selected_warehouse', 'options' => $warehouse_options));
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

        // Ensure we have an order and the required POST data
        if (!isset($order) || !isset($_POST['_boxnow_locker_id']) || !isset($_POST['_selected_warehouse'])) {
            return;
        }

        $order->update_meta_data('_boxnow_locker_id', sanitize_text_field($_POST['_boxnow_locker_id']));
        $order->update_meta_data('_selected_warehouse', sanitize_text_field($_POST['_selected_warehouse']));
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

        // Attempt to get locker data from POST (JSON format)
        if (!empty($_POST['box_now_selected_locker'])) {
            $locker_data = json_decode(stripslashes($_POST['box_now_selected_locker']), true);
            if (is_array($locker_data) && !empty($locker_data['boxnowLockerId'])) {
                $locker_id = sanitize_text_field($locker_data['boxnowLockerId']);
            }
        }

        // Fallback: Try direct POST field
        if (empty($locker_id) && !empty($_POST['_boxnow_locker_id'])) {
            $locker_id = sanitize_text_field($_POST['_boxnow_locker_id']);
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
            <p><?php _e('BOX NOW Delivery requires WooCommerce to be installed and active.', 'box-now-delivery'); ?></p>
        </div>
        <?php
    }

    add_action('admin_notices', 'bndp_box_now_delivery_admin_notice');
}

/**
 * Change Cash on delivery title to custom
 */
add_filter('woocommerce_gateway_title', 'bndp_change_cod_title_for_box_now_delivery', 20, 2);

function bndp_change_cod_title_for_box_now_delivery($title, $payment_id)
{
    if (!is_admin() && $payment_id === 'cod') {
        if (function_exists('WC') && WC()->session) {
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
            $box_now_delivery_method = 'box_now_delivery';

            if (is_array($chosen_shipping_methods) && in_array($box_now_delivery_method, $chosen_shipping_methods)) {
                $title = __('BOX NOW PAY ON THE GO!', 'woocommerce');
            }
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
        // Check if the voucher has already been created
        if ($order->get_meta('_voucher_created', true)) {
            return;
        }

        $prep_data = boxnow_prepare_data($order);
        $response = boxnow_order_completed_delivery_request($prep_data, $order->get_id(), 1);
        $response_data = json_decode($response, true);

        // Log the full order object after we call the functions
        $order = wc_get_order($order_id);

        if (isset($response_data['parcels'][0]['id'])) {
            $order->update_meta_data('_boxnow_parcel_id', $response_data['parcels'][0]['id']);
            // Set the flag to indicate that the voucher has been created
            $order->update_meta_data('_voucher_created', 'yes');
            $order->save();
        }
    }
}

// This is the delivery request only for the boxnow_order_completed function
function boxnow_order_completed_delivery_request($prep_data, $order_id, $num_vouchers)
{
    $access_token = boxnow_get_access_token();
    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/delivery-requests';
    $randStr = strval(mt_rand());
    $payment_method = $prep_data['payment_method'];
    $send_voucher_via_email = get_option('boxnow_voucher_option', 'button') === 'email';

    for ($i = 0; $i < $num_vouchers; $i++) {
        $item_data = [
                "value" => $prep_data['product_price'],
                "weight" => $prep_data['weight']
        ];

        if (isset($prep_data['compartment_sizes'])) {
            $item_data["compartmentSize"] = $prep_data['compartment_sizes'][0];
        }

        $items[] = $item_data;
    }

    $order = wc_get_order($order_id);
    // Get the billing address client email because shipping address does not have email
    $client_email = $order->get_billing_email();

    $data = [
            "notifyOnAccepted" => $send_voucher_via_email ? get_option('boxnow_voucher_email', '') : '',
            "orderNumber" => $randStr,
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
            "items" => $items
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
        if (isset($response_body['id'])) {
            $parcel_ids = [];
            foreach ($response_body['parcels'] as $parcel) {
                $parcel_ids[] = $parcel['id'];
            }
            $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
            $order->save();
        } else {
            throw new Exception('Error: Unable to create vouchers.' . json_encode($response_body));
        }
        return wp_remote_retrieve_body($response);
    }
}

// Function to determine the compartment size based on dimensions
function boxnow_get_compartment_size($dimensions)
{
    // Define the dimensions for each compartment size
    $small = ['length' => 60, 'width' => 45, 'height' => 8];
    $medium = ['length' => 60, 'width' => 45, 'height' => 17];
    $large = ['length' => 60, 'width' => 45, 'height' => 36];

    // Check if all dimensions are either not set or equal to 0
    if ((!isset($dimensions['length']) || $dimensions['length'] == 0) &&
            (!isset($dimensions['width']) || $dimensions['width'] == 0) &&
            (!isset($dimensions['height']) || $dimensions['height'] == 0)
    ) {
        // Return the default compartment size
        return 2;
    }

    // Check if the product dimensions fit the small compartment size
    if (
            $dimensions['length'] <= $small['length'] &&
            $dimensions['width'] <= $small['width'] &&
            $dimensions['height'] <= $small['height']
    ) {
        return 1;
    }

    // Check if the product dimensions fit the medium compartment size
    if (
            $dimensions['length'] <= $medium['length'] &&
            $dimensions['width'] <= $medium['width'] &&
            $dimensions['height'] <= $medium['height']
    ) {
        return 2;
    }

    // Check if the product dimensions fit the large compartment size
    if (
            $dimensions['length'] <= $large['length'] &&
            $dimensions['width'] <= $large['width'] &&
            $dimensions['height'] <= $large['height']
    ) {
        return 3;
    }

    // If the product dimensions don't fit any of the compartment sizes, return an error
    throw new Exception('Invalid product dimensions.');
}

function boxnow_prepare_data($order)
{
    // Update possibly edited fields
    if (isset($_POST['_boxnow_locker_id']) && !empty($_POST['_boxnow_locker_id'])) {
        $order->update_meta_data('_boxnow_locker_id', wc_clean($_POST['_boxnow_locker_id']));
    }

    if (isset($_POST['_selected_warehouse']) && !empty($_POST['_selected_warehouse'])) {
        $order->update_meta_data('_selected_warehouse', wc_clean($_POST['_selected_warehouse']));
    }

    $order->save();

    // We need the shipping address for the voucher
    $prep_data = $order->get_address('shipping');

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

    $compartment_sizes = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();

        // Ensure the dimensions are valid float values. If not, consider them as 0.
        $dimensions = [
                'length' => is_numeric($product->get_length()) ? floatval($product->get_length()) : 0,
                'width' => is_numeric($product->get_width()) ? floatval($product->get_width()) : 0,
                'height' => is_numeric($product->get_height()) ? floatval($product->get_height()) : 0
        ];

        $compartment_size = boxnow_get_compartment_size($dimensions);
        $quantity = $item->get_quantity();
        for ($i = 0; $i < $quantity; $i++) {
            $compartment_sizes[] = $compartment_size;
        }
    }
    $prep_data['compartment_sizes'] = $compartment_sizes;


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

    // Calculate the weight and pass it
    $weight = 0;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $quantity = $item->get_quantity();
        $product_weight = $product->get_weight();

        // Check if weight is not null and is a numeric value, else consider it as 0
        if (!is_null($product_weight) && is_numeric($product_weight)) {
            $weight += floatval($product_weight) * $quantity;
        }
    }
    $prep_data['weight'] = $weight;

    return $prep_data;
}

function boxnow_send_delivery_request($prep_data, $order_id, $num_vouchers, $compartment_sizes)
{
    $access_token = boxnow_get_access_token();
    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/delivery-requests';
    $randStr = strval(mt_rand());
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

    $data = [
            "notifyOnAccepted" => $send_voucher_via_email ? get_option('boxnow_voucher_email', '') : '',
            "orderNumber" => $randStr,
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
            "items" => $items
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
        if (isset($response_body['id'])) {
            $parcel_ids = [];
            foreach ($response_body['parcels'] as $parcel) {
                $parcel_ids[] = $parcel['id'];
            }
            $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
            $order->save();
        } else {
            throw new Exception('Error: Unable to create vouchers.' . json_encode($response_body));
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
    if (isset($_POST['locker_id'])) {
        WC()->session->set('boxnow_selected_locker_id', sanitize_text_field($_POST['locker_id']));
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

    // Only proceed if Box Now Delivery was used
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
                    $links_html .= '<a href="#" data-parcel-id="' . $parcel_id . '" class="parcel-id-link box-now-link">&#128196; ' . $parcel_id . '</a> ';
                    $links_html .= '<button class="cancel-voucher-btn" data-order-id="' . $order->get_id() . '" style="color: white; background-color: red; border-radius: 4px; margin: 4px 0; border: none; cursor: pointer; padding: 6px 12px; font-size: 13px;">&#9664; Cancel Voucher</button><br>';
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
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'box-now-delivery-nonce')) {
        wp_die('Invalid nonce');
    }

    // Get order ID and parcel ID from the request
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $parcel_id = isset($_POST['parcel_id']) ? sanitize_text_field($_POST['parcel_id']) : '';

    // Check if the order ID is valid
    if ($order_id > 0 && $parcel_id) {
        // Get the order object
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Invalid order ID');
            return;
        }

        // Call the function to cancel the order on the Box Now API
        $api_cancellation_result = boxnow_send_cancellation_request($parcel_id);
        if ($api_cancellation_result === 'success') {
            // Call the function to cancel the order in WooCommerce
            boxnow_order_canceled($order_id, '', 'wc-boxnow-canceled', $order);

            // Remove the parcel_id from the parcel_ids array in the order metadata
            $parcel_ids = $order->get_meta('_boxnow_parcel_ids');
            if (($key = array_search($parcel_id, $parcel_ids)) !== false) {
                unset($parcel_ids[$key]);
                $parcel_ids = array_values($parcel_ids); // Reindex the array

                // Update the parcel_ids metadata only if the parcel ID was removed
                $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);
                $order->save();
            }

            // Send a success response
            wp_send_json_success();
        } else {
            // Send an error response with the API error message
            wp_send_json_error("Box Now API cancellation failed: " . $api_cancellation_result);
        }
    } else {
        // Send an error response
        wp_send_json_error('Invalid order or parcel ID');
    }
}
add_action('wp_ajax_cancel_voucher', 'boxnow_cancel_voucher_ajax_handler');
add_action('wp_ajax_nopriv_cancel_voucher', 'boxnow_cancel_voucher_ajax_handler');

function boxnow_create_box_now_vouchers_callback()
{
    // Check for the nonce
    check_ajax_referer('box-now-delivery-nonce', 'security');

    if (!isset($_POST['order_id']) || !isset($_POST['voucher_quantity']) || !isset($_POST['compartment_size'])) {
        wp_send_json_error('Error: Missing required data.');
    }

    $order_id = intval($_POST['order_id']);
    $voucher_quantity = intval($_POST['voucher_quantity']);
    $compartment_size = intval(sanitize_text_field($_POST['compartment_size']));

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Error: Order not found.');
    }
    $prep_data = boxnow_prepare_data($order);

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

                // Save the order ID in the parcel's metadata
                update_option('_boxnow_parcel_order_id_' . $parcel['id'], $order_id);
            }
            $order->update_meta_data('_boxnow_parcel_ids', $parcel_ids);

            $order->update_meta_data('_boxnow_vouchers_created', 1);
            $order->save();

            // check if there are any parcel ids after the update
            $parcel_ids = $order->get_meta('_boxnow_parcel_ids', true);
            if (!$parcel_ids || count($parcel_ids) == 0) {
                throw new Exception('Error: No parcel ids available. API response: ' . json_encode($response_body));
            }
        } else {
            throw new Exception('Error: Unable to create vouchers. API response: ' . json_encode($response_body));
        }
    } catch (Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }

    if ($parcel_ids) {
        $new_parcel_ids = array_slice($parcel_ids, -$voucher_quantity); // Get the new parcel IDs
        wp_send_json_success(array('new_parcel_ids' => $new_parcel_ids));
    } else {
        throw new Exception('Error: Unable to create vouchers. API response: ' . json_encode($response_body));
    }
}
add_action('wp_ajax_create_box_now_vouchers', 'boxnow_create_box_now_vouchers_callback');

function boxnow_print_box_now_voucher_callback()
{
    if (!isset($_GET['parcel_id'])) {
        wp_die('Error: Missing required data.');
    }

    $parcel_id = sanitize_text_field($_GET['parcel_id']);

    // Retrieve the order ID from the parcel ID's metadata
    $order_id = get_option('_boxnow_parcel_order_id_' . $parcel_id);

    if (!$order_id) {
        wp_die('Error: Order not found.');
    }

    $order = wc_get_order($order_id);

    if (!$order) {
        wp_die('Error: Order not found.');
    }

    try {
        boxnow_print_voucher_pdf($parcel_id);
    } catch (Exception $e) {
        wp_die('Error: ' . $e->getMessage());
    }

    exit();
}
add_action('wp_ajax_print_box_now_voucher', 'boxnow_print_box_now_voucher_callback');
add_action('wp_ajax_nopriv_print_box_now_voucher', 'boxnow_print_box_now_voucher_callback');

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
        null, // Version number (you can set this to a version, or null to disable versioning)
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
        'gps_option' => get_option('boxnow_gps_tracking', 'on'),
    ));
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
        $carrier_name = 'No shipping method found';
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

add_action('wp_ajax_thankyou_php_boxnow', 'thankyou_php_boxnow');
add_action('wp_ajax_nopriv_thankyou_php_boxnow', 'thankyou_php_boxnow');


function thankyou_php_boxnow() {
    // Check if order_id is set and valid
    if (empty($_POST['order_id']) || !ctype_digit($_POST['order_id'])) {
        wp_send_json_error(['message' => 'No or invalid order ID found.']);
    }

    // Check if order_key is set
    if (empty($_POST['order_key'])) {
        wp_send_json_error(['message' => 'No order key found.']);
    }

    // Retrieve and sanitize POST data
    $order_id = intval($_POST['order_id']);
    $order_key = sanitize_text_field($_POST['order_key']);
    $locker_id_raw = $_POST['_boxnow_locker_id'];
    $locker_id = sanitize_text_field($locker_id_raw);

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