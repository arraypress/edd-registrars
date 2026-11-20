<?php
/**
 * Customers Class
 *
 * Handles custom column registration for Easy Digital Downloads customers.
 * Integrates with EDD's customer table filters and provides customer-specific functionality.
 *
 * @package     ArrayPress\EDD\Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tables;

use ArrayPress\EDD\Register\Abstracts\Columns;

/**
 * Class Customers
 *
 * Manages custom columns for EDD customers in the WordPress admin.
 *
 * @package ArrayPress\EDD\Register
 */
class Customers extends Columns {

	/**
	 * Object type for customers.
	 *
	 * @var string
	 */
	protected const string OBJECT_TYPE = 'edd_customer';

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers EDD hooks for adding, sorting, and displaying custom customer columns.
	 *
	 * @return void
	 */
	protected function load_hooks(): void {
		add_filter( 'edd_report_customer_columns', [ $this, 'register_columns' ] );
		$this->register_dynamic_column_filters();
	}

	/**
	 * Register dynamic filters for each custom column.
	 *
	 * EDD uses individual filters per column (edd_customers_column_{column_name})
	 * rather than a unified rendering hook, so we need to register a filter for each.
	 *
	 * @return void
	 */
	protected function register_dynamic_column_filters(): void {
		$custom_columns = self::get_columns( $this->object_type );

		foreach ( $custom_columns as $column_name => $column_config ) {
			add_filter( "edd_customers_column_{$column_name}", function ( $value, $customer_id ) use ( $column_name ) {
				return $this->render_column_content( (string) $value, (string) $column_name, (int) $customer_id );
			}, 10, 2 );
		}
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the customers screen, false otherwise.
	 */
	/**
	 * One meta value for this object type.
	 *
	 * The only thing that differed between the copies of the renderer this
	 * replaced, which now lives in the base class.
	 *
	 * @param int|string|object $object_id The object, or its id.
	 * @param string            $meta_key  The key.
	 *
	 * @return mixed
	 */
	protected function get_meta( $object_id, string $meta_key ) {
		return edd_get_customer_meta( $object_id->id, $meta_key, true );
	}

	protected function is_screen(): bool {
		$screen = get_current_screen();

		return $screen && $screen->id === 'download_page_edd-customers';
	}

	/**
	 * Render the custom column content.
	 *
	 * @param string $value       The current value of the column.
	 * @param string $column_name The name/key of the current column.
	 * @param mixed  $object      The customer ID.
	 *
	 * @return string The rendered column content.
	 */
	public function render_column_content( string $value, string $column_name, $object ): string {
		$customer_id = is_numeric( $object ) ? (int) $object : $object->id;
		$column      = $this->get_column_by_name( $column_name, $this->object_type );

		if ( ! $column ) {
			return $value;
		}

		$customer = edd_get_customer( $customer_id );

		if ( ! $customer ) {
			return $value;
		}

		return $this->render_custom_column_content( $customer, $column );
	}
}
