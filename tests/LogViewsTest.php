<?php
/**
 * Registering a custom log view.
 *
 * register() answers with a bool rather than throwing, so a view rejected for a
 * missing argument disappears without a word. These tests pin which arguments
 * are actually required, and that a rejected view leaves nothing behind.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests;

use ArrayPress\EDD\Register\LogViewsManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Class LogViewsTest
 */
final class LogViewsTest extends TestCase {

	/**
	 * A complete configuration.
	 *
	 * @return array
	 */
	private function valid(): array {
		return [
			'id'         => 'product_notes',
			'title'      => 'Product Notes',
			'class_name' => 'Some_List_Table',
			'file'       => 'tables/class-notes.php',
		];
	}

	/**
	 * A fresh manager.
	 *
	 * @return LogViewsManager
	 */
	private function manager(): LogViewsManager {
		return new LogViewsManager();
	}

	/**
	 * Read the stored views.
	 *
	 * @param LogViewsManager $manager The manager.
	 *
	 * @return array
	 */
	private function stored( LogViewsManager $manager ): array {
		return (array) ( new ReflectionProperty( $manager, 'views' ) )->getValue( $manager );
	}

	/**
	 * A complete configuration registers.
	 */
	public function test_a_complete_view_registers(): void {
		$this->assertTrue( $this->manager()->register( $this->valid() ) );
	}

	/**
	 * Each required argument is genuinely required.
	 *
	 * @param string $drop The argument to remove.
	 */
	#[DataProvider( 'requiredArguments' )]
	public function test_every_required_argument_is_required( string $drop ): void {
		$args = $this->valid();
		unset( $args[ $drop ] );

		$manager = $this->manager();

		$this->assertFalse( $manager->register( $args ), "'{$drop}' should be required." );
		$this->assertSame( [], $this->stored( $manager ), 'A rejected view must not be stored.' );
	}

	/**
	 * The required arguments.
	 *
	 * @return array
	 */
	public static function requiredArguments(): array {
		return [
			'id'         => [ 'id' ],
			'title'      => [ 'title' ],
			'class_name' => [ 'class_name' ],
			'file'       => [ 'file' ],
		];
	}

	/**
	 * An empty string counts as missing, not as a value.
	 */
	public function test_an_empty_string_is_treated_as_missing(): void {
		$args       = $this->valid();
		$args['id'] = '';

		$this->assertFalse( $this->manager()->register( $args ) );
	}

	/**
	 * The capability defaults rather than being left empty.
	 *
	 * An empty capability passed to current_user_can() is not a permissive
	 * default, it is an unpredictable one.
	 */
	public function test_the_capability_defaults(): void {
		$manager = $this->manager();
		$manager->register( $this->valid() );

		$this->assertSame( 'view_shop_reports', $this->stored( $manager )['product_notes']['capability'] );
	}

	/**
	 * A supplied capability is kept.
	 */
	public function test_a_supplied_capability_is_kept(): void {
		$manager = $this->manager();
		$manager->register( array_merge( $this->valid(), [ 'capability' => 'edit_shop_payments' ] ) );

		$this->assertSame( 'edit_shop_payments', $this->stored( $manager )['product_notes']['capability'] );
	}

	/**
	 * Registered views reach EDD's list, keyed by id and labelled by title.
	 */
	public function test_views_are_handed_to_edd(): void {
		$manager = $this->manager();
		$manager->register( $this->valid() );

		$views = $manager->register_views_with_edd( [ 'file_downloads' => 'File Downloads' ] );

		$this->assertSame( 'File Downloads', $views['file_downloads'], "EDD's own views must survive." );
		$this->assertSame( 'Product Notes', $views['product_notes'] );
	}

	/**
	 * Two views can coexist.
	 */
	public function test_two_views_coexist(): void {
		$manager = $this->manager();
		$manager->register( $this->valid() );
		$manager->register( [
			'id'         => 'other',
			'title'      => 'Other',
			'class_name' => 'Other_Table',
			'file'       => 'tables/class-other.php',
		] );

		$this->assertCount( 2, $manager->register_views_with_edd( [] ) );
	}

	/**
	 * Registering the same id twice replaces rather than duplicating.
	 */
	public function test_the_same_id_replaces(): void {
		$manager = $this->manager();
		$manager->register( $this->valid() );
		$manager->register( array_merge( $this->valid(), [ 'title' => 'Renamed' ] ) );

		$views = $manager->register_views_with_edd( [] );

		$this->assertCount( 1, $views );
		$this->assertSame( 'Renamed', $views['product_notes'] );
	}
}
