<?php
/**
 * FlexWave – Product Options Frontend Rendering
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FW_Frontend {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'woocommerce_before_add_to_cart_button', [ __CLASS__, 'auto_render' ], 5 );
	}

	public static function enqueue(): void {
		if ( ! is_product() ) {
			return;
		}
		wp_enqueue_style(
			'fw-frontend',
			FW_URL . 'assets/frontend.css',
			[],
			FW_VERSION
		);
		wp_enqueue_script(
			'fw-frontend',
			FW_URL . 'assets/frontend.js',
			[],
			FW_VERSION,
			true
		);
		wp_localize_script( 'fw-frontend', 'FW_Ajax', [
			'url'   => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'fw_price_nonce' ),
		] );
	}

	public static function auto_render(): void {
		self::render( get_the_ID() );
	}

	public static function render( int $product_id ): void {
		if ( ! $product_id ) {
			return;
		}

		$groups = FW_ProductMeta::get_active_groups( $product_id );
		if ( empty( $groups ) ) {
			return;
		}

		$product    = wc_get_product( $product_id );
		$base_price = $product ? (float) $product->get_price() : 0;
		$sym        = get_woocommerce_currency_symbol();

		$groups_json = [];

		?>
		<div class="fw-options"
			 id="fw-options-<?php echo esc_attr( $product_id ); ?>">
			<?php foreach ( $groups as $group ) : ?>
				<?php if ( empty( $group['variations'] ) && $group['type'] !== 'text' ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<div class="fw-group fw-group--<?php echo esc_attr( $group['type'] ); ?>"
					 data-group-id="<?php echo esc_attr( $group['id'] ); ?>">
					<div class="fw-group-label">
						<?php echo esc_html( $group['name'] ); ?>
						<?php if ( $group['required'] ) : ?>
							<span class="fw-required" aria-label="Verplicht">*</span>
						<?php endif; ?>
					</div>
					<?php
					switch ( $group['type'] ) {
						case 'color':
							self::render_color( $product_id, $group, $sym );
							break;
						case 'text':
							self::render_text( $product_id, $group, $sym );
							break;
						case 'dimensions':
							self::render_dimensions( $product_id, $group, $sym );
							break;
						case 'length':
							self::render_length( $product_id, $group, $sym );
							break;
						case 'depth':
							self::render_depth( $product_id, $group, $sym );
							break;
						default:
							self::render_radio( $product_id, $group, $sym );
							break;
					}
					?>
					<div class="fw-group-error" role="alert" style="display:none">
						Maak een keuze voor "<?php echo esc_html( $group['name'] ); ?>".
					</div>
				</div>
				<?php
				$groups_json[] = [
					'id'         => $group['id'],
					'type'       => $group['type'],
					'name'       => $group['name'],
					'required'   => $group['required'],
					'variations' => $group['variations'],
				];
				?>
			<?php endforeach; ?>
			<input type="hidden" name="fw_product_id"
				   value="<?php echo esc_attr( $product_id ); ?>">
			<input type="hidden" name="fw_selections"
				   id="fw-sel-<?php echo esc_attr( $product_id ); ?>"
				   value="[]">
			<input type="hidden" name="fw_extra_price"
				   id="fw-extra-<?php echo esc_attr( $product_id ); ?>"
				   value="0">
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var el = document.getElementById('fw-options-<?php echo esc_js( $product_id ); ?>');
			if (el) {
				el.setAttribute('data-fw', JSON.stringify({
					product_id: <?php echo (int) $product_id; ?>,
					base_price: <?php echo (float) $base_price; ?>,
					groups: <?php echo wp_json_encode( $groups_json ); ?>
				}));
			}
		});
		</script>
		<?php
	}

	// ── DEPTH RENDER ──────────────────────────────────────────────────────────

	private static function render_depth( int $pid, array $group, string $sym ): void {
		$v            = $group['variations'][0] ?? [];
		$depths       = $v['depths'] ?? [];
		$allow_custom = ( $v['depth_custom'] ?? '' ) === '1';
		$min          = $v['depth_min'] ?? '';
		$max          = $v['depth_max'] ?? '';
		$step         = $v['depth_step'] ?? '1';
		$name         = 'fw_depth_' . $pid . '_' . $group['id'];

		usort( $depths, fn( $a, $b ) => floatval( $a['value'] ) <=> floatval( $b['value'] ) );
		?>
		<div class="fw-depth-wrapper" data-group-id="<?php echo esc_attr( $group['id'] ); ?>">
			<select name="<?php echo esc_attr( $name ); ?>_type"
					class="fw-depth-type"
					data-group="<?php echo esc_attr( $group['id'] ); ?>">
				<option value=""><?php esc_html_e( 'Kies diepte...', 'flexwave' ); ?></option>
				<?php foreach ( $depths as $i => $row ) : ?>
					<option value="vast_<?php echo esc_attr( $row['value'] ); ?>"
							data-percent="<?php echo esc_attr( $row['percent'] ?? 0 ); ?>"
							data-depth="<?php echo esc_attr( $row['value'] ); ?>">
						<?php
						$cm_val = floatval( $row['value'] ) / 10;
						$pct    = floatval( $row['percent'] ?? 0 );
						printf(
							'%s cm (%s%%)',
							esc_html( fmod( $cm_val, 1 ) == 0 ? number_format( $cm_val, 0, ',', '' ) : number_format( $cm_val, 1, ',', '' ) ),
							esc_html( number_format( $pct, 1, ',', '.' ) )
						);
						?>
					</option>
				<?php endforeach; ?>
				<?php if ( $allow_custom ) : ?>
					<option value="maatwerk" data-percent="0" data-depth="">
						<?php esc_html_e( 'Maatwerk diepte', 'flexwave' ); ?>
					</option>
				<?php endif; ?>
			</select>
			<?php if ( $allow_custom ) : ?>
				<div class="fw-depth-custom-row" style="display:none;margin-top:8px;">
					<label for="<?php echo esc_attr( $name ); ?>_custom" class="screen-reader-text">
						<?php esc_html_e( 'Maatwerk diepte', 'flexwave' ); ?>
					</label>
					<input type="number"
						   name="<?php echo esc_attr( $name ); ?>_custom"
						   id="<?php echo esc_attr( $name ); ?>_custom"
						   class="fw-depth-custom"
						   data-group="<?php echo esc_attr( $group['id'] ); ?>"
						   min="<?php echo esc_attr( $min > 0 ? $min / 10 : '' ); ?>"
						   max="<?php echo esc_attr( $max > 0 ? $max / 10 : '' ); ?>"
						   step="<?php echo esc_attr( $step > 0 ? $step / 10 : '0.1' ); ?>"
						   placeholder="<?php esc_attr_e( 'Eigen diepte (cm)', 'flexwave' ); ?>"
						   inputmode="decimal">
					<span class="fw-depth-unit">cm</span>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// ── RADIO ─────────────────────────────────────────────────────────────────

	private static function render_radio( int $pid, array $group, string $sym ): void {
		$name = 'fw_g' . $pid . '_' . $group['id'];

		echo '<div class="fw-items">';

		foreach ( $group['variations'] as $vi => $v ) {
			$iid   = $name . '_' . $vi;
			$price = (float) ( $v['price'] ?? 0 );
			$badge = $price > 0
				? '<span class="fw-opt-price">+&nbsp;' . esc_html( $sym ) . number_format( $price, 2, ',', '.' ) . '</span>'
				: '';
			?>
			<label class="fw-option" for="<?php echo esc_attr( $iid ); ?>">
				<?php if ( ! empty( $v['image_url'] ) ) : ?>
					<img class="fw-opt-img"
						 src="<?php echo esc_url( $v['image_url'] ); ?>"
						 alt="<?php echo esc_attr( $v['name'] ?? '' ); ?>">
				<?php endif; ?>
				<input type="radio"
					   id="<?php echo esc_attr( $iid ); ?>"
					   name="<?php echo esc_attr( $name ); ?>"
					   data-group="<?php echo esc_attr( $group['id'] ); ?>"
					   data-price="<?php echo esc_attr( $price ); ?>"
					   data-label="<?php echo esc_attr( $v['name'] ?? '' ); ?>"
					   data-image="<?php echo esc_url( $v['image_url'] ?? '' ); ?>"
					   value="<?php echo esc_attr( $vi ); ?>">
				<span class="fw-opt-name"><?php echo esc_html( $v['name'] ?? '' ); ?></span>
				<?php echo $badge; ?>
			</label>
			<?php
		}

		echo '</div>';
	}

	// ── COLOR ─────────────────────────────────────────────────────────────────

	private static function render_color( int $pid, array $group, string $sym ): void {
		$name = 'fw_g' . $pid . '_' . $group['id'];

		echo '<div class="fw-items fw-items--color">';

		foreach ( $group['variations'] as $vi => $v ) {
			$iid   = $name . '_' . $vi;
			$price = (float) ( $v['price'] ?? 0 );
			$color = esc_attr( $v['color'] ?? '#cccccc' );
			$label = esc_attr( $v['name'] ?? '' );
			$title = $label . ( $price > 0 ? ' (+' . $sym . number_format( $price, 2, ',', '.' ) . ')' : '' );
			?>
			<label class="fw-option fw-option--swatch"
				   for="<?php echo esc_attr( $iid ); ?>"
				   title="<?php echo esc_attr( $title ); ?>">
				<input type="radio"
					   id="<?php echo esc_attr( $iid ); ?>"
					   name="<?php echo esc_attr( $name ); ?>"
					   data-group="<?php echo esc_attr( $group['id'] ); ?>"
					   data-price="<?php echo esc_attr( $price ); ?>"
					   data-label="<?php echo esc_attr( $v['name'] ?? '' ); ?>"
					   value="<?php echo esc_attr( $vi ); ?>">
				<span class="fw-swatch" style="background:<?php echo $color; ?>"></span>
				<span class="fw-opt-name"><?php echo esc_html( $v['name'] ?? '' ); ?></span>
				<?php if ( $price > 0 ) : ?>
					<span class="fw-opt-price">+<?php echo esc_html( $sym . number_format( $price, 2, ',', '.' ) ); ?></span>
				<?php endif; ?>
			</label>
			<?php
		}

		echo '</div>';

		echo '<div class="fw-color-preview" id="fw-cp-' . esc_attr( $name ) . '" aria-live="polite" style="display:none">'
			. '<span class="fw-cp-dot"></span><span class="fw-cp-label"></span></div>';
	}

	// ── TEXT ──────────────────────────────────────────────────────────────────

	private static function render_text( int $pid, array $group, string $sym ): void {
		$v           = $group['variations'][0] ?? [];
		$price       = (float) ( $v['price'] ?? 0 );
		$placeholder = $v['placeholder'] ?? '';
		$key         = 'fw_txt_' . $pid . '_' . $group['id'];
		$note        = $price > 0
			? '(+ ' . $sym . number_format( $price, 2, ',', '.' ) . ' bij invullen)'
			: '';
		?>
		<div class="fw-text-wrap">
			<label for="<?php echo esc_attr( $key ); ?>" class="screen-reader-text"><?php esc_html_e( 'Vrije tekst invoer', 'flexwave' ); ?></label>
			<input type="text"
				   id="<?php echo esc_attr( $key ); ?>"
				   name="<?php echo esc_attr( $key ); ?>"
				   class="fw-text-input"
				   data-group="<?php echo esc_attr( $group['id'] ); ?>"
				   data-price="<?php echo esc_attr( $price ); ?>"
				   placeholder="<?php echo esc_attr( $placeholder ); ?>"
				   autocomplete="off">
			<?php if ( $note ) : ?>
				<span class="fw-text-price-note"><?php echo esc_html( $note ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	// ── DIMENSIONS ────────────────────────────────────────────────────────────

	private static function render_dimensions( int $pid, array $group, string $sym ): void {
		$v_l    = $group['variations'][0] ?? [];
		$v_b    = $group['variations'][1] ?? [];
		$key_l  = 'fw_diml_' . $pid . '_' . $group['id'];
		$key_b  = 'fw_dimb_' . $pid . '_' . $group['id'];
		$price_l = (float) ( $v_l['price'] ?? 0 );
		$price_b = (float) ( $v_b['price'] ?? 0 );
		$note_l = $price_l > 0 ? '(+ ' . $sym . number_format( $price_l, 2, ',', '.' ) . ' per ' . ( $v_l['step'] ?? 'stap' ) . ' vanaf ' . $v_l['min'] . ')' : '';
		$note_b = $price_b > 0 ? '(+ ' . $sym . number_format( $price_b, 2, ',', '.' ) . ' per stapgrootte vanaf ' . $v_b['min'] . ')' : '';
		?>
		<div class="fw-dimensions-wrap">
			<div class="fw-dimension-field">
				<?php if ( ! empty( $v_l['image_url'] ) ) : ?>
					<img src="<?php echo esc_url( $v_l['image_url'] ); ?>" alt="Lengte" class="fw-dim-icon" />
				<?php endif; ?>
				<label for="<?php echo esc_attr( $key_l ); ?>">
					<?php echo esc_html( $v_l['label'] ?? 'Lengte (cm)' ); ?>
					<?php if ( $note_l ) : ?><span class="fw-dim-price-note"><?php echo esc_html( $note_l ); ?></span><?php endif; ?>
				</label>
				<div class="fw-quantity-controls">
					<button type="button" class="fw-qty-btn minus" aria-label="Minder">-</button>
					<input type="number"
						   id="<?php echo esc_attr( $key_l ); ?>"
						   name="<?php echo esc_attr( $key_l ); ?>"
						   class="fw-dim-input"
						   data-group="<?php echo esc_attr( $group['id'] ); ?>"
						   data-price="<?php echo esc_attr( $price_l ); ?>"
						   value="<?php echo esc_attr( $v_l['min'] ?? '' ); ?>"
						   min="<?php echo esc_attr( $v_l['min'] ?? '' ); ?>"
						   max="<?php echo esc_attr( $v_l['max'] ?? '' ); ?>"
						   step="<?php echo esc_attr( $v_l['step'] ?? '1' ); ?>"
						   placeholder="<?php echo esc_attr( $v_l['placeholder'] ?? '' ); ?>"
						   autocomplete="off">
					<button type="button" class="fw-qty-btn plus" aria-label="Meer">+</button>
				</div>
			</div>
			<div class="fw-dimension-field">
				<?php if ( ! empty( $v_b['image_url'] ) ) : ?>
					<img src="<?php echo esc_url( $v_b['image_url'] ); ?>" alt="Breedte" class="fw-dim-icon" />
				<?php endif; ?>
				<label for="<?php echo esc_attr( $key_b ); ?>">
					<?php echo esc_html( $v_b['label'] ?? 'Breedte (cm)' ); ?>
					<?php if ( $note_b ) : ?><span class="fw-dim-price-note"><?php echo esc_html( $note_b ); ?></span><?php endif; ?>
				</label>
				<div class="fw-quantity-controls">
					<button type="button" class="fw-qty-btn minus" aria-label="Minder">-</button>
					<input type="number"
						   id="<?php echo esc_attr( $key_b ); ?>"
						   name="<?php echo esc_attr( $key_b ); ?>"
						   class="fw-dim-input"
						   data-group="<?php echo esc_attr( $group['id'] ); ?>"
						   data-price="<?php echo esc_attr( $price_b ); ?>"
						   value="<?php echo esc_attr( $v_b['min'] ?? '' ); ?>"
						   min="<?php echo esc_attr( $v_b['min'] ?? '' ); ?>"
						   max="<?php echo esc_attr( $v_b['max'] ?? '' ); ?>"
						   step="<?php echo esc_attr( $v_b['step'] ?? '1' ); ?>"
						   placeholder="<?php echo esc_attr( $v_b['placeholder'] ?? '' ); ?>"
						   autocomplete="off">
					<button type="button" class="fw-qty-btn plus" aria-label="Meer">+</button>
				</div>
			</div>
		</div>
		<?php
	}

	// ── LENGTH ────────────────────────────────────────────────────────────────

	private static function render_length( int $pid, array $group, string $sym ): void {
		$v            = $group['variations'][0] ?? [];
		$lengths      = $v['lengths'] ?? [];
		$allow_custom = ( $v['custom'] ?? '' ) === '1';
		$step         = isset( $v['step'] ) ? floatval( $v['step'] ) : 1;
		$active_lengths = $lengths;
		$min = isset( $v['min'] ) && $v['min'] > 0 ? floatval( $v['min'] ) : ( count( $active_lengths ) ? min( array_column( $active_lengths, 'value' ) ) : 0 );
		$max = isset( $v['max'] ) && $v['max'] > 0 ? floatval( $v['max'] ) : ( count( $active_lengths ) ? max( array_column( $active_lengths, 'value' ) ) : 0 );
		$name = 'fw_length_' . $pid . '_' . $group['id'];
		?>
		<div class="fw-length-wrapper" data-group-id="<?php echo esc_attr( $group['id'] ); ?>">
			<select name="<?php echo esc_attr( $name ); ?>_type"
					class="fw-length-type"
					data-group="<?php echo esc_attr( $group['id'] ); ?>">
				<option value=""><?php esc_html_e( 'Kies lengte...', 'flexwave' ); ?></option>
				<?php foreach ( $active_lengths as $i => $row ) : ?>
					<option value="vast_<?php echo esc_attr( $row['value'] ); ?>"
							data-price="<?php echo esc_attr( $row['price'] ?? 0 ); ?>"
							data-length="<?php echo esc_attr( $row['value'] ); ?>">
						<?php
						$cm_val = floatval( $row['value'] ) / 10;
						printf(
							'%s cm (+%s)',
							esc_html( fmod( $cm_val, 1 ) == 0 ? number_format( $cm_val, 0, ',', '' ) : number_format( $cm_val, 1, ',', '' ) ),
							wp_strip_all_tags( wc_price( $row['price'] ?? 0 ) )
						);
						?>
					</option>
				<?php endforeach; ?>
				<?php if ( $allow_custom ) : ?>
					<option value="maatwerk" data-price="0" data-length="">
						<?php esc_html_e( 'Maatwerk lengte', 'flexwave' ); ?>
					</option>
				<?php endif; ?>
			</select>
			<?php if ( $allow_custom ) : ?>
				<div class="fw-custom-length-row" style="display:none;margin-top:8px;">
					<label for="<?php echo esc_attr( $name ); ?>_custom" class="screen-reader-text">
						<?php esc_html_e( 'Maatwerk lengte', 'flexwave' ); ?>
					</label>
					<input type="number"
						   name="<?php echo esc_attr( $name ); ?>_custom"
						   id="<?php echo esc_attr( $name ); ?>_custom"
						   class="fw-custom-length"
						   data-group="<?php echo esc_attr( $group['id'] ); ?>"
						   min="<?php echo esc_attr( $min > 0 ? $min / 10 : '' ); ?>"
						   max="<?php echo esc_attr( $max > 0 ? $max / 10 : '' ); ?>"
						   step="0.1"
						   placeholder="<?php echo esc_attr( number_format( $min / 10, 1, ',', '' ) . ' - ' . number_format( $max / 10, 1, ',', '' ) ); ?>"
						   inputmode="decimal">
					<span class="fw-length-unit">cm</span>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
