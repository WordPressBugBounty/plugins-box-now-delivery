<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register custom order status
add_action('init', 'boxnow_register_boxnow_canceled_order_status');
// Add custom order status to WooCommerce
//add_filter('wc_order_statuses', 'boxnow_add_canceled_order_status');
add_filter('woocommerce_admin_order_actions', 'boxnow_add_cancel_order_button', 10, 2);
add_action('admin_head', 'boxnow_add_cancel_order_button_css');
// Add the action for sending a cancellation request to the BOX NOW API
add_action('woocommerce_order_status_changed', 'boxnow_order_canceled', 5, 4);

function boxnow_register_boxnow_canceled_order_status()
{
    register_post_status('wc-boxnow-canceled', array(
        'label' => __('Box Now Canceled', 'box-now-delivery'),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        /* translators: %s: number of orders with BOX NOW canceled status. */
        'label_count' => _n_noop('Box Now Canceled <span class="count">(%s)</span>', 'Box Now Canceled <span class="count">(%s)</span>', 'box-now-delivery')
    ));
}

function boxnow_add_cancel_order_button($actions, $order)
{
    if ($order->has_status(array('completed'))) {
        $actions['boxnow_cancel'] = array(
            'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woocommerce_mark_order_status&status=wc-boxnow-canceled&order_id=' . $order->get_id()), 'woocommerce-mark-order-status'),
            'name' => __('Cancel Order', 'box-now-delivery'),
            'action' => "boxnow_cancel",
        );
    }
    return $actions;
}

function boxnow_add_cancel_order_button_css()
{
    echo '<style>
          .wc-action-button-boxnow_cancel::after {
            content: "\f153";
            color: #a00;
          }
          </style>';
}

function boxnow_order_canceled($order_id, $old_status, $new_status, $order)
{
    // Check if the new status is "wc-boxnow-canceled" or "boxnow-canceled"
    if ($new_status != 'wc-boxnow-canceled' && $new_status != 'boxnow-canceled') {
        return;
    }

    if ($order->has_shipping_method('box_now_delivery')) {
        $parcel_id = $order->get_meta('_boxnow_parcel_id');

        if (!empty($parcel_id)) {
            $result = boxnow_send_cancellation_request($parcel_id);
        }
    }
}

function boxnow_send_cancellation_request($parcel_id)
{
    $access_token = boxnow_get_access_token();
    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/parcels/' . $parcel_id . ':cancel';

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ],
        'body' => '{}',
    ]);

    if (is_wp_error($response)) {
        return $response->get_error_message();
    } else {
        $response_body = wp_remote_retrieve_body($response);
        // Check for empty response and treat as success
        if (empty($response_body)) {
            return 'success';
        }

        $response_data = json_decode($response_body, true);
        if (isset($response_data['success']) && $response_data['success'] == true) {
            return 'success';
        } else {
            return 'failed';
        }
    }
}
