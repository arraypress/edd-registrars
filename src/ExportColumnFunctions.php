<?php
/**
 * Helper functions for EDD Export Columns registration
 *
 * @package     ArrayPress\EDD\Register
 * @copyright   Copyright 2024, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 */

declare( strict_types=1 );

use ArrayPress\EDD\Register\ExportColumns;

if ( ! function_exists( 'edd_register_export_columns' ) ) :
	/**
	 * Register custom export columns for EDD CSV exports.
	 *
	 * @param string $type     The type of CSV export (e.g., 'downloads', 'customers').
	 * @param array  $columns  An associative array of custom columns.
	 * @param string $id_field The field to use as the identifier.
	 *
	 * @return ExportColumns|WP_Error
	 */
	function edd_register_export_columns( string $type, array $columns, string $id_field = 'ID' ) {
		$manager = new ExportColumns( $type, [], $id_field );
		$result  = $manager->register( $columns );

		if ( is_wp_error( $result ) ) {
			edd_debug_log( sprintf( '[EDD Export Columns] Registration error: %s', $result->get_error_message() ) );

			return $result;
		}

		$manager->init();

		return $manager;
	}
endif;
