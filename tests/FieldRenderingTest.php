<?php
/**
 * Rendering registered fields.
 *
 * Every renderer here builds markup by hand, so escaping is the thing worth
 * pinning: once is correct, twice corrupts the value, and none is an
 * injection. These tests assert the exact rendered string rather than
 * "contains", because "contains" cannot tell one escape from two.
 *
 * @package ArrayPress\EDD\RegisterFields
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests;

use ArrayPress\EDD\Register\Tests\Doubles\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Doubles/Registry.php';

/**
 * Class FieldRenderingTest
 */
final class FieldRenderingTest extends TestCase {

	/**
	 * A registry with one field registered.
	 *
	 * @param string $type   Field type.
	 * @param array  $config Extra configuration.
	 *
	 * @return Registry
	 */
	private function registry( string $type = 'text', array $config = [] ): Registry {
		$registry = new Registry();
		$registry->register_field( 'thing', array_merge( [ 'type' => $type, 'label' => 'Thing' ], $config ) );

		return $registry;
	}

	/**
	 * A value carrying every character escaping cares about.
	 */
	private const AWKWARD = 'Tom & Jerry\'s "<b>";';

	/**
	 * The value is escaped exactly once.
	 *
	 * Escaping twice is not a harmless extra: the ampersand in a value comes
	 * back from the input as &amp;, is stored, and grows another &amp; on the
	 * next save. The corruption compounds every time the field is edited.
	 *
	 * @param string $type The field type.
	 */
	#[DataProvider( 'attributeValueTypes' )]
	public function test_the_value_is_escaped_once( string $type ): void {
		$html = $this->registry( $type )->render( 'thing', self::AWKWARD );

		$this->assertStringContainsString( 'value="' . esc_attr( self::AWKWARD ) . '"', $html );
		$this->assertStringNotContainsString( '&amp;amp;', $html, 'The value was escaped twice.' );
	}

	/**
	 * Field types that put the value in an attribute.
	 *
	 * @return array
	 */
	public static function attributeValueTypes(): array {
		return [
			'text'   => [ 'text' ],
			'number' => [ 'number' ],
			'email'  => [ 'email' ],
			'url'    => [ 'url' ],
			'color'  => [ 'color' ],
		];
	}

	/**
	 * A value is never emitted raw.
	 *
	 * @param string $type The field type.
	 */
	#[DataProvider( 'attributeValueTypes' )]
	public function test_the_value_cannot_break_out_of_the_attribute( string $type ): void {
		$payload = '" onfocus="alert(1)';

		$html = $this->registry( $type )->render( 'thing', $payload );

		// The payload staying visible as text is fine and expected. What must
		// not happen is its quote closing the attribute, which is what the
		// whole escaped attribute proves.
		$this->assertStringContainsString( 'value="&quot; onfocus=&quot;alert(1)"', $html );
	}

	/**
	 * Caller-supplied attribute names and values are escaped.
	 */
	public function test_supplied_attributes_are_escaped(): void {
		$registry = $this->registry( 'text', [ 'attributes' => [ 'data-note' => '"><script>' ] ] );

		$html = $registry->render( 'thing', '' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'data-note="&quot;&gt;&lt;script&gt;"', $html );
	}

	/**
	 * A required field says so in the markup.
	 */
	public function test_a_required_field_is_marked_required(): void {
		$this->assertStringContainsString( 'required="required"', $this->registry( 'text', [ 'required' => true ] )->render( 'thing' ) );
	}

	/**
	 * An unknown type falls back to a text field rather than rendering nothing.
	 */
	public function test_an_unknown_type_falls_back_to_text(): void {
		$html = $this->registry( 'not_a_real_type' )->render( 'thing', 'value' );

		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( 'type="text"', $html );
	}

	/**
	 * An unregistered field renders nothing at all.
	 */
	public function test_an_unregistered_field_renders_nothing(): void {
		$this->assertSame( '', ( new Registry() )->render( 'missing', 'value' ) );
	}

	/**
	 * A textarea escapes its content and carries no value attribute.
	 */
	public function test_a_textarea_escapes_its_content(): void {
		$html = $this->registry( 'textarea' )->render( 'thing', self::AWKWARD );

		$this->assertStringNotContainsString( 'value=', $html );
		$this->assertStringContainsString( '>' . esc_textarea( self::AWKWARD ) . '<', $html );
	}

	/**
	 * Select option labels are escaped and the current value is selected.
	 */
	public function test_a_select_escapes_labels_and_marks_the_selection(): void {
		$registry = $this->registry( 'select', [ 'options' => [ 'a' => 'Fish & Chips', 'b' => '<b>Bold</b>' ] ] );

		$html = $registry->render( 'thing', 'b' );

		$this->assertStringNotContainsString( '<b>Bold</b>', $html );
		$this->assertStringContainsString( 'Fish &amp; Chips', $html );
		$this->assertMatchesRegularExpression( '/<option value="b"\s+selected=/', $html );
	}

	/**
	 * Radio option labels are escaped.
	 */
	public function test_radio_labels_are_escaped(): void {
		$html = $this->registry( 'radio', [ 'options' => [ 'a' => '<b>Bold</b>' ] ] )->render( 'thing', 'a' );

		$this->assertStringNotContainsString( '<b>Bold</b>', $html );
		$this->assertStringContainsString( "checked='checked'", $html );
	}
}
