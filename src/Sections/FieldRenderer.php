<?php
/**
 * Section Field Renderer for EDD
 *
 * @package ArrayPress/EDD-Utils
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Sections;

use ArrayPress\EDD\Register\Abstracts\BaseFieldRegistry;

/**
 * Section Field Renderer
 *
 * Handles rendering of fields within EDD download sections.
 * This class is used by configurable sections to render their fields
 * with proper EDD styling and save functionality.
 */
class FieldRenderer extends BaseFieldRegistry {

	/**
	 * Static array to track all field IDs across all renderer instances
	 *
	 * @var array
	 */
	private static array $all_field_ids = [];

	/**
	 * Static flag to ensure init only runs once
	 *
	 * @var bool
	 */
	private static bool $hooks_added = false;

	/**
	 * Initialize the registry
	 *
	 * @return void
	 */
	public function init(): void {
		// Only add hooks once across all instances
		if ( ! self::$hooks_added ) {
			self::$hooks_added = true;

			// Add the save filter
			add_filter( 'edd_metabox_fields_save', function ( $fields ) {
				return array_merge( $fields, self::$all_field_ids );
			} );
		}
	}

	/**
	 * Override register_field to collect field IDs
	 *
	 * @param string $id     Field ID
	 * @param array  $config Field configuration
	 *
	 * @return bool
	 */
	public function register_field( string $id, array $config ): bool {
		$result = parent::register_field( $id, $config );

		if ( $result ) {
			// Add to our static collection
			if ( ! in_array( $id, self::$all_field_ids, true ) ) {
				self::$all_field_ids[] = $id;
			}

			// Register meta field
			$this->register_meta_field( $id, $this->fields[ $id ] );
		}

		return $result;
	}

	/**
	 * Render fields for a section
	 *
	 * @param array $fields  Array of field configurations
	 * @param int   $post_id Post ID
	 *
	 * @return void
	 */
	public function render_fields( array $fields, int $post_id ): void {
		// Make sure init has run
		$this->init();

		// Register and render fields
		foreach ( $fields as $field_id => $field_config ) {
			$this->register_field( $field_id, $field_config );
			$this->render_single_field( $field_id, $post_id );
		}
	}

	/**
	 * Render a single field
	 *
	 * @param string $field_id Field ID
	 * @param int    $post_id  Post ID
	 *
	 * @return void
	 */
	protected function render_single_field( string $field_id, int $post_id ): void {
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
}