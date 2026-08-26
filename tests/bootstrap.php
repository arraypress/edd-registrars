<?php
/**
 * PHPUnit bootstrap.
 *
 * One set of stubs for what were eight packages. They each carried their own,
 * and the eight disagreed: three different `add_action`, two `WP_Error`, two
 * `esc_url` -- one of which stripped `javascript:` and one of which returned
 * whatever it was given. Merging them meant choosing, and the choice was the
 * strictest version every time, because a stub that is more permissive than
 * WordPress is a test that passes on markup WordPress would have rejected.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

/*
 * What the hooks recorded, and what was logged.
 *
 * The packages kept these under $GLOBALS['cs'] and $GLOBALS['rt'] -- two
 * names for one thing, because two packages happened to be written on
 * different days. One store now, and one accessor for it.
 */
if ( ! function_exists( 'edd_registrars_reset' ) ) {
	function edd_registrars_reset(): void {
		$GLOBALS['edd_registrars'] = [
			'actions' => [],
			'log'     => [],
		];
	}
}

if ( ! function_exists( 'edd_registrars_hooked' ) ) {
	/**
	 * What is hooked to one action.
	 *
	 * @param string $hook Hook name.
	 *
	 * @return array<int, array{callback: mixed, priority: int}>
	 */
	function edd_registrars_hooked( string $hook ): array {
		return $GLOBALS['edd_registrars']['actions'][ $hook ] ?? [];
	}
}

edd_registrars_reset();

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Record a hook rather than run it.
	 *
	 * The priority is kept because one of the merged suites asserts on it,
	 * and a recorder that throws information away is a recorder that has to
	 * be rewritten the first time somebody needs it.
	 *
	 * @param string $hook     Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $args     Accepted arguments.
	 *
	 * @return bool
	 */
	function add_action( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
		$GLOBALS['edd_registrars']['actions'][ $hook ][] = [
			'callback' => $callback,
			'priority' => $priority,
		];

		return true;
	}
}

if ( ! function_exists( 'edd_debug_log' ) ) {
	/**
	 * @param string $message Message.
	 * @param bool   $force   Unused.
	 *
	 * @return void
	 */
	function edd_debug_log( string $message, bool $force = false ): void {
		$GLOBALS['edd_registrars']['log'][] = $message;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Enough of WP_Error for the register() return contract.
	 */
	class WP_Error {

		/**
		 * Error codes and messages.
		 *
		 * @var array
		 */
		public array $errors = [];

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			if ( '' !== $code ) {
				$this->errors[ $code ][] = $message;
			}
		}

		/**
		 * The first error code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			$codes = array_keys( $this->errors );

			return $codes[0] ?? '';
		}

		/**
		 * The first error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			$code = $this->get_error_code();

			return $this->errors[ $code ][0] ?? '';
		}
	}
}

if ( ! class_exists( 'EDD_Customer' ) ) {
	/**
	 * Stand-in for EDD's customer object. Only the id is read.
	 */
	class EDD_Customer {

		/** @var int */
		public int $id;

		/**
		 * @param int $id Customer id.
		 */
		public function __construct( int $id = 1 ) {
			$this->id = $id;
		}
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 *
	 * @return string
	 */
	function __( $text, $domain = null ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * @param string $text Text.
	 *
	 * @return string
	 */
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Strip the schemes core rejects, then escape.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	function esc_url( $url ): string {
		$url = (string) $url;

		if ( preg_match( '#^\s*(javascript|data|vbscript):#i', $url ) ) {
			return '';
		}

		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 *
	 * @return bool
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $display = true ) {
		$result = (string) $checked === (string) $current ? " checked='checked'" : '';

		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $display = true ) {
		$result = (string) $selected === (string) $current ? " selected='selected'" : '';

		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

if ( ! function_exists( 'register_meta' ) ) {
	function register_meta( $object_type, $meta_key, $args = [] ) {
		$GLOBALS['registered_meta'][ $meta_key ] = $args;

		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		// Tests that care about capability set $GLOBALS['edd_rc_test_caps'].
		$caps = $GLOBALS['edd_rc_test_caps'] ?? null;

		return null === $caps ? true : in_array( $capability, (array) $caps, true );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param array $args     Arguments.
	 * @param array $defaults Defaults.
	 *
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = [] ): array {
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	/**
	 * Copied from core: the class is interpolated into a class attribute.
	 *
	 * @param string $class_name Class name.
	 * @param string $fallback   Fallback.
	 *
	 * @return string
	 */
	function sanitize_html_class( $class_name, $fallback = '' ): string {
		$sanitized = preg_replace( '/%[a-f0-9]{2}/i', '', (string) $class_name );
		$sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', $sanitized );

		if ( '' === $sanitized && $fallback ) {
			return sanitize_html_class( $fallback );
		}

		return $sanitized;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Key.
	 *
	 * @return string
	 */
	function sanitize_key( $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $str Value.
	 *
	 * @return string
	 */
	function sanitize_text_field( $str ): string {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Enough of core's behaviour for the assertions: post-safe markup survives,
	 * script tags do not.
	 *
	 * @param string $html HTML.
	 *
	 * @return string
	 */
	function wp_kses_post( $html ): string {
		return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $html );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'edd_debug_log_exception' ) ) {
	/**
	 * @param Throwable $exception The exception.
	 *
	 * @return void
	 */
	function edd_debug_log_exception( $exception ): void {
		$GLOBALS['edd_registrars']['log'][] = get_class( $exception ) . ': ' . $exception->getMessage();
	}
}

if ( ! function_exists( 'EDD' ) ) {
	function EDD() {
		return new stdClass();
	}
}

if ( ! function_exists( 'edd_add_email_tag' ) ) {
	/**
	 * Stands in for EDD's own registration, recording the six arguments in the
	 * order EDD 3.x declares them.
	 */
	function edd_add_email_tag( $tag = '', $description = '', $func = '', $label = '', $contexts = null, $recipients = null ) {
		$GLOBALS['edd_registered_email_tags'][] = compact( 'tag', 'description', 'func', 'label', 'contexts', 'recipients' );
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/*
 * And the global functions again. They are Composer `files` entries, so they
 * already ran when the autoloader was required -- and returned without
 * declaring anything, because they guard on ABSPATH, which did not exist
 * yet. `require`, not `require_once`: the paths are already in the included
 * list, so require_once would do nothing at all.
 */
foreach (
	[
		'ColumnFunctions.php',
		'CustomerStatFunctions.php',
		'EmailTagFunctions.php',
		'ExportColumnFunctions.php',
		'ExporterFunctions.php',
		'FieldFunctions.php',
		'LogViewFunctions.php',
		'RecountFunctions.php',
	] as $entrypoint
) {
	$path = dirname( __DIR__ ) . '/src/' . $entrypoint;

	if ( is_file( $path ) ) {
		require $path;
	}
}
