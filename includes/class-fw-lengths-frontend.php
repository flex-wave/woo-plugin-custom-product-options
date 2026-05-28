<?php
/**
 * FW Lengtes Frontend Class
 * Renderen en verwerken van lengteselectie op de productpagina.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class FW_Lengths_Frontend {
    public function __construct() {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_lengths_selector'], 15);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'override_cart_item_price'], 10, 1);
        add_filter('woocommerce_order_item_name', [$this, 'order_item_name'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public static function get_lengths_settings($product_id) {
        $lengths = get_post_meta($product_id, '_fw_lengths', true);
        $prices = get_post_meta($product_id, '_fw_length_prices', true);
        $allow_custom = get_post_meta($product_id, '_fw_allow_custom_length', true);
        $min = get_post_meta($product_id, '_fw_custom_length_min', true);
        $max = get_post_meta($product_id, '_fw_custom_length_max', true);
        $step = get_post_meta($product_id, '_fw_custom_length_step', true);
        $lengths_arr = array_filter(array_map('trim', explode(',', $lengths)));
        $prices_arr = array_filter(array_map('trim', explode(',', $prices)));
        $lengths_arr = array_map('intval', $lengths_arr);
        $prices_arr = array_map('floatval', $prices_arr);
        array_multisort($lengths_arr, SORT_ASC, $prices_arr);
        return [
            'lengths' => $lengths_arr,
            'prices' => $prices_arr,
            'allow_custom' => $allow_custom === '1',
            'min' => intval($min),
            'max' => intval($max),
            'step' => intval($step) > 0 ? intval($step) : 1,
        ];
    }

    public static function format_cm($val) {
        $cm = floatval($val);
        return fmod($cm, 1) == 0 ? number_format($cm, 0, ',', '') : number_format($cm, 1, ',', '');
    }

    public function render_lengths_selector() {
        global $product;
        $settings = self::get_lengths_settings($product->get_id());
        if (empty($settings['lengths'])) return;
        $nonce = wp_create_nonce('fw_lengths_nonce');
        echo '<div class="fw-lengths-wrapper" data-settings="' . esc_attr(json_encode($settings)) . '">';
        echo '<label><strong>' . __('Kies lengte:', 'fw') . '</strong></label><br />';
        echo '<select name="fw_length_type" class="fw-length-type">';
        foreach ($settings['lengths'] as $i => $len) {
            $price = wc_price($settings['prices'][$i]);
            $cm = self::format_cm($len);
            if ($price == wc_price(0)) {
                echo '<option value="vast_' . esc_attr($len) . '">' . esc_html($cm) . ' cm</option>';
            } else {
                echo '<option value="vast_' . esc_attr($len) . '">' . esc_html($cm) . ' cm (' . $price . ')</option>';
            }
        }
        if ($settings['allow_custom']) {
            echo '<option value="maatwerk">' . __('Maatwerk lengte', 'fw') . '</option>';
        }
        echo '</select>';
        if ($settings['allow_custom']) {
            $min_cm = self::format_cm($settings['min']);
            $max_cm = self::format_cm($settings['max']);
            echo '<div class="fw-custom-length-row" style="display:none;margin-top:8px;">';
            echo '<input type="number" name="fw_custom_length" class="fw-custom-length" min="' . esc_attr($min_cm) . '" max="' . esc_attr($max_cm) . '" step="0.1" placeholder="' . esc_attr($min_cm) . ' - ' . esc_attr($max_cm) . ' cm" /> cm';
            echo '<div class="fw-custom-length-error" style="color:red;display:none;"></div>';
            echo '</div>';
        }
        echo '<input type="hidden" name="fw_lengths_nonce" value="' . esc_attr($nonce) . '" />';
        echo '</div>';
    }

    public function enqueue_scripts() {
        if (is_product()) {
            wp_enqueue_script('fw-lengths-js', plugins_url('../assets/lengths.js', __FILE__), ['jquery'], null, true);
        }
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (!isset($_POST['fw_lengths_nonce']) || !wp_verify_nonce($_POST['fw_lengths_nonce'], 'fw_lengths_nonce')) return $cart_item_data;
        $settings = self::get_lengths_settings($product_id);
        if (empty($settings['lengths'])) return $cart_item_data;
        $type = isset($_POST['fw_length_type']) ? sanitize_text_field($_POST['fw_length_type']) : '';
        $custom_length = isset($_POST['fw_custom_length']) ? intval($_POST['fw_custom_length']) : null;
        $used_length = null;
        $used_price = null;
        if (strpos($type, 'vast_') === 0) {
            $chosen = intval(str_replace('vast_', '', $type));
            $idx = array_search($chosen, $settings['lengths']);
            if ($idx !== false) {
                $used_length = $chosen;
                $used_price = $settings['prices'][$idx];
                $cart_item_data['fw_length_type'] = 'vast';
                $cart_item_data['fw_length'] = $used_length;
                $cart_item_data['fw_length_price'] = $used_price;
                $cart_item_data['length'] = $used_length;
            }
        } elseif ($type === 'maatwerk' && $settings['allow_custom']) {
            if ($custom_length < $settings['min'] || $custom_length > $settings['max']) {
                wc_add_notice(__('Maatwerk lengte buiten bereik.', 'fw'), 'error');
                return $cart_item_data;
            }
            $used_length = null;
            foreach ($settings['lengths'] as $i => $len) {
                if ($custom_length <= $len) {
                    $used_length = $len;
                    $used_price = $settings['prices'][$i];
                    break;
                }
            }
            if ($used_length === null) {
                wc_add_notice(__('Gekozen maatwerk lengte is te groot.', 'fw'), 'error');
                return $cart_item_data;
            }
            $cart_item_data['fw_length_type'] = 'maatwerk';
            $cart_item_data['fw_length'] = $custom_length;
            $cart_item_data['fw_length_price'] = $used_price;
            $cart_item_data['fw_length_price_length'] = $used_length;
            $cart_item_data['length'] = $custom_length; // <-- Voeg WooCommerce length toe
        }
        return $cart_item_data;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['fw_length_type'])) {
            $item_data[] = [
                'name' => __('Lengte type', 'fw'),
                'value' => $cart_item['fw_length_type'] === 'vast' ? __('Vast', 'fw') : __('Maatwerk', 'fw'),
            ];
        }
        if (isset($cart_item['fw_length'])) {
            $item_data[] = [
                'name' => __('Gekozen lengte', 'fw'),
                'value' => esc_html($cart_item['fw_length']) . ' cm',
            ];
        }
        if (isset($cart_item['fw_length_type']) && $cart_item['fw_length_type'] === 'maatwerk' && isset($cart_item['fw_length_price_length'])) {
            $item_data[] = [
                'name' => __('Prijs-lengte', 'fw'),
                'value' => esc_html($cart_item['fw_length_price_length']) . ' cm',
            ];
        }
        return $item_data;
    }

    public function override_cart_item_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['fw_length_price'])) {
                $cart_item['data']->set_price($cart_item['fw_length_price']);
            }
        }
    }

    public function order_item_name($name, $item) {
        // Optioneel: Toon lengte info bij order items
        return $name;
    }
}

