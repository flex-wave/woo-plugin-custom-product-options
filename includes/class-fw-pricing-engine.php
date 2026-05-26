<?php
/**
 * FW_Pricing_Engine
 *
 * Centralized pricing calculation engine for FlexWave Product Options.
 * Handles fixed-price and percentage-based adjustments with safeguards
 * against duplicate and recursive calculations.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class FW_Pricing_Engine {

	private static bool $calculating = false;

	/**
	 * Calculate the total option price for a set of selections against a base price.
	 *
	 * @param float $base_price   The product's base price.
	 * @param array $selections   Array of selection arrays, each with 'price', 'type', 'percent' keys.
	 * @return float              Total additional price from all options.
	 */
	public static function calculate_option_total( float $base_price, array $selections ): float {
		$total = 0.0;

		foreach ( $selections as $sel ) {
			$total += self::calculate_single_option( $base_price, $sel );
		}

		return $total;
	}

	/**
	 * Calculate the price contribution of a single option selection.
	 *
	 * @param float $base_price
	 * @param array $sel  Must contain 'type'. For depth types, 'percent' is used.
	 * @return float
	 */
	public static function calculate_single_option( float $base_price, array $sel ): float {
		$type = $sel['type'] ?? '';

		if ( in_array( $type, [ 'depth_fixed', 'depth_custom' ], true ) ) {
			$percent = floatval( $sel['percent'] ?? 0 );
			return $base_price * ( $percent / 100 );
		}

		return floatval( $sel['price'] ?? 0 );
	}

	/**
	 * Retrieve the effective base price for a cart item (regular price).
	 */
	public static function get_item_base_price( $product ): float {
		if ( $product instanceof WC_Product ) {
			if ( method_exists( $product, 'get_regular_price' ) ) {
				$price = $product->get_regular_price();
			} else {
				$price = $product->get_price();
			}
			return (float) $price;
		}
		return 0.0;
	}

	/**
	 * Safely calculate a new cart item price without triggering loops.
	 *
	 * @param WC_Cart $cart
	 */
	public static function apply_cart_price_adjustments( WC_Cart $cart ): void {
		if ( self::$calculating ) return;
		self::$calculating = true;

		foreach ( $cart->get_cart() as $cart_item_key => &$item ) {
			if ( empty( $item['fw_data'] ) ) continue;

			$product     = $item['data'];
			$base        = self::get_item_base_price( $product );
			$option_total = self::calculate_option_total( $base, $item['fw_data'] );

			$item['fw_extra'] = $option_total;
			$product->set_price( $base + $option_total );
		}

		self::$calculating = false;
	}

	/**
	 * Build a human-readable price string for cart/order display.
	 */
	public static function format_option_display( array $sel ): string {
		$type    = $sel['type'] ?? '';
		$label   = $sel['label'] ?? '';
		$percent = floatval( $sel['percent'] ?? 0 );
		$price   = floatval( $sel['price'] ?? 0 );

		$parts = [ esc_html( $label ) ];

		if ( in_array( $type, [ 'depth_fixed', 'depth_custom' ], true ) && $percent > 0 ) {
			$parts[] = sprintf( '(+%s%%)', number_format( $percent, 1, ',', '.' ) );
		} elseif ( $price > 0 ) {
			$parts[] = '(+ ' . get_woocommerce_currency_symbol() . number_format( $price, 2, ',', '.' ) . ')';
		}

		return implode( ' ', $parts );
	}

	/**
	 * AJAX handler for price recalculation.
	 */
	public static function ajax_get_price(): void {
		if ( ! isset( $_POST['product_id'] ) ) {
			wp_send_json_error( [ 'message' => 'Geen product_id opgegeven.' ] );
		}

		if ( ! isset( $_POST['fw_nonce'] ) || ! wp_verify_nonce( $_POST['fw_nonce'], 'fw_price_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Veiligheidscontrole mislukt.' ] );
		}

		$product_id   = intval( $_POST['product_id'] );
		$selections_raw = sanitize_text_field( wp_unslash( $_POST['fw_selections'] ?? '' ) );
		$selections   = $selections_raw ? json_decode( $selections_raw, true ) : [];
		if ( ! is_array( $selections ) ) $selections = [];

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => 'Product niet gevonden.' ] );
		}

		$base_price = self::get_item_base_price( $product );
		$extra      = self::calculate_option_total( $base_price, $selections );
		$total      = $base_price + $extra;
		$formatted  = wc_price( $total );

		wp_send_json_success( [
			'price'     => $total,
			'base'      => $base_price,
			'extra'     => $extra,
			'formatted' => $formatted,
			'currency'  => get_woocommerce_currency_symbol(),
		] );
	}
}
