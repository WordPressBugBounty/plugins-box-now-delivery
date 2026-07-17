<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register custom order status
add_action('init', 'boxnow_register_boxnow_canceled_order_status');
// Add custom order status to WooCommerce
//add_filter('wc_order_statuses', 'boxnow_add_canceled_order_status');
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

function boxnow_order_has_boxnow_shipping_method($order)
{
    if (!$order || !is_a($order, 'WC_Order')) {
        return false;
    }

    foreach ($order->get_shipping_methods() as $shipping_method) {
        if (method_exists($shipping_method, 'get_method_id') && $shipping_method->get_method_id() === 'box_now_delivery') {
            return true;
        }
    }

    return false;
}

function boxnow_add_parcel_id_to_list(&$parcel_ids, $parcel_id)
{
    $parcel_id = sanitize_text_field((string) $parcel_id);

    if ($parcel_id === '') {
        return;
    }

    $parcel_ids[] = $parcel_id;
}

function boxnow_get_order_parcel_ids($order)
{
    if (!$order || !is_a($order, 'WC_Order')) {
        return array();
    }

    $parcel_ids = array();
    $multiple_parcel_ids = $order->get_meta('_boxnow_parcel_ids', true);

    if (is_array($multiple_parcel_ids)) {
        foreach ($multiple_parcel_ids as $parcel_id) {
            boxnow_add_parcel_id_to_list($parcel_ids, $parcel_id);
        }
    } elseif (is_string($multiple_parcel_ids) && $multiple_parcel_ids !== '') {
        $decoded_parcel_ids = json_decode($multiple_parcel_ids, true);
        if (is_array($decoded_parcel_ids)) {
            foreach ($decoded_parcel_ids as $parcel_id) {
                boxnow_add_parcel_id_to_list($parcel_ids, $parcel_id);
            }
        } else {
            boxnow_add_parcel_id_to_list($parcel_ids, $multiple_parcel_ids);
        }
    }

    boxnow_add_parcel_id_to_list($parcel_ids, $order->get_meta('_boxnow_parcel_id', true));

    return array_values(array_unique($parcel_ids));
}

function boxnow_remove_order_parcel_ids($order, $parcel_ids_to_remove)
{
    if (!$order || !is_a($order, 'WC_Order')) {
        return array();
    }

    $parcel_ids_to_remove = array_map('sanitize_text_field', array_map('strval', (array) $parcel_ids_to_remove));
    $parcel_ids_to_remove = array_filter($parcel_ids_to_remove);

    if (empty($parcel_ids_to_remove)) {
        return boxnow_get_order_parcel_ids($order);
    }

    $remaining_parcel_ids = array_values(array_diff(boxnow_get_order_parcel_ids($order), $parcel_ids_to_remove));

    if (empty($remaining_parcel_ids)) {
        $order->delete_meta_data('_boxnow_parcel_ids');
        $order->delete_meta_data('_boxnow_vouchers_created');
    } else {
        $order->update_meta_data('_boxnow_parcel_ids', $remaining_parcel_ids);
        $order->update_meta_data('_boxnow_vouchers_created', 1);
    }

    if (in_array($order->get_meta('_boxnow_parcel_id', true), $parcel_ids_to_remove, true)) {
        $order->delete_meta_data('_boxnow_parcel_id');
    }

    $order->save();

    return $remaining_parcel_ids;
}

function boxnow_order_canceled($order_id, $old_status, $new_status, $order)
{
    // Check if the new status is "wc-boxnow-canceled" or "boxnow-canceled"
    if ($new_status != 'wc-boxnow-canceled' && $new_status != 'boxnow-canceled') {
        return;
    }

    if (!boxnow_order_has_boxnow_shipping_method($order)) {
        return;
    }

    $parcel_ids = boxnow_get_order_parcel_ids($order);
    if (empty($parcel_ids)) {
        $order->add_order_note('BOX NOW: Order marked as canceled, but no parcel IDs were found for API cancellation.', false);
        return;
    }

    $cancelled_parcel_ids = array();
    $failed_cancellations = array();

    foreach ($parcel_ids as $parcel_id) {
        $result = boxnow_send_cancellation_request($parcel_id);

        if ($result === 'success') {
            $cancelled_parcel_ids[] = $parcel_id;
        } else {
            $failed_cancellations[] = $parcel_id . ' (' . $result . ')';
        }
    }

    if (!empty($cancelled_parcel_ids)) {
        boxnow_remove_order_parcel_ids($order, $cancelled_parcel_ids);
        $order->add_order_note('BOX NOW voucher cancellation request sent for parcel ID(s): ' . implode(', ', $cancelled_parcel_ids), false);
    }

    if (!empty($failed_cancellations)) {
        $order->add_order_note('BOX NOW voucher cancellation failed for parcel ID(s): ' . implode(', ', $failed_cancellations), false);
    }
}

function boxnow_send_cancellation_request($parcel_id)
{
    $parcel_id = sanitize_text_field((string) $parcel_id);
    if ($parcel_id === '') {
        return 'missing parcel ID';
    }

    $access_token = boxnow_get_access_token();
    if (empty($access_token)) {
        return 'missing access token';
    }

    $api_url = 'https://' . get_option('boxnow_api_url', '') . '/api/v1/parcels/' . rawurlencode($parcel_id) . ':cancel';

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ],
        'body' => '{}',
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        return $response->get_error_message();
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    // Check for empty successful response and treat as success.
    if ($response_code >= 200 && $response_code < 300 && empty($response_body)) {
        return 'success';
    }

    $response_data = json_decode($response_body, true);
    if ($response_code >= 200 && $response_code < 300 && isset($response_data['success']) && $response_data['success'] == true) {
        return 'success';
    }

    if (is_array($response_data)) {
        if (!empty($response_data['message'])) {
            return sanitize_text_field($response_data['message']);
        }

        if (!empty($response_data['error'])) {
            return sanitize_text_field($response_data['error']);
        }
    }

    return 'failed with HTTP ' . (int) $response_code;
}
