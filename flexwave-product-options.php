<?php
/**
 * Plugin Name: FlexWave Product Options
 * Plugin URI:  https://flex-wave.nl
 * Description: Centrale bibliotheek voor productopties met variaties (keuze, kleur, tekst), waarbij opties per product eenvoudig kunnen worden aangevinkt. Ondersteunt dynamische prijsberekening in WooCommerce.
 * Version:     1.0.0
 * Author:      FlexWave - https://flex-wave.nl
 * Author URI:  https://flex-wave.nl
 * Author Email: jorian@flex-wave.nl
 * Text Domain: flexwave
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FW_VERSION',    '1.0.0' );
define( 'FW_DIR',        plugin_dir_path( __FILE__ ) );
define( 'FW_URL',        plugin_dir_url( __FILE__ ) );

require_once FW_DIR . 'includes/class-fw-library.php';
require_once FW_DIR . 'includes/class-fw-product-meta.php';
require_once FW_DIR . 'includes/class-fw-pricing-engine.php';
require_once FW_DIR . 'includes/class-fw-frontend.php';
require_once FW_DIR . 'includes/class-fw-pricing.php';
require_once __DIR__ . '/includes/class-fw-lengths-autosave.php';

add_action( 'plugins_loaded', function () {
    FW_Library::init();
    FW_ProductMeta::init();
    FW_Frontend::init();
    FW_Pricing::init();
} );

register_activation_hook( __FILE__, function () {
    FW_Library::register_cpt();
    flush_rewrite_rules();
} );

function flexwave_render_options( int $product_id = 0 ): void {
    FW_Frontend::render( $product_id ?: get_the_ID() );
}
