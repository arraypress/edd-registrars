<?php
/**
 * Registering email tags.
 *
 * The callback this library hands EDD is a wrapper, not the callback the
 * caller wrote. These tests hold that wrapper to the contract EDD's own
 * Handler expects: it is called with either an Email object or a plain
 * context string, and whatever it returns is what appears in the email.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests;

use ArrayPress\EDD\Register\EmailTag;
use ArrayPress\EDD\Register\EmailTags;
use ArrayPress\EDD\Register\Tests\Doubles\FakeEmail;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

require_once __DIR__ . '/Doubles/FakeEmail.php';

/**
 * Class EmailTagsTest
 */
final class EmailTagsTest extends TestCase {

	/**
	 * Reset the per-file factory registry and the recorded registrations.
	 */
	protected function setUp(): void {
		parent::setUp();

		( new ReflectionProperty( EmailTags::class, 'registry' ) )->setValue( null, [] );

		$GLOBALS['edd_registered_email_tags'] = [];
	}

	/**
	 * Build a tag through the public builder.
	 *
	 * @param callable $callback The callback.
	 * @param array    $contexts Allowed contexts.
	 *
	 * @return EmailTag
	 */
	private function tag( callable $callback, array $contexts = [ 'order' ] ): EmailTag {
		$factory = EmailTags::register( __FILE__ );

		$factory->tag( 'thing' )
			->description( 'A thing' )
			->callback( $callback )
			->contexts( $contexts )
			->register();

		$tags = EmailTags::get_by( __FILE__ );

		return $tags[ count( $tags ) - 1 ];
	}

	/**
	 * A tag reaches EDD with the six arguments in EDD's own order.
	 */
	public function test_a_tag_is_handed_to_edd_in_order(): void {
		$factory = EmailTags::register( __FILE__ );
		$factory->tag( 'order_note' )
			->description( 'The note' )
			->label( 'Order Note' )
			->callback( fn() => 'x' )
			->contexts( [ 'order' ] )
			->recipients( [ 'admin' ] )
			->register();

		$factory->register_tags();

		$this->assertCount( 1, $GLOBALS['edd_registered_email_tags'] );

		$sent = $GLOBALS['edd_registered_email_tags'][0];

		$this->assertSame( 'order_note', $sent['tag'] );
		$this->assertSame( 'The note', $sent['description'] );
		$this->assertSame( 'Order Note', $sent['label'], 'The label is the fourth argument, not the contexts.' );
		$this->assertSame( [ 'order' ], $sent['contexts'] );
		$this->assertSame( [ 'admin' ], $sent['recipients'] );
		$this->assertIsCallable( $sent['func'] );
	}

	/**
	 * An unset label falls back to a readable form of the tag.
	 */
	public function test_the_label_falls_back_to_the_tag(): void {
		$factory = EmailTags::register( __FILE__ );
		$factory->tag( 'order_note' )->callback( fn() => 'x' )->register();
		$factory->register_tags();

		$this->assertSame( 'Order note', $GLOBALS['edd_registered_email_tags'][0]['label'] );
	}

	/**
	 * A tag without a callback is refused rather than registered empty.
	 */
	public function test_a_tag_without_a_callback_is_refused(): void {
		$this->expectException( RuntimeException::class );

		EmailTags::register( __FILE__ )->tag( 'broken' )->register();
	}

	/**
	 * The wrapper returns what the callback returned.
	 */
	public function test_the_wrapper_returns_the_callback_result(): void {
		$callback = $this->tag( fn( $id, $object, $email ) => 'the value' )->get_callback();

		$this->assertSame( 'the value', $callback( 1, null, 'order' ) );
	}

	/**
	 * EDD passes a context string when there is no email object.
	 *
	 * Handler::do_tag() sends $this->email if set and $this->context otherwise,
	 * so a plain string is a normal call, not an edge case. A wrapper that
	 * treats it as an object silently blanks the tag.
	 */
	public function test_a_context_string_still_reaches_the_callback(): void {
		$callback = $this->tag( fn( $id, $object, $email ) => 'rendered', [ 'order' ] )->get_callback();

		$this->assertSame( 'rendered', $callback( 1, null, 'order' ) );
	}

	/**
	 * EDD passes its Email object when one is in play.
	 *
	 * Its $context is protected, so reaching for ->context is an Error, not a
	 * blank tag -- a fatal inside email rendering.
	 */
	public function test_an_email_object_still_reaches_the_callback(): void {
		$callback = $this->tag( fn( $id, $object, $email ) => 'rendered', [ 'order' ] )->get_callback();

		$this->assertSame( 'rendered', $callback( 1, null, new FakeEmail( 'order' ) ) );
	}

	/**
	 * A callback that throws produces an empty tag, not a broken email.
	 */
	public function test_a_throwing_callback_is_contained(): void {
		$callback = $this->tag( fn() => throw new RuntimeException( 'boom' ) )->get_callback();

		$this->assertSame( '', $callback( 1, null, 'order' ) );
	}

	/**
	 * A callback returning null produces an empty string.
	 *
	 * EDD substitutes the return value straight into the content, and null
	 * would surface as a deprecation once it reaches preg_replace_callback.
	 */
	public function test_a_null_return_becomes_a_string(): void {
		$callback = $this->tag( fn() => null )->get_callback();

		$this->assertSame( '', $callback( 1, null, 'order' ) );
	}

	/**
	 * Factories are per plugin file.
	 */
	public function test_factories_are_per_file(): void {
		$a = EmailTags::register( 'plugin-a.php' );
		$b = EmailTags::register( 'plugin-b.php' );

		$this->assertNotSame( $a, $b );
		$this->assertSame( $a, EmailTags::register( 'plugin-a.php' ) );
	}

	/**
	 * Tags are looked up by the file that registered them.
	 */
	public function test_tags_are_listed_by_file(): void {
		EmailTags::register( 'plugin-a.php' )->tag( 'one' )->callback( fn() => 'x' )->register();

		$this->assertCount( 1, EmailTags::get_by( 'plugin-a.php' ) );
		$this->assertSame( [], EmailTags::get_by( 'plugin-b.php' ) );
	}
}
