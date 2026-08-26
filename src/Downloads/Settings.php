<?php
/**
 * Download Settings Field Registry for Easy Digital Downloads
 *
 * @package ArrayPress/EDD-Utils
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Downloads;

use ArrayPress\EDD\Register\Abstracts\BaseFieldRegistry;

/**
 * Download Settings Field Registry
 *
 * Handles registration and rendering of custom fields in the download
 * settings tab. Organizes fields into sections with proper EDD integration.
 */
class Settings extends BaseFieldRegistry {

	/**
	 * Registered sections
	 *
	 * @var array
	 */
	protected array $sections = [];

	/**
	 * Initialize the registry
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'edd_meta_box_settings_fields', [ $this, 'render_sections' ], 100 );
		add_filter( 'edd_metabox_fields_save', [ $this, 'register_save_fields' ] );
	}

	/**
	 * Register a section
	 *
	 * @param string $id     Section ID
	 * @param string $title  Section title
	 * @param array  $config Section configuration
	 *
	 * @return bool
	 */
	public function register_section( string $id, string $title, array $config = [] ): bool {
		$defaults = [
			'title'       => $title,
			'description' => '',
			'priority'    => 10,
			'fields'      => [],
		];

		$this->sections[ $id ] = wp_parse_args( $config, $defaults );

		return true;
	}

	/**
	 * Add field to section
	 *
	 * @param string $section_id   Section ID
	 * @param string $field_id     Field ID
	 * @param array  $field_config Field configuration
	 *
	 * @return bool
	 */
	public function add_field_to_section( string $section_id, string $field_id, array $field_config ): bool {
		if ( ! isset( $this->sections[ $section_id ] ) ) {
			return false;
		}

		$this->register_field( $field_id, $field_config );
		$this->sections[ $section_id ]['fields'][] = $field_id;

		return true;
	}

	/**
	 * Render all sections
	 *
	 * @param int $post_id Post ID
	 *
	 * @return void
	 */
	public function render_sections( int $post_id ): void {
		foreach ( $this->sections as $section_id => $section ) {
			$this->render_section( $section_id, $section, $post_id );
		}
	}

	/**
	 * Render a section
	 *
	 * @param string $section_id Section ID
	 * @param array  $section    Section configuration
	 * @param int    $post_id    Post ID
	 *
	 * @return void
	 */
	private function render_section( string $section_id, array $section, int $post_id ): void {
		?>
        <div class="edd-form-group edd-product-options-wrapper">
            <div class="edd-product-options__title">
				<?php echo esc_html( $section['title'] ); ?>
            </div>
			<?php if ( ! empty( $section['description'] ) ) : ?>
                <p class="description"><?php echo wp_kses_post( $section['description'] ); ?></p>
			<?php endif; ?>
        </div>

		<?php if ( isset( $section['fields'] ) && is_array( $section['fields'] ) ) : ?>
			<?php foreach ( $section['fields'] as $field_data ) : ?>
				<?php
				// Handle both string field IDs and array field data
				if ( is_string( $field_data ) ) {
					$field_id = $field_data;
				} elseif ( is_array( $field_data ) && isset( $field_data[0] ) && is_string( $field_data[0] ) ) {
					$field_id = $field_data[0];
				} else {
					continue; // Skip invalid field data
				}
				?>
				<?php $this->render_field_wrapper( $field_id, $post_id ); ?>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render field wrapper
	 *
	 * @param string $field_id Field ID
	 * @param int    $post_id  Post ID
	 *
	 * @return void
	 */
	private function render_field_wrapper( string $field_id, int $post_id ): void {
		if ( ! isset( $this->fields[ $field_id ] ) ) {
			return;
		}

		$field = $this->fields[ $field_id ];
		$value = $this->get_field_value( $post_id, $field_id );

		?>
        <div class="edd-form-group">
			<?php if ( $field['type'] !== 'checkbox' && $field['type'] !== 'checkbox_toggle' ) : ?>
                <label for="<?php echo esc_attr( $field_id ); ?>" class="edd-form-group__label">
					<?php echo esc_html( $field['label'] ); ?>
					<?php if ( $field['required'] ) : ?>
                        <span class="required">*</span>
					<?php endif; ?>
                </label>
			<?php endif; ?>

            <div class="edd-form-group__control">
				<?php
                echo $this->render_field( $field_id, $value, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderers escape every value and attribute they emit; escaping the assembled markup again would print the tags.
				?>

				<?php if ( $field['type'] === 'checkbox' ) : ?>
                    <label class="edd-form-group__label" for="<?php echo esc_attr( $field_id ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
						<?php if ( $field['required'] ) : ?>
                            <span class="required">*</span>
						<?php endif; ?>
                    </label>
				<?php endif; ?>
            </div>

			<?php if ( ! empty( $field['description'] ) ) : ?>
                <p class="edd-form-group__help description"><?php echo wp_kses_post( $field['description'] ); ?></p>
			<?php endif; ?>
        </div>
		<?php
	}

	/**
	 * Register fields for saving
	 *
	 * @param array $fields Current save fields
	 *
	 * @return array
	 */
	public function register_save_fields( array $fields ): array {
		return array_merge( $fields, array_keys( $this->fields ) );
	}
}