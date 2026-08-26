<?php
/**
 * A concrete registry for testing.
 *
 * BaseFieldRegistry is abstract and its renderers are protected, which is the
 * right shape for the library and an awkward one for a test. This exposes
 * render_field() and nothing else.
 *
 * @package ArrayPress\EDD\Register
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Tests\Doubles;

use ArrayPress\EDD\Register\Abstracts\BaseFieldRegistry;

/**
 * Class Registry
 */
class Registry extends BaseFieldRegistry {

	/**
	 * Nothing to hook in a unit test.
	 *
	 * @return void
	 */
	public function init(): void {
	}

	/**
	 * Render a field.
	 *
	 * @param string $field_id Field ID.
	 * @param mixed  $value    Current value.
	 * @param int    $post_id  Post ID.
	 *
	 * @return string
	 */
	public function render( string $field_id, $value = '', int $post_id = 1 ): string {
		return $this->render_field( $field_id, $value, $post_id );
	}

	/**
	 * The stored configuration for a field.
	 *
	 * @param string $field_id Field ID.
	 *
	 * @return array
	 */
	public function config( string $field_id ): array {
		return $this->fields[ $field_id ] ?? [];
	}
}
