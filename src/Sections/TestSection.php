<?php
/**
 * Test Section for EDD Field Registry
 *
 * @package ArrayPress/EDD-Utils
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Sections;

/**
 * Test Section
 *
 * A simple test section demonstrating the configurable section functionality.
 * This section includes basic text and toggle fields for testing purposes.
 */
class TestSection extends Configurable {

	/**
	 * Section ID
	 *
	 * @var string
	 */
	protected $id = 'test_section';

	/**
	 * Section priority
	 *
	 * @var int
	 */
	protected $priority = 25;

	/**
	 * Section icon
	 *
	 * @var string
	 */
	protected $icon = 'admin-tools';

	/**
	 * Get section configuration
	 *
	 * @return array
	 */
	protected function get_section_config(): array {
		return [
			'id'       => 'test_section',
			'title'    => 'Test Section',
			'priority' => 25,
			'icon'     => 'admin-tools',
			'supports' => [],
			'fields'   => [
				'test_text_field' => [
					'type'        => 'text',
					'label'       => 'Test Text Field',
					'description' => 'A simple test text field for demonstration',
					'default'     => '',
					'class'       => 'regular-text',
				],
				'test_toggle'     => [
					'type'        => 'checkbox_toggle',
					'label'       => 'Test Toggle',
					'description' => 'Test toggle field using EDD\'s toggle component',
					'default'     => false,
				],
				'test_number'     => [
					'type'        => 'number',
					'label'       => 'Test Number',
					'description' => 'A test number field',
					'default'     => 0,
					'attributes'  => [
						'min' => 0,
						'max' => 100,
					],
				],
				'test_select'     => [
					'type'        => 'select',
					'label'       => 'Test Select',
					'description' => 'A test select field',
					'options'     => [
						''        => 'Select an option',
						'option1' => 'Option 1',
						'option2' => 'Option 2',
						'option3' => 'Option 3',
					],
					'default'     => '',
				],
			],
		];
	}
}
