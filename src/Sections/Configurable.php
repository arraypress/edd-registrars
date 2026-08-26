<?php
/**
 * Configurable Section Base Class for EDD
 *
 * @package ArrayPress/EDD-Utils
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Sections;

/**
 * Configurable Section Base Class
 *
 * Abstract base class for creating configurable sections in EDD download editor.
 * Provides a standard interface for defining section configuration including
 * fields, priority, supports, and rendering behavior.
 */
abstract class Configurable extends \EDD\Admin\Downloads\Editor\Section {

	/**
	 * Field renderer instance
	 *
	 * @var FieldRenderer
	 */
	protected FieldRenderer $field_renderer;

	/**
	 * Section configuration
	 *
	 * @var array
	 */
	protected array $config = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		// Set up the section configuration
		$this->config = $this->get_section_config();

		// Set properties from config
		$this->id       = $this->config['id'] ?? $this->id;
		$this->priority = $this->config['priority'] ?? $this->priority;
		$this->icon     = $this->config['icon'] ?? $this->icon;
		$this->supports = $this->config['supports'] ?? [];

		// Initialize field renderer
		$this->field_renderer = new FieldRenderer();

		parent::__construct();
	}

	/**
	 * Get section configuration - to be implemented by child classes
	 *
	 * This method should return an array with the following structure:
	 * [
	 *     'id' => 'section_id',
	 *     'title' => 'Section Title',
	 *     'priority' => 25,
	 *     'icon' => 'admin-tools',
	 *     'supports' => [],
	 *     'fields' => [
	 *         'field_id' => [
	 *             'type' => 'text',
	 *             'label' => 'Field Label',
	 *             'description' => 'Field description',
	 *             'default' => '',
	 *         ]
	 *     ]
	 * ]
	 *
	 * @return array Section configuration array
	 */
	abstract protected function get_section_config(): array;

	/**
	 * Render the section
	 *
	 * @return void
	 */
	public function render() {
		if ( ! $this->item || ! current_user_can( 'edit_product', $this->item->ID ) ) {
			return;
		}

		// Render fields using the renderer
		if ( isset( $this->config['fields'] ) && is_array( $this->config['fields'] ) ) {
			$this->field_renderer->render_fields( $this->config['fields'], $this->item->ID );
		}
	}

	/**
	 * Get section label from config
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->config['title'] ?? 'Custom Section';
	}
}
