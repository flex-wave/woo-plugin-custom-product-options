<?php
/**
 * FW Lengtes Admin Class
 * Beheert de productinstellingen voor lengtes.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class FW_Lengths_Admin {
    public function __construct() {
        add_filter('woocommerce_product_data_tabs', [$this, 'add_lengths_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_lengths_panel']);
        add_action('woocommerce_process_product_meta', [$this, 'save_lengths_fields']);
        add_filter('woocommerce_product_data_tabs', [$this, 'add_depth_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_depth_panel']);
        add_action('woocommerce_process_product_meta', [$this, 'save_depth_fields']);
    }

    public function add_lengths_tab($tabs) {
        $tabs['fw_lengths'] = [
            'label'    => __('Lengtes', 'fw'),
            'target'   => 'fw_lengths_options',
            'class'    => [],
            'priority' => 80,
        ];
        return $tabs;
    }

    public function add_lengths_panel() {
        global $post;
        $lengths = get_post_meta($post->ID, '_fw_lengths', true);
        $prices = get_post_meta($post->ID, '_fw_length_prices', true);
        $allow_custom = get_post_meta($post->ID, '_fw_allow_custom_length', true);
        $min = get_post_meta($post->ID, '_fw_custom_length_min', true);
        $max = get_post_meta($post->ID, '_fw_custom_length_max', true);
        $step = get_post_meta($post->ID, '_fw_custom_length_step', true);
        ?>
        <div id="fw_lengths_options" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label for="fw_lengths"><?php _e('Vaste lengtes (cm, komma-gescheiden)', 'fw'); ?></label>
                    <input type="text" id="fw_lengths" name="fw_lengths" value="<?php echo esc_attr($lengths); ?>" placeholder="100,120,140,170,200" />
                </p>
                <p class="form-field">
                    <label for="fw_length_prices"><?php _e('Prijzen per lengte (komma-gescheiden, volgorde gelijk aan lengtes)', 'fw'); ?></label>
                    <input type="text" id="fw_length_prices" name="fw_length_prices" value="<?php echo esc_attr($prices); ?>" placeholder="10.00,12.00,14.00,17.00,20.00" />
                </p>
                <p class="form-field">
                    <label for="fw_allow_custom_length">
                        <input type="checkbox" id="fw_allow_custom_length" name="fw_allow_custom_length" value="1" <?php checked($allow_custom, '1'); ?> />
                        <?php _e('Maatwerk lengte toestaan', 'fw'); ?>
                    </label>
                </p>
                <p class="form-field">
                    <label for="fw_custom_length_min"><?php _e('Min maatwerk lengte (cm)', 'fw'); ?></label>
                    <input type="number" id="fw_custom_length_min" name="fw_custom_length_min" value="<?php echo esc_attr($min); ?>" min="0" step="1" />
                </p>
                <p class="form-field">
                    <label for="fw_custom_length_max"><?php _e('Max maatwerk lengte (cm)', 'fw'); ?></label>
                    <input type="number" id="fw_custom_length_max" name="fw_custom_length_max" value="<?php echo esc_attr($max); ?>" min="0" step="1" />
                </p>
                <p class="form-field">
                    <label for="fw_custom_length_step"><?php _e('Stapgrootte maatwerk (cm)', 'fw'); ?></label>
                    <input type="number" id="fw_custom_length_step" name="fw_custom_length_step" value="<?php echo esc_attr($step); ?>" min="1" step="1" />
                </p>
            </div>
        </div>
        <?php
    }

    public function save_lengths_fields($post_id) {
        $lengths = isset($_POST['fw_lengths']) ? sanitize_text_field($_POST['fw_lengths']) : '';
        $prices = isset($_POST['fw_length_prices']) ? sanitize_text_field($_POST['fw_length_prices']) : '';
        $allow_custom = isset($_POST['fw_allow_custom_length']) ? '1' : '';
        $min = isset($_POST['fw_custom_length_min']) ? intval($_POST['fw_custom_length_min']) : '';
        $max = isset($_POST['fw_custom_length_max']) ? intval($_POST['fw_custom_length_max']) : '';
        $step = isset($_POST['fw_custom_length_step']) ? intval($_POST['fw_custom_length_step']) : '';

        update_post_meta($post_id, '_fw_lengths', $lengths);
        update_post_meta($post_id, '_fw_length_prices', $prices);
        update_post_meta($post_id, '_fw_allow_custom_length', $allow_custom);
        update_post_meta($post_id, '_fw_custom_length_min', $min);
        update_post_meta($post_id, '_fw_custom_length_max', $max);
        update_post_meta($post_id, '_fw_custom_length_step', $step);
    }

    public function add_depth_tab($tabs) {
        $tabs['fw_depths'] = [
            'label'    => __('Dieptes', 'fw'),
            'target'   => 'fw_depths_options',
            'class'    => [],
            'priority' => 81,
        ];
        return $tabs;
    }

    public function add_depth_panel() {
        global $post;
        $depths = get_post_meta($post->ID, '_fw_depths', true);
        $percents = get_post_meta($post->ID, '_fw_depth_percents', true);
        $allow_custom = get_post_meta($post->ID, '_fw_allow_custom_depth', true);
        $min = get_post_meta($post->ID, '_fw_custom_depth_min', true);
        $max = get_post_meta($post->ID, '_fw_custom_depth_max', true);
        $step = get_post_meta($post->ID, '_fw_custom_depth_step', true);
        ?>
        <div id="fw_depths_options" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label for="fw_depths"><?php _e('Vaste dieptes (cm, komma-gescheiden)', 'fw'); ?></label>
                    <input type="text" id="fw_depths" name="fw_depths" value="<?php echo esc_attr($depths); ?>" placeholder="10,15,20,25,30" />
                </p>
                <p class="form-field">
                    <label for="fw_depth_percents"><?php _e('Prijsverhoging per diepte (% komma-gescheiden, volgorde gelijk aan dieptes)', 'fw'); ?></label>
                    <input type="text" id="fw_depth_percents" name="fw_depth_percents" value="<?php echo esc_attr($percents); ?>" placeholder="0,5,10,15,20" />
                </p>
                <p class="form-field">
                    <label for="fw_allow_custom_depth">
                        <input type="checkbox" id="fw_allow_custom_depth" name="fw_allow_custom_depth" value="1" <?php checked($allow_custom, '1'); ?> />
                        <?php _e('Maatwerk diepte toestaan', 'fw'); ?>
                    </label>
                </p>
                <p class="form-field">
                    <label for="fw_custom_depth_min"><?php _e('Min maatwerk diepte (cm)', 'fw'); ?></label>
                    <input type="number" id="fw_custom_depth_min" name="fw_custom_depth_min" value="<?php echo esc_attr($min); ?>" min="0" step="1" />
                </p>
                <p class="form-field">
                    <label for="fw_custom_depth_max"><?php _e('Max maatwerk diepte (cm)', 'fw'); ?></label>
                    <input type="number" id="fw_custom_depth_max" name="fw_custom_depth_max" value="<?php echo esc_attr($max); ?>" min="0" step="1" />
                </p>
                <p class="form-field">
                    <label for="fw_custom_depth_step"><?php _e('Stapgrootte maatwerk (cm)', 'fw'); ?></label>
                    <input type="number" id="fw_custom_depth_step" name="fw_custom_depth_step" value="<?php echo esc_attr($step); ?>" min="1" step="1" />
                </p>
            </div>
        </div>
        <?php
    }

    public function save_depth_fields($post_id) {
        $depths = isset($_POST['fw_depths']) ? sanitize_text_field($_POST['fw_depths']) : '';
        $percents = isset($_POST['fw_depth_percents']) ? sanitize_text_field($_POST['fw_depth_percents']) : '';
        $allow_custom = isset($_POST['fw_allow_custom_depth']) ? '1' : '';
        $min = isset($_POST['fw_custom_depth_min']) ? intval($_POST['fw_custom_depth_min']) : '';
        $max = isset($_POST['fw_custom_depth_max']) ? intval($_POST['fw_custom_depth_max']) : '';
        $step = isset($_POST['fw_custom_depth_step']) ? intval($_POST['fw_custom_depth_step']) : '';

        update_post_meta($post_id, '_fw_depths', $depths);
        update_post_meta($post_id, '_fw_depth_percents', $percents);
        update_post_meta($post_id, '_fw_allow_custom_depth', $allow_custom);
        update_post_meta($post_id, '_fw_custom_depth_min', $min);
        update_post_meta($post_id, '_fw_custom_depth_max', $max);
        update_post_meta($post_id, '_fw_custom_depth_step', $step);
    }
}
