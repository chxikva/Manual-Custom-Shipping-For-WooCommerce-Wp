<?php
/**
 * Plugin Name: Manual Custom Shipping
 * Description: Allows setting unique shipping price & time on each WooCommerce product.
 * Version: 1.0
 * Author: Kanaleto
 * Author URI: https://github.com/chxikva
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 1) Add fields in the "Shipping" tab on the Product edit page
 */
add_action('woocommerce_product_options_shipping', 'manual_custom_shipping_product_fields');
function manual_custom_shipping_product_fields() {
    echo '<div class="options_group">';

    // Custom Shipping Price
    woocommerce_wp_text_input(array(
        'id'                => '_manual_custom_shipping_price',
        'label'             => 'Custom Shipping Price',
        'desc_tip'          => true,
        'description'       => 'Set a custom shipping cost for this product.',
        'type'              => 'number',
        'custom_attributes' => array(
            'step' => '0.01',
            'min'  => '0'
        )
    ));

    // Custom Shipping Time
    woocommerce_wp_text_input(array(
        'id'          => '_manual_custom_shipping_time',
        'label'       => 'Custom Shipping Time',
        'desc_tip'    => true,
        'description' => 'Set a custom shipping time for this product (e.g. "2-3 days").',
        'type'        => 'text'
    ));

    echo '</div>';
}

/**
 * 2) Save the custom fields when the product is saved
 */
add_action('woocommerce_admin_process_product_object', 'manual_custom_shipping_save_fields');
function manual_custom_shipping_save_fields($product) {
    if (isset($_POST['_manual_custom_shipping_price'])) {
        $product->update_meta_data('_manual_custom_shipping_price', sanitize_text_field($_POST['_manual_custom_shipping_price']));
    }
    if (isset($_POST['_manual_custom_shipping_time'])) {
        $product->update_meta_data('_manual_custom_shipping_time', sanitize_text_field($_POST['_manual_custom_shipping_time']));
    }
}

/**
 * 3) Register and initialize a new shipping method
 */
function manual_custom_shipping_method_init() {
    if (!class_exists('WC_Manual_Custom_Shipping_Method')) {
        class WC_Manual_Custom_Shipping_Method extends WC_Shipping_Method {
            public function __construct() {
                $this->id                 = 'manual_custom_shipping';
                $this->method_title       = 'Manual Custom Shipping';
                $this->method_description = 'Uses per-product custom shipping price.';
                $this->enabled            = 'yes';

                // The label that shows at checkout
                $this->title = 'Shipping';

                $this->init();
            }

            public function init() {
                // No additional settings in this example
            }

            // Sum up each product's custom shipping cost * quantity
            public function calculate_shipping($package = array()) {
                $cost = 0;

                foreach (WC()->cart->get_cart() as $cart_item) {
                    $product_id   = $cart_item['product_id'];
                    $qty          = $cart_item['quantity'];
                    $custom_price = get_post_meta($product_id, '_manual_custom_shipping_price', true);

                    if (is_numeric($custom_price)) {
                        $cost += floatval($custom_price) * $qty;
                    }
                }

                $rate = array(
                    'id'    => $this->id,
                    'label' => $this->title,  // "Shipping"
                    'cost'  => $cost,
                );

                // Add the rate
                $this->add_rate($rate);
            }
        }
    }
}
add_action('woocommerce_shipping_init', 'manual_custom_shipping_method_init');

/**
 * 4) Add the new shipping method to WooCommerce
 */
function manual_add_custom_shipping_method($methods) {
    $methods['manual_custom_shipping'] = 'WC_Manual_Custom_Shipping_Method';
    return $methods;
}
add_filter('woocommerce_shipping_methods', 'manual_add_custom_shipping_method');

/**
 * 5) Hide method if no products with custom shipping in cart
 */
add_filter('woocommerce_package_rates', 'manual_hide_shipping_if_no_custom_items', 9999, 2);
function manual_hide_shipping_if_no_custom_items($rates, $package) {
    $found_custom = false;

    foreach (WC()->cart->get_cart() as $cart_item) {
        $price = get_post_meta($cart_item['product_id'], '_manual_custom_shipping_price', true);
        if (!empty($price) && is_numeric($price)) {
            $found_custom = true;
            break;
        }
    }

    if (!$found_custom) {
        foreach ($rates as $rate_id => $rate) {
            if ('manual_custom_shipping' === $rate->method_id) {
                unset($rates[$rate_id]);
            }
        }
    }

    return $rates;
}

/**
 * 6) Display "Shipping Days" in a separate row below shipping & above total,
 *    but now more user-friendly:
 *      - If multiple numeric times, display "X - Y days"
 *      - If text or mixed, comma-separate them
 */
add_action('woocommerce_review_order_before_order_total', 'manual_custom_shipping_display_time', 10);
add_action('woocommerce_cart_totals_before_order_total', 'manual_custom_shipping_display_time', 10);
function manual_custom_shipping_display_time() {
    // Gather all shipping times from products in the cart
    $all_times = array();
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product_id  = $cart_item['product_id'];
        $custom_time = get_post_meta($product_id, '_manual_custom_shipping_time', true);

        if (!empty($custom_time)) {
            $all_times[] = trim($custom_time);
        }
    }

    if (empty($all_times)) {
        return; // No shipping times at all
    }

    // Remove duplicates for clarity
    $unique_times = array_unique($all_times);

    /**
     * Attempt to parse numeric times.
     * If user typed "5" or "5 days", let's detect the numeric portion "5".
     * If we find purely numeric times, we keep them in $numeric_times.
     * If we find something that doesn't parse as a simple integer, keep it in $mixed_strings.
     */
    $numeric_times  = array();
    $mixed_strings  = array();

    foreach ($unique_times as $time_text) {
        // Extract just digits
        $digits = preg_replace('/[^0-9]/', '', $time_text);

        // If we got digits & not empty, consider it numeric
        if ('' !== $digits) {
            $numeric_times[] = (int) $digits;
        } else {
            // e.g. "2-3 days" won't parse as a single integer
            $mixed_strings[] = $time_text;
        }
    }

    // Decide the final display
    $display_text = '';

    // If we have numeric times
    if (!empty($numeric_times) && empty($mixed_strings)) {
        // All shipping times appear numeric
        $min_time = min($numeric_times);
        $max_time = max($numeric_times);

        if ($min_time === $max_time) {
            // e.g. only "5"
            $display_text = $min_time . ' days';
        } else {
            // e.g. "5 - 10 days"
            $display_text = $min_time . ' - ' . $max_time . ' days';
        }
    } elseif (!empty($numeric_times) && !empty($mixed_strings)) {
        // We have some purely numeric and some text
        // e.g. "5 days" and "2-3 days"
        // We'll display them all, comma-separated
        $all_combined = array();

        // Let's do a min/max for numeric portion
        $min_time = min($numeric_times);
        $max_time = max($numeric_times);
        if ($min_time === $max_time) {
            $all_combined[] = $min_time . ' days';
        } else {
            $all_combined[] = $min_time . ' - ' . $max_time . ' days';
        }

        // Then append the textual ones
        $all_combined = array_merge($all_combined, $mixed_strings);

        $display_text = implode(', ', $all_combined);
    } else {
        // No numeric times, only text entries
        // e.g. "2-3 days", "7-9 days"
        $display_text = implode(', ', $mixed_strings);
    }

    // Output a row: "Shipping Days | $display_text"
    echo '<tr class="shipping-time">
            <th style="white-space: nowrap;">Shipping Days</th>
            <td class="woocommerce-Price-amount amount">'
               . esc_html($display_text) .
            '</td>
          </tr>';
}
