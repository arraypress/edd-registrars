<?php
/**
 * What register() accepts, and what it refuses.
 *
 * This library is compiled into six paid plugins, so a defect here is a defect
 * in all six at once. register() is also the only part that can be exercised
 * without WordPress: everything past it renders markup through EDD's own
 * helpers.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests;

use ArrayPress\EDD\Register\Exporters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Class RegisterTest
 */
final class RegisterTest extends TestCase {

	/**
	 * A file that certainly exists, to satisfy the file check.
	 *
	 * @return string
	 */
	private function real_file(): string {
		return basename( __FILE__ );
	}

	/**
	 * A registry rooted at this directory.
	 *
	 * @return Exporters
	 */
	private function exporters(): Exporters {
		return new Exporters( __DIR__ );
	}

	/**
	 * A minimally valid export.
	 *
	 * @return array
	 */
	private function valid(): array {
		return [
			'class' => 'EDD_Batch_Thing_Export',
			'file'  => $this->real_file(),
		];
	}

	/**
	 * The happy path returns true.
	 */
	public function test_a_valid_export_registers(): void {
		$this->assertTrue( $this->exporters()->register( [ 'things' => $this->valid() ] ) );
	}

	/**
	 * An empty array is refused rather than silently accepted.
	 */
	public function test_an_empty_array_is_refused(): void {
		$result = $this->exporters()->register( [] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'empty_exports', $result->get_error_code() );
	}

	/**
	 * Every required key is checked.
	 *
	 * @param string $drop Key to remove.
	 * @param string $code Expected error code.
	 */
	#[DataProvider( 'missingKeys' )]
	public function test_missing_required_keys_are_refused( string $drop, string $code ): void {
		$export = $this->valid();
		unset( $export[ $drop ] );

		$result = $this->exporters()->register( [ 'things' => $export ] );

		$this->assertInstanceOf( WP_Error::class, $result, "Missing '{$drop}' should be refused." );
		$this->assertSame( $code, $result->get_error_code() );
	}

	/**
	 * Cases for the above.
	 *
	 * @return array
	 */
	public static function missingKeys(): array {
		return [
			'no class' => [ 'class', 'missing_export_class' ],
			'no file'  => [ 'file', 'missing_export_file' ],
		];
	}

	/**
	 * A file that is not there is caught at registration.
	 *
	 * Caught here, this is a clear error. Caught later, it is a fatal when
	 * somebody clicks Generate CSV.
	 */
	public function test_a_missing_file_is_refused(): void {
		$result = $this->exporters()->register( [
			'things' => [ 'class' => 'X', 'file' => 'no-such-file.php' ],
		] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'export_file_not_found', $result->get_error_code() );
	}

	/**
	 * An empty key is refused.
	 */
	public function test_an_empty_key_is_refused(): void {
		$result = $this->exporters()->register( [ '' => $this->valid() ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_export_key', $result->get_error_code() );
	}

	/**
	 * The base path is joined to the file, not ignored.
	 *
	 * Without this, a relative file resolves against the current working
	 * directory, which is wherever the request happened to start.
	 */
	public function test_the_base_path_is_applied(): void {
		// Same file, but the registry is rooted somewhere it does not exist.
		$elsewhere = new Exporters( __DIR__ . '/nowhere' );

		$result = $elsewhere->register( [ 'things' => $this->valid() ] );

		$this->assertInstanceOf( WP_Error::class, $result, 'The base path should be part of the lookup.' );
		$this->assertSame( 'export_file_not_found', $result->get_error_code() );
	}

	/**
	 * Optional keys get sensible defaults derived from the id.
	 */
	public function test_defaults_are_derived_from_the_key(): void {
		$exporters = $this->exporters();

		$this->assertTrue( $exporters->register( [ 'discounted-orders' => $this->valid() ] ) );

		$stored = $this->stored( $exporters );

		$this->assertArrayHasKey( 'discounted-orders', $stored );
		$this->assertSame( 'Discounted Orders', $stored['discounted-orders']['title'] );
		$this->assertSame( 'Discounted Orders', $stored['discounted-orders']['label'] );
		$this->assertSame( 60, $stored['discounted-orders']['priority'] );
		$this->assertNotSame( '', $stored['discounted-orders']['button'] );
	}

	/**
	 * Supplied values win over the derived ones.
	 */
	public function test_supplied_values_are_kept(): void {
		$exporters = $this->exporters();

		$exporters->register( [
			'things' => $this->valid() + [
				'title'    => 'My Title',
				'priority' => 10,
			],
		] );

		$stored = $this->stored( $exporters );

		$this->assertSame( 'My Title', $stored['things']['title'] );
		$this->assertSame( 10, $stored['things']['priority'] );
	}

	/**
	 * Registering twice adds rather than replaces.
	 *
	 * Two plugins each registering their own exports must not clear each
	 * other's, and the same plugin may register in more than one place.
	 */
	public function test_registering_twice_merges(): void {
		$exporters = $this->exporters();

		$exporters->register( [ 'first' => $this->valid() ] );
		$exporters->register( [ 'second' => $this->valid() ] );

		$stored = $this->stored( $exporters );

		$this->assertArrayHasKey( 'first', $stored );
		$this->assertArrayHasKey( 'second', $stored );
	}

	/**
	 * Fields are carried through only when they are an array.
	 */
	public function test_fields_are_carried_through(): void {
		$exporters = $this->exporters();

		$exporters->register( [
			'with'    => $this->valid() + [ 'fields' => [ [ 'type' => 'date', 'name' => 'range' ] ] ],
			'without' => $this->valid(),
			'bad'     => $this->valid() + [ 'fields' => 'not an array' ],
		] );

		$stored = $this->stored( $exporters );

		$this->assertCount( 1, $stored['with']['fields'] );
		$this->assertArrayNotHasKey( 'fields', $stored['without'] );
		$this->assertArrayNotHasKey( 'fields', $stored['bad'], 'A non-array fields value should be dropped, not stored.' );
	}

	/**
	 * Read the private registry.
	 *
	 * @param Exporters $exporters The instance.
	 *
	 * @return array
	 */
	private function stored( Exporters $exporters ): array {
		// No setAccessible(): it has had no effect since PHP 8.1 and is
		// deprecated in 8.5, which fails this suite under failOnWarning.
		return (array) ( new \ReflectionProperty( $exporters, 'exports' ) )->getValue( $exporters );
	}
}
