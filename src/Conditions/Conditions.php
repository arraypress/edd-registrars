<?php
/**
 * EDD Conditions
 *
 * Every Easy Digital Downloads condition for sugarcommerce/conditions, by
 * group, and the counts and session hooks they lean on.
 *
 * @package     ArrayPress\EDD\Register\Conditions
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Conditions;

/**
 * Class Conditions
 *
 * The EDD conditions wp-conditions carried and sugarcommerce/conditions left
 * to the shop: the same ids, labels and answers, so saved rules keep working.
 * Each group's `get_all()` is an `id => configuration` map to pass to
 * `Conditions::register()` (or `Conditions::add()`), whole or picked from.
 *
 * Call {@see self::register()} once, after `Conditions::use_prefix()`, from
 * any plugin that uses them.
 */
final class Conditions {

	/**
	 * Hook what the conditions need: EDD's order tables answering the
	 * velocity rules, and the cart clock behind "Seconds Since Cart Started".
	 *
	 * @return void
	 */
	public static function register(): void {
		Velocity::register();
		Data\CartClock::register();
	}

	/**
	 * Get all EDD conditions.
	 *
	 * @return array<string, array>
	 */
	public static function get_all(): array {
		return array_merge(
			Cart::get_all(),
			Checkout::get_all(),
			Customer::get_all(),
			Order::get_all(),
			Product::get_all(),
			Store::get_all(),
			Commission::get_all(),
			Recipient::get_all()
		);
	}

	/**
	 * Get conditions by category.
	 *
	 * @param string $category The category name.
	 *
	 * @return array<string, array>
	 */
	public static function get_by_category( string $category ): array {
		return match ( strtolower( $category ) ) {
			'cart'       => Cart::get_all(),
			'checkout'   => Checkout::get_all(),
			'customer'   => Customer::get_all(),
			'order'      => Order::get_all(),
			'product'    => Product::get_all(),
			'store'      => Store::get_all(),
			'commission' => Commission::get_all(),
			'recipient'  => Recipient::get_all(),
			default      => [],
		};
	}

	/**
	 * Get available categories.
	 *
	 * @return array<string, string>
	 */
	public static function get_categories(): array {
		return [
			'cart'       => __( 'Cart', 'arraypress' ),
			'checkout'   => __( 'Checkout', 'arraypress' ),
			'customer'   => __( 'Customer', 'arraypress' ),
			'order'      => __( 'Order', 'arraypress' ),
			'product'    => __( 'Product', 'arraypress' ),
			'store'      => __( 'Store', 'arraypress' ),
			'commission' => __( 'Commission', 'arraypress' ),
			'recipient'  => __( 'Recipient', 'arraypress' ),
		];
	}

	/**
	 * Check if EDD Commissions add-on is active.
	 *
	 * @return bool
	 */
	public static function has_commissions(): bool {
		return function_exists( 'eddc_get_commission' );
	}

	/**
	 * Get all conditions, excluding add-on specific ones if not active.
	 *
	 * @return array<string, array>
	 */
	public static function get_available(): array {
		$conditions = array_merge(
			Cart::get_all(),
			Checkout::get_all(),
			Customer::get_all(),
			Order::get_all(),
			Product::get_all(),
			Store::get_all()
		);

		// Only include commission/recipient conditions if add-on is active
		if ( self::has_commissions() ) {
			$conditions = array_merge(
				$conditions,
				Commission::get_all(),
				Recipient::get_all()
			);
		}

		return $conditions;
	}
}
