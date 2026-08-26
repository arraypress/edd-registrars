<?php
/**
 * Sidebar Metaboxes Field Registry for Easy Digital Downloads
 *
 * @package ArrayPress/EDD-Utils
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register\Downloads;

use ArrayPress\EDD\Register\Abstracts\BaseFieldRegistry;

/**
 * Sidebar Metaboxes Field Registry
 *
 * Handles registration and rendering of custom metaboxes that appear
 * in the sidebar of the download editor. Perfect for additional
 * settings, SEO options, analytics configuration, etc.
 */
class SidebarMetaboxes extends BaseFieldRegistry {

	/**
	 * Registered metaboxes
	 *
	 * @var array
	 */
	protected array $metaboxes = [];

	/**
	 * Initialize the registry
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_filter( 'edd_metabox_fields_save', [ $this, 'register_save_fields' ] );
		add_action( 'admin_footer', [ $this, 'add_inline_css' ] );
	}

	/**
	 * Register a metabox
	 *
	 * @param string $id     Metabox ID
	 * @param string $title  Metabox title
	 * @param array  $config Metabox configuration
	 *
	 * @return bool
	 */
	public function register_metabox( string $id, string $title, array $config = [] ): bool {
		$defaults = [
			'post_type' => 'download',
			'priority'  => 'default',
			'sections'  => [],
		];

		$this->metaboxes[ $id ]          = wp_parse_args( $config, $defaults );
		$this->metaboxes[ $id ]['title'] = $title;

		return true;
	}

	/**
	 * Register a section within a metabox
	 *
	 * @param string $metabox_id Metabox ID
	 * @param string $section_id Section ID
	 * @param string $title      Section title
	 * @param array  $config     Section configuration
	 *
	 * @return bool
	 */
	public function register_section( string $metabox_id, string $section_id, string $title, array $config = [] ): bool {
		if ( ! isset( $this->metaboxes[ $metabox_id ] ) ) {
			return false;
		}

		$defaults = [
			'title'       => $title,
			'description' => '',
			'fields'      => [],
		];

		$this->metaboxes[ $metabox_id ]['sections'][ $section_id ] = wp_parse_args( $config, $defaults );

		return true;
	}

	/**
	 * Add field to a section within a metabox
	 *
	 * @param string $metabox_id   Metabox ID
	 * @param string $section_id   Section ID
	 * @param string $field_id     Field ID
	 * @param array  $field_config Field configuration
	 *
	 * @return bool
	 */
	public function add_field_to_section( string $metabox_id, string $section_id, string $field_id, array $field_config ): bool {
		if ( ! isset( $this->metaboxes[ $metabox_id ]['sections'][ $section_id ] ) ) {
			return false;
		}

		$this->register_field( $field_id, $field_config );
		$this->metaboxes[ $metabox_id ]['sections'][ $section_id ]['fields'][] = $field_id;

		return true;
	}

	/**
	 * Add meta boxes to WordPress
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		foreach ( $this->metaboxes as $id => $metabox ) {
			add_meta_box(
				$id,
				$metabox['title'],
				[ $this, 'render_metabox' ],
				$metabox['post_type'],
				'side',
				$metabox['priority'],
				[ 'metabox_id' => $id ]
			);
		}
	}

	/**
	 * Render a metabox
	 *
	 * @param \WP_Post $post    Post object
	 * @param array    $metabox Metabox arguments
	 *
	 * @return void
	 */
	public function render_metabox( $post, $metabox ): void {
		$metabox_id   = $metabox['args']['metabox_id'];
		$metabox_data = $this->metaboxes[ $metabox_id ];

		foreach ( $metabox_data['sections'] as $section_id => $section ) {
			$this->render_section( $section_id, $section, $post->ID );
		}
	}

	/**
	 * Render a section
	 *
	 * @param string $section_id Section ID
	 * @param array  $section    Section configuration
	 * @param int    $post_id    Post ID
	 *
	 * @return void
	 */
	private function render_section( string $section_id, array $section, int $post_id ): void {
		?>
        <div class="edd-form-group edd-product-options-wrapper">
            <div class="edd-product-options__title">
				<?php echo esc_html( $section['title'] ); ?>
            </div>
			<?php if ( ! empty( $section['description'] ) ) : ?>
                <p class="description"><?php echo wp_kses_post( $section['description'] ); ?></p>
			<?php endif; ?>

			<?php if ( isset( $section['fields'] ) && is_array( $section['fields'] ) ) : ?>
				<?php foreach ( $section['fields'] as $field_data ) : ?>
					<?php
					// Handle both string field IDs and array field data
					if ( is_string( $field_data ) ) {
						$field_id = $field_data;
					} elseif ( is_array( $field_data ) && isset( $field_data[0] ) && is_string( $field_data[0] ) ) {
						$field_id = $field_data[0];
					} else {
						continue; // Skip invalid field data
					}
					?>
					<?php $this->render_sidebar_field( $field_id, $post_id ); ?>
				<?php endforeach; ?>
			<?php endif; ?>
        </div>
		<?php
	}

	/**
	 * Render sidebar field
	 *
	 * @param string $field_id Field ID
	 * @param int    $post_id  Post ID
	 *
	 * @return void
	 */
	private function render_sidebar_field( string $field_id, int $post_id ): void {
		if ( ! isset( $this->fields[ $field_id ] ) ) {
			return;
		}

		$field = $this->fields[ $field_id ];
		$value = $this->get_field_value( $post_id, $field_id );

		?>
        <div class="edd-form-group__control">
			<?php if ( $field['type'] !== 'checkbox' && $field['type'] !== 'checkbox_toggle' ) : ?>
                <label for="<?php echo esc_attr( $field_id ); ?>" class="edd-form-group__label">
					<?php echo esc_html( $field['label'] ); ?>
					<?php if ( $field['required'] ) : ?>
                        <span class="required">*</span>
					<?php endif; ?>
                </label>
			<?php endif; ?>

			<?php
            echo $this->render_field( $field_id, $value, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the renderers escape every value and attribute they emit; escaping the assembled markup again would print the tags.
			?>

			<?php if ( $field['type'] === 'checkbox' ) : ?>
                <label class="edd-form-group__label" for="<?php echo esc_attr( $field_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php if ( $field['required'] ) : ?>
                        <span class="required">*</span>
					<?php endif; ?>
                </label>
			<?php endif; ?>

			<?php if ( ! empty( $field['description'] ) ) : ?>
                <p class="edd-form-group__help description"><?php echo wp_kses_post( $field['description'] ); ?></p>
			<?php endif; ?>
        </div>
		<?php
	}

	/**
	 * Register fields for saving
	 *
	 * @param array $fields Current save fields
	 *
	 * @return array
	 */
	public function register_save_fields( array $fields ): array {
		return array_merge( $fields, array_keys( $this->fields ) );
	}

	/**
	 * Add inline CSS for styling
	 *
	 * @return void
	 */
	public function add_inline_css(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || $current_screen->post_type !== 'download' ) {
			return;
		}

		?>
        <style type="text/css">
            .edd-product-options__title {
                border-top: 1px solid #c3c4c7;
                border-bottom: 1px solid #c3c4c7;
                background-color: #f9f9f9;
                display: flex;
                font-weight: 600;
                margin: 0 -20px 16px;
                padding: 8px 12px;
                justify-content: space-between;
                align-items: center;
            }
        </style>
		<?php
	}
}