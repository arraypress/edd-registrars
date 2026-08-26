<?php
/**
 * A stand-in for EDD's email object.
 *
 * The real \EDD\Emails\Types\Email keeps $context protected and exposes it
 * through get_context(). That visibility is the whole point of this double --
 * a library reaching for ->context directly does not get a warning, it gets an
 * Error.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests\Doubles;

/**
 * Class FakeEmail
 */
class FakeEmail {

	/**
	 * The email context.
	 *
	 * @var string
	 */
	protected $context;

	/**
	 * Constructor.
	 *
	 * @param string $context The context.
	 */
	public function __construct( string $context ) {
		$this->context = $context;
	}

	/**
	 * Get the email context.
	 *
	 * @return string
	 */
	public function get_context() {
		return $this->context;
	}
}
