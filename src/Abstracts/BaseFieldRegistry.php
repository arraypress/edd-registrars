<?php
/**
 * Admin Fields for Easy Digital Downloads
 *
 * @package     ArrayPress\EDD\Fields
 * @copyright   Copyright 2024, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Abstracts;

/**
 * Base Field Registry Class
 *
 * Provides core functionality for registering and rendering custom fields
 * in Easy Digital Downloads. This class handles field registration, meta
 * field setup, and provides rendering methods for various field types.
 */
abstract class BaseFieldRegistry {

	/**
	 * Registered fields
	 *
	 * @var array
	 */
	protected array $fields = [];

	/**
	 * Field types and their renderers
	 *
	 * @var array
	 */
	protected array $field_types = [
		'text'              => 'render_text_field',
		'textarea'          => 'render_textarea_field',
		'select'            => 'render_select_field',
		'checkbox'          => 'render_checkbox_field',
		'checkbox_toggle'   => 'render_checkbox_toggle_field',
		'number'            => 'render_number_field',
		'currency'          => 'render_currency_field',
		'email'             => 'render_email_field',
		'url'               => 'render_url_field',
		'color'             => 'render_color_field',
		'date'              => 'render_date_field',
		'radio'             => 'render_radio_field',
		'file'              => 'render_file_field',
		'editor'            => 'render_editor_field',
		'product_dropdown'  => 'render_product_dropdown',
		'customer_dropdown' => 'render_customer_dropdown',
		'user_dropdown'     => 'render_user_dropdown',
		'discount_dropdown' => 'render_discount_dropdown',
		'category_dropdown' => 'render_category_dropdown',
		'country_select'    => 'render_country_select',
		'region_select'     => 'render_region_select',
	];

	/**
	 * Register a field
	 *
	 * @param string $id     Field ID
	 * @param array  $config Field configuration
	 *
	 * @return bool
	 */
	public function register_field( string $id, array $config ): bool {
		$defaults = [
			'type'              => 'text',
			'label'             => '',
			'description'       => '',
			'default'           => '',
			'required'          => false,
			'class'             => '',
			'attributes'        => [],
			'options'           => [],
			'sanitize_callback' => null, // Will be determined based on type
			'validate_callback' => null,
			'show_in_rest'      => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a field config key, not a query argument.
			'meta_key'          => $id,
		];

		$this->fields[ $id ] = wp_parse_args( $config, $defaults );

		// Set sanitize callback based on field type if not explicitly provided
		if ( ! $this->fields[ $id ]['sanitize_callback'] ) {
			$this->fields[ $id ]['sanitize_callback'] = $this->get_sanitize_callback_for_type( $this->fields[ $id ]['type'] );
		}

		// Register WordPress meta field
		$this->register_meta_field( $id, $this->fields[ $id ] );

		return true;
	}

	/**
	 * Get appropriate sanitize callback based on field type
	 *
	 * @param string $field_type Field type
	 *
	 * @return string|array
	 */
	protected function get_sanitize_callback_for_type( string $field_type ) {
		$sanitize_map = [
			'email'           => 'sanitize_email',
			'url'             => 'sanitize_url',
			'textarea'        => 'sanitize_textarea_field',
			'number'          => 'absint',
			'currency'        => [ $this, 'sanitize_price' ],
			'checkbox'        => 'rest_sanitize_boolean',
			'checkbox_toggle' => 'rest_sanitize_boolean',
			'color'           => 'sanitize_hex_color',
		];

		return $sanitize_map[ $field_type ] ?? 'sanitize_text_field';
	}

	/**
	 * Sanitize price field using EDD's price sanitization
	 *
	 * Performs sanitization on the amount field including not allowing
	 * negative values by default, following EDD's native price handling.
	 *
	 * @param float $price The price to sanitize
	 *
	 * @return float A sanitized price
	 *
	 * @since 2.0.0
	 */
	public function sanitize_price( $price ) {
		$allow_negative_prices = apply_filters( 'edd_allow_negative_prices', false );

		if ( ! $allow_negative_prices && $price < 0 ) {
			$price = 0;
		}

		return edd_sanitize_amount( $price );
	}

	/**
	 * Register multiple fields at once
	 *
	 * @param array $fields Array of field configurations
	 *
	 * @return bool
	 */
	public function register_fields( array $fields ): bool {
		foreach ( $fields as $id => $config ) {
			$this->register_field( $id, $config );
		}

		return true;
	}

	/**
	 * Register WordPress meta field
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 *
	 * @return void
	 */
	protected function register_meta_field( string $field_id, array $field ): void {
		$args = [
			'object_subtype'    => 'download',
			'type'              => $this->get_meta_type( $field['type'] ),
			'description'       => $field['description'],
			'single'            => true,
			'sanitize_callback' => $field['sanitize_callback'],
			'show_in_rest'      => $field['show_in_rest'],
		];

		if ( $field['validate_callback'] ) {
			$args['auth_callback'] = $field['validate_callback'];
		}

		register_meta( 'post', $field['meta_key'], $args );
	}

	/**
	 * Get meta type based on field type
	 *
	 * @param string $field_type Field type
	 *
	 * @return string
	 */
	protected function get_meta_type( string $field_type ): string {
		$type_map = [
			'number'          => 'number',
			'currency'        => 'number',
			'checkbox'        => 'boolean',
			'checkbox_toggle' => 'boolean',
			'email'           => 'string',
			'url'             => 'string',
			'date'            => 'string',
			'color'           => 'string',
			'editor'          => 'string',
		];

		return $type_map[ $field_type ] ?? 'string';
	}

	/**
	 * Render a field
	 *
	 * @param string $field_id Field ID
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_field( string $field_id, $value, int $post_id ): string {
		if ( ! isset( $this->fields[ $field_id ] ) ) {
			return '';
		}

		$field           = $this->fields[ $field_id ];
		$renderer_method = $this->field_types[ $field['type'] ] ?? 'render_text_field';

		if ( ! method_exists( $this, $renderer_method ) ) {
			$renderer_method = 'render_text_field';
		}

		return $this->$renderer_method( $field_id, $field, $value, $post_id );
	}

	/**
	 * Render text field
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_text_field( string $field_id, array $field, $value, int $post_id ): string {
		$attributes = $this->build_attributes( $field_id, $field, [
			'type'  => 'text',
			'value' => $value,
		] );

		return sprintf( '<input %s />', $attributes );
	}

	/**
	 * Render number field
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_number_field( string $field_id, array $field, $value, int $post_id ): string {
		$attributes = $this->build_attributes( $field_id, $field, [
			'type'  => 'number',
			'value' => $value,
		] );

		return sprintf( '<input %s />', $attributes );
	}

	/**
	 * Render email field
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_email_field( string $field_id, array $field, $value, int $post_id ): string {
		$attributes = $this->build_attributes( $field_id, $field, [
			'type'  => 'email',
			'value' => $value,
		] );

		return sprintf( '<input %s />', $attributes );
	}

	/**
	 * Render URL field
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_url_field( string $field_id, array $field, $value, int $post_id ): string {
		$attributes = $this->build_attributes( $field_id, $field, [
			'type'  => 'url',
			'value' => $value,
		] );

		return sprintf( '<input %s />', $attributes );
	}

	/**
	 * Render textarea field
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_textarea_field( string $field_id, array $field, $value, int $post_id ): string {
		$attributes = $this->build_attributes( $field_id, $field, [], [ 'value' ] );

		return sprintf( '<textarea %s>%s</textarea>', $attributes, esc_textarea( $value ) );
	}

	/**
	 * Render select field
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_select_field( string $field_id, array $field, $value, int $post_id ): string {
		$attributes   = $this->build_attributes( $field_id, $field, [], [ 'value' ] );
		$options_html = '';

		if ( ! empty( $field['placeholder'] ) ) {
			$options_html .= sprintf(
				'<option value="">%s</option>',
				esc_html( $field['placeholder'] )
			);
		}

		foreach ( $field['options'] as $option_value => $option_label ) {
			$selected     = selected( $value, $option_value, false );
			$options_html .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option_value ),
				$selected,
				esc_html( $option_label )
			);
		}

		return sprintf( '<select %s>%s</select>', $attributes, $options_html );
	}

	/**
	 * Render checkbox toggle field using EDD's toggle component
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_checkbox_toggle_field( string $field_id, array $field, $value, int $post_id ): string {
		$checked = (bool) $value;

		// Get the actual name and id from attributes, fallback to field_id
		$name = $field['attributes']['name'] ?? $field_id;
		$id = $field['attributes']['id'] ?? $field_id;

		// Create EDD's toggle HTML structure
		$html = sprintf(
			'<div class="edd-toggle %s edd-form-group__input">
				<input type="checkbox" name="%s" id="%s" value="1"%s>
				<label for="%s">%s</label>
			</div>',
			esc_attr( $field_id ),
			esc_attr( $name ),
			esc_attr( $id ),
			checked( $checked, true, false ),
			esc_attr( $id ),
			esc_html( $field['label'] )
		);

		return $html;
	}

	/**
	 * Render checkbox field
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_checkbox_field( string $field_id, array $field, $value, int $post_id ): string {
		$checked = checked( $value, 1, false );

		// Override the default name/id if they're set in attributes
		$field_copy = $field;
		if ( isset( $field['attributes']['name'] ) ) {
			$field_copy['attributes']['name'] = $field['attributes']['name'];
		}
		if ( isset( $field['attributes']['id'] ) ) {
			$field_copy['attributes']['id'] = $field['attributes']['id'];
		}

		$attributes = $this->build_attributes( $field_id, $field_copy, [
			'type'  => 'checkbox',
			'value' => '1',
		] );

		return sprintf( '<input %s %s />', $attributes, $checked );
	}

	/**
	 * Render color field
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_color_field( string $field_id, array $field, $value, int $post_id ): string {
		$attributes = $this->build_attributes( $field_id, $field, [
			'type'  => 'color',
			'value' => $value,
		] );

		return sprintf( '<input %s />', $attributes );
	}

	/**
	 * Render currency field with EDD's currency wrapper
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_currency_field( string $field_id, array $field, $value, int $post_id ): string {
		$currency_position = edd_get_option( 'currency_position', 'before' );
		$currency_symbol   = edd_currency_symbol();

		$html = '<span class="edd-amount-type-wrapper">';

		// Currency symbol before
		if ( 'before' === $currency_position ) {
			$html .= sprintf( '<span class="edd-input__symbol edd-input__symbol--prefix">%s</span>', esc_html( $currency_symbol ) );
		}

		// Build field attributes for currency input
		$currency_field = $field;
		$currency_field['class'] = 'edd-amount-input edd-price-field no-controls symbol-' . $currency_position;
		$currency_field['attributes']['step'] = '0.01';

		// Render as number field with currency styling
		$attributes = $this->build_attributes( $field_id, $currency_field, [
			'type'  => 'text', // Use text to allow proper formatting
			'value' => $value,
		] );

		$html .= sprintf( '<input %s />', $attributes );

		// Currency symbol after
		if ( 'after' === $currency_position ) {
			$html .= sprintf( '<span class="edd-input__symbol edd-input__symbol--suffix">%s</span>', esc_html( $currency_symbol ) );
		}

		$html .= '</span>';

		return $html;
	}

	/**
	 * Render radio field
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_radio_field( string $field_id, array $field, $value, int $post_id ): string {
		$options_html = '';

		foreach ( $field['options'] as $option_value => $option_label ) {
			$checked = checked( $value, $option_value, false );
			$radio_id = $field_id . '_' . $option_value;

			$options_html .= sprintf(
				'<label><input type="radio" name="%s" id="%s" value="%s" %s class="%s" /> %s</label><br/>',
				esc_attr( $field_id ),
				esc_attr( $radio_id ),
				esc_attr( $option_value ),
				$checked,
				esc_attr( $field['class'] ),
				esc_html( $option_label )
			);
		}

		return $options_html;
	}

	/**
	 * Render product dropdown using EDD's built-in functionality
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_product_dropdown( string $field_id, array $field, $value, int $post_id ): string {
		$args = [
			'id'       => $field_id,
			'name'     => $field_id,
			'selected' => $value,
			'class'    => $field['class'],
		];

		return EDD()->html->product_dropdown( $args );
	}

	/**
	 * Render customer dropdown using EDD's built-in functionality
	 *
	 * @param string $field_id Field ID
	 * @param array  $field    Field configuration
	 * @param mixed  $value    Current value
	 * @param int    $post_id  Post ID
	 *
	 * @return string
	 */
	protected function render_customer_dropdown( string $field_id, array $field, $value, int $post_id ): string {
		$args = [
			'id'       => $field_id,
			'name'     => $field_id,
			'selected' => $value,
			'class'    => $field['class'],
		];

		return EDD()->html->customer_dropdown( $args );
	}

	/**
	 * Build HTML attributes string
	 *
	 * @param string $field_id      Field ID
	 * @param array  $field         Field configuration
	 * @param array  $extra_attrs   Extra attributes
	 * @param array  $exclude_attrs Attributes to exclude
	 *
	 * @return string
	 */
	protected function build_attributes( string $field_id, array $field, array $extra_attrs = [], array $exclude_attrs = [] ): string {
		$attributes = array_merge( [
			'id'    => $field_id,
			'name'  => $field_id,
			'class' => $field['class'],
		], $field['attributes'], $extra_attrs );

		if ( $field['required'] ) {
			$attributes['required'] = 'required';
		}

		// Remove excluded attributes
		foreach ( $exclude_attrs as $exclude ) {
			unset( $attributes[ $exclude ] );
		}

		// Build attribute string
		$attr_string = '';
		foreach ( $attributes as $name => $value ) {
			if ( $value === null || $value === false ) {
				continue;
			}

			if ( $value === true ) {
				$attr_string .= sprintf( ' %s', esc_attr( $name ) );
			} else {
				$attr_string .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
			}
		}

		return trim( $attr_string );
	}

	/**
	 * Get field value with fallback to default
	 *
	 * @param int    $post_id  Post ID
	 * @param string $field_id Field ID
	 *
	 * @return mixed
	 */
	protected function get_field_value( int $post_id, string $field_id ) {
		if ( ! isset( $this->fields[ $field_id ] ) ) {
			return '';
		}

		$field = $this->fields[ $field_id ];
		$value = get_post_meta( $post_id, $field['meta_key'], true );

		return $value !== '' ? $value : $field['default'];
	}

	/**
	 * Abstract method to be implemented by child classes
	 *
	 * @return void
	 */
	abstract public function init(): void;
}
