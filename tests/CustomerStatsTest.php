<?php
/**
 * CustomerStats tests.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests;

use ArrayPress\EDD\Register\CustomerStats;
use EDD_Customer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_Error;

/**
 * Covers CustomerStats.
 */
final class CustomerStatsTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		edd_registrars_reset();
	}

	/**
	 * A registration that should succeed.
	 *
	 * @param array $overrides Fields to change.
	 *
	 * @return array
	 */
	private function stat( array $overrides = [] ): array {
		return $overrides + [
			'callback' => static fn( $customer ) => 5,
			'singular' => 'Purchase',
			'plural'   => 'Purchases',
		];
	}

	/**
	 * Render one customer's stats and return the markup.
	 *
	 * @param CustomerStats $stats    The registry.
	 * @param int           $customer Customer id.
	 *
	 * @return string
	 */
	private function render( CustomerStats $stats, int $customer = 7 ): string {
		ob_start();
		$stats->render( new EDD_Customer( $customer ) );

		return (string) ob_get_clean();
	}

	/**
	 * A valid registration succeeds.
	 *
	 * @return void
	 */
	public function test_register_returns_true(): void {
		$this->assertTrue( ( new CustomerStats() )->register( 'purchases', $this->stat() ) );
	}

	/**
	 * Each required field is checked, and named in the error code.
	 *
	 * @return void
	 */
	public function test_register_validates_required_fields(): void {
		$stats = new CustomerStats();

		$cases = [
			'invalid_key'      => [ 'key' => '', 'args' => $this->stat() ],
			'invalid_callback' => [ 'key' => 'a', 'args' => [ 'singular' => 'S', 'plural' => 'P' ] ],
			'missing_singular' => [ 'key' => 'b', 'args' => [ 'callback' => 'strlen', 'plural' => 'P' ] ],
			'missing_plural'   => [ 'key' => 'c', 'args' => [ 'callback' => 'strlen', 'singular' => 'S' ] ],
		];

		foreach ( $cases as $expected => $case ) {
			$result = $stats->register( $case['key'], $case['args'] );

			$this->assertInstanceOf( WP_Error::class, $result, $expected );
			$this->assertSame( $expected, $result->get_error_code() );
		}
	}

	/**
	 * A non-callable callback is rejected at registration, not at render.
	 *
	 * @return void
	 */
	public function test_uncallable_callback_is_rejected(): void {
		$result = ( new CustomerStats() )->register( 'x', $this->stat( [ 'callback' => 'no_such_function_xyz' ] ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_callback', $result->get_error_code() );
	}

	/**
	 * register_multiple() rejects an empty array and stops at the first error.
	 *
	 * @return void
	 */
	public function test_register_multiple(): void {
		$stats = new CustomerStats();

		$this->assertSame( 'empty_stats', $stats->register_multiple( [] )->get_error_code() );

		$result = $stats->register_multiple( [
			'good' => $this->stat(),
			'bad'  => [ 'callback' => 'strlen', 'singular' => 'S' ],
		] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_plural', $result->get_error_code() );

		// The one that passed is still registered.
		$this->assertStringContainsString( 'Purchases', $this->render( $stats ) );
	}

	/**
	 * register_multiple() returns true when every item is valid.
	 *
	 * @return void
	 */
	public function test_register_multiple_success(): void {
		$stats = new CustomerStats();

		$this->assertTrue( $stats->register_multiple( [
			'a' => $this->stat(),
			'b' => $this->stat( [ 'singular' => 'Refund', 'plural' => 'Refunds' ] ),
		] ) );
	}

	/**
	 * init() hooks render onto EDD's customer stats list.
	 *
	 * @return void
	 */
	public function test_init_hooks_render(): void {
		$stats = new CustomerStats();
		$stats->init();

		$hooked = edd_registrars_hooked( 'edd_customer_stats_list' );

		$this->assertCount( 1, $hooked );
		$this->assertSame( [ $stats, 'render' ], $hooked[0]['callback'] );
	}

	/**
	 * Nothing registered renders nothing at all.
	 *
	 * @return void
	 */
	public function test_render_with_nothing_registered(): void {
		$this->assertSame( '', $this->render( new CustomerStats() ) );
	}

	/**
	 * Items render in priority order, lowest first.
	 *
	 * @return void
	 */
	public function test_render_respects_priority(): void {
		$stats = new CustomerStats();
		$stats->register( 'last', $this->stat( [ 'singular' => 'Zebra', 'plural' => 'Zebras', 'priority' => 30 ] ) );
		$stats->register( 'first', $this->stat( [ 'singular' => 'Apple', 'plural' => 'Apples', 'priority' => 5 ] ) );
		$stats->register( 'mid', $this->stat( [ 'singular' => 'Mango', 'plural' => 'Mangoes', 'priority' => 10 ] ) );

		$html = $this->render( $stats );

		$this->assertLessThan( strpos( $html, 'Mangoes' ), strpos( $html, 'Apples' ) );
		$this->assertLessThan( strpos( $html, 'Zebras' ), strpos( $html, 'Mangoes' ) );
	}

	/**
	 * The singular label is used for exactly one, the plural otherwise.
	 *
	 * @return void
	 */
	public function test_singular_and_plural_labels(): void {
		foreach ( [ 0 => 'Purchases', 1 => 'Purchase', 2 => 'Purchases' ] as $count => $expected ) {
			edd_registrars_reset();
			$stats = new CustomerStats();
			$stats->register( 'p', $this->stat( [ 'callback' => static fn() => $count ] ) );

			$this->assertMatchesRegularExpression(
				'/\b' . preg_quote( $expected, '/' ) . '\b/',
				$this->render( $stats ),
				"count {$count}"
			);
		}
	}

	/**
	 * A count that arrives as a numeric string still reads as one.
	 *
	 * $wpdb->get_var() returns a string, so a callback that counts rows with a
	 * direct query hands back "1" rather than 1. Comparing strictly against the
	 * integer labels that customer "1 Purchases".
	 *
	 * @return void
	 */
	public function test_numeric_string_count_uses_the_singular(): void {
		$stats = new CustomerStats();
		$stats->register( 'p', $this->stat( [ 'callback' => static fn() => '1' ] ) );

		$html = $this->render( $stats );

		$this->assertMatchesRegularExpression( '/\bPurchase\b/', $html );
		$this->assertStringNotContainsString( 'Purchases', $html );
	}

	/**
	 * The value, icon and class reach the markup.
	 *
	 * @return void
	 */
	public function test_render_markup(): void {
		$stats = new CustomerStats();
		$stats->register( 'p', $this->stat( [ 'icon' => 'dashicons-cart', 'span_class' => 'my-span' ] ) );

		$html = $this->render( $stats );

		$this->assertStringContainsString( '<li>', $html );
		$this->assertStringContainsString( 'dashicons dashicons-cart', $html );
		$this->assertStringContainsString( 'edd_purchase', $html );
		$this->assertStringContainsString( 'my-span', $html );
		$this->assertStringContainsString( '>5<', $html );
	}

	/**
	 * A url wraps the item and carries the customer id.
	 *
	 * @return void
	 */
	public function test_url_wraps_the_item(): void {
		$stats = new CustomerStats();
		$stats->register( 'p', $this->stat( [ 'url' => 'https://example.test/orders?customer=' ] ) );

		$html = $this->render( $stats, 42 );

		$this->assertStringContainsString( 'href="https://example.test/orders?customer=42"', $html );
		$this->assertStringContainsString( '</a>', $html );
	}

	/**
	 * Without a url there is no anchor at all.
	 *
	 * @return void
	 */
	public function test_no_url_renders_no_anchor(): void {
		$stats = new CustomerStats();
		$stats->register( 'p', $this->stat() );

		$html = $this->render( $stats );

		$this->assertStringNotContainsString( '<a href', $html );
		$this->assertStringNotContainsString( '</a>', $html );
	}

	/**
	 * A callback returning markup is escaped, not rendered.
	 *
	 * @return void
	 */
	public function test_callback_output_is_escaped(): void {
		$stats = new CustomerStats();
		$stats->register( 'p', $this->stat( [ 'callback' => static fn() => '<script>alert(1)</script>' ] ) );

		$html = $this->render( $stats );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * A throwing callback is logged and skipped, not fatal.
	 *
	 * This renders on the customer screen, so an exception from one stat must
	 * not take the page down with it.
	 *
	 * @return void
	 */
	public function test_throwing_callback_does_not_break_the_page(): void {
		$stats = new CustomerStats();
		$stats->register( 'boom', $this->stat( [
			'callback' => static function () {
				throw new RuntimeException( 'callback exploded' );
			},
		] ) );
		$stats->register( 'fine', $this->stat( [ 'singular' => 'Refund', 'plural' => 'Refunds' ] ) );

		$html = $this->render( $stats );

		$this->assertStringContainsString( 'Refunds', $html );
		$this->assertNotEmpty( array_filter(
			$GLOBALS['edd_registrars']['log'],
			static fn( $line ) => str_contains( $line, 'callback exploded' )
		) );
	}

	/**
	 * A callback that raises an Error is caught too.
	 *
	 * A TypeError from a callback is not an Exception, so catching only
	 * Exception would still white-screen the customer page.
	 *
	 * @return void
	 */
	public function test_error_in_callback_is_caught(): void {
		$stats = new CustomerStats();
		$stats->register( 'boom', $this->stat( [
			'callback' => static function () {
				throw new \TypeError( 'wrong type' );
			},
		] ) );
		$stats->register( 'fine', $this->stat( [ 'singular' => 'Refund', 'plural' => 'Refunds' ] ) );

		$html = $this->render( $stats );

		$this->assertStringContainsString( 'Refunds', $html );
	}

	/**
	 * label falls back to singular when it is not supplied.
	 *
	 * @return void
	 */
	public function test_label_defaults_to_singular(): void {
		$stats = new CustomerStats();
		$stats->register( 'p', $this->stat() );

		$this->assertNotEmpty( array_filter(
			$GLOBALS['edd_registrars']['log'],
			static fn( $line ) => str_contains( $line, 'singular: Purchase' )
		) );
	}

	/**
	 * The customer is handed to the callback.
	 *
	 * @return void
	 */
	public function test_callback_receives_the_customer(): void {
		$seen  = null;
		$stats = new CustomerStats();
		$stats->register( 'p', $this->stat( [
			'callback' => static function ( $customer ) use ( &$seen ) {
				$seen = $customer;

				return 1;
			},
		] ) );

		$this->render( $stats, 99 );

		$this->assertInstanceOf( EDD_Customer::class, $seen );
		$this->assertSame( 99, $seen->id );
	}

}
