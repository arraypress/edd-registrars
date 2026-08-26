<?php
/**
 * Customer Stats Registry for Easy Digital Downloads
 *
 * @package     ArrayPress\EDD\Register
 * @copyright   Copyright 2025, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register;

use EDD_Customer;
use Error;
use Exception;
use WP_Error;

class CustomerStats {

	/**
	 * Registered stats items.
	 *
	 * @var array
	 */
	private array $items = [];

	/**
	 * Register a new stats item.
	 *
	 * @param string $key  Unique identifier for the stats item.
	 * @param array  $args Configuration arguments for the stats item.
	 *
	 * @return WP_Error|true
	 */
	public function register( string $key, array $args ) {
		if ( empty( $key ) ) {
			return new WP_Error(
				'invalid_key',
				__( 'Stats key cannot be empty.', 'arraypress' )
			);
		}

		if ( ! isset( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return new WP_Error(
				'invalid_callback',
				__( 'Stats callback must be callable.', 'arraypress' )
			);
		}

		if ( empty( $args['singular'] ) ) {
			return new WP_Error(
				'missing_singular',
				/* translators: %s: the stats item key. */
				sprintf( __( 'Stats item "%s" must have a singular label.', 'arraypress' ), $key )
			);
		}

		if ( empty( $args['plural'] ) ) {
			return new WP_Error(
				'missing_plural',
				/* translators: %s: the stats item key. */
				sprintf( __( 'Stats item "%s" must have a plural label.', 'arraypress' ), $key )
			);
		}

		$defaults = [
			'label'      => '',
			'callback'   => '',
			'icon'       => 'dashicons-info',
			'url'        => '',
			'singular'   => '',
			'plural'     => '',
			'priority'   => 10,
			'span_class' => '',
		];

		$args = wp_parse_args( $args, $defaults );

		// Use label as fallback for singular if not provided
		if ( empty( $args['label'] ) ) {
			$args['label'] = $args['singular'];
		}

		$this->items[ $key ] = $args;

		edd_debug_log( sprintf( '[EDD Customer Stats] Registered customer stat: %s (singular: %s, plural: %s)', $key, $args['singular'], $args['plural'] ) );

		return true;
	}

	/**
	 * Register multiple stats items.
	 *
	 * @param array $stats Array of stats configurations.
	 *
	 * @return WP_Error|true
	 */
	public function register_multiple( array $stats ) {
		if ( empty( $stats ) ) {
			return new WP_Error(
				'empty_stats',
				__( 'Stats array cannot be empty.', 'arraypress' )
			);
		}

		foreach ( $stats as $key => $config ) {
			$result = $this->register( $key, $config );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Setup hooks to integrate with EDD.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'edd_customer_stats_list', [ $this, 'render' ] );
	}

	/**
	 * Render the stats list for a given customer.
	 *
	 * @param EDD_Customer $customer The customer object.
	 *
	 * @return void
	 */
	public function render( EDD_Customer $customer ): void {
		if ( empty( $this->items ) ) {
			return;
		}

		uasort( $this->items, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );

		foreach ( $this->items as $key => $item ) {
			$this->render_item( $key, $item, $customer );
		}
	}

	/**
	 * Render a single stats item.
	 *
	 * @param string       $key      The item key.
	 * @param array        $item     The item data.
	 * @param EDD_Customer $customer The customer object.
	 *
	 * @return void
	 */
	private function render_item( string $key, array $item, EDD_Customer $customer ): void {
		if ( ! is_callable( $item['callback'] ) ) {
			edd_debug_log( sprintf( '[EDD Customer Stats] Callback for stat "%s" is not callable', $key ), true );

			return;
		}

		try {
			$value       = call_user_func( $item['callback'], $customer );
			/*
			 * Compare numerically, not identically. A callback that counts rows
			 * with $wpdb->get_var() hands back the string "1", and === against
			 * the integer labels that customer "1 Purchases".
			 */
			$is_one      = is_numeric( $value ) && 1.0 === (float) $value;
			$label       = $is_one ? $item['singular'] : $item['plural'];
			$value_class = 'edd_' . strtolower( sanitize_html_class( $item['singular'] ) );

			if ( ! empty( $item['span_class'] ) ) {
				$value_class .= ' ' . sanitize_html_class( $item['span_class'] );
			}

			?>
            <li>
				<?php if ( ! empty( $item['url'] ) ) : ?>
                <a href="<?php echo esc_url( $item['url'] . $customer->id ); ?>">
					<?php endif; ?>
                    <span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
                    <span class="<?php echo esc_attr( $value_class ); ?>"><?php echo esc_html( $value ); ?></span>
					<?php echo esc_html( $label ); ?>
					<?php if ( ! empty( $item['url'] ) ) : ?>
                </a>
			<?php endif; ?>
            </li>
			<?php
		} catch ( Exception $e ) {
			edd_debug_log_exception( $e );
		} catch ( Error $e ) {
			edd_debug_log( sprintf( '[EDD Customer Stats] Fatal error in callback for stat "%s": %s', $key, $e->getMessage() ), true );
		}
	}
}