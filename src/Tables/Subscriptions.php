<?php
/**
 * Subscriptions Class
 *
 * Handles custom column registration for Easy Digital Downloads Recurring.
 * Integrates with EDD Recurring table filters and provides subscription-specific functionality.
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
 * Class Subscriptions
 *
 * Manages custom columns for EDD subscriptions in the WordPress admin.
 *
 * @package ArrayPress\EDD\Register
 */
class Subscriptions extends Columns {

	/**
	 * Object type for subscriptions.
	 *
	 * @var string
	 */
	protected const OBJECT_TYPE = 'edd_subscription';

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers EDD Recurring hooks for adding and displaying custom subscription columns.
	 * Note: Sortable columns are not supported by EDD Recurring.
	 *
	 * @return void
	 */
	protected function load_hooks(): void {
		add_filter( 'edd_report_subscription_columns', [ $this, 'register_columns' ] );
		$this->register_dynamic_column_filters();
	}

	/**
	 * Register dynamic filters for each custom column.
	 *
	 * EDD Recurring uses individual filters per column (edd_subscription_column_{column_name})
	 * similar to how customers work.
	 *
	 * @return void
	 */
	protected function register_dynamic_column_filters(): void {
		$custom_columns = self::get_columns( $this->object_type );

		foreach ( $custom_columns as $column_name => $column_config ) {
			add_filter( "edd_subscription_column_{$column_name}", function ( $value, $item ) use ( $column_name ) {
				return $this->render_subscription_column( $column_name, $item );
			}, 10, 2 );
		}
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the subscriptions screen, false otherwise.
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
		return EDD()->subscriptions->get_meta( $object_id->id, $meta_key, true );
	}

	protected function is_screen(): bool {
		$screen = get_current_screen();

		return $screen && $screen->id === 'download_page_edd-subscriptions';
	}

	/**
	 * Render the subscription column content (implementation for the filter hook).
	 *
	 * @param string $column_name The name/key of the current column.
	 * @param object $item        The subscription object.
	 *
	 * @return string The rendered column content.
	 */
	protected function render_subscription_column( string $column_name, $item ): string {
		$column = $this->get_column_by_name( $column_name, $this->object_type );

		if ( ! $column ) {
			return '';
		}

		// $item is already the subscription object from EDD Recurring
		return $this->render_custom_column_content( $item, $column );
	}

	/**
	 * Render the custom column content (required by parent abstract class).
	 *
	 * @param string $value       The current value of the column.
	 * @param string $column_name The name/key of the current column.
	 * @param mixed  $object      The subscription object or data.
	 *
	 * @return string The rendered column content.
	 */
	public function render_column_content( string $value, string $column_name, $object ): string {
		return '';
	}
}
