<?php
/**
 * The EDD conditions, moved here from wp-conditions.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests;

use ArrayPress\EDD\Register\Conditions\Conditions;
use ArrayPress\EDD\Register\Conditions\Data\CartClock;
use ArrayPress\EDD\Register\Conditions\Data\Checkout;
use ArrayPress\EDD\Register\Conditions\Velocity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SugarCommerce\Conditions\Conditions as Engine;

/**
 * Covers the EDD condition groups, the checkout reader, the velocity counts
 * and the cart clock.
 */
final class ConditionsTest extends TestCase {

	/**
	 * The groups, by class name.
	 *
	 * @var string[]
	 */
	private const GROUPS = [ 'Cart', 'Checkout', 'Customer', 'Order', 'Product', 'Store', 'Commission', 'Recipient' ];

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		edd_registrars_reset();
		unset( $GLOBALS['wpdb'] );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		Engine::use_prefix( '' );
		parent::tearDown();
	}

	/**
	 * One group's conditions.
	 *
	 * @param string $group A class name from GROUPS.
	 *
	 * @return array<string, array>
	 */
	private static function group( string $group ): array {
		return ( 'ArrayPress\\EDD\\Register\\Conditions\\' . $group )::get_all();
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function group_names(): array {
		return array_combine( self::GROUPS, array_map( static fn( $g ) => [ $g ], self::GROUPS ) );
	}

	/**
	 * Every id wp-conditions had is still here, in the same group: rules are
	 * saved against these ids.
	 */
	#[DataProvider( 'group_names' )]
	public function test_every_id_from_wp_conditions_is_kept( string $group ): void {
		$old = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/wp-conditions-edd-ids.json' ), true );

		$this->assertSame( $old[ $group ], array_keys( self::group( $group ) ) );
	}

	/**
	 * Each condition is a configuration sugarcommerce/conditions can register.
	 */
	#[DataProvider( 'group_names' )]
	public function test_every_condition_is_well_formed( string $group ): void {
		$types = [ 'text', 'number', 'number_unit', 'text_unit', 'boolean', 'date', 'time', 'select', 'tags', 'ip', 'email', 'post', 'term', 'user', 'ajax' ];

		foreach ( self::group( $group ) as $id => $condition ) {
			$this->assertNotSame( '', (string) ( $condition['label'] ?? '' ), $id );
			$this->assertNotSame( '', (string) ( $condition['group'] ?? '' ), $id );
			$this->assertContains( $condition['type'] ?? '', $types, $id );
			$this->assertIsCallable( $condition['compare_value'] ?? null, $id );

			if ( 'ajax' === $condition['type'] ) {
				$this->assertIsCallable( $condition['ajax'] ?? null, $id );
			}
		}
	}

	/**
	 * The aggregate is every group, with no id in two of them.
	 */
	public function test_get_all_is_every_group_once(): void {
		$count = array_sum( array_map( static fn( $g ) => count( self::group( $g ) ), self::GROUPS ) );

		$this->assertCount( $count, Conditions::get_all() );
		$this->assertSame( array_keys( self::group( 'Store' ) ), array_keys( Conditions::get_by_category( 'store' ) ) );
		$this->assertSame( [], Conditions::get_by_category( 'nope' ) );
	}

	/**
	 * A posted checkout field is read flat, nested by a dotted key, and in
	 * the order the keys are given; an array is no value.
	 */
	public function test_checkout_reads_posted_fields(): void {
		$this->assertSame( 'a@example.com', Checkout::get_email( [ 'posted' => [ 'edd_email' => ' a@example.com ' ] ] ) );
		$this->assertSame( 'b@example.com', Checkout::get_email( [ 'posted' => [ 'edd-email' => 'b@example.com' ] ] ) );
		$this->assertSame( 'a@example.com', Checkout::get_email( [ 'posted' => [ 'edd_email' => 'a@example.com', 'edd-email' => 'b@example.com' ] ] ) );
		$this->assertSame( '', Checkout::get_email( [ 'posted' => [ 'edd_email' => [ 'x' ] ] ] ) );
		$this->assertSame( '', Checkout::get_email( [] ) );

		$this->assertSame( 'GB', Checkout::get_country( [ 'posted' => [ 'edd_address' => [ 'country' => 'GB' ] ] ] ) );
		$this->assertSame( 'FR', Checkout::get_country( [ 'posted' => [ 'edd_address.country' => 'FR', 'edd_address' => [ 'country' => 'GB' ] ] ] ) );
	}

	/**
	 * The counts hook onto this copy's prefixed filters, late, and only the
	 * kinds EDD's tables can answer.
	 */
	public function test_velocity_hooks_the_prefixed_filters(): void {
		Engine::use_prefix( 'eddtest' );
		Velocity::register();

		foreach ( array_keys( Velocity::KINDS ) as $kind ) {
			$hooked = edd_registrars_hooked( 'eddtest_conditions_velocity_' . $kind );

			$this->assertCount( 1, $hooked, $kind );
			$this->assertSame( Velocity::PRIORITY, $hooked[0]['priority'] );
		}

		$this->assertSame( [], edd_registrars_hooked( 'eddtest_conditions_velocity_blocked_attempts_by_ip' ) );
		$this->assertSame( [], edd_registrars_hooked( 'eddtest_conditions_velocity_orders_by_card_fingerprint' ) );

		// Once per prefix.
		Velocity::register();
		$this->assertCount( 1, edd_registrars_hooked( 'eddtest_conditions_velocity_orders_by_ip' ) );
	}

	/**
	 * An answer from an earlier filter stands; with none, EDD's table is
	 * counted.
	 */
	public function test_velocity_counts_edd_orders_unless_answered(): void {
		Engine::use_prefix( 'eddtest2' );
		Velocity::register();
		$wpdb = $this->fake_wpdb( 4 );

		$by_ip = edd_registrars_hooked( 'eddtest2_conditions_velocity_orders_by_ip' )[0]['callback'];

		$this->assertSame( 9, $by_ip( 9, '203.0.113.9', 1, 'hour' ) );
		$this->assertSame( [], $wpdb->queries );

		$this->assertSame( 4, $by_ip( null, '203.0.113.9', 10, 'minute' ) );
		$this->assertStringContainsString( 'SELECT COUNT(*) FROM wp_edd_orders WHERE ip = \'203.0.113.9\'', $wpdb->queries[0] );
		$this->assertStringContainsString( 'INTERVAL 10 MINUTE', $wpdb->queries[0] );

		$failed = edd_registrars_hooked( 'eddtest2_conditions_velocity_failed_orders_by_email' )[0]['callback'];
		$failed( null, 'a@example.com', 2, 'day' );
		$this->assertStringContainsString( "email = 'a@example.com' AND status IN ('failed','revoked','abandoned')", end( $wpdb->queries ) );

		$emails = edd_registrars_hooked( 'eddtest2_conditions_velocity_distinct_emails_by_ip' )[0]['callback'];
		$emails( null, '203.0.113.9', 1, 'hour' );
		$this->assertStringContainsString( 'SELECT COUNT(DISTINCT email) FROM wp_edd_orders WHERE ip = ', end( $wpdb->queries ) );

		$ips = edd_registrars_hooked( 'eddtest2_conditions_velocity_distinct_ips_by_email' )[0]['callback'];
		$ips( null, 'a@example.com', 1, 'hour' );
		$this->assertStringContainsString( 'SELECT COUNT(DISTINCT ip) FROM wp_edd_orders WHERE email = ', end( $wpdb->queries ) );
	}

	/**
	 * The same-product count is the busiest product's, keyed on the column
	 * asked for, over the products handed in.
	 */
	public function test_same_product_count(): void {
		Engine::use_prefix( 'eddtest3' );
		Velocity::register();
		$wpdb = $this->fake_wpdb( 3 );

		$same = edd_registrars_hooked( 'eddtest3_conditions_velocity_same_product_orders_by_email' )[0]['callback'];

		$this->assertSame( 7, $same( 7, 'a@example.com', [ 5 ], 1, 'hour' ) );
		$this->assertSame( 3, $same( null, 'a@example.com', [ '5', 9, 'x' ], 1, 'hour' ) );
		$this->assertStringContainsString( 'SELECT MAX(c)', end( $wpdb->queries ) );
		$this->assertStringContainsString( "o.email = 'a@example.com'", end( $wpdb->queries ) );
		$this->assertStringContainsString( 'oi.product_id IN (5,9)', end( $wpdb->queries ) );
	}

	/**
	 * With no orders table there is no count, so the rule is not judged.
	 */
	public function test_no_orders_table_is_no_count(): void {
		$this->fake_wpdb( 4, false );

		$this->assertNull( Velocity::count( 'orders', 'ip', '203.0.113.9', 1, 'hour' ) );
		$this->assertNull( Velocity::count_same_product( 'ip', '203.0.113.9', [ 5 ], 1, 'hour' ) );
	}

	/**
	 * The cart clock believes a start passed in, and hooks EDD's cart.
	 */
	public function test_cart_clock(): void {
		$this->assertSame( 1700000000, CartClock::started_at( [ 'cart_started_at' => 1700000000 ] ) );
		$this->assertNull( CartClock::started_at( [ 'cart_started_at' => 0 ] ) );
		$this->assertGreaterThanOrEqual( 60, CartClock::seconds_since_start( [ 'cart_started_at' => time() - 60 ] ) );

		Engine::use_prefix( 'eddtest4' );
		Conditions::register();

		$this->assertNotSame( [], edd_registrars_hooked( 'edd_post_add_to_cart' ) );
		$this->assertNotSame( [], edd_registrars_hooked( 'edd_empty_cart' ) );
		$this->assertNotSame( [], edd_registrars_hooked( 'eddtest4_conditions_velocity_orders_by_ip' ) );
	}

	/**
	 * A $wpdb that records its queries and answers every count the same.
	 *
	 * @param int  $answer The count every query returns.
	 * @param bool $tables Whether SHOW TABLES finds the table.
	 *
	 * @return object
	 */
	private function fake_wpdb( int $answer, bool $tables = true ): object {
		$GLOBALS['wpdb'] = new class( $answer, $tables ) {

			/** @var string */
			public string $prefix = 'wp_';

			/** @var string[] */
			public array $queries = [];

			/**
			 * @param int  $answer The count.
			 * @param bool $tables Whether tables exist.
			 */
			public function __construct( private int $answer, private bool $tables ) {}

			/**
			 * @param string $sql  SQL.
			 * @param mixed  ...$args Values.
			 *
			 * @return string
			 */
			public function prepare( string $sql, ...$args ): string {
				return vsprintf( str_replace( '%s', "'%s'", $sql ), $args );
			}

			/**
			 * @param string $sql SQL.
			 *
			 * @return string|null
			 */
			public function get_var( string $sql ): ?string {
				if ( str_starts_with( $sql, 'SHOW TABLES' ) ) {
					return $this->tables ? 'yes' : null;
				}

				$this->queries[] = $sql;

				return (string) $this->answer;
			}
		};

		return $GLOBALS['wpdb'];
	}
}
