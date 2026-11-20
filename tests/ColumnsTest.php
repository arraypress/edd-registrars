<?php
/**
 * Column registration, positioning and permissions.
 *
 * This library is compiled into four paid plugins. The part worth pinning is
 * register_columns(): it decides where a column lands relative to the ones
 * already there, and getting that wrong does not error -- the column simply
 * turns up somewhere else, or not at all.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests;

use ArrayPress\EDD\Register\Abstracts\Columns;
use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * A concrete table, so the abstract can be exercised without a screen.
 */
final class TestTable extends Columns {

	protected const string OBJECT_TYPE = 'test_object';

	protected function is_screen(): bool {
		return true;
	}

	public function render_column_content( string $value, string $column_name, $object ): string {
		return $value;
	}

	protected function load_hooks(): void {
		// Nothing: hooks need WordPress, and the logic under test does not.
	}

	/**
	 * Whatever a test put there.
	 *
	 * The one thing each EDD table supplies for itself — orders read order
	 * meta, licences read Software Licensing's — and the only reason the
	 * renderer can live in the base class.
	 *
	 * @param int|string|object $object_id The object, or its id.
	 * @param string            $meta_key  The key.
	 *
	 * @return mixed
	 */
	protected function get_meta( $object_id, string $meta_key ) {
		return $GLOBALS['edd_rc_meta'][ $meta_key ] ?? '';
	}
}

/**
 * A subclass that forgot its OBJECT_TYPE.
 */
final class NamelessTable extends Columns {

	protected function is_screen(): bool {
		return true;
	}

	public function render_column_content( string $value, string $column_name, $object ): string {
		return $value;
	}

	protected function load_hooks(): void {
	}

	/**
	 * @param int|string|object $object_id The object, or its id.
	 * @param string            $meta_key  The key.
	 *
	 * @return mixed
	 */
	protected function get_meta( $object_id, string $meta_key ) {
		return '';
	}
}

/**
 * Class ColumnsTest
 */
final class ColumnsTest extends TestCase {

	/**
	 * The registry is static, so it survives between tests unless cleared.
	 *
	 * That is worth knowing about the library itself: two instances for the
	 * same object type share state by design, and a test that forgets this
	 * passes or fails depending on what ran before it.
	 */
	protected function setUp(): void {
		// And so is the record of which tables have had their hooks
		// attached — which is what stops a second registration for one table
		// rendering every cell twice, and which would stop a second *test*
		// for one table seeing any hooks at all.
		foreach ( [ 'columns', 'hooked' ] as $name ) {
			( new ReflectionProperty( Columns::class, $name ) )->setValue( null, [] );
		}

		unset( $GLOBALS['edd_rc_test_caps'] );
	}

	/**
	 * A column with no position is appended.
	 */
	public function test_a_column_without_a_position_is_appended(): void {
		$table = new TestTable( [ 'mine' => [ 'label' => 'Mine' ] ] );

		$result = $table->register_columns( [ 'title' => 'Title', 'price' => 'Price' ] );

		$this->assertSame( [ 'title', 'price', 'mine' ], array_keys( $result ) );
	}

	/**
	 * after: puts the column immediately after its reference.
	 */
	public function test_after_places_the_column_next(): void {
		$table = new TestTable( [ 'mine' => [ 'label' => 'Mine', 'position' => 'after:title' ] ] );

		$result = $table->register_columns( [ 'title' => 'Title', 'price' => 'Price' ] );

		$this->assertSame( [ 'title', 'mine', 'price' ], array_keys( $result ) );
	}

	/**
	 * before: puts it immediately in front.
	 */
	public function test_before_places_the_column_in_front(): void {
		$table = new TestTable( [ 'mine' => [ 'label' => 'Mine', 'position' => 'before:price' ] ] );

		$result = $table->register_columns( [ 'title' => 'Title', 'price' => 'Price' ] );

		$this->assertSame( [ 'title', 'mine', 'price' ], array_keys( $result ) );
	}

	/**
	 * A reference column that is not there must not lose the column.
	 *
	 * Screens differ by EDD version and by what other add-ons have done, so
	 * naming a column that has gone is an ordinary situation, not a bug. The
	 * column should still appear somewhere rather than vanish.
	 */
	public function test_an_unknown_reference_still_yields_the_column(): void {
		$table = new TestTable( [ 'mine' => [ 'label' => 'Mine', 'position' => 'after:nonexistent' ] ] );

		$result = $table->register_columns( [ 'title' => 'Title' ] );

		$this->assertArrayHasKey( 'mine', $result, 'A missing reference column should not swallow the new one.' );
	}

	/**
	 * Labels are escaped on the way in.
	 */
	public function test_labels_are_escaped(): void {
		$table = new TestTable( [ 'mine' => [ 'label' => '<script>x</script>' ] ] );

		$result = $table->register_columns( [] );

		$this->assertStringNotContainsString( '<script>', $result['mine'] );
	}

	/**
	 * A permission callback returning false hides the column.
	 */
	public function test_a_failing_permission_callback_hides_the_column(): void {
		$table = new TestTable( [
			'secret' => [ 'label' => 'Secret', 'permission_callback' => static fn (): bool => false ],
			'open'   => [ 'label' => 'Open' ],
		] );

		$result = $table->register_columns( [] );

		$this->assertArrayNotHasKey( 'secret', $result );
		$this->assertArrayHasKey( 'open', $result );
	}

	/**
	 * Keys asked to be removed are removed.
	 */
	public function test_requested_keys_are_removed(): void {
		$table = new TestTable( [], [ 'price' ] );

		$result = $table->register_columns( [ 'title' => 'Title', 'price' => 'Price' ] );

		$this->assertArrayNotHasKey( 'price', $result );
		$this->assertArrayHasKey( 'title', $result );
	}

	/**
	 * Removing a key that is not there is not an error.
	 */
	public function test_removing_an_absent_key_is_harmless(): void {
		$table = new TestTable( [], [ 'nope' ] );

		$result = $table->register_columns( [ 'title' => 'Title' ] );

		$this->assertSame( [ 'title' ], array_keys( $result ) );
	}

	/**
	 * An empty or non-string key is refused loudly.
	 */
	public function test_an_empty_key_throws(): void {
		$this->expectException( Exception::class );

		new TestTable( [ '' => [ 'label' => 'Nameless' ] ] );
	}

	/**
	 * A subclass without OBJECT_TYPE is refused.
	 *
	 * Without this the columns would be filed under an empty object type and
	 * silently never render.
	 */
	public function test_a_subclass_without_an_object_type_throws(): void {
		$this->expectException( Exception::class );

		new NamelessTable( [ 'mine' => [ 'label' => 'Mine' ] ] );
	}

	/**
	 * Defaults are filled in for everything not supplied.
	 */
	public function test_defaults_are_applied(): void {
		new TestTable( [ 'mine' => [ 'label' => 'Mine' ] ] );

		$property = new ReflectionProperty( Columns::class, 'columns' );
		$stored   = $property->getValue()['test_object']['test_object']['mine'];

		$this->assertFalse( $stored['sortable'] );
		$this->assertFalse( $stored['numeric'] );
		$this->assertNull( $stored['display_callback'] );
		$this->assertNull( $stored['permission_callback'] );
		$this->assertSame( '', $stored['meta_key'] );
	}

	/**
	 * Only sortable columns are offered as sortable.
	 */
	public function test_only_sortable_columns_are_sortable(): void {
		$table = new TestTable( [
			'yes' => [ 'label' => 'Yes', 'sortable' => true ],
			'no'  => [ 'label' => 'No' ],
		] );

		$result = $table->register_sortable_columns( [] );

		$this->assertArrayHasKey( 'yes', $result );
		$this->assertArrayNotHasKey( 'no', $result );
	}

	/**
	 * A width that is not a CSS length never reaches the stylesheet.
	 *
	 * The value is printed inside a <style> block, where esc_attr() is no
	 * defence: it escapes for an HTML attribute and leaves } and ; untouched,
	 * so "10px; } body { display:none" would close the rule and open another.
	 */
	public function test_a_malicious_width_is_dropped(): void {
		$table = new TestTable( [
			'bad'  => [ 'label' => 'Bad', 'width' => '10px; } body { display:none; ' ],
			'good' => [ 'label' => 'Good', 'width' => '80px' ],
		] );

		ob_start();
		$table->add_custom_column_styles();
		$css = ob_get_clean();

		$this->assertStringNotContainsString( 'display:none', $css );
		// The parts, not the whitespace: how the rule is spaced is the base
		// class's business and not what this test is about.
		$this->assertMatchesRegularExpression( '/\.column-good\s*\{\s*width:\s*80px;?\s*\}/', $css );
	}

	/**
	 * A key that is not a valid class name never reaches the selector.
	 */
	public function test_a_malicious_key_is_sanitised(): void {
		$table = new TestTable( [ 'ok<script>' => [ 'label' => 'X', 'width' => '50px' ] ] );

		ob_start();
		$table->add_custom_column_styles();
		$css = ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $css );
	}

	/**
	 * Nothing to style means no empty style block.
	 */
	public function test_no_widths_means_no_style_block(): void {
		$table = new TestTable( [ 'plain' => [ 'label' => 'Plain' ] ] );

		ob_start();
		$table->add_custom_column_styles();

		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * The ordinary CSS units all pass.
	 */
	public function test_real_css_lengths_are_allowed(): void {
		foreach ( [ '80px', '10%', '2.5rem', '4em', 'auto' ] as $width ) {
			$property = new ReflectionProperty( Columns::class, 'columns' );
			$property->setValue( null, [] );

			$table = new TestTable( [ 'c' => [ 'label' => 'C', 'width' => $width ] ] );

			ob_start();
			$table->add_custom_column_styles();
			$css = ob_get_clean();

			$this->assertMatchesRegularExpression(
				sprintf( '/width:\s*%s;?/', preg_quote( $width, '/' ) ),
				$css,
				sprintf( '%s should be allowed.', $width )
			);
		}
	}
}
