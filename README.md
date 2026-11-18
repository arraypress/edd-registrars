# EDD Registrars

Adds columns, fields, email tags, exporters and tools to Easy Digital Downloads
without writing the hook plumbing each one normally needs.

## What it does

Every extension point EDD offers — a column on the orders table, a field on a
download, a tag in an email, a CSV export, a recount tool — is reached through
a different hook, with a different callback signature and a different set of
things you have to remember. This registers all of them the same way: describe
what you want as an array, and the wiring is done.

It replaces eight separate packages that each did one of these.

## Features

* Add a column to the orders, downloads, customers, discounts, licences,
  commissions or subscriptions table, sortable if you want it to be
* Put fields on a download's settings, its price options, or a sidebar metabox
* Add a merge tag to EDD's emails, with a preview value so the email tester
  shows something real
* Ship a CSV exporter, batched, without writing a batch processor
* Add columns to the exports EDD already has
* Show a figure on the customer screen alongside EDD's own
* Add a view to the logs screen
* Add a tool to the recount screen, with the batch loop handled
* Give a sugarcommerce/conditions rule set EDD's conditions — cart, checkout,
  customer, order, product, store, commission and recipient — and answer its
  velocity rules from EDD's order tables

## Installation

```bash
composer require arraypress/edd-registrars
```

## Quick start

A column showing what each order was worth in the customer's own currency,
sortable, on the orders table:

```php
edd_register_order_columns( [
	'local_total' => [
		'label'            => __( 'Local total', 'my-plugin' ),
		'sortable'         => true,
		'position'         => 'after:amount',
		'display_callback' => function ( $order ) {
			return edd_currency_filter(
				edd_format_amount( $order->total ),
				$order->currency
			);
		},
	],
] );
```

## EDD conditions

sugarcommerce/conditions is the rules engine; it knows nothing about EDD.
The 195 EDD conditions wp-conditions carried live here, with the same ids,
labels and answers, so rules saved against them keep working. Require
`sugarcommerce/conditions` alongside this library to use them.

```php
use ArrayPress\EDD\Register\Conditions;
use SugarCommerce\Conditions\Conditions as Rules;

add_action( 'init', function () {
	Rules::use_prefix( 'myshop' );
	Conditions\Conditions::register();   // velocity counts + cart clock, for this prefix

	Rules::register( 'myshop_rule', [
		'conditions' => Conditions\Cart::get_all() + Conditions\Customer::get_all(),
	] );
} );
```

Each group (`Cart`, `Checkout`, `Customer`, `Order`, `Product`, `Store`,
`Commission`, `Recipient`) has `get_all()`, an `id => configuration` map to
pass whole or pick from; `Conditions\Conditions::get_all()` is every group.

`Conditions\Conditions::register()` hooks `Conditions\Velocity` onto the
`{prefix}_conditions_velocity_*` filters at priority 20, so EDD's orders
answer orders by IP or email, distinct emails/IPs, failed orders and
same-product orders — unless a filter at an earlier priority already
answered. Blocked attempts and card fingerprints are not EDD's to count.
The same-product counts read `product_ids` from the rule's arguments;
`Conditions\Velocity::cart_product_ids()` gives the live cart's.

| wp-conditions | Here |
|---|---|
| `ArrayPress\Conditions\Conditions\EDD\{Cart, …, Recipient}` | `ArrayPress\EDD\Register\Conditions\{Cart, …, Recipient}` |
| `ArrayPress\Conditions\Conditions\EDD\Conditions` | `ArrayPress\EDD\Register\Conditions\Conditions` (+ `register()`) |
| `ArrayPress\Conditions\Integrations\EDD\*`, `Helpers\{Address, CartClock}` | `ArrayPress\EDD\Register\Conditions\Data\*` (CartClock is EDD-only now) |
| the EDD order counts in `Helpers\Velocity` | `Conditions\Velocity`, on the prefixed filters |
| the live cart read for same-product counts | pass `product_ids` (`Velocity::cart_product_ids()`) |

Fixed on the way: the cart's product-age conditions (average, newest, oldest,
median) compared a UTC publish date with the site's local time, so every
age was off by the site's UTC offset.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later
* Easy Digital Downloads 3.0 or later
* sugarcommerce/columns (the table columns' base class; it replaced
  arraypress/wp-register-columns, and wp-array-utils is no longer needed)
* sugarcommerce/conditions, for the EDD conditions only

## License

GPL-2.0-or-later
