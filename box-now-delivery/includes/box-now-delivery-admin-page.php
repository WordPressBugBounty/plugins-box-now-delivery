<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function box_now_delivery_menu()
{
    add_menu_page(
            'BOX NOW Delivery',
            'BOX NOW Delivery',
            'manage_options',
            'box-now-delivery',
            'box_now_delivery_options',
            'dashicons-location',
            80
    );
}

require_once plugin_dir_path(__FILE__) .'box-now-delivery-validation.php';

// Enqueue admin scripts
function box_now_delivery_enqueue_admin_scripts($hook)
{
    if ($hook != 'toplevel_page_box-now-delivery') {
        return;
    }

    wp_enqueue_script('box_now_delivery_admin_page_script', plugins_url('../js/box-now-delivery-admin-page.js', __FILE__), array(), BOX_NOW_DELIVERY_VERSION, true);
}

add_action('admin_enqueue_scripts', 'box_now_delivery_enqueue_admin_scripts');

function box_now_delivery_options()
{
    ?>
    <div class="wrap">
        <h1>BOX NOW Delivery Plugin</h1>
        <?php settings_fields('box-now-delivery-settings-group'); ?>
        <?php do_settings_sections('box-now-delivery-settings-group'); ?>
        <label style="width: 100%; float: left;">Thank you for choosing BOX NOW as your delivery option! To learn more about our services, visit our <a href="https://boxnow.gr/">website</a> or contact us at <a href="mailto:info@boxnow.gr">info@boxnow.gr</a>.</label>
        <br><br>
        <label style="width: 100%; float: left;">
        <a href="https://boxnow.gr/en/diy/eshops/plugins/woocommerce" target="_blank" rel="noopener noreferrer">Need help with the configuration? See BOX NOW plugin configuration guide</a>
        </label>
        <br>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="boxnow-settings-save">
            <?php wp_nonce_field('boxnow-settings-save', 'boxnow-custom-message'); ?>

            <div id="main-container">

                <!-- Main inputs and credentials -->
                <div style="width: 100%; float: left;">
                    <h2 style="width: 100%; float: left;">API Details</h2>
                    <div style="width:30%; float: left;">
                        <p>
                            <label>Select API URL</label>
                            <br />
                            <select name="boxnow_api_url" style="min-width: 100%">
                                <option value="api-stage.boxnow.gr" <?php selected(get_option('boxnow_api_url', ''), 'api-stage.boxnow.gr'); ?>>api-stage.boxnow.gr</option>
                                <option value="api-production.boxnow.gr" <?php selected(get_option('boxnow_api_url', ''), 'api-production.boxnow.gr'); ?>>api-production.boxnow.gr</option>
                            </select>
                        </p>
                        <p>
                            <label>Your Warehouse IDs (Multiple IDs separated by commas ",") *</label>
                            <br />
                            <input type="text" name="boxnow_warehouse_id" value="<?php echo esc_attr(get_option('boxnow_warehouse_id', '')); ?>" placeholder="Enter your Warehouse ID" required />
                        </p>
                    </div>
                    <div style="width:30%; float: left;">
                        <p>
                            <label>Your Client ID *</label>
                            <br />
                            <input type="text" name="boxnow_client_id" value="<?php echo esc_attr(get_option('boxnow_client_id', '')); ?>" placeholder="Enter your Client ID" required />
                        </p>
                        <p>
                            <label>Your Partner ID *</label>
                            <br />
                            <input type="text" name="boxnow_partner_id" value="<?php echo esc_attr(get_option('boxnow_partner_id', '')); ?>" placeholder="Enter your Partner ID" required />
                        </p>
                    </div>
                    <div style="width:30%; float: left;">
                        <p>
                            <label>Your Client Secret *</label>
                            <br />
                            <input type="text" name="boxnow_client_secret" value="<?php echo esc_attr(get_option('boxnow_client_secret', '')); ?>" placeholder="Enter your Client Secret" required />
                        </p>
                    </div>

                    <!-- Sender's Contact field -->
                    <h2 style="width: 100%; float: left;">Contact Details (Contact details are also used for Parcel Returns)</h2>
                    <div style="width: 30%; float: left;">
                        <!-- Email input for voucher -->
                        <div id="email_input_container" style="width: 100%; float: left;">
                            <p>
                                <label>Your Orders Contact Email *</label>
                                <br />
                                <input type="text" name="boxnow_voucher_email" value="<?php echo esc_attr(get_option('boxnow_voucher_email', '')); ?>" placeholder="Please insert your email here" required />
                            <p id="email_validation_message" style="color: red;"></p>
                            </p>
                            <br />
                        </div>
                        <p>
                            <label>Your Orders Contact Mobile Phone *</label>
                            <br />
                            <input type="text" name="boxnow_mobile_number" value="<?php echo esc_attr(get_option('boxnow_mobile_number', '')); ?>" pattern="^\+(30|357|359|385)(9|69|87|88|89).*" placeholder="Enter your phone with country preffix (+30 , +357, etc)" required />
                        </p>
                    </div>

                    <!-- Voucher options -->
                    <h2 style="width: 100%; float: left;">Voucher Creation Mode</h2>
                    <div style="width: 100%; float: left;">
                        <p>
                            <input type="radio" id="display_voucher_button" name="boxnow_voucher_option" value="button" <?php checked(get_option('boxnow_voucher_option', 'button'), 'button'); ?>>
                            <label for="display_voucher_button">Manual Voucher Issuance (Created and printed from the Order page)</label>
                        </p>
                        <p>
                            <input type="radio" id="send_voucher_email" name="boxnow_voucher_option" value="email" <?php checked(get_option('boxnow_voucher_option', 'button'), 'email'); ?>>
                            <label for="send_voucher_email">Automatic Voucher Issuance (Sent by email when the order status changes to Completed)</label>
                        </p>
                    </div>
                    <div style="max-width: 550px; float: left;">
                        <p>*Please note: Automatic voucher issuance is not recommended. This method automatically selects compartment sizes based on item dimensions, which may lead to incorrect compartment assignments if your items are not properly configured.</p>
                    </div>
					<h3 style="width: 100%; float: left;">Allow Returns</h3>
                    <div style="width: 100%; float: left;">
                        <p>
                            <input type="radio" id="display_allow_returns_yes" name="boxnow_allow_returns" value="1" <?php checked(get_option('boxnow_allow_returns', '1'), '1'); ?>>
                            <label for="display_allowReturns_yes">Yes</label>
                        </p>
                        <p>
                            <input type="radio" id="display_allow_returns_no" name="boxnow_allow_returns" value="0" <?php checked(get_option('boxnow_allow_returns', '1'), '0'); ?>>
                            <label for="display_allowReturns_no">No</label>
                        </p>
                    </div>

                    <!-- Widget Options -->
                    <h2 style="width: 100%; float: left;">Widget Options</h2>
                    <h3 style="width: 100%; float: left;">Widget Display Mode</h3>
                    <div style="width: 100%; float: left;">
                        <p>
                            <input type="radio" id="box_now_display_mode_popup" name="box_now_display_mode" value="popup" <?php checked(get_option('box_now_display_mode', 'popup'), 'popup'); ?>>
                            <label for="box_now_display_mode_popup">Popup Window</label>
                        </p>
                        <p>
                            <input type="radio" id="box_now_display_mode_embedded" name="box_now_display_mode" value="embedded" <?php checked(get_option('box_now_display_mode', 'popup'), 'embedded'); ?>>
                            <label for="box_now_display_mode_embedded">Embedded iFrame</label>
                        </p>
                    </div>

                    <!-- GPS Options -->
                    <h3 style="width: 100%; float: left;">Widget GPS Permission</h3>
                    <div style="width: 100%; float: left;">
                        <p>
                            <input type="radio" id="gps_tracking_on" name="boxnow_gps_tracking" value="on" <?php checked(get_option('boxnow_gps_tracking', 'on'), 'on'); ?>>
                            <label for="gps_tracking_on">GPS ON</label>
                        </p>
                        <p>
                            <input type="radio" id="gps_tracking_off" name="boxnow_gps_tracking" value="off" <?php checked(get_option('boxnow_gps_tracking', 'on'), 'off'); ?>>
                            <label for="gps_tracking_off">GPS OFF</label>
                        </p>
                    </div>

					<!-- Thank you page -->
					<h3 style="width: 100%; float: left;">Allow Locker Change on Thank You Page</h3>
                    <div style="width: 100%; float: left;">
                        <p>
                            <input type="radio" id="boxnow_thankyou_page_yes" name="boxnow_thankyou_page" value="1" <?php checked(get_option('boxnow_thankyou_page', '1'), '1'); ?>>
                            <label for="boxnow_thankyou_page_yes">Yes</label>
                        </p>
                        <p>
                            <input type="radio" id="boxnow_thankyou_page_no" name="boxnow_thankyou_page" value="0" <?php checked(get_option('boxnow_thankyou_page', '1'), '0'); ?>>
                            <label for="boxnow_thankyou_page_no">No</label>
                        </p>
                    </div>
                    <div style="max-width: 550px; float: left;">
                        <p>*Allows customers to change their selected locker on the Thank You page after a successful order.</p>
                    </div>

                    <!-- Button options -->
                    <h2 style="width: 100%; float: left;">Button & Customization</h2>
                    <div style="width:30%; float: left;">
                        <p>
                            <label>Change Button Background Color</label>
                            <br />
                            <input type="text" name="boxnow_button_color" value="<?php echo esc_attr(get_option('boxnow_button_color', '#6CD04E ')); ?>" placeholder="#6CD04E " />
                        </p>
                        <p>
                            <label>Change Button Text</label>
                            <br />
                            <input type="text" id="button_text_input" name="boxnow_button_text" value="<?php echo esc_attr(get_option('boxnow_button_text', 'Pick a locker')); ?>" placeholder="Pick a locker" />
                        </p>
                        <p>
                            <label>Change Message Text (Displayed when no locker is selected)</label>
                            <br />
                            <input type="text" name="boxnow_locker_not_selected_message" value="<?php echo esc_attr(get_option('boxnow_locker_not_selected_message', '')); ?>" placeholder="Enter your message" />
                        </p>
                    </div>

                    <!-- Save button -->
                    <div style="width:100%; float: left; clear: both;">
                        <?php submit_button(); ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <?php
}

function box_now_delivery_admin_init()
{
    // 1. Initialize serializer
    $serializer = new BNDP_Serializer();
    $serializer->init();

    /****************************************************/
    /****************************************************/
    /****************************************************/
    /****************************************************/

    // 2. Perform version migrations only once per request
    static $already_ran_migrations = false;
    if ($already_ran_migrations) {
        return;
    }

    // Skip AJAX requests
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    $already_ran_migrations = true;
    $installed_version = get_option('boxnow_plugin_version', '0.0.0');

    if (version_compare($installed_version, BOX_NOW_DELIVERY_VERSION, '<')) {
        if (function_exists('boxnow_remove_legacy_shipping_method_fields')) {
            boxnow_remove_legacy_shipping_method_fields();
        }

        update_option('boxnow_plugin_version', BOX_NOW_DELIVERY_VERSION);
    }
}

add_action('admin_menu', 'box_now_delivery_menu');
add_action('admin_init', 'box_now_delivery_admin_init');

function boxnow_remove_legacy_shipping_method_fields() {
    // Safety check – exit if WooCommerce is not fully ready
    if (!function_exists('WC') || !WC()->countries || !class_exists('WC_Shipping_Zones')) {
        return;
    }

    // legacy fields to remove from shipping method settings
    $legacy_fields = [
        'max_length',
        'max_width',
        'max_height',
        'max_dimensions_unit',
    ];

    // Get all zones. Add default zone (ID 0)
    $zones = WC_Shipping_Zones::get_zones();
    $zones[] = [ 'zone_id' => 0 ];

    foreach ($zones as $zone_data) {
        $zone = new WC_Shipping_Zone($zone_data['zone_id']);

        foreach ($zone->get_shipping_methods() as $method) {
            if ($method->id !== 'box_now_delivery') {
                continue;
            }

            $option_key = 'woocommerce_' . $method->id . '_' . $method->instance_id . '_settings';
            $settings   = get_option($option_key, []);

            if (!is_array($settings)) {
                continue;
            }

            $changed = false;
            foreach ($legacy_fields as $field) {
                if (isset($settings[$field])) {
                    unset($settings[$field]);
                    $changed = true;
                }
            }

            if ($changed) {
                update_option($option_key, $settings);
            }
        }
    }

}

function box_now_delivery_enqueue_admin_styles($hook)
{
    if ($hook != 'toplevel_page_box-now-delivery') {
        return;
    }

    wp_register_style('box_now_delivery_admin_styles', plugin_dir_url(__FILE__) . '../css/box-now-delivery-admin.css', array(), BOX_NOW_DELIVERY_VERSION);
    wp_enqueue_style('box_now_delivery_admin_styles');
}

add_action('admin_enqueue_scripts', 'box_now_delivery_enqueue_admin_styles');

function box_now_delivery_enqueue_styles()
{
    wp_register_style('box_now_delivery_styles', plugin_dir_url(__FILE__) . '../css/box-now-delivery.css', array(), BOX_NOW_DELIVERY_VERSION);
    wp_enqueue_style('box_now_delivery_styles');
}

add_action('admin_enqueue_scripts', 'box_now_delivery_enqueue_styles');
