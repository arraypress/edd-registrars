<?php
/**
 * Single Price Options Field Registry for Easy Digital Downloads
 *
 * @package ArrayPress/EDD-Utils
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Downloads;

use ArrayPress\EDD\Register\Abstracts\BaseFieldRegistry;

/**
 * Single Price Options Field Registry
 *
 * Handles registration and rendering of custom fields that appear
 * when variable pricing is disabled. Fields are shown directly
 * after the main price field.
 */
class SinglePriceOptions extends BaseFieldRegistry {

	/**
	 * Registered sections
	 *
	 * @var array
	 */
	protected array $sections = [];

	/**
	 * Flag to check if JS has been added
	 *
	 * @var bool
	 */
	private static bool $js_added = false;

	/**
	 * Initialize the registry
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'edd_after_price_field', [ $this, 'render_sections' ], 10 );
		add_filter( 'edd_metabox_fields_save', [ $this, 'register_save_fields' ] );
		add_action( 'admin_footer', [ $this, 'add_admin_scripts' ] );
	}

	/**
	 * Register a section
	 *
	 * @param string $id     Section ID
	 * @param array  $config Section configuration
	 *
	 * @return bool
	 */
	public function register_section( string $id, array $config = [] ): bool {
		$defaults = [
			'fields'   => [],
			'priority' => 10,
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
		?>
        <div id="edd-custom-single-price-options" class="edd-custom-single" data-edd-requires-variable-pricing="false">
			<?php foreach ( $this->sections as $section_id => $section ) : ?>
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
						<?php $this->render_single_price_field( $field_id, $post_id ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
			<?php endforeach; ?>
        </div>
		<?php
	}

	/**
	 * Render single price field
	 *
	 * @param string $field_id Field ID
	 * @param int    $post_id  Post ID
	 *
	 * @return void
	 */
	private function render_single_price_field( string $field_id, int $post_id ): void {
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
				<?php if ( $field['type'] === 'currency' ) : ?>
					<?php
                    echo $this->render_currency_wrapper( $field_id, $field, $value, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderers escape every value and attribute they emit; escaping the assembled markup again would print the tags.
				?>
				<?php else : ?>
					<?php if ( ! empty( $field['prefix'] ) ) : ?>
                        <span class="edd-field-prefix"><?php echo esc_html( $field['prefix'] ); ?></span>
					<?php endif; ?>

					<?php
                    echo $this->render_field( $field_id, $value, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderers escape every value and attribute they emit; escaping the assembled markup again would print the tags.
					?>

					<?php if ( ! empty( $field['suffix'] ) ) : ?>
                        <span class="edd-field-suffix"><?php echo esc_html( $field['suffix'] ); ?></span>
					<?php endif; ?>
				<?php endif; ?>

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
	 * Render currency wrapper with symbol
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	private function render_currency_wrapper( string $field_id, array $field, $value, int $post_id ): string {
		$currency_position = edd_get_option( 'currency_position', 'before' );
		$currency_symbol   = edd_currency_symbol();

		$html = '<span class="edd-amount-type-wrapper">';

		// Currency symbol before
		if ( 'before' === $currency_position ) {
			$html .= sprintf( '<span class="edd-input__symbol edd-input__symbol--prefix">%s</span>', esc_html( $currency_symbol ) );
		}

		// Render the input field
		$field_copy                       = $field;
		$field_copy['type']               = 'number'; // Currency fields are number inputs
		$field_copy['class']              = 'edd-amount-input edd-price-field no-controls symbol-' . $currency_position;
		$field_copy['attributes']['step'] = '0.01';

		$html .= $this->render_number_field( $field_id, $field_copy, $value, $post_id );

		// Currency symbol after
		if ( 'after' === $currency_position ) {
			$html .= sprintf( '<span class="edd-input__symbol edd-input__symbol--suffix">%s</span>', esc_html( $currency_symbol ) );
		}

		$html .= '</span>';

		return $html;
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

	/**
	 * Add admin scripts to toggle visibility
	 *
	 * @return void
	 */
	public function add_admin_scripts(): void {
		if ( self::$js_added ) {
			return;
		}

		$current_screen = get_current_screen();
		if ( ! $current_screen || $current_screen->post_type !== 'download' ) {
			return;
		}

		?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                function toggleSinglePriceOptions() {
                    const $variablePricing = $('#edd_variable_pricing');
                    const $singlePriceOptions = $('#edd-custom-single-price-options');

                    function updateVisibility() {
                        $singlePriceOptions.toggle(!$variablePricing.is(':checked'));
                    }

                    $variablePricing.on('change', updateVisibility);
                    updateVisibility(); // Initial state
                }

                toggleSinglePriceOptions();
            });
        </script>
		<?php

		self::$js_added = true;
	}
}