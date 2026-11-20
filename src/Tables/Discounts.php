<?php
/**
 * Discounts Class
 *
 * Handles custom column registration for Easy Digital Downloads discounts.
 * Integrates with EDD's discount (adjustment) table filters and query system.
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
 * Class Discounts
 *
 * Manages custom columns for EDD discounts in the WordPress admin.
 *
 * @package ArrayPress\EDD\Register
 */
class Discounts extends Columns {

	/**
	 * Object type for discounts.
	 *
	 * @var string
	 */
	protected const string OBJECT_TYPE = 'edd_adjustment';

	/**
	 * Load the necessary hooks for custom columns.
	 *
	 * Registers EDD hooks for adding, sorting, and displaying custom discount columns.
	 *
	 * @return void
	 */
	protected function load_hooks(): void {
		add_filter( 'edd_discounts_table_columns', [ $this, 'register_columns' ] );
		add_filter( 'edd_discounts_table_sortable_columns', [ $this, 'register_sortable_columns' ] );
		add_filter( 'edd_discounts_table_column', [ $this, 'render_discount_column_content' ], 10, 3 );
		add_filter( 'edd_adjustments_query_clauses', [ $this, 'query_clauses' ], 10, 2 );
	}

	/**
	 * Check if we are on the correct screen for custom columns.
	 *
	 * @return bool True if on the discounts screen, false otherwise.
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
		return edd_get_adjustment_meta( $object_id->id, $meta_key, true );
	}

	protected function is_screen(): bool {
		$screen = get_current_screen();

		return $screen && $screen->id === 'download_page_edd-discounts';
	}

	/**
	 * Render the custom column content wrapper for discounts.
	 *
	 * @param string $value       The current value of the column.
	 * @param object $discount    The discount object.
	 * @param string $column_name The name of the column.
	 *
	 * @return string The rendered column content.
	 */
	public function render_discount_column_content( string $value, $discount, string $column_name ): string {
		return $this->render_column_content( $value, $column_name, $discount->id );
	}

	/**
	 * Render the custom column content.
	 *
	 * @param string $value       The current value of the column.
	 * @param string $column_name The name/key of the current column.
	 * @param mixed  $object      The discount ID.
	 *
	 * @return string The rendered column content.
	 */
	public function render_column_content( string $value, string $column_name, $object ): string {
		$discount_id = is_numeric( $object ) ? (int) $object : $object->id;
		$column      = $this->get_column_by_name( $column_name, $this->object_type );

		if ( ! $column ) {
			return $value;
		}

		$discount = edd_get_discount( $discount_id );

		if ( ! $discount ) {
			return $value;
		}

		return $this->render_custom_column_content( $discount, $column );
	}

	/**
	 * Modify the SQL clauses for retrieving discounts, allowing for sorting by custom meta keys.
	 *
	 * @param array               $clauses Existing SQL clauses for the discounts query.
	 * @param \EDD\Database\Query $base    Instance passed by reference.
	 *
	 * @return array Modified SQL clauses with additional joins and orderby clauses.
	 */
	public function query_clauses( array $clauses, \EDD\Database\Query $base ): array {
		global $wpdb;

		// Bail if not admin
		if ( ! is_admin() ) {
			return $clauses;
		}

		// Get the column name for ordering (using trait method)
		$orderby = $this->get_orderby();
		$order   = $this->get_order();

		// Get the column details using the column name
		$column = $this->get_column_by_name( $orderby, $this->object_type );

		// Bail if column is not sortable or meta key is not set
		if ( ! $column || empty( $column['meta_key'] ) || ! $column['sortable'] ) {
			return $clauses;
		}

		// Get the meta key for ordering
		$meta_key = $column['meta_key'];

		// Join discount meta data
		$clauses['join'] .= $wpdb->prepare(
			" INNER JOIN {$wpdb->prefix}edd_adjustmentmeta AS edd_am ON edd_a.id = edd_am.edd_adjustment_id AND edd_am.meta_key = %s",
			$meta_key
		);

		// Determine the order by clause based on column type
		if ( $column['numeric'] ) {
			$clauses['orderby'] = sprintf( 'CAST(edd_am.meta_value AS SIGNED) %s', $order );
		} else {
			$clauses['orderby'] = sprintf( 'edd_am.meta_value %s', $order );
		}

		// Return modified clauses
		return $clauses;
	}
}
