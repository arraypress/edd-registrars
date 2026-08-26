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

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later
* Easy Digital Downloads 3.0 or later

## License

GPL-2.0-or-later
