<?php

/**
 * Avoiding Direct File Access
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_shipping_init', 'box_now_delivery_shipping_method_init');
add_filter('woocommerce_shipping_methods', 'box_now_delivery_shipping_method_add');

/**
 * Add BOX NOW Delivery to WooCommerce method list.
 *
 * @param array $methods Existing shipping methods.
 * @return array Updated shipping methods.
 */
function box_now_delivery_shipping_method_add($methods)
{
    $methods['box_now_delivery'] = 'Box_Now_Delivery_Shipping_Method';
    return $methods;
}

/**
 * Initialize the BOX NOW Delivery shipping method.
 */
function box_now_delivery_shipping_method_init()
{
    if (!class_exists('Box_Now_Delivery_Shipping_Method')) {
        /**
         * Class Box_Now_Delivery_Shipping_Method
         *
         * @property array $form_fields
         */
        class Box_Now_Delivery_Shipping_Method extends WC_Shipping_Method
        {
            /**
             * Configured shipping cost for the current method instance.
             *
             * @var mixed
             */
            public $cost;

            /**
             * Configured free-delivery threshold for the current method instance.
             *
             * @var mixed
             */
            public $free_delivery_threshold;

            /**
             * Whether this shipping method is taxable.
             *
             * @var mixed
             */
            public $taxable;

            /**
             * Constructor for the shipping class.
             */
            public function __construct($instance_id = 0)
            {
                $this->id = 'box_now_delivery';
                $this->instance_id = absint($instance_id);
                $this->method_title = __('BOX NOW Delivery', 'box-now-delivery');
                $this->method_description = __('Custom settings for the BOX NOW Delivery', 'box-now-delivery');

                $this->supports = array(
                        'shipping-zones',
                        'instance-settings',
                        'instance-settings-modal',
                );
                
                // Initialize settings and form fields.
                $this->init_form_fields();
                $this->init_settings();
        
                // Define user set variables.
                $this->title = $this->get_option('title');
                $this->cost = $this->get_option('cost');
                $this->free_delivery_threshold = $this->get_option('free_delivery_threshold');
                $this->taxable = $this->get_option('taxable');
            }


            /**
             * Override WC_Shipping_Method's process_admin_options to add custom logic and error handling.
             * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
             *
             * @return bool was anything saved?
             */
            public function process_admin_options()
            {
                // required call to init_settings() in order to access $this->settings array
                $this->init_settings();

                $post_data = $this->get_post_data();
                foreach ($this->get_form_fields() as $key => $field) {
                    if ('title' !== $this->get_field_type($field)) {
                        try {
                            $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                        } catch (Exception $e) {
                            $this->add_error($e->getMessage());
                        }
                    }
                }

                return update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
            }

            public function get_option_key()
            {
                return $this->plugin_id . $this->id . '_' . $this->instance_id . '_settings';
            }

            /**
             * Define settings fields for the shipping method.
             * 
             * The default values are crucial for proper functioning of the shipping method. 
             * If the settings option is not found in DB during checkout, the default value
             * (as defined in $this->form_fields array) will be used instead. This way we ensure
             * that the custom_weight_unit & max_dimensions_unit always have a valid value during checkout.
             */
            function init_form_fields()
            {
                // Decide default unit to be used for setting 'custom_weight_unit' on plugin update v3.1.1
                // If the 'custom_weight' setting is higher than 100 or has more than 3 digits, then use grams instead
                // of the default value, kilograms.
                // TODO 3.1.1: this check can be safely removed only when all clients have migrated to a version greater than v3.1.1
                // If so, make sure to the default unit for setting 'custom_weight_unit' to 'kg' below
                $box_now_weight_limit = floatval($this->get_option('custom_weight'));
                $default_custom_weight_unit = 
                    ($box_now_weight_limit >= 100 || strlen($box_now_weight_limit) > 3)
                    ? 'g' 
                    : 'kg' ;

                $this->form_fields = array(
                        'enabled' => array(
                                'title' => __('Enable/Disable', 'box-now-delivery'),
                                'type' => 'checkbox',
                                'description' => '',
                                'default' => 'yes'
                        ),
                        'title' => array(
                                'title' => __('Method Title', 'box-now-delivery'),
                                'type' => 'text',
                                'description' => __('This controls the title which the user sees during checkout.', 'box-now-delivery'),
                                'default' => __('BOX NOW Delivery', 'box-now-delivery'),
                                'desc_tip' => true,
                        ),
                        'cost' => array(
                                'title' => __('Cost', 'box-now-delivery'),
                                'type' => 'number',
                                'description' => __('Enter the cost for this shipping method', 'box-now-delivery'),
                                'default' => 0,
                                'desc_tip' => true,
                                'custom_attributes' => array(
                                        'step' => '0.01',
                                        'min' => '0',
                                ),
                        ),
                        'free_delivery_threshold' => array(
                                'title' => __('Free Delivery Threshold', 'box-now-delivery'),
                                'type' => 'number',
                                'description' => __('If the cart total is above this amount, the shipping cost will be free.', 'box-now-delivery'),
                                'default' => '',
                                'desc_tip' => true,
                        ),
                        'taxable' => array(
                                'title' => __('Taxable', 'box-now-delivery'),
                                'type' => 'select',
                                'description' => __('Should the shipping cost be taxed?', 'box-now-delivery'),
                                'default' => 'yes',
                                'options' => array(
                                        'yes' => __('Yes', 'box-now-delivery'),
                                        'no' => __('No', 'box-now-delivery'),
                                ),
                        ),
                        'custom_weight' => array(
                                'title'       => __('Max Weight', 'box-now-delivery'),
                                'type'        => 'number',
                                'description' => __('Maximum weight allowed value for this shipping method', 'box-now-delivery'),
                                'placeholder' => __('20', 'box-now-delivery'),
                                'default' => 20,
                                'desc_tip' => true,
                                'custom_attributes' => array(
                                        'step' => '0.1',
                                        'min' => '0',
                                ),
                        ),
                        'custom_weight_unit' => array(
                                'title'       => __('Max Weight Unit', 'box-now-delivery'),
						        'type'    => 'select',
                                'description' => __('Unit of measurement for the Max Weight value.<br>*Make sure this unit matches the WooCommerce Product Settings Weight unit.', 'box-now-delivery'),
                                'default' => $default_custom_weight_unit,
                                'options'     => array(
                                    'g' => __('Grams', 'box-now-delivery'),
                                    'kg' => __('Kilograms', 'box-now-delivery'),
                                ),
                                'desc_tip' => true,
                        ),
                        'dimensions' => array(
                            'title' => __('Max Package Dimensions', 'box-now-delivery'),
                            'type'  => 'title',
                            'description' => sprintf(
                                '<ul><li>%1$s</li><li>%2$s</li><li>%3$s</li></ul>',
                                sprintf(
                                    /* translators: %d: maximum package length in centimeters. */
                                    esc_html__('Length: %d cm', 'box-now-delivery'),
                                    BOX_NOW_LENGTH
                                ),
                                sprintf(
                                    /* translators: %d: maximum package width in centimeters. */
                                    esc_html__('Width: %d cm', 'box-now-delivery'),
                                    BOX_NOW_WIDTH
                                ),
                                sprintf(
                                    /* translators: %d: maximum package height in centimeters. */
                                    esc_html__('Height: %d cm', 'box-now-delivery'),
                                    BOX_NOW_LARGE_HEIGHT
                                )
                            ),
                        ),
                        'cod_description' => array(
                                'title' => __('Cash on delivery custom description settings', 'box-now-delivery'),
                                'type' => 'title',
                                'description' => __('Enable the custom Cash on delivery description and enter your custom text', 'box-now-delivery'),
                        ),
                        'enable_custom_cod_description' => array(
                                'title' => __('Enable Custom Description for COD', 'box-now-delivery'),
                                'type' => 'checkbox',
                                'description' => __('Enable or disable the custom description when Cash on Delivery is selected.', 'box-now-delivery'),
                                'default' => 'no',
                                'class' => 'enable_custom_cod_description',
                        ),
                        'custom_cod_description' => array(
                                'title' => __('Custom COD Description', 'box-now-delivery'),
                                'type' => 'text',
                                'description' => __('Enter the custom description for Cash on Delivery.', 'box-now-delivery'),
                                'default' => '',
                                'desc_tip' => true,
                                'class' => 'custom_cod_description_field',
                        ),
                );
            }

            /**
             * Calculate the shipping cost.
             *
             * @param array $package Shipping package.
             */
            public function calculate_shipping($package = [])
            {
                $woocommerce = function_exists('WC') ? WC() : null;
                $cart = is_object($woocommerce) && isset($woocommerce->cart)
                    ? $woocommerce->cart
                    : null;

                if (!$cart) {
                    return;
                }

                // Check if any item in the cart is oversized
                if ($this->has_oversized_products($cart)) {
                    // Do not display the BOX NOW Delivery shipping method if an item is oversized
                    return;
                }

                // Taxable yes or no
                $taxable = ($this->taxable == 'yes') ? true : false;

                // Get the order total
                $order_total = $cart->get_displayed_subtotal();

                // Adjust total for any coupons
                if (!empty($cart->get_coupons())) {
                    foreach ($cart->get_coupons() as $code => $coupon) {
                        if ($coupon->is_type('fixed_cart')) {
                            $order_total -= $coupon->get_amount();
                        } else if ($coupon->is_type('percent')) {
                            $order_total -= ($coupon->get_amount() / 100) * $order_total;
                        } else if ($coupon->is_type('fixed_product')) {
                            // Use WooCommerce's calculated discount so product eligibility,
                            // quantities, usage limits, rounding, and taxes are respected.
                            $order_total -= $cart->get_coupon_discount_amount(
                                $code,
                                !$cart->display_prices_including_tax()
                            );
                        }
                    }
                }

                // Get the user-defined threshold for free delivery
                $free_delivery_threshold = $this->get_option('free_delivery_threshold');

                // Check if the order total is above the threshold for free delivery
                if (!empty($free_delivery_threshold) && $order_total >= $free_delivery_threshold) {
                    $this->cost = 0;
                }

                $rate = [
                        'id'       => $this->id,
                        'label'    => $this->title,
                        'cost'     => $this->cost,
                        'taxes' => $taxable ? WC_Tax::calc_shipping_tax($this->cost, WC_Tax::get_shipping_tax_rates()) : '',
                        'calc_tax' => 'per_item',
                ];

                // Register the rate.
                $this->add_rate($rate);
            }

            /**
             * Checks if the cart contains any oversized products or if the total weight exceeds the custom weight limit.
             *
             * @param WC_Cart $cart Current WooCommerce cart.
             * @return bool Returns true if the cart contains oversized products or if the total weight exceeds the custom weight limit, otherwise returns false.
             */
            private function has_oversized_products($cart)
            {
                // Get WooCommerce units
                $wc_weight_unit = get_option('woocommerce_weight_unit');    // kg, g, lbs, oz
                $wc_dimensions_unit = get_option('woocommerce_dimension_unit'); // cm, mm, m, in, yd
                
                // Get BOX NOW settings units and max values
                // Dimensions - always use cm as unit for BOX NOW shipping method dimensions
                // Weight
                $box_now_weight_limit = floatval($this->get_option('custom_weight'));
                $box_now_weight_unit = $this->get_option('custom_weight_unit'); // kg, g

                foreach ($cart->get_cart_contents() as $cart_item) {
                    $length = floatval($cart_item['data']->get_length());
                    $width  = floatval($cart_item['data']->get_width());
                    $height = floatval($cart_item['data']->get_height());
                    $weight = $cart_item['data']->get_weight();
                    $weight = is_numeric($weight) ? floatval($weight) : 0;
                    
                    // Convert the product dimensions to the unit of measurement defined in BOX NOW shipping method settings
                    try {
                        $converted_length =  $this->convert_dimension_to_cm($length, $wc_dimensions_unit, BOX_NOW_DIMENSIONS_UNIT);
                        $converted_width =  $this->convert_dimension_to_cm($width, $wc_dimensions_unit, BOX_NOW_DIMENSIONS_UNIT);
                        $converted_height =  $this->convert_dimension_to_cm($height, $wc_dimensions_unit, BOX_NOW_DIMENSIONS_UNIT);
                        $converted_weight = $this->convert_weight($weight, $wc_weight_unit, $box_now_weight_unit);
                        
                        // Compare the converted product dimensions to the BOX NOW shipping method settings max dimensions values
                        if (
                            $converted_length > BOX_NOW_LENGTH ||
                            $converted_width  > BOX_NOW_WIDTH  ||
                            $converted_height > BOX_NOW_LARGE_HEIGHT ||
                            $converted_weight > $box_now_weight_limit
                        ) {
                            // cart contains oversized items
                            return true;
                        }
                    } catch(InvalidArgumentException $e) {
                        // Failed to convert the dimensions or weight; treating the cart as containing oversized items.
                        return true;
                    }
                }
                
                // cart does not contain oversized items
                return false;
            }

            /**
             * @throws InvalidArgumentException
             */
            function convert_dimension_to_cm(float $value, string $from, string $to): float {
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
             * @throws InvalidArgumentException
             */
            function convert_weight(float $value, string $from, string $to): float {
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
        }
    }
}

/**
 * Determine whether gateway copy is being requested by a checkout flow.
 *
 * Gateway descriptions can also be read from wp-admin and arbitrary REST API
 * endpoints, where WooCommerce intentionally does not initialize a session.
 *
 * @return bool
 */
function boxnow_is_checkout_or_store_api_request()
{
    if (function_exists('is_checkout') && is_checkout()) {
        return true;
    }

    $wc_ajax = isset($_REQUEST['wc-ajax']) && is_string($_REQUEST['wc-ajax'])
        ? sanitize_key(wp_unslash($_REQUEST['wc-ajax']))
        : '';

    if (in_array($wc_ajax, array('update_order_review', 'checkout'), true)) {
        return true;
    }

    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
        $action = isset($_REQUEST['action']) && is_string($_REQUEST['action'])
            ? sanitize_key(wp_unslash($_REQUEST['action']))
            : '';

        if (in_array($action, array('woocommerce_update_order_review', 'woocommerce_checkout'), true)) {
            return true;
        }
    }

    if (!(defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }

    $route = isset($_REQUEST['rest_route']) && is_string($_REQUEST['rest_route'])
        ? sanitize_text_field(wp_unslash($_REQUEST['rest_route']))
        : '';

    if ('' === $route && isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
        $route = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
    }

    return false !== strpos($route, '/wc/store/');
}

// Modify the Cash on Delivery payment method's description based on the shipping zone
add_filter('woocommerce_gateway_description', 'boxnow_change_cod_description', 10, 2);
function boxnow_change_cod_description($description, $payment_id)
{
    if ('cod' !== $payment_id || !boxnow_is_checkout_or_store_api_request()) {
        return $description;
    }

    if (!function_exists('bndp_is_box_now_delivery_selected') || !bndp_is_box_now_delivery_selected()) {
        return $description;
    }

    $custom_description = function_exists('bndp_get_custom_cod_description_for_current_zone')
        ? bndp_get_custom_cod_description_for_current_zone()
        : '';

    return '' !== $custom_description ? $custom_description : $description;
}

// Refresh the checkout page when the payment method changes
add_action('woocommerce_review_order_before_payment', 'boxnow_add_cod_payment_refresh_script');
function boxnow_add_cod_payment_refresh_script()
{
    ?>
    <script>
        jQuery(document.body).on('change', 'input[name="payment_method"]', function() {
            jQuery('body').trigger('update_checkout');
        });
    </script>
    <?php
}
