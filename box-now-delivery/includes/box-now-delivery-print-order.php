<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Template Name: BOX NOW Delivery Print Order
 */
function boxnow_print_voucher_pdf($parcel_id = '')
{
    if (empty($parcel_id)) {
        wp_die(esc_html__('Parcel ID was not found!', 'box-now-delivery'));
    }

    $api_url = get_option('boxnow_api_url');
    $api_id = get_option('boxnow_client_id');
    $api_secret = get_option('boxnow_client_secret');
    $api_warehouse = get_option('boxnow_warehouse_id');
    $api_partner = get_option('boxnow_partner_id');

    // Get API session
    $auth_response = wp_remote_post('https://' . $api_url . '/api/v1/auth-sessions', array(
        'method' => 'POST',
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode(array(
            'grant_type' => 'client_credentials',
            'client_id' => $api_id,
            'client_secret' => $api_secret,
        )),
    ));

    if (is_wp_error($auth_response) || 200 != wp_remote_retrieve_response_code($auth_response)) {
        wp_die(esc_html__('Error: Authentication failed.', 'box-now-delivery'));
    }

    $auth_json = json_decode(wp_remote_retrieve_body($auth_response), true);
    $access_token = $auth_json['access_token'];

    $headers = [
        'accept' => 'application/pdf',
        'Authorization' => 'Bearer ' . $access_token
    ];

    $response = wp_remote_get('https://' . $api_url . '/api/v1/parcels/' . $parcel_id . '/label.pdf', array(
        'headers' => $headers,
        'timeout' => 5,
    ));

    if (is_wp_error($response)) {
        wp_die(
            esc_html(
                sprintf(
                    /* translators: %s: error message from the remote label request. */
                    __('Error: %s', 'box-now-delivery'),
                    $response->get_error_message()
                )
            )
        );
    }

    // Print voucher only after a successful PDF response is available.
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="label.pdf"');

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF response should be sent raw.
    echo wp_remote_retrieve_body($response);

    exit();
}
