<?php
/**
 * Base Columns Class
 *
 * @package     ArrayPress\EDD\Register
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Abstracts;

use ArrayPress\RegisterColumns\Abstracts\Columns as BaseColumns;
use Exception;

/**
 * Registering custom columns on Easy Digital Downloads' tables.
 *
 * This was a copy of wp-register-columns with the namespace changed: the same
 * fourteen methods, the same trait, the same three hundred lines, differing
 * only in doc wording. Two copies of a thing is one chance for a fix to land
 * in the wrong one, and they had already drifted — the sortable-columns bug
 * and the width escaping existed in both and were fixed in neither.
 *
 * What is actually EDD's, and all that is left here:
 *
 * - **No subtype.** A post column is registered for a post type; an order
 *   column is registered for orders, and that is the whole of it. The base
 *   class keys its store by type and subtype, so this passes the type as
 *   both — one bucket per table, the same as before.
 *
 * - **A different capability.** `manage_shop_settings` rather than
 *   `manage_options`: an EDD store manager may be nobody's administrator.
 *
 * Everything else — positions, removals, sorting, widths, the rendering and
 * the escaping — is the base class's, and a fix there is a fix here.
 */
abstract class Columns extends BaseColumns {

	/**
	 * Columns constructor.
	 *
	 * @param array $columns        Custom columns configuration.
	 * @param array $keys_to_remove Optional. Column keys to remove.
	 *
	 * @throws Exception If a column key is invalid or OBJECT_TYPE is not defined.
	 */
	public function __construct( array $columns, array $keys_to_remove = [] ) {
		parent::__construct( $columns, static::OBJECT_TYPE, $keys_to_remove );
	}

	/**
	 * Every column registered for an EDD table.
	 *
	 * The base class takes a subtype as well; there is not one here, so the
	 * type stands in for both. Kept as a one-argument call because that is
	 * what this library has always offered and what its tables use.
	 *
	 * @param string $object_type    The EDD object type.
	 * @param string $object_subtype Unused; the type is its own subtype here.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_columns( string $object_type, string $object_subtype = '' ): array {
		return parent::get_columns( $object_type, '' === $object_subtype ? $object_type : $object_subtype );
	}

	/**
	 * Whether the current user may see a column.
	 *
	 * `manage_shop_settings` rather than the base class's `manage_options`:
	 * somebody running a store is not necessarily an administrator of the
	 * site it is on, and a column they cannot see is a column that may as
	 * well not have been registered.
	 *
	 * @param array $column The column configuration.
	 *
	 * @return bool
	 */
	protected function check_column_permission( array $column ): bool {
		if ( isset( $column['permission_callback'] ) && is_callable( $column['permission_callback'] ) ) {
			return (bool) call_user_func( $column['permission_callback'] );
		}

		return current_user_can( 'manage_shop_settings' );
	}
}
