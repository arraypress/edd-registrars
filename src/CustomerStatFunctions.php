<?php
/**
 * Helper functions for EDD Customer Stats registration
 *
 * @package     ArrayPress\EDD\Register
 * @copyright   Copyright 2025, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 */

declare( strict_types=1 );

use ArrayPress\EDD\Register\CustomerStats;

if ( ! function_exists( 'edd_register_customer_stats' ) ) :
	/**
	 * Register custom customer stats for EDD.
	 *
	 * @param array $stats An associative array of custom customer stats.
	 *
	 * @return CustomerStats|WP_Error
	 */
	function edd_register_customer_stats( array $stats ) {
		$manager = new CustomerStats();
		$result  = $manager->register_multiple( $stats );

		if ( is_wp_error( $result ) ) {
			edd_debug_log( sprintf( '[EDD Customer Stats] Registration error: %s', $result->get_error_message() ) );

			return $result;
		}

		$manager->init();

		return $manager;
	}
endif;

if ( ! function_exists( 'edd_register_customer_stat' ) ) :
	/**
	 * Register a single customer stat for EDD.
	 *
	 * @param string $key  Unique identifier for the stat.
	 * @param array  $args Configuration arguments.
	 *
	 * @return CustomerStats|WP_Error
	 */
	function edd_register_customer_stat( string $key, array $args ) {
		return edd_register_customer_stats( [ $key => $args ] );
	}
endif;
