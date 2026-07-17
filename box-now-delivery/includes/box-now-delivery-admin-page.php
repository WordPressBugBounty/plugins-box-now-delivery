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
    <div class="wrap boxnow-settings">
        <div class="boxnow-settings__header">
            <div>
                <h1>BOX NOW Delivery</h1>
                <p>Configure the BOX NOW API connection, voucher handling, widget behavior, and checkout messaging for this store.</p>
            </div>
            <div class="boxnow-settings__links">
                <a href="https://boxnow.gr/" target="_blank" rel="noopener noreferrer">BOX NOW website</a>
                <a href="https://boxnow.gr/en/diy/eshops/plugins/woocommerce" target="_blank" rel="noopener noreferrer">Configuration guide</a>
                <a href="mailto:info@boxnow.gr">info@boxnow.gr</a>
                <img class="boxnow-settings__logo" src="<?php echo esc_url(plugins_url('../img/boxnow-logo.svg', __FILE__)); ?>" alt="BOX NOW" loading="lazy" />
            </div>
        </div>

        <?php settings_fields('box-now-delivery-settings-group'); ?>
        <?php do_settings_sections('box-now-delivery-settings-group'); ?>

        <form class="boxnow-settings__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="boxnow-settings-save">
            <?php wp_nonce_field('boxnow-settings-save', 'boxnow-custom-message'); ?>

            <section class="boxnow-settings-card">
                <div class="boxnow-settings-card__header">
                    <h2>API Details</h2>
                    <p>Credentials and warehouse identifiers used for BOX NOW API requests.</p>
                </div>
                <div class="boxnow-settings-grid boxnow-settings-grid--three">
                    <div class="boxnow-field">
                        <label for="boxnow_api_url">Select API URL</label>
                        <select id="boxnow_api_url" name="boxnow_api_url">
                            <option value="api-stage.boxnow.gr" <?php selected(get_option('boxnow_api_url', ''), 'api-stage.boxnow.gr'); ?>>api-stage.boxnow.gr</option>
                            <option value="api-production.boxnow.gr" <?php selected(get_option('boxnow_api_url', ''), 'api-production.boxnow.gr'); ?>>api-production.boxnow.gr</option>
                        </select>
                    </div>
                    <div class="boxnow-field">
                        <label for="boxnow_warehouse_id">Your Warehouse IDs <span>*</span></label>
                        <input id="boxnow_warehouse_id" type="text" name="boxnow_warehouse_id" value="<?php echo esc_attr(get_option('boxnow_warehouse_id', '')); ?>" placeholder="Enter your Warehouse ID" required />
                        <p class="boxnow-field__help">Use commas for multiple IDs.</p>
                    </div>
                    <div class="boxnow-field">
                        <label for="boxnow_partner_id">Your Partner ID <span>*</span></label>
                        <input id="boxnow_partner_id" type="text" name="boxnow_partner_id" value="<?php echo esc_attr(get_option('boxnow_partner_id', '')); ?>" placeholder="Enter your Partner ID" required />
                    </div>
                    <div class="boxnow-field">
                        <label for="boxnow_client_id">Your Client ID <span>*</span></label>
                        <input id="boxnow_client_id" type="text" name="boxnow_client_id" value="<?php echo esc_attr(get_option('boxnow_client_id', '')); ?>" placeholder="Enter your Client ID" required />
                    </div>
                    <div class="boxnow-field">
                        <label for="boxnow_client_secret">Your Client Secret <span>*</span></label>
                        <input id="boxnow_client_secret" type="text" name="boxnow_client_secret" value="<?php echo esc_attr(get_option('boxnow_client_secret', '')); ?>" placeholder="Enter your Client Secret" required />
                    </div>
                </div>
            </section>

            <section class="boxnow-settings-card">
                <div class="boxnow-settings-card__header">
                    <h2>Contact Details</h2>
                    <p>Contact details are used for voucher creation and parcel returns.</p>
                </div>
                <div class="boxnow-settings-grid">
                    <div id="email_input_container" class="boxnow-field">
                        <label for="boxnow_voucher_email">Your Orders Contact Email <span>*</span></label>
                        <input id="boxnow_voucher_email" type="text" name="boxnow_voucher_email" value="<?php echo esc_attr(get_option('boxnow_voucher_email', '')); ?>" placeholder="Please insert your email here" required />
                        <p id="email_validation_message" class="boxnow-field__error"></p>
                    </div>
                    <div class="boxnow-field">
                        <label for="boxnow_mobile_number">Your Orders Contact Mobile Phone <span>*</span></label>
                        <input id="boxnow_mobile_number" type="text" name="boxnow_mobile_number" value="<?php echo esc_attr(get_option('boxnow_mobile_number', '')); ?>" pattern="^\+(30|357|359|385)(9|69|87|88|89).*" placeholder="Enter your phone with country prefix (+30, +357, etc)" required />
                    </div>
                </div>
            </section>

            <section class="boxnow-settings-card">
                <div class="boxnow-settings-card__header">
                    <h2>Voucher Creation Mode</h2>
                    <p>Choose whether vouchers are created manually from orders or automatically after completion.</p>
                </div>
                <div class="boxnow-radio-group">
                    <label class="boxnow-radio-option" for="display_voucher_button">
                        <input type="radio" id="display_voucher_button" name="boxnow_voucher_option" value="button" <?php checked(get_option('boxnow_voucher_option', 'button'), 'button'); ?>>
                        <span>Manual Voucher Issuance <small>Created and printed from the order page.</small></span>
                    </label>
                    <label class="boxnow-radio-option" for="send_voucher_email">
                        <input type="radio" id="send_voucher_email" name="boxnow_voucher_option" value="email" <?php checked(get_option('boxnow_voucher_option', 'button'), 'email'); ?>>
                        <span>Automatic Voucher Issuance <small>Sent by email when the order status changes to Completed.</small></span>
                    </label>
                </div>
                <p class="boxnow-settings-note">Automatic voucher issuance is not recommended. This method automatically selects compartment sizes based on item dimensions, which may lead to incorrect compartment assignments if your items are not properly configured.</p>
            </section>

            <section class="boxnow-settings-card">
                <div class="boxnow-settings-card__header">
                    <h2>Returns</h2>
                    <p>Control whether BOX NOW delivery requests allow parcel returns.</p>
                </div>
                <div class="boxnow-radio-group boxnow-radio-group--inline">
                    <label class="boxnow-radio-option" for="display_allow_returns_yes">
                        <input type="radio" id="display_allow_returns_yes" name="boxnow_allow_returns" value="1" <?php checked(get_option('boxnow_allow_returns', '1'), '1'); ?>>
                        <span>Yes</span>
                    </label>
                    <label class="boxnow-radio-option" for="display_allow_returns_no">
                        <input type="radio" id="display_allow_returns_no" name="boxnow_allow_returns" value="0" <?php checked(get_option('boxnow_allow_returns', '1'), '0'); ?>>
                        <span>No</span>
                    </label>
                </div>
            </section>

            <section class="boxnow-settings-card">
                <div class="boxnow-settings-card__header">
                    <h2>Widget Options</h2>
                    <p>Configure how customers choose lockers during checkout.</p>
                </div>
                <div class="boxnow-settings-grid">
                    <div class="boxnow-field">
                        <h3>Widget Display Mode</h3>
                        <div class="boxnow-radio-group">
                            <label class="boxnow-radio-option" for="box_now_display_mode_popup">
                                <input type="radio" id="box_now_display_mode_popup" name="box_now_display_mode" value="popup" <?php checked(get_option('box_now_display_mode', 'popup'), 'popup'); ?>>
                                <span>Popup Window</span>
                            </label>
                            <label class="boxnow-radio-option" for="box_now_display_mode_embedded">
                                <input type="radio" id="box_now_display_mode_embedded" name="box_now_display_mode" value="embedded" <?php checked(get_option('box_now_display_mode', 'popup'), 'embedded'); ?>>
                                <span>Embedded iFrame</span>
                            </label>
                        </div>
                    </div>
                    <div class="boxnow-field">
                        <h3>Widget GPS Permission</h3>
                        <div class="boxnow-radio-group">
                            <label class="boxnow-radio-option" for="gps_tracking_on">
                                <input type="radio" id="gps_tracking_on" name="boxnow_gps_tracking" value="on" <?php checked(get_option('boxnow_gps_tracking', 'on'), 'on'); ?>>
                                <span>GPS ON</span>
                            </label>
                            <label class="boxnow-radio-option" for="gps_tracking_off">
                                <input type="radio" id="gps_tracking_off" name="boxnow_gps_tracking" value="off" <?php checked(get_option('boxnow_gps_tracking', 'on'), 'off'); ?>>
                                <span>GPS OFF</span>
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <section class="boxnow-settings-card">
                <div class="boxnow-settings-card__header">
                    <h2>Thank You Page Options</h2>
                    <p>Allow customers to adjust their selected locker after a successful order.</p>
                </div>
                <div class="boxnow-radio-group boxnow-radio-group--inline">
                    <label class="boxnow-radio-option" for="boxnow_thankyou_page_yes">
                        <input type="radio" id="boxnow_thankyou_page_yes" name="boxnow_thankyou_page" value="1" <?php checked(get_option('boxnow_thankyou_page', '1'), '1'); ?>>
                        <span>Yes</span>
                    </label>
                    <label class="boxnow-radio-option" for="boxnow_thankyou_page_no">
                        <input type="radio" id="boxnow_thankyou_page_no" name="boxnow_thankyou_page" value="0" <?php checked(get_option('boxnow_thankyou_page', '1'), '0'); ?>>
                        <span>No</span>
                    </label>
                </div>
            </section>

            <section class="boxnow-settings-card">
                <div class="boxnow-settings-card__header">
                    <h2>Button &amp; Customization</h2>
                    <p>Customize the checkout locker button and validation message.</p>
                </div>
                <div class="boxnow-settings-grid">
                    <div class="boxnow-field">
                        <label for="boxnow_button_color">Change Button Background Color</label>
                        <input id="boxnow_button_color" type="text" name="boxnow_button_color" value="<?php echo esc_attr(get_option('boxnow_button_color', '#6CD04E ')); ?>" placeholder="#6CD04E" />
                    </div>
                    <div class="boxnow-field">
                        <label for="button_text_input">Change Button Text</label>
                        <input type="text" id="button_text_input" name="boxnow_button_text" value="<?php echo esc_attr(get_option('boxnow_button_text', 'Pick a locker')); ?>" placeholder="Pick a locker" />
                    </div>
                    <div class="boxnow-field boxnow-field--wide">
                        <label for="boxnow_locker_not_selected_message">Change Message Text</label>
                        <input id="boxnow_locker_not_selected_message" type="text" name="boxnow_locker_not_selected_message" value="<?php echo esc_attr(get_option('boxnow_locker_not_selected_message', '')); ?>" placeholder="Enter your message" />
                        <p class="boxnow-field__help">Displayed when no locker is selected.</p>
                    </div>
                </div>
            </section>

            <div class="boxnow-settings__actions">
                <?php submit_button('Save Changes', 'primary', 'submit', false); ?>
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

    if (function_exists('boxnow_disable_legacy_parcel_option_autoload')) {
        boxnow_disable_legacy_parcel_option_autoload();
    }

    if (version_compare($installed_version, BOX_NOW_DELIVERY_VERSION, '<')) {
        if (function_exists('boxnow_remove_legacy_shipping_method_fields')) {
            boxnow_remove_legacy_shipping_method_fields();
        }

        update_option('boxnow_plugin_version', BOX_NOW_DELIVERY_VERSION);
    }
}

add_action('admin_menu', 'box_now_delivery_menu');
add_action('admin_init', 'box_now_delivery_admin_init');

function boxnow_disable_legacy_parcel_option_autoload() {
    $migration_option = 'boxnow_legacy_parcel_options_autoload_fixed';

    if (get_option($migration_option, '') === BOX_NOW_DELIVERY_VERSION) {
        return;
    }

    $legacy_prefix = '_boxnow_parcel_order_id_';
    $alloptions = wp_load_alloptions();

    foreach (array_keys($alloptions) as $option_name) {
        if (strpos($option_name, $legacy_prefix) !== 0) {
            continue;
        }

        $stored_value = get_option($option_name);

        if (delete_option($option_name)) {
            add_option($option_name, $stored_value, '', false);
        }
    }

    update_option($migration_option, BOX_NOW_DELIVERY_VERSION, false);
}

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
