<?php
/**
 * Registering export columns, and surviving the callbacks.
 *
 * This library is compiled into four paid plugins. The part that matters most
 * is filter_csv_data(): it runs a plugin's callback once per column per row, so
 * one bad callback in one column would otherwise take down an entire export
 * halfway through writing a file.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests;

use ArrayPress\EDD\Register\ExportColumns;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Class ExportColumnsTest
 */
final class ExportColumnsTest extends TestCase {

	/**
	 * A minimally valid column.
	 *
	 * @return array
	 */
	private function valid(): array {
		return [
			'notes' => [
				'label'    => 'Notes',
				'callback' => static fn ( $id ): string => 'note-for-' . $id,
			],
		];
	}

	/**
	 * The happy path.
	 */
	public function test_a_valid_column_registers(): void {
		$columns = new ExportColumns( 'downloads' );

		$this->assertTrue( $columns->register( $this->valid() ) );
	}

	/**
	 * An export with no type has nothing to attach to.
	 */
	public function test_an_empty_type_is_refused(): void {
		$columns = new ExportColumns( '' );

		$result = $columns->register( $this->valid() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_type', $result->get_error_code() );
	}

	/**
	 * An empty column set is refused rather than quietly accepted.
	 */
	public function test_an_empty_column_set_is_refused(): void {
		$result = ( new ExportColumns( 'downloads' ) )->register( [] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'empty_columns', $result->get_error_code() );
	}

	/**
	 * A column with no label is refused.
	 */
	public function test_a_missing_label_is_refused(): void {
		$result = ( new ExportColumns( 'downloads' ) )->register( [
			'notes' => [ 'callback' => static fn ( $id ): string => '' ],
		] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_column_label', $result->get_error_code() );
	}

	/**
	 * A callback that is not callable is refused at registration.
	 *
	 * Caught here it is an error message. Caught later it is a fatal in the
	 * middle of writing a CSV.
	 */
	public function test_an_uncallable_callback_is_refused(): void {
		$result = ( new ExportColumns( 'downloads' ) )->register( [
			'notes' => [ 'label' => 'Notes', 'callback' => 'no_such_function_anywhere' ],
		] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_column_callback', $result->get_error_code() );
	}

	/**
	 * Labels are added to the CSV header.
	 */
	public function test_labels_reach_the_header(): void {
		$columns = new ExportColumns( 'downloads' );
		$columns->register( $this->valid() );

		$header = $columns->register_csv_columns( [ 'id' => 'ID' ] );

		$this->assertSame( [ 'id' => 'ID', 'notes' => 'Notes' ], $header );
	}

	/**
	 * Each row gets its column filled from the callback.
	 */
	public function test_rows_are_filled_from_the_callback(): void {
		$columns = new ExportColumns( 'downloads' );
		$columns->register( $this->valid() );

		$data = $columns->filter_csv_data( [ [ 'ID' => 7 ], [ 'ID' => 9 ] ] );

		$this->assertSame( 'note-for-7', $data[0]['notes'] );
		$this->assertSame( 'note-for-9', $data[1]['notes'] );
	}

	/**
	 * A callback that throws leaves an empty cell and the export continues.
	 *
	 * The alternative is a fatal partway through a download, which leaves the
	 * user with a truncated file and no explanation.
	 */
	public function test_a_throwing_callback_does_not_stop_the_export(): void {
		$columns = new ExportColumns( 'downloads' );
		$columns->register( [
			'boom' => [
				'label'    => 'Boom',
				'callback' => static function ( $id ) {
					throw new \RuntimeException( 'callback exploded' );
				},
			],
			'fine' => [
				'label'    => 'Fine',
				'callback' => static fn ( $id ): string => 'ok',
			],
		] );

		$data = $columns->filter_csv_data( [ [ 'ID' => 1 ] ] );

		$this->assertSame( '', $data[0]['boom'] );
		$this->assertSame( 'ok', $data[0]['fine'], 'One bad column must not take the others with it.' );
	}

	/**
	 * A callback that raises an Error is contained too.
	 *
	 * A TypeError from a callback expecting an int and handed null is far more
	 * likely in practice than a deliberate exception, and only catching
	 * Exception would miss it.
	 */
	public function test_a_fatal_error_in_a_callback_is_contained(): void {
		$columns = new ExportColumns( 'downloads' );
		$columns->register( [
			'boom' => [
				'label'    => 'Boom',
				'callback' => static function ( $id ) {
					throw new \Error( 'not an exception' );
				},
			],
		] );

		$data = $columns->filter_csv_data( [ [ 'ID' => 1 ] ] );

		$this->assertSame( '', $data[0]['boom'] );
	}

	/**
	 * The id field falls back rather than passing null to every callback.
	 */
	public function test_the_id_field_falls_back(): void {
		$seen = [];

		$columns = new ExportColumns( 'downloads', [], 'order_id' );
		$columns->register( [
			'c' => [
				'label'    => 'C',
				'callback' => static function ( $id ) use ( &$seen ) {
					$seen[] = $id;

					return (string) $id;
				},
			],
		] );

		$columns->filter_csv_data( [
			[ 'order_id' => 1 ],  // The configured field.
			[ 'ID' => 2 ],        // Falls back to ID.
			[ 'id' => 3 ],        // Falls back to lowercase id.
			[ 'nothing' => 4 ],   // Nothing to find.
		] );

		$this->assertSame( [ 1, 2, 3, null ], $seen );
	}

	/**
	 * Registering twice adds rather than replaces.
	 */
	public function test_registering_twice_merges(): void {
		$columns = new ExportColumns( 'downloads' );
		$columns->register( $this->valid() );
		$columns->register( [
			'second' => [ 'label' => 'Second', 'callback' => static fn ( $id ): string => 'x' ],
		] );

		$header = $columns->register_csv_columns( [] );

		$this->assertArrayHasKey( 'notes', $header );
		$this->assertArrayHasKey( 'second', $header );
	}
}
