<?php
/**
 * Variable Price Options Field Registry for Easy Digital Downloads
 *
 * @package ArrayPress/EDD-Utils
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Downloads;

use ArrayPress\EDD\Register\Abstracts\BaseFieldRegistry;

/**
 * Variable Price Options Field Registry
 *
 * Handles registration and rendering of custom fields that appear
 * in each variable price row when variable pricing is enabled.
 * Perfect for per-variation settings like license limits, support levels, etc.
 */
class VariablePriceOptions extends BaseFieldRegistry {

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
		add_action( 'edd_download_price_option_row', [ $this, 'render_sections' ], 800, 3 );
		add_filter( 'edd_metabox_save_edd_variable_prices', [ $this, 'sanitize_variable_prices' ], - 999 );
		add_action( 'admin_footer', [ $this, 'add_inline_css' ] );
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
			'title'    => $title,
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

		// Remove 'required' and 'description' for variable pricing to keep interface clean
		unset( $field_config['required'], $field_config['description'] );

		$this->register_field( $field_id, $field_config );
		$this->sections[ $section_id ]['fields'][] = $field_id;

		return true;
	}

	/**
	 * Render sections for variable price row
	 *
	 * @param int   $download_id Download ID
	 * @param int   $price_id    Price ID
	 * @param array $args        Arguments
	 *
	 * @return void
	 */
	public function render_sections( int $download_id, int $price_id, array $args ): void {
		foreach ( $this->sections as $section_id => $section ) {
			$this->render_section( $section_id, $section, $download_id, $price_id );
		}
	}

	/**
	 * Render a section
	 *
	 * @param string $section_id  Section ID
	 * @param array  $section     Section configuration
	 * @param int    $download_id Download ID
	 * @param int    $price_id    Price ID
	 *
	 * @return void
	 */
	private function render_section( string $section_id, array $section, int $download_id, int $price_id ): void {
		?>
        <div class="edd-custom-price-option-section edd-custom-price-option-section--<?php echo esc_attr( $section_id ); ?>">
            <span class="edd-custom-price-option-section-title"><?php echo esc_html( $section['title'] ); ?></span>
            <div class="edd-custom-price-option-section-content edd-form-row edd-form-row__settings">
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
						<?php $this->render_variable_price_field( $field_id, $download_id, $price_id ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
            </div>
        </div>
		<?php
	}

	/**
	 * Render variable price field
	 *
	 * @param string $field_id    Field ID
	 * @param int    $download_id Download ID
	 * @param int    $price_id    Price ID
	 *
	 * @return void
	 */
	private function render_variable_price_field( string $field_id, int $download_id, int $price_id ): void {
		if ( ! isset( $this->fields[ $field_id ] ) ) {
			return;
		}

		$field = $this->fields[ $field_id ];
		$value = $this->get_variable_price_value( $download_id, $price_id, $field_id, $field['default'] );
		$name  = "edd_variable_prices[{$price_id}][{$field_id}]";
		$id    = "edd_variable_prices_{$price_id}_{$field_id}";

		// Create a copy of the field to modify attributes without affecting the original
		$field_copy               = $field;
		$field_copy['attributes'] = $field['attributes']; // Copy attributes array

		// Override the build_attributes method by temporarily modifying the field
		$original_field                                  = $this->fields[ $field_id ];
		$this->fields[ $field_id ]['attributes']['name'] = $name;
		$this->fields[ $field_id ]['attributes']['id']   = $id;

		?>
        <div class="edd-form-group edd-form-row__column">
			<?php if ( $field['type'] !== 'checkbox' && $field['type'] !== 'checkbox_toggle' ) : ?>
                <label for="<?php echo esc_attr( $id ); ?>" class="edd-form-group__label">
					<?php echo esc_html( $field['label'] ); ?>
                </label>
			<?php endif; ?>

            <div class="edd-form-group__control">
				<?php if ( ! empty( $field['prefix'] ) ) : ?>
                    <span class="edd-field-prefix"><?php echo esc_html( $field['prefix'] ); ?></span>
				<?php endif; ?>

				<?php
                echo $this->render_field( $field_id, $value, $download_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderers escape every value and attribute they emit; escaping the assembled markup again would print the tags.
				?>

				<?php if ( ! empty( $field['suffix'] ) ) : ?>
                    <span class="edd-field-suffix"><?php echo esc_html( $field['suffix'] ); ?></span>
				<?php endif; ?>

				<?php if ( $field['type'] === 'checkbox' ) : ?>
                    <label class="edd-form-group__label" for="<?php echo esc_attr( $id ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
                    </label>
				<?php endif; ?>
            </div>
        </div>
		<?php

		// Restore original field configuration
		$this->fields[ $field_id ] = $original_field;
	}

	/**
	 * Get variable price field value
	 *
	 * @param int    $download_id Download ID
	 * @param int    $price_id    Price ID
	 * @param string $field_id    Field ID
	 * @param mixed  $fallback    Value to use when the price has none
	 *
	 * @return mixed
	 */
	private function get_variable_price_value( int $download_id, int $price_id, string $field_id, $fallback ) {
		$prices = edd_get_variable_prices( $download_id );

		return $prices[ $price_id ][ $field_id ] ?? $fallback;
	}

	/**
	 * Sanitize variable prices
	 *
	 * @param array $prices Variable prices array
	 *
	 * @return array
	 */
	public function sanitize_variable_prices( array $prices ): array {
		foreach ( $prices as $price_id => $price ) {
			foreach ( $this->fields as $field_id => $field ) {
				if ( isset( $price[ $field_id ] ) ) {
					// Use the sanitize callback from the registered field configuration
					$sanitize_callback                = $field['sanitize_callback'];
					$prices[ $price_id ][ $field_id ] = call_user_func( $sanitize_callback, $price[ $field_id ] );
				}
			}
		}

		return $prices;
	}

	/**
	 * Add CSS for variable pricing fields
	 *
	 * @return void
	 */
	public function add_inline_css(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || $current_screen->post_type !== 'download' ) {
			return;
		}

		?>
        <style type="text/css">
            .edd-custom-price-option-section .edd-toggle label {
                margin: 0;
                order: 1;
            }

            .edd-custom-price-option-section .edd-toggle input[type="checkbox"] {
                order: 2;
                margin: 0;
            }

            /* Fix color field width */
            .edd-custom-price-option-section input[type="color"] {
                width: 32px;
                height: 32px;
                padding: 1px;
                border: 1px solid #ddd;
                border-radius: 3px;
            }

            /* Ensure consistent field widths */
            .edd-custom-price-option-section input[type="text"],
            .edd-custom-price-option-section input[type="email"],
            .edd-custom-price-option-section input[type="url"],
            .edd-custom-price-option-section input[type="number"],
            .edd-custom-price-option-section select {
                min-width: 120px;
                max-width: 200px;
            }

            /* Currency field styling */
            .edd-custom-price-option-section .edd-amount-type-wrapper {
                display: flex;
                align-items: center;
            }

            /* Textarea styling */
            .edd-custom-price-option-section textarea {
                min-width: 200px;
                max-width: 300px;
            }

            /* Radio button styling */
            .edd-custom-price-option-section input[type="radio"] {
                margin-right: 5px;
            }
        </style>
		<?php
	}
}