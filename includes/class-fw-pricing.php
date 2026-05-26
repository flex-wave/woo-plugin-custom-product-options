<?php
/**
 * FW_Pricing
 *
 * WooCommerce integration for FlexWave Product Options pricing.
 * Handles validation, cart data, price adjustment, display, order meta, and AJAX.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class FW_Pricing {

	private static bool $calculating = false;

	public static function init(): void {
		add_filter( 'woocommerce_add_to_cart_validation',          [ __CLASS__, 'validate' ], 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data',              [ __CLASS__, 'add_cart_data' ], 10, 2 );
		add_action( 'woocommerce_before_calculate_totals',         [ __CLASS__, 'adjust_cart_price' ], 20 );
		add_filter( 'woocommerce_get_item_data',                   [ __CLASS__, 'display_cart_data' ], 10, 2 );
		add_filter( 'woocommerce_cart_item_key',                   [ __CLASS__, 'unique_cart_key' ], 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'add_order_meta' ], 10, 4 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'add_order_item_total' ], 15, 4 );

		add_action( 'wp_ajax_fw_get_price',         [ __CLASS__, 'ajax_get_price' ] );
		add_action( 'wp_ajax_nopriv_fw_get_price',  [ __CLASS__, 'ajax_get_price' ] );

		add_filter( 'woocommerce_order_item_get_formatted_meta_data', [ __CLASS__, 'admin_order_meta_display' ], 10, 2 );
	}

	// ── VALIDATION ────────────────────────────────────────────────────────────

	public static function validate( bool $valid, int $product_id, int $qty ): bool {
		if ( ! $valid ) return false;

		$groups = FW_ProductMeta::get_active_groups( $product_id );
		$selections = [];
		$raw = sanitize_text_field( wp_unslash( $_POST['fw_selections'] ?? '' ) );
		if ( $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) $selections = $decoded;
		}

		foreach ( $groups as $group ) {
			if ( ! $group['required'] ) continue;

			if ( $group['type'] === 'text' ) {
				$key = 'fw_txt_' . $product_id . '_' . $group['id'];
				if ( empty( trim( $_POST[ $key ] ?? '' ) ) ) {
					wc_add_notice(
						sprintf( __( '"%s" is een verplicht veld.', 'flexwave' ), $group['name'] ),
						'error'
					);
					$valid = false;
				}
				continue;
			}

			if ( $group['type'] === 'depth' ) {
				$key = 'fw_depth_' . $product_id . '_' . $group['id'] . '_type';
				$val = sanitize_text_field( $_POST[ $key ] ?? '' );
				if ( empty( $val ) ) {
					wc_add_notice(
						sprintf( __( 'Maak een keuze voor "%s".', 'flexwave' ), $group['name'] ),
						'error'
					);
					$valid = false;
				}
				continue;
			}

			if ( $group['type'] === 'length' ) {
				$key = 'fw_length_' . $product_id . '_' . $group['id'] . '_type';
				$val = sanitize_text_field( $_POST[ $key ] ?? '' );
				if ( empty( $val ) ) {
					wc_add_notice(
						sprintf( __( 'Maak een keuze voor "%s".', 'flexwave' ), $group['name'] ),
						'error'
					);
					$valid = false;
				}
				continue;
			}

			$found = false;
			foreach ( $selections as $sel ) {
				if ( (int) ( $sel['group_id'] ?? 0 ) === $group['id'] ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				wc_add_notice(
					sprintf( __( 'Maak een keuze voor "%s".', 'flexwave' ), $group['name'] ),
					'error'
				);
				$valid = false;
			}
		}

		return $valid;
	}

	// ── ADD CART DATA ─────────────────────────────────────────────────────────

	public static function add_cart_data( array $data, int $product_id ): array {
		$selections_raw = sanitize_text_field( wp_unslash( $_POST['fw_selections'] ?? '' ) );
		$selections     = $selections_raw ? json_decode( $selections_raw, true ) : [];
		if ( ! is_array( $selections ) ) $selections = [];

		$product  = wc_get_product( $product_id );
		$base_price = $product ? (float) $product->get_price( 'edit' ) : 0;

		$groups = FW_ProductMeta::get_active_groups( $product_id );

		// fw_selections (populated by JS) is the single source for all option data.
		// We only read POST fields directly to extract WooCommerce shipping dimensions.
		self::extract_shipping_dimensions( $data, $groups, $product_id );

		if ( ! empty( $selections ) ) {
			$extra            = FW_Pricing_Engine::calculate_option_total( $base_price, $selections );
			$data['fw_data']  = $selections;
			$data['fw_extra'] = $extra;
		}

		return $data;
	}

	/**
	 * Extract length/width from POST fields for WooCommerce shipping dimensions.
	 */
	private static function extract_shipping_dimensions( array &$data, array $groups, int $product_id ): void {
		foreach ( $groups as $group ) {
			if ( $group['type'] !== 'dimensions' ) continue;

			$key_l   = 'fw_diml_' . $product_id . '_' . $group['id'];
			$key_b   = 'fw_dimb_' . $product_id . '_' . $group['id'];
			$value_l = isset( $_POST[ $key_l ] ) ? floatval( $_POST[ $key_l ] ) : 0;
			$value_b = isset( $_POST[ $key_b ] ) ? floatval( $_POST[ $key_b ] ) : 0;

			if ( $value_l > 0 ) $data['length'] = $value_l;
			if ( $value_b > 0 ) $data['width']  = $value_b;
		}
	}

	// ── CART PRICE ADJUSTMENT ─────────────────────────────────────────────────

	public static function adjust_cart_price( WC_Cart $cart ): void {
		if ( self::$calculating ) return;
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

		self::$calculating = true;

		foreach ( $cart->get_cart() as $cart_item_key => &$item ) {
			if ( ! empty( $item['length'] ) ) {
				$item['data']->set_length( (float) $item['length'] );
			}
			if ( ! empty( $item['width'] ) ) {
				$item['data']->set_width( (float) $item['width'] );
			}
			if ( empty( $item['fw_data'] ) ) continue;

			$item['data']->set_price( 0 );

			$base         = FW_Pricing_Engine::get_item_base_price( $item['data'] );
			$option_total = FW_Pricing_Engine::calculate_option_total( $base, $item['fw_data'] );

			$item['fw_extra'] = $option_total;
			$item['data']->set_price( $base + $option_total );
		}

		self::$calculating = false;
	}

	// ── CART DISPLAY ──────────────────────────────────────────────────────────

	public static function display_cart_data( array $item_data, array $cart_item ): array {
		if ( empty( $cart_item['fw_data'] ) ) return $item_data;

		foreach ( $cart_item['fw_data'] as $sel ) {
			$display = FW_Pricing_Engine::format_option_display( $sel );
			$item_data[] = [
				'name'  => esc_html( $sel['group_name'] ),
				'value' => $display,
			];
		}

		return $item_data;
	}

	// ── UNIQUE CART KEY ───────────────────────────────────────────────────────

	public static function unique_cart_key( string $key, array $cart_item, array $extra ): string {
		if ( ! empty( $extra['fw_data'] ) ) {
			$key .= '_fw_' . md5( wp_json_encode( $extra['fw_data'] ) );
		}
		return $key;
	}

	// ── ORDER META ────────────────────────────────────────────────────────────

	public static function add_order_meta(
		WC_Order_Item_Product $item,
		string $cart_item_key,
		array $values,
		$order
	): void {
		if ( empty( $values['fw_data'] ) ) return;

		foreach ( $values['fw_data'] as $sel ) {
			$display = FW_Pricing_Engine::format_option_display( $sel );

			$item->add_meta_data(
				esc_html( $sel['group_name'] ),
				$display,
				false
			);

			if ( $sel['type'] === 'dimension_length' ) {
				$item->add_meta_data( '_length', $sel['raw_value'] ?? '', true );
			}
			if ( $sel['type'] === 'dimension_width' ) {
				$item->add_meta_data( '_width', $sel['raw_value'] ?? '', true );
			}
		}
	}

	/**
	 * Recalculate and store the final line total so it survives in order context.
	 */
	public static function add_order_item_total(
		WC_Order_Item_Product $item,
		string $cart_item_key,
		array $values,
		$order
	): void {
		if ( empty( $values['fw_data'] ) ) return;

		$product  = $item->get_product();
		$base     = FW_Pricing_Engine::get_item_base_price( $product );
		$extra    = FW_Pricing_Engine::calculate_option_total( $base, $values['fw_data'] );

		$item->update_meta_data( '_fw_option_total', $extra );
		$item->update_meta_data( '_fw_base_price', $base );
	}

	// ── ADMIN ORDER META DISPLAY ──────────────────────────────────────────────

	public static function admin_order_meta_display( array $formatted_meta, WC_Order_Item $item ): array {
		foreach ( $formatted_meta as $idx => $meta ) {
			if ( strpos( $meta->key, '_fw_' ) === 0 ) {
				unset( $formatted_meta[ $idx ] );
			}
		}
		return $formatted_meta;
	}

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public static function ajax_get_price(): void {
		FW_Pricing_Engine::ajax_get_price();
	}


}
