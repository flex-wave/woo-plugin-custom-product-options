<?php
/* ================================================================
   FlexWave – Frontend CSS
   https://flex-wave.nl
   Copyright (c) 2026 FlexWave
   E-mail: jorian@flex-wave.nl
   Alle rechten voorbehouden
   ================================================================ */

if ( ! defined( 'ABSPATH' ) ) exit;

class FW_ProductMeta {

    public static function init(): void {
        add_action( 'add_meta_boxes',        [ __CLASS__, 'add' ] );
        add_action( 'save_post_product',     [ __CLASS__, 'save' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function add(): void {
        add_meta_box(
            'fw_product_options',
            'FlexWave – Actieve optiegroepen & variaties',
            [ __CLASS__, 'render' ],
            'product',
            'normal',
            'high'
        );
    }

    public static function enqueue( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        if ( get_post_type() !== 'product' ) return;
        wp_enqueue_style(  'fw-product-css', FW_URL . 'assets/product-meta.css', [], FW_VERSION );
        wp_enqueue_script( 'fw-product-js',  FW_URL . 'assets/product-meta.js',  [], FW_VERSION, true );
    }

    public static function render( WP_Post $post ): void {
        wp_nonce_field( 'fw_product_save', 'fw_product_nonce' );

        $all_groups = FW_Library::get_all_groups();
        $saved      = self::get_saved_config( $post->ID );

        if ( empty( $all_groups ) ) {
            $url = admin_url( 'edit.php?post_type=fw_option_group' );
            printf(
                '<p>Nog geen optiegroepen aangemaakt. <a href="%s">Maak eerst optiegroepen aan</a>.</p>',
                esc_url( $url )
            );
            return;
        }

        $type_icons = [ 'radio' => '🔘', 'color' => '🎨', 'text' => '✏️' ];

        $active_ids = array_keys( $saved );
        $sorted = [];
        foreach ( $active_ids as $aid ) {
            foreach ( $all_groups as $g ) {
                if ( $g['id'] === (int) $aid ) { $sorted[] = $g; break; }
            }
        }
        foreach ( $all_groups as $g ) {
            if ( ! isset( $saved[ $g['id'] ] ) ) $sorted[] = $g;
        }
        ?>
        <p class="description" style="margin-bottom:.75rem">
            ☑ Vink een groep aan om hem actief te maken. Klik op <strong>▶ variaties</strong> om per variatie te kiezen welke zichtbaar zijn op de productpagina. Sleep rijen voor de volgorde.
        </p>

        <input type="hidden" name="fw_groups_order" id="fw_groups_order"
               value="<?php echo esc_attr( wp_json_encode( $active_ids ) ); ?>">

        <div id="fw-pm-list">
        <?php foreach ( $sorted as $group ) :
            $gid        = $group['id'];
            $is_active  = isset( $saved[ $gid ] );
            $saved_vars = $saved[ $gid ]['variations'] ?? 'all';
            $icon       = $type_icons[ $group['type'] ] ?? '';
            $is_text    = $group['type'] === 'text';
        ?>
            <div class="fw-pm-row <?php echo $is_active ? 'fw-pm--active' : ''; ?>"
                 data-gid="<?php echo esc_attr( $gid ); ?>">

                <div class="fw-pm-header">
                    <span class="fw-pm-drag" title="Versleep om volgorde te wijzigen">⠿</span>

                    <label class="fw-pm-toggle" title="<?php echo $is_active ? 'Deactiveren' : 'Activeren'; ?>">
                        <input type="checkbox"
                               class="fw-pm-check"
                               name="fw_groups[<?php echo esc_attr( $gid ); ?>][active]"
                               value="1"
                               <?php checked( $is_active ); ?>>
                    </label>

                    <span class="fw-pm-icon"><?php echo $icon; ?></span>
                    <span class="fw-pm-name"><?php echo esc_html( $group['name'] ); ?></span>

                    <span class="fw-pm-meta">
                        <?php if ( $is_text ) : ?>
                            <em class="fw-pm-hint">vrije invoer</em>
                        <?php else : ?>
                            <?php
                            $n_lib     = count( $group['variations'] );
                            $n_active  = ( $is_active && is_array( $saved_vars ) ) ? count( $saved_vars ) : $n_lib;
                            $show_all  = ! $is_active || $saved_vars === 'all';
                            $badge_txt = $show_all
                                ? $n_lib . ' variaties'
                                : $n_active . ' / ' . $n_lib . ' variaties';
                            ?>
                            <span class="fw-pm-badge <?php echo $show_all ? '' : 'fw-pm-badge--filtered'; ?>">
                                <?php echo esc_html( $badge_txt ); ?>
                            </span>
                            <button type="button"
                                    class="fw-pm-expand button button-small"
                                    <?php echo ! $is_active ? 'disabled' : ''; ?>>
                                ▶ variaties
                            </button>
                        <?php endif; ?>
                        <?php if ( $group['required'] ) : ?>
                            <span class="fw-pm-req" title="Verplicht veld op frontend">verplicht</span>
                        <?php endif; ?>
                    </span>

                    <a href="<?php echo esc_url( get_edit_post_link( $gid ) ); ?>"
                       target="_blank" class="fw-pm-edit button button-small" title="Groep bewerken in bibliotheek">✏️</a>
                </div>

                <?php if ( $group['type'] === 'length' ) : ?>
                <div class="fw-pm-vars" style="display:none">
                    <div class="fw-pm-vars-inner">
                        <label class="fw-pm-selall">
                            <input type="checkbox"
                                   class="fw-pm-select-all"
                                   data-gid="<?php echo esc_attr( $gid ); ?>"
                                   <?php checked( $saved_vars === 'all' || ! $is_active ); ?>>
                            <strong>Alle standaardmaten tonen</strong>
                        </label>
                        <div class="fw-pm-var-list">
                            <?php
                            $lengths = $group['variations'][0]['lengths'] ?? [];
                            foreach ( $lengths as $vi => $v ) :
                                $var_checked = $saved_vars === 'all'
                                    || ! $is_active
                                    || ( is_array( $saved_vars ) && in_array( $vi, $saved_vars, true ) );
                            ?>
                            <label class="fw-pm-var-item">
                                <input type="checkbox"
                                       class="fw-pm-var-check"
                                       name="fw_groups[<?php echo esc_attr( $gid ); ?>][vars][]"
                                       value="<?php echo esc_attr( $vi ); ?>"
                                       <?php checked( $var_checked ); ?>>
                                <span class="fw-pm-var-name"><?php echo esc_html( $v['value'] ); ?> mm</span>
                                <?php if ( (float)( $v['price'] ?? 0 ) > 0 ) : ?>
                                    <em class="fw-pm-var-price">
                                        + €<?php echo number_format( (float)$v['price'], 2, ',', '.' ); ?>
                                    </em>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php elseif ( $group['type'] === 'depth' ) : ?>
                <div class="fw-pm-vars" style="display:none">
                    <div class="fw-pm-vars-inner">
                        <label class="fw-pm-selall">
                            <input type="checkbox"
                                   class="fw-pm-select-all"
                                   data-gid="<?php echo esc_attr( $gid ); ?>"
                                   <?php checked( $saved_vars === 'all' || ! $is_active ); ?>>
                            <strong>Alle standaardmaten tonen</strong>
                        </label>
                        <div class="fw-pm-var-list">
                            <?php
                            $depths = $group['variations'][0]['depths'] ?? [];
                            foreach ( $depths as $vi => $v ) :
                                $var_checked = $saved_vars === 'all'
                                    || ! $is_active
                                    || ( is_array( $saved_vars ) && in_array( $vi, $saved_vars, true ) );
                            ?>
                            <label class="fw-pm-var-item">
                                <input type="checkbox"
                                       class="fw-pm-var-check"
                                       name="fw_groups[<?php echo esc_attr( $gid ); ?>][vars][]"
                                       value="<?php echo esc_attr( $vi ); ?>"
                                       <?php checked( $var_checked ); ?>>
                                <span class="fw-pm-var-name"><?php echo esc_html( $v['value'] ); ?> mm</span>
                                <?php if ( (float)( $v['percent'] ?? 0 ) > 0 ) : ?>
                                    <em class="fw-pm-var-price">
                                        + <?php echo number_format( (float)$v['percent'], 2, ',', '.' ); ?>%
                                    </em>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php elseif ( ! $is_text ) : ?>
                <div class="fw-pm-vars" style="display:none">
                    <div class="fw-pm-vars-inner">

                        <label class="fw-pm-selall">
                            <input type="checkbox"
                                   class="fw-pm-select-all"
                                   data-gid="<?php echo esc_attr( $gid ); ?>"
                                   <?php checked( $saved_vars === 'all' || ! $is_active ); ?>>
                            <strong>Alle variaties tonen</strong>
                        </label>

                        <div class="fw-pm-var-list">
                            <?php foreach ( $group['variations'] as $vi => $v ) :
                                $var_checked = $saved_vars === 'all'
                                    || ! $is_active
                                    || ( is_array( $saved_vars ) && in_array( $vi, $saved_vars, true ) );
                            ?>
                            <label class="fw-pm-var-item">
                                <?php if ( ! empty( $v['image_url'] ) ) : ?>
                                    <img src="<?php echo esc_url( $v['image_url'] ); ?>"
                                         alt="<?php echo esc_attr( $v['name'] ?? '' ); ?>"
                                         class="fw-pm-var-thumb">
                                <?php elseif ( ! empty( $v['color'] ) ) : ?>
                                    <span class="fw-pm-var-swatch"
                                          style="background:<?php echo esc_attr( $v['color'] ); ?>"></span>
                                <?php endif; ?>

                                <input type="checkbox"
                                       class="fw-pm-var-check"
                                       name="fw_groups[<?php echo esc_attr( $gid ); ?>][vars][]"
                                       value="<?php echo esc_attr( $vi ); ?>"
                                       <?php checked( $var_checked ); ?>>

                                <span class="fw-pm-var-name"><?php echo esc_html( $v['name'] ?? '' ); ?></span>
                                <?php if ( (float)( $v['price'] ?? 0 ) > 0 ) : ?>
                                    <em class="fw-pm-var-price">
                                        + €<?php echo number_format( (float)$v['price'], 2, ',', '.' ); ?>
                                    </em>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
        </div>
        <?php
    }

    public static function save( int $post_id ): void {
        if ( ! isset( $_POST['fw_product_nonce'] ) ||
             ! wp_verify_nonce( $_POST['fw_product_nonce'], 'fw_product_save' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $raw        = $_POST['fw_groups'] ?? [];
        $order_json = sanitize_text_field( wp_unslash( $_POST['fw_groups_order'] ?? '' ) );
        $order      = $order_json ? array_map( 'intval', (array) json_decode( $order_json, true ) ) : [];

        $config = [];
        foreach ( $raw as $gid => $data ) {
            $gid = absint( $gid );
            if ( empty( $data['active'] ) ) continue;

            if ( isset( $data['vars'] ) ) {
                $vars = array_map( 'intval', (array) $data['vars'] );
            } else {
                $vars = 'all';
            }

            $config[ $gid ] = [ 'variations' => $vars ];
        }

        $ordered = [];
        foreach ( $order as $gid ) {
            if ( isset( $config[ $gid ] ) ) {
                $ordered[ $gid ] = $config[ $gid ];
                unset( $config[ $gid ] );
            }
        }
        foreach ( $config as $gid => $val ) {
            $ordered[ $gid ] = $val;
        }

        update_post_meta( $post_id, '_fw_active_groups', wp_json_encode( $ordered ) );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function get_saved_config( int $product_id ): array {
        $json = get_post_meta( $product_id, '_fw_active_groups', true );
        if ( ! $json ) return [];
        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) return [];

        // Backwards-compat: oud formaat was int[] (alleen IDs, geen variaties)
        if ( isset( $decoded[0] ) && is_int( $decoded[0] ) ) {
            $compat = [];
            foreach ( $decoded as $id ) {
                $compat[ (int) $id ] = [ 'variations' => 'all' ];
            }
            return $compat;
        }

        $result = [];
        foreach ( $decoded as $gid => $val ) {
            $result[ (int) $gid ] = is_array( $val ) ? $val : [ 'variations' => 'all' ];
        }
        return $result;
    }

    /**
     * Geeft actieve groepen terug met variaties gefilterd op productinstelling.
     */
    public static function get_active_groups( int $product_id ): array {
        $config = self::get_saved_config( $product_id );
        $result = [];

        foreach ( $config as $gid => $cfg ) {
            $group        = FW_Library::get_group( $gid );
            if ( ! $group ) continue;

            $allowed = $cfg['variations'] ?? 'all';

            if ( $group['type'] !== 'text' && $allowed !== 'all' && is_array( $allowed ) ) {
                $filtered = [];
                foreach ( $group['variations'] as $vi => $v ) {
                    if ( in_array( $vi, $allowed, true ) ) {
                        $filtered[] = $v;
                    }
                }
                $group['variations'] = array_values( $filtered );
            }

            $result[] = $group;
        }

        return $result;
    }
}
