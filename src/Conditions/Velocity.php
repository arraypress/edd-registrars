<?php
/**
 * EDD Velocity Counts
 *
 * @package     ArrayPress\EDD\Register\Conditions
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.1.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Conditions;

use SugarCommerce\Conditions\Conditions as Engine;
use SugarCommerce\Conditions\Helpers\Velocity as Window;

/**
 * Class Velocity
 *
 * sugarcommerce/conditions counts nothing itself: each velocity rule asks
 * the `{prefix}_conditions_velocity_{kind}` filter. This answers it from
 * EDD's own order tables, the way wp-conditions used to by default.
 *
 * Hooked late (priority 20), and only when nobody earlier answered, so a
 * plugin's own count -- a fraud log, say -- still wins, as it did before.
 * Blocked attempts and card fingerprints are not EDD's to count: there is
 * no column for either, so those stay with the plugin that records them.
 *
 * The same-product counts read the products from the rule's arguments
 * (`product_ids` or `cart_product_ids`); {@see self::cart_product_ids()}
 * gives the live cart's for a caller building them at checkout.
 *
 * Uncached on purpose: a velocity check exists to notice what happened in
 * the last few minutes, and a cached count is a count of the past.
 */
final class Velocity {

	/**
	 * The kinds answered here: kind => [ what is counted, the column keyed on ].
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	public const KINDS = [
		'orders_by_ip'                 => [ 'orders', 'ip' ],
		'orders_by_email'              => [ 'orders', 'email' ],
		'distinct_emails_by_ip'        => [ 'distinct_email', 'ip' ],
		'distinct_ips_by_email'        => [ 'distinct_ip', 'email' ],
		'failed_orders_by_ip'          => [ 'failed', 'ip' ],
		'failed_orders_by_email'       => [ 'failed', 'email' ],
		'same_product_orders_by_ip'    => [ 'same_product', 'ip' ],
		'same_product_orders_by_email' => [ 'same_product', 'email' ],
	];

	/**
	 * The filter priority the counts answer at.
	 */
	public const PRIORITY = 20;

	/**
	 * Hook the counts onto this copy's velocity filters.
	 *
	 * Call after `Conditions::use_prefix()`: the filter names carry the
	 * prefix in force when this runs. Once per prefix.
	 *
	 * @return void
	 */
	public static function register(): void {
		static $done = [];

		$probe = Engine::hook( 'velocity_orders_by_ip' );

		if ( isset( $done[ $probe ] ) ) {
			return;
		}

		$done[ $probe ] = true;

		foreach ( self::KINDS as $kind => [ $what, $column ] ) {
			if ( 'same_product' === $what ) {
				add_filter(
					Engine::hook( 'velocity_' . $kind ),
					static fn( $count, $value, $products, $number, $unit ) => null !== $count
						? $count
						: self::count_same_product( $column, (string) $value, (array) $products, (int) $number, (string) $unit ),
					self::PRIORITY,
					5
				);

				continue;
			}

			add_filter(
				Engine::hook( 'velocity_' . $kind ),
				static fn( $count, $value, $number, $unit ) => null !== $count
					? $count
					: self::count( $what, $column, (string) $value, (int) $number, (string) $unit ),
				self::PRIORITY,
				4
			);
		}
	}

	/**
	 * The download IDs in the live EDD cart, for the same-product counts.
	 *
	 * wp-conditions read the cart itself when no `product_ids` were passed;
	 * sugarcommerce/conditions wants them in the arguments.
	 *
	 * @return int[]
	 */
	public static function cart_product_ids(): array {
		if ( ! function_exists( 'edd_get_cart_contents' ) ) {
			return [];
		}

		$contents = edd_get_cart_contents() ?: [];

		return array_values( array_filter( array_map( 'intval', array_unique( array_column( (array) $contents, 'id' ) ) ) ) );
	}

	/**
	 * Orders matching one identifier within the window.
	 *
	 * @param string $what   'orders', 'distinct_email', 'distinct_ip' or 'failed'.
	 * @param string $column 'ip' or 'email'.
	 * @param string $value  The identifier.
	 * @param int    $number Window amount.
	 * @param string $unit   Window unit.
	 *
	 * @return int|null Null when there is no EDD orders table to count.
	 */
	public static function count( string $what, string $column, string $value, int $number, string $unit ): ?int {
		global $wpdb;

		$column = 'email' === $column ? 'email' : 'ip';

		if ( '' === $value || ! self::has_edd_table( 'edd_orders' ) ) {
			return null;
		}

		[ $select, $where ] = match ( $what ) {
			'distinct_email' => [ 'COUNT(DISTINCT email)', $column . ' = %s' ],
			'distinct_ip'    => [ 'COUNT(DISTINCT ip)', $column . ' = %s' ],
			'failed'         => [ 'COUNT(*)', $column . " = %s AND status IN ('failed','revoked','abandoned')" ],
			default          => [ 'COUNT(*)', $column . ' = %s' ],
		};

		$table    = $wpdb->prefix . 'edd_orders';
		$interval = Window::to_interval( $number, $unit );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT {$select} FROM {$table} WHERE {$where} AND date_created >= ( UTC_TIMESTAMP() - {$interval} )", $value ) );
	}

	/**
	 * The most orders any one of the products is in, for one identifier
	 * within the window.
	 *
	 * The card-testing pattern: one cheap product bought again and again
	 * from the same IP or email. The highest count across the products, so a
	 * multi-item cart trips when any one of them has been hammered.
	 *
	 * @param string $column      'ip' or 'email'.
	 * @param string $value       The identifier.
	 * @param array  $product_ids The products.
	 * @param int    $number      Window amount.
	 * @param string $unit        Window unit.
	 *
	 * @return int|null Null when there are no EDD order tables to count.
	 */
	public static function count_same_product( string $column, string $value, array $product_ids, int $number, string $unit ): ?int {
		global $wpdb;

		$column      = 'email' === $column ? 'email' : 'ip';
		$product_ids = array_filter( array_map( 'intval', $product_ids ) );

		if ( '' === $value || ! self::has_edd_table( 'edd_orders' ) || ! self::has_edd_table( 'edd_order_items' ) ) {
			return null;
		}

		if ( empty( $product_ids ) ) {
			return 0;
		}

		$orders   = $wpdb->prefix . 'edd_orders';
		$items    = $wpdb->prefix . 'edd_order_items';
		$ids      = implode( ',', $product_ids );
		$interval = Window::to_interval( $number, $unit );

		$sql = "SELECT MAX(c) FROM (
			SELECT COUNT(DISTINCT o.id) AS c
			FROM {$orders} o
			INNER JOIN {$items} oi ON oi.order_id = o.id
			WHERE o.{$column} = %s
			  AND oi.product_id IN ({$ids})
			  AND o.date_created >= ( UTC_TIMESTAMP() - {$interval} )
			GROUP BY oi.product_id
		) sub";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $value ) );
	}

	/**
	 * Whether EDD is active and one of its tables exists, remembered per
	 * table for the request (keyed by the full name, so a switch_to_blog()
	 * is a different answer, and by the connection).
	 *
	 * @param string $table The table name without the prefix.
	 *
	 * @return bool
	 */
	private static function has_edd_table( string $table ): bool {
		global $wpdb;

		static $known = [];

		if ( ! isset( $wpdb ) || ! function_exists( 'EDD' ) ) {
			return false;
		}

		$table = $wpdb->prefix . $table;
		$key   = spl_object_id( $wpdb ) . ':' . $table;

		if ( ! array_key_exists( $key, $known ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$known[ $key ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		}

		return $known[ $key ];
	}
}
