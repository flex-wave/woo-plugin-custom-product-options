<?php
/* ================================================================
   FlexWave – Frontend CSS
   https://flex-wave.nl
   Copyright (c) 2026 FlexWave
   E-mail: jorian@flex-wave.nl
   Alle rechten voorbehouden
   ================================================================ */

if ( ! defined( 'ABSPATH' ) ) exit;

class FW_Library {

    public static function init(): void {
        add_action( 'init',                [ __CLASS__, 'register_cpt' ] );
        add_action( 'add_meta_boxes',      [ __CLASS__, 'add_meta_box' ] );
        add_action( 'save_post_fw_option_group', [ __CLASS__, 'save_meta' ] );
        add_action( 'admin_enqueue_scripts',     [ __CLASS__, 'enqueue' ] );
        add_filter( 'manage_fw_option_group_posts_columns',       [ __CLASS__, 'columns' ] );
        add_action( 'manage_fw_option_group_posts_custom_column', [ __CLASS__, 'column_content' ], 10, 2 );
    }

    // ── CPT ───────────────────────────────────────────────────────────────────
    public static function register_cpt(): void {
        register_post_type( 'fw_option_group', [
            'labels' => [
                'name'          => 'FW Optiegroepen',
                'singular_name' => 'Optiegroep',
                'add_new'       => 'Groep toevoegen',
                'add_new_item'  => 'Nieuwe optiegroep',
                'edit_item'     => 'Optiegroep bewerken',
                'all_items'     => 'Alle optiegroepen',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,
            'menu_label'   => 'FW Optiegroepen',
            'menu_icon'    => 'dashicons-list-view',
            'supports'     => [ 'title' ],
            'rewrite'      => false,
        ] );
    }

    // ── Admin assets ──────────────────────────────────────────────────────────
    public static function enqueue( string $hook ): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'fw_option_group' ) return;

        wp_enqueue_media();
        wp_enqueue_style(  'fw-lib-css', FW_URL . 'assets/library.css', [], FW_VERSION );
        wp_enqueue_script( 'fw-lib-js',  FW_URL . 'assets/library.js',  [], FW_VERSION, true );
    }

    // ── Meta box ──────────────────────────────────────────────────────────────
    public static function add_meta_box(): void {
        add_meta_box(
            'fw_group_details',
            'Groep instellingen & variaties',
            [ __CLASS__, 'render_meta_box' ],
            'fw_option_group',
            'normal',
            'high'
        );
    }

    public static function render_meta_box( WP_Post $post ): void {
        wp_nonce_field( 'fw_lib_save', 'fw_lib_nonce' );

        $type       = get_post_meta( $post->ID, '_fw_group_type',     true ) ?: 'radio';
        $required   = get_post_meta( $post->ID, '_fw_group_required', true );
        $variations = self::get_variations( $post->ID );
        ?>
        <div id="fw-lib-wrap">

            <div class="fw-lib-row">
                <label><strong>Type</strong>
                    <select name="fw_group_type" id="fw_group_type">
                        <option value="radio" <?php selected( $type, 'radio' ); ?>>🔘 Keuze (radio)</option>
                        <option value="color" <?php selected( $type, 'color' ); ?>>🎨 Kleurstaal</option>
                        <option value="text"  <?php selected( $type, 'text' );  ?>>✏️ Vrije tekstinvoer</option>
                        <option value="length" <?php selected( $type, 'length' ); ?>>📏 Lengte</option>
                        <option value="width" <?php selected( $type, 'width' ); ?>>📐 Breedte</option>
                        <option value="dimensions" <?php selected( $type, 'dimensions' ); ?>>📏📐 Lengte + Breedte</option>
                        <option value="depth" <?php selected( $type, 'depth' ); ?>>📏 Diepte</option>
                    </select>
                </label>
                <label>
                    <input type="checkbox" name="fw_group_required" value="1" <?php checked( $required, '1' ); ?>>
                    Verplicht veld
                </label>
            </div>

            <!-- VARIATIES (radio / color) -->
            <div id="fw-variations-wrap" class="<?php echo in_array($type, ['text','length','width']) ? 'fw-hidden' : ''; ?>">
                <h4>Variaties</h4>
                <table id="fw-variations-table" class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width:30px">☰</th>
                            <th>Naam / Label</th>
                            <th style="width:110px">Meerprijs (€)</th>
                            <th id="fw-col-color" class="<?php echo $type !== 'color' ? 'fw-hidden' : ''; ?>" style="width:80px">Kleur</th>
                            <th id="fw-col-image" class="<?php echo $type === 'color' ? 'fw-hidden' : ''; ?>" style="width:90px">Afbeelding</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="fw-variations-body">
                        <?php if ($type !== 'length' && $type !== 'depth') : ?>
                        <?php foreach ( $variations as $i => $v ) : ?>
                            <?php self::render_variation_row( $i, $v, $type ); ?>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($type !== 'length' && $type !== 'depth') : ?>
                <button type="button" id="fw-add-variation" class="button" style="margin-top:.6rem">
                    + Variatie toevoegen
                </button>
                <?php endif; ?>
            </div>

            <!-- TEKST type uitleg -->
            <div id="fw-text-wrap" class="<?php echo $type !== 'text' ? 'fw-hidden' : ''; ?>">
                <h4>Tekst optie instellingen</h4>
                <p class="description">Bij dit type typt de klant zelf een waarde. Stel hieronder een meerprijs in.</p>
                <label>Meerprijs voor invullen (€, ongeacht lengte)
                    <input type="number" name="fw_text_price" step="0.01" min="0"
                           value="<?php echo esc_attr( $variations[0]['price'] ?? '' ); ?>"
                           style="width:120px">
                </label>
                &nbsp;&nbsp;
                <label>Placeholder tekst
                    <input type="text" name="fw_text_placeholder"
                           value="<?php echo esc_attr( $variations[0]['placeholder'] ?? '' ); ?>"
                           placeholder="bijv. Voer uw naam in" style="width:220px">
                </label>
            </div>

            <!-- LENGTE type uitleg -->
            <div id="fw-length-wrap" class="<?php echo $type !== 'length' ? 'fw-hidden' : ''; ?>">
                <h4>Lengte optie instellingen</h4>
                <table id="fw-length-table" class="widefat striped" style="max-width:500px">
                    <thead>
                        <tr>
                            <th style="width:120px">Standaardmaat (mm)</th>
                            <th style="width:120px">Prijs (€)</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $lengths = $variations[0]['lengths'] ?? [];
                        if (!is_array($lengths)) $lengths = [];
                        if (empty($lengths)) $lengths = [['value'=>'','price'=>'']];
                        foreach ($lengths as $i => $row) : ?>
                        <tr>
                            <td><label for="fw_length_value_<?php echo $i; ?>" class="screen-reader-text">Lengte waarde</label><input id="fw_length_value_<?php echo $i; ?>" type="number" name="fw_length_lengths[<?php echo $i; ?>][value]" value="<?php echo esc_attr($row['value'] ?? ''); ?>" step="0.1" min="0" style="width:100%"></td>
                            <td><label for="fw_length_price_<?php echo $i; ?>" class="screen-reader-text">Lengte prijs</label><input id="fw_length_price_<?php echo $i; ?>" type="number" name="fw_length_lengths[<?php echo $i; ?>][price]" value="<?php echo esc_attr($row['price'] ?? ''); ?>" step="0.01" min="0" style="width:100%"></td>
                            <td><button type="button" class="button fw-remove-length" title="Verwijder">✕</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" id="fw-add-length" class="button">+ Maat toevoegen</button>
                <br><br>
                <label><input type="checkbox" name="fw_length_custom" value="1" <?php checked($variations[0]['custom'] ?? '', '1'); ?>> Maatwerk lengte toestaan</label>
                &nbsp;
                <label>Stapgrootte maatwerk (mm)
                    <input type="number" name="fw_length_step" value="<?php echo esc_attr($variations[0]['step'] ?? '1'); ?>" min="0.1" step="0.1" style="width:80px">
                </label>
                &nbsp;
                <label>Min maatwerk (mm, leeg = kleinste maat)
                    <input type="number" name="fw_length_min" value="<?php echo esc_attr($variations[0]['min'] ?? ''); ?>" min="0" step="0.1" style="width:80px">
                </label>
                &nbsp;
                <label>Max maatwerk (mm, leeg = grootste maat)
                    <input type="number" name="fw_length_max" value="<?php echo esc_attr($variations[0]['max'] ?? ''); ?>" min="0" step="0.1" style="width:80px">
                </label>
            </div>

            <!-- DIEPTE type uitleg -->
            <div id="fw-depth-wrap" class="<?php echo $type !== 'depth' ? 'fw-hidden' : ''; ?>">
                <h4>Diepte optie instellingen</h4>
                <table id="fw-depth-table" class="widefat striped" style="max-width:500px">
                    <thead>
                        <tr>
                            <th style="width:120px">Standaardmaat (mm)</th>
                            <th style="width:120px">Prijs (%)</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $depths = $variations[0]['depths'] ?? [];
                        if (!is_array($depths)) $depths = [];
                        if (empty($depths)) $depths = [['value'=>'','percent'=>'']];
                        foreach ($depths as $i => $row) : ?>
                        <tr>
                            <td><label for="fw_depth_value_<?php echo $i; ?>" class="screen-reader-text">Diepte waarde</label><input id="fw_depth_value_<?php echo $i; ?>" type="number" name="fw_depth_depths[<?php echo $i; ?>][value]" value="<?php echo esc_attr($row['value'] ?? ''); ?>" step="0.1" min="0" style="width:100%"></td>
                            <td><label for="fw_depth_percent_<?php echo $i; ?>" class="screen-reader-text">Diepte procent</label><input id="fw_depth_percent_<?php echo $i; ?>" type="number" name="fw_depth_depths[<?php echo $i; ?>][percent]" value="<?php echo esc_attr($row['percent'] ?? ''); ?>" step="0.01" min="0" style="width:100%"></td>
                            <td><button type="button" class="button fw-remove-depth" title="Verwijder">✕</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" id="fw-add-depth" class="button">+ Maat toevoegen</button>
                <br><br>
                <label><input type="checkbox" name="fw_depth_custom" value="1" <?php checked($variations[0]['depth_custom'] ?? '', '1'); ?>> Maatwerk diepte toestaan</label>
                &nbsp;
                <label>Stapgrootte maatwerk (mm)
                    <input type="number" name="fw_depth_step" value="<?php echo esc_attr($variations[0]['depth_step'] ?? '1'); ?>" min="0.1" step="0.1" style="width:80px">
                </label>
                &nbsp;
                <label>Min maatwerk (mm, leeg = kleinste maat)
                    <input type="number" name="fw_depth_min" value="<?php echo esc_attr($variations[0]['depth_min'] ?? ''); ?>" min="0" step="0.1" style="width:80px">
                </label>
                &nbsp;
                <label>Max maatwerk (mm, leeg = grootste maat)
                    <input type="number" name="fw_depth_max" value="<?php echo esc_attr($variations[0]['depth_max'] ?? ''); ?>" min="0" step="0.1" style="width:80px">
                </label>
            </div>
        </div><!-- #fw-lib-wrap -->

        <script type="text/template" id="fw-var-row-tpl">
            <?php self::render_variation_row( '__VI__', [], $type ); ?>
        </script>
        <?php
    }

    public static function render_variation_row( $i, array $v, string $type ): void {
        $name        = $v['name']        ?? '';
        $price       = $v['price']       ?? '';
        $color       = $v['color']       ?? '#ffffff';
        $image_id    = $v['image_id']    ?? '';
        $image_url   = $v['image_url']   ?? '';
        $placeholder = $v['placeholder'] ?? '';
        ?>
        <tr class="fw-var-row" data-i="<?php echo esc_attr( $i ); ?>">
            <td class="fw-drag-handle" style="cursor:grab">⠿</td>
            <td>
                <label for="fw_variations_<?php echo esc_attr( $i ); ?>_name" class="screen-reader-text">Naam variatie</label>
                <input type="text"
                       id="fw_variations_<?php echo esc_attr( $i ); ?>_name"
                       name="fw_variations[<?php echo esc_attr( $i ); ?>][name]"
                       value="<?php echo esc_attr( $name ); ?>"
                       placeholder="Naam variatie" style="width:100%">
            </td>
            <td>
                <label for="fw_variations_<?php echo esc_attr( $i ); ?>_price" class="screen-reader-text">Meerprijs</label>
                <input type="number"
                       id="fw_variations_<?php echo esc_attr( $i ); ?>_price"
                       name="fw_variations[<?php echo esc_attr( $i ); ?>][price]"
                       value="<?php echo esc_attr( $price ); ?>"
                       step="0.01" min="0" style="width:100%">
            </td>
            <td class="fw-col-color <?php echo $type !== 'color' ? 'fw-hidden' : ''; ?>">
                <label for="fw_variations_<?php echo esc_attr( $i ); ?>_color" class="screen-reader-text">Kleur</label>
                <input type="color"
                       id="fw_variations_<?php echo esc_attr( $i ); ?>_color"
                       name="fw_variations[<?php echo esc_attr( $i ); ?>][color]"
                       value="<?php echo esc_attr( $color ); ?>"
                       style="width:48px;height:34px;padding:2px;cursor:pointer">
            </td>
            <td class="fw-col-image <?php echo $type === 'color' ? 'fw-hidden' : ''; ?>">
                <div class="fw-img-wrap">
                    <?php if ( $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>" class="fw-thumb" alt="Variatie afbeelding">
                    <?php endif; ?>
                    <input type="hidden" name="fw_variations[<?php echo esc_attr( $i ); ?>][image_id]"  value="<?php echo esc_attr( $image_id );  ?>" class="fw-image-id">
                    <input type="hidden" name="fw_variations[<?php echo esc_attr( $i ); ?>][image_url]" value="<?php echo esc_attr( $image_url ); ?>" class="fw-image-url">
                    <button type="button" class="button button-small fw-upload-img">📷</button>
                    <?php if ( $image_url ) : ?>
                        <button type="button" class="button-link-delete fw-remove-img" style="display:block;margin-top:3px;font-size:11px">verwijder</button>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <button type="button" class="button-link-delete fw-remove-var" title="Verwijder">✕</button>
            </td>
        </tr>
        <?php
    }

    // ── Save ──────────────────────────────────────────────────────────────────
    public static function save_meta( int $post_id ): void {
        if ( ! isset( $_POST['fw_lib_nonce'] ) ||
             ! wp_verify_nonce( $_POST['fw_lib_nonce'], 'fw_lib_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $type     = sanitize_text_field( $_POST['fw_group_type'] ?? 'radio' );
        $required = isset( $_POST['fw_group_required'] ) ? '1' : '';
        update_post_meta( $post_id, '_fw_group_type',     $type );
        update_post_meta( $post_id, '_fw_group_required', $required );

        if ( $type === 'text' ) {
            $variations = [ [
                'price'       => floatval( $_POST['fw_text_price']       ?? 0 ),
                'placeholder' => sanitize_text_field( $_POST['fw_text_placeholder'] ?? '' ),
            ] ];
        } elseif ( $type === 'length' ) {
            $lengths  = [];
            $raw      = $_POST['fw_length_lengths'] ?? [];
            foreach ( $raw as $v ) {
                $value = floatval( $v['value'] ?? 0 );
                if ( $value === 0 ) continue;
                $price = floatval( $v['price'] ?? 0 );
                $lengths[] = [ 'value' => $value, 'price' => $price ];
            }
            $custom  = isset( $_POST['fw_length_custom'] ) ? '1' : '';
            $min     = floatval( $_POST['fw_length_min'] ?? 0 );
            $max     = floatval( $_POST['fw_length_max'] ?? 0 );
            $step    = floatval( $_POST['fw_length_step'] ?? 1 );
            $variations = [ [
                'lengths'      => $lengths,
                'custom'      => $custom,
                'min'         => $min,
                'max'         => $max,
                'step'        => $step,
            ] ];
        } elseif ( in_array( $type, [ 'width' ] ) ) {
            $variations = [ [
                'label'       => sanitize_text_field( $_POST['fw_dimension_label'] ?? '' ),
                'min'         => floatval( $_POST['fw_dimension_min'] ?? 0 ),
                'max'         => floatval( $_POST['fw_dimension_max'] ?? 0 ),
                'step'        => floatval( $_POST['fw_dimension_step'] ?? 1 ),
                'price'       => floatval( $_POST['fw_dimension_price']       ?? 0 ),
                'placeholder' => sanitize_text_field( $_POST['fw_dimension_placeholder'] ?? '' ),
            ] ];
        } elseif ( $type === 'dimensions' ) {
            $variations = [];
            for ( $i = 0; $i < 2; $i++ ) {
                $prefix = $i === 0 ? 'l' : 'b';
                $variations[] = [
                    'label'       => sanitize_text_field( $_POST["fw_dimension_label_{$prefix}"] ?? '' ),
                    'min'         => floatval( $_POST["fw_dimension_min_{$prefix}"] ?? 0 ),
                    'max'         => floatval( $_POST["fw_dimension_max_{$prefix}"] ?? 0 ),
                    'step'        => floatval( $_POST["fw_dimension_step_{$prefix}"] ?? 1 ),
                    'price'       => floatval( $_POST["fw_dimension_price_{$prefix}"]       ?? 0 ),
                    'placeholder' => sanitize_text_field( $_POST["fw_dimension_placeholder_{$prefix}"] ?? '' ),
                    'image_id'    => absint( $_POST["fw_dimension_image_id_{$prefix}"] ?? 0 ),
                    'image_url'   => esc_url_raw( $_POST["fw_dimension_image_url_{$prefix}"] ?? '' ),
                ];
            }
        } elseif ( $type === 'depth' ) {
            $depths  = [];
            $raw      = $_POST['fw_depth_depths'] ?? [];
            foreach ( $raw as $v ) {
                $value = floatval( $v['value'] ?? 0 );
                if ( $value === 0 ) continue;
                $percent = floatval( $v['percent'] ?? 0 );
                $depths[] = [ 'value' => $value, 'percent' => $percent ];
            }
            $custom  = isset( $_POST['fw_depth_custom'] ) ? '1' : '';
            $min     = floatval( $_POST['fw_depth_min'] ?? 0 );
            $max     = floatval( $_POST['fw_depth_max'] ?? 0 );
            $step    = floatval( $_POST['fw_depth_step'] ?? 1 );
            $variations = [ [
                'depths'      => $depths,
                'depth_custom' => $custom,
                'depth_min'    => $min,
                'depth_max'    => $max,
                'depth_step'   => $step,
            ] ];
        } else {
            $raw        = $_POST['fw_variations'] ?? [];
            $variations = [];
            foreach ( $raw as $v ) {
                $name = sanitize_text_field( $v['name'] ?? '' );
                if ( $name === '' ) continue;
                $variations[] = [
                    'name'      => $name,
                    'price'     => floatval( $v['price'] ?? 0 ),
                    'color'     => sanitize_hex_color( $v['color'] ?? '#ffffff' ) ?: '#ffffff',
                    'image_id'  => absint( $v['image_id']  ?? 0 ),
                    'image_url' => esc_url_raw( $v['image_url'] ?? '' ),
                ];
            }
        }
        update_post_meta( $post_id, '_fw_variations', wp_json_encode( $variations ) );
    }

    // ── Admin kolommen ────────────────────────────────────────────────────────
    public static function columns( array $cols ): array {
        return [
            'cb'       => $cols['cb'],
            'title'    => 'Naam',
            'fw_type'  => 'Type',
            'fw_count' => 'Variaties',
            'fw_req'   => 'Verplicht',
            'date'     => $cols['date'],
        ];
    }

    public static function column_content( string $col, int $post_id ): void {
        $map = [ 'radio' => '🔘 Keuze', 'color' => '🎨 Kleur', 'text' => '✏️ Tekst' ];
        if ( $col === 'fw_type' ) {
            $t = get_post_meta( $post_id, '_fw_group_type', true ) ?: 'radio';
            echo esc_html( $map[ $t ] ?? $t );
        }
        if ( $col === 'fw_count' ) {
            $v = self::get_variations( $post_id );
            echo count( $v );
        }
        if ( $col === 'fw_req' ) {
            echo get_post_meta( $post_id, '_fw_group_required', true ) ? 'Ja' : '—';
        }
    }

    public static function get_all_groups(): array {
        $posts = get_posts( [
            'post_type'      => 'fw_option_group',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ] );

        $groups = [];
        foreach ( $posts as $p ) {
            $groups[] = [
                'id'         => $p->ID,
                'name'       => $p->post_title,
                'type'       => get_post_meta( $p->ID, '_fw_group_type',     true ) ?: 'radio',
                'required'   => get_post_meta( $p->ID, '_fw_group_required', true ) === '1',
                'variations' => self::get_variations( $p->ID ),
            ];
        }
        return $groups;
    }

    public static function get_group( int $id ): ?array {
        foreach ( self::get_all_groups() as $g ) {
            if ( $g['id'] === $id ) return $g;
        }
        return null;
    }

    public static function get_variations( int $post_id ): array {
        $json = get_post_meta( $post_id, '_fw_variations', true );
        return $json ? (array) json_decode( $json, true ) : [];
    }
}
