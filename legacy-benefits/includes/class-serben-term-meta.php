<?php
/**
 * Category extra fields: icon, color and display order.
 */

defined( 'ABSPATH' ) || exit;

class Serben_Term_Meta {
	public function init(): void {
		add_action( SERBEN_TAXONOMY . '_add_form_fields', array( $this, 'add_fields' ) );
		add_action( SERBEN_TAXONOMY . '_edit_form_fields', array( $this, 'edit_fields' ) );
		add_action( 'created_' . SERBEN_TAXONOMY, array( $this, 'save' ) );
		add_action( 'edited_' . SERBEN_TAXONOMY, array( $this, 'save' ) );
		add_filter( 'manage_edit-' . SERBEN_TAXONOMY . '_columns', array( $this, 'columns' ) );
		add_filter( 'manage_' . SERBEN_TAXONOMY . '_custom_column', array( $this, 'column_content' ), 10, 3 );
	}

	public function add_fields(): void {
		wp_nonce_field( 'serben_term_meta', 'serben_term_meta_nonce' );
		?>
		<div class="form-field term-serben-icon-wrap">
			<label for="serben-icon"><?php esc_html_e( 'Ícone', 'serben-clube-beneficios' ); ?></label>
			<input name="serben_icon" id="serben-icon" type="text" value="" placeholder="Ex.: 🏋️ ou dashicons-heart">
		</div>
		<div class="form-field term-serben-color-wrap">
			<label for="serben-color"><?php esc_html_e( 'Cor', 'serben-clube-beneficios' ); ?></label>
			<input name="serben_color" id="serben-color" type="color" value="#2f7d63">
		</div>
		<div class="form-field term-serben-order-wrap">
			<label for="serben-order"><?php esc_html_e( 'Ordem de exibição', 'serben-clube-beneficios' ); ?></label>
			<input name="serben_order" id="serben-order" type="number" value="0" min="0" step="1">
		</div>
		<?php
	}

	public function edit_fields( WP_Term $term ): void {
		$icon  = (string) get_term_meta( $term->term_id, SERBEN_META_PREFIX . 'icon', true );
		$color = (string) get_term_meta( $term->term_id, SERBEN_META_PREFIX . 'color', true );
		$order = absint( get_term_meta( $term->term_id, SERBEN_META_PREFIX . 'order', true ) );
		wp_nonce_field( 'serben_term_meta', 'serben_term_meta_nonce' );
		?>
		<tr class="form-field term-serben-icon-wrap">
			<th scope="row"><label for="serben-icon"><?php esc_html_e( 'Ícone', 'serben-clube-beneficios' ); ?></label></th>
			<td><input name="serben_icon" id="serben-icon" type="text" value="<?php echo esc_attr( $icon ); ?>"></td>
		</tr>
		<tr class="form-field term-serben-color-wrap">
			<th scope="row"><label for="serben-color"><?php esc_html_e( 'Cor', 'serben-clube-beneficios' ); ?></label></th>
			<td><input name="serben_color" id="serben-color" type="color" value="<?php echo esc_attr( $color ?: '#2f7d63' ); ?>"></td>
		</tr>
		<tr class="form-field term-serben-order-wrap">
			<th scope="row"><label for="serben-order"><?php esc_html_e( 'Ordem de exibição', 'serben-clube-beneficios' ); ?></label></th>
			<td><input name="serben_order" id="serben-order" type="number" value="<?php echo esc_attr( $order ); ?>" min="0" step="1"></td>
		</tr>
		<?php
	}

	public function save( int $term_id ): void {
		if ( ! isset( $_POST['serben_term_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['serben_term_meta_nonce'] ) ), 'serben_term_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$icon  = isset( $_POST['serben_icon'] ) ? sanitize_text_field( wp_unslash( $_POST['serben_icon'] ) ) : '';
		$color = isset( $_POST['serben_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['serben_color'] ) ) : '#2f7d63';
		$order = isset( $_POST['serben_order'] ) ? absint( $_POST['serben_order'] ) : 0;

		update_term_meta( $term_id, SERBEN_META_PREFIX . 'icon', $icon );
		update_term_meta( $term_id, SERBEN_META_PREFIX . 'color', $color ?: '#2f7d63' );
		update_term_meta( $term_id, SERBEN_META_PREFIX . 'order', $order );
		Serben_Utils::clear_query_cache();
	}

	public function columns( array $columns ): array {
		$columns['serben_icon']  = __( 'Ícone', 'serben-clube-beneficios' );
		$columns['serben_color'] = __( 'Cor', 'serben-clube-beneficios' );
		$columns['serben_order'] = __( 'Ordem', 'serben-clube-beneficios' );
		return $columns;
	}

	public function column_content( string $content, string $column_name, int $term_id ): string {
		if ( 'serben_icon' === $column_name ) {
			return esc_html( (string) get_term_meta( $term_id, SERBEN_META_PREFIX . 'icon', true ) );
		}
		if ( 'serben_color' === $column_name ) {
			$color = Serben_Utils::term_color( $term_id );
			return '<span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:' . esc_attr( $color ) . '"></span> ' . esc_html( $color );
		}
		if ( 'serben_order' === $column_name ) {
			return esc_html( (string) absint( get_term_meta( $term_id, SERBEN_META_PREFIX . 'order', true ) ) );
		}
		return $content;
	}
}
