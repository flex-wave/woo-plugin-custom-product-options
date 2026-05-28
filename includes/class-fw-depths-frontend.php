<?php
/**
 * FW Dieptes Frontend Class
 * Renderen en verwerken van diepteselectie op de productpagina, winkelmand, checkout en order.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class FW_Depths_Frontend {
    public function __construct() {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_depths_selector'], 16);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'override_cart_item_price'], 10, 1);
        add_filter('woocommerce_order_item_name', [$this, 'order_item_name'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);
    }

    public static function get_depths_settings($product_id) {
        $depths = get_post_meta($product_id, '_fw_depths', true);
        $percents = get_post_meta($product_id, '_fw_depth_percents', true);
        $allow_custom = get_post_meta($product_id, '_fw_allow_custom_depth', true);
        $min = get_post_meta($product_id, '_fw_custom_depth_min', true);
        $max = get_post_meta($product_id, '_fw_custom_depth_max', true);
        $step = get_post_meta($product_id, '_fw_custom_depth_step', true);
        $depths_arr = array_filter(array_map('trim', explode(',', $depths)));
        $percents_arr = array_filter(array_map('trim', explode(',', $percents)));
        $depths_arr = array_map('intval', $depths_arr);
        $percents_arr = array_map('floatval', $percents_arr);
        while (count($percents_arr) < count($depths_arr)) $percents_arr[] = 0;
        return [
            'depths' => $depths_arr,
            'percents' => $percents_arr,
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

    public function render_depths_selector() {
        global $product;
        $settings = self::get_depths_settings($product->get_id());
        if (empty($settings['depths'])) return;
        $nonce = wp_create_nonce('fw_depths_nonce');
        echo '<div class="fw-depths-wrapper" data-settings="' . esc_attr(json_encode($settings)) . '">';
        echo '<label><strong>' . __('Kies diepte:', 'fw') . '</strong></label><br />';
        echo '<select name="fw_depth_type" class="fw-depth-type">';
        foreach ($settings['depths'] as $i => $depth) {
            $percent = $settings['percents'][$i];
            $cm = self::format_cm($depth);
            if ($percent == 0) {
                echo '<option value="vast_' . esc_attr($depth) . '">' . esc_html($cm) . ' cm</option>';
            } else {
                echo '<option value="vast_' . esc_attr($depth) . '">' . esc_html($cm) . ' cm (+' . $percent . 'procent)</option>';
            }
        }
        if ($settings['allow_custom']) {
            echo '<option value="maatwerk">' . __('Maatwerk diepte', 'fw') . '</option>';
        }
        echo '</select>';
        if ($settings['allow_custom']) {
            $min_cm = self::format_cm($settings['min']);
            $max_cm = self::format_cm($settings['max']);
            echo '<div class="fw-custom-depth-row" style="display:none;margin-top:8px;">';
            echo '<input type="number" name="fw_custom_depth" class="fw-custom-depth" min="' . esc_attr($min_cm) . '" max="' . esc_attr($max_cm) . '" step="0.1" placeholder="' . esc_attr($min_cm) . ' - ' . esc_attr($max_cm) . ' cm" /> cm';
            echo '<div class="fw-custom-depth-error" style="color:red;display:none;"></div>';
            echo '</div>';
        }
        echo '<input type="hidden" name="fw_depths_nonce" value="' . esc_attr($nonce) . '" />';
        echo '</div>';
    }

    public function enqueue_scripts() {
        if (is_product()) {
            wp_enqueue_script('fw-depths-js', plugins_url('../assets/depths.js', __FILE__), ['jquery'], null, true);
        }
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        if (!isset($_POST['fw_depths_nonce']) || !wp_verify_nonce($_POST['fw_depths_nonce'], 'fw_depths_nonce')) return $cart_item_data;
        $settings = self::get_depths_settings($product_id);
        if (empty($settings['depths'])) return $cart_item_data;
        $type = isset($_POST['fw_depth_type']) ? sanitize_text_field($_POST['fw_depth_type']) : '';
        $custom_depth = isset($_POST['fw_custom_depth']) ? intval($_POST['fw_custom_depth']) : null;
        $used_depth = null;
        $used_percent = null;
        if (strpos($type, 'vast_') === 0) {
            $chosen = intval(str_replace('vast_', '', $type));
            $idx = array_search($chosen, $settings['depths']);
            if ($idx !== false) {
                $used_depth = $chosen;
                $used_percent = $settings['percents'][$idx];
                $cart_item_data['fw_depth_type'] = 'vast';
                $cart_item_data['fw_depth'] = $used_depth;
                $cart_item_data['fw_depth_percent'] = $used_percent;
            }
        } elseif ($type === 'maatwerk' && $settings['allow_custom']) {
            if ($custom_depth < $settings['min'] || $custom_depth > $settings['max']) {
                wc_add_notice(__('Maatwerk diepte buiten bereik.', 'fw'), 'error');
                return $cart_item_data;
            }
            $used_depth = null;
            foreach ($settings['depths'] as $i => $d) {
                if ($custom_depth <= $d) {
                    $used_depth = $d;
                    $used_percent = $settings['percents'][$i];
                    break;
                }
            }
            if ($used_depth === null) {
                wc_add_notice(__('Gekozen maatwerk diepte is te groot.', 'fw'), 'error');
                return $cart_item_data;
            }
            $cart_item_data['fw_depth_type'] = 'maatwerk';
            $cart_item_data['fw_depth'] = $custom_depth;
            $cart_item_data['fw_depth_price_depth'] = $used_depth;
            $cart_item_data['fw_depth_percent'] = $used_percent;
        }
        return $cart_item_data;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (isset($cart_item['fw_depth_type'])) {
            $item_data[] = [
                'name' => __('Diepte type', 'fw'),
                'value' => $cart_item['fw_depth_type'] === 'vast' ? __('Vast', 'fw') : __('Maatwerk', 'fw'),
            ];
        }
        if (isset($cart_item['fw_depth'])) {
            $item_data[] = [
                'name' => __('Gekozen diepte', 'fw'),
                'value' => esc_html($cart_item['fw_depth']) . ' cm',
            ];
        }
        if (isset($cart_item['fw_depth_type']) && $cart_item['fw_depth_type'] === 'maatwerk' && isset($cart_item['fw_depth_price_depth'])) {
            $item_data[] = [
                'name' => __('Prijs-diepte', 'fw'),
                'value' => esc_html($cart_item['fw_depth_price_depth']) . ' cm',
            ];
        }
        return $item_data;
    }

    public function override_cart_item_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['fw_depth_percent'])) {
                $product = $cart_item['data'];
                $base_price = method_exists($product, 'get_regular_price') ? (float)$product->get_regular_price() : (float)$product->get_price();
                $percent = (float)$cart_item['fw_depth_percent'];
                $new_price = $base_price + ($base_price * $percent / 100);
                $product->set_price($new_price);
            }
        }
    }

    public function order_item_name($name, $item) {
        if (!empty($item['fw_depth_type'])) {
            $value = $item['fw_depth_type'];
            if ($value === 'maatwerk' && !empty($item['fw_custom_depth'])) {
                $value = __('Maatwerk diepte', 'fw') . ': ' . esc_html($item['fw_custom_depth']) . ' cm';
            } else {
                $value = str_replace('vast_', '', $value) . ' cm';
            }
            $name .= ' <small>(' . $value . ')</small>';
        }
        return $name;
    }

    public function add_order_item_meta($item, $cart_item_key, $values, $order) {
        if (!empty($values['fw_depth_type'])) {
            $value = $values['fw_depth_type'];
            if ($value === 'maatwerk' && !empty($values['fw_custom_depth'])) {
                $value = __('Maatwerk diepte', 'fw') . ': ' . esc_html($values['fw_custom_depth']) . ' cm';
            } else {
                $value = str_replace('vast_', '', $value) . ' cm';
            }
            $item->add_meta_data(__('Diepte', 'fw'), $value, true);
        }
    }
}

