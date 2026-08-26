<?php

/**
 * EDD Field Registration Functions
 *
 * This file contains helper functions for registering custom fields
 * with Easy Digital Downloads using the ArrayPress field registry system.
 *
 * @package ArrayPress/EDD-Utils
 * @since   2.0.0
 */

// return, not exit. This file is a Composer `files` autoload entry, so it runs
// whenever anything requires the autoloader -- phpunit, phpcs, a composer
// script. Ending the process there kills the tool with status 0 and no output,
// which reads as success: a lint that never looked at a file, or a test suite
// that never ran, both report as passing.
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

use ArrayPress\EDD\Register\Downloads\Settings;
use ArrayPress\EDD\Register\Downloads\SinglePriceOptions;
use ArrayPress\EDD\Register\Downloads\VariablePriceOptions;
use ArrayPress\EDD\Register\Downloads\SidebarMetaboxes;


if ( ! function_exists( 'edd_register_download_settings_fields' ) ) {
	/**
	 * Register custom fields for the download settings section
	 *
	 * This function allows you to easily add custom fields to the download
	 * settings tab in the EDD download editor. Fields are organized into
	 * sections and support EDD's native field types plus custom types.
	 *
	 * @param array $sections Array of section configurations
	 *                        Format: [
	 *                        'section_id' => [
	 *                        'title' => 'Section Title',
	 *                        'description' => 'Optional description',
	 *                        'priority' => 10,
	 *                        'fields' => [
	 *                        'field_id' => [
	 *                        'type' => 'text|textarea|select|checkbox|checkbox_toggle|number|currency|email|url|color|radio',
	 *                        'label' => 'Field Label',
	 *                        'description' => 'Field description',
	 *                        'default' => 'default_value',
	 *                        'options' => ['key' => 'value'], // For select/radio
	 *                        'attributes' => ['min' => 0], // HTML attributes
	 *                        'sanitize_callback' => 'sanitize_text_field'
	 *                        ]
	 *                        ]
	 *                        ]
	 *                        ]
	 *
	 * @return ArrayPress\EDD\Register\Downloads\Settings The registry instance
	 *
	 * @since 1.0.0
	 *
	 * @example
	 * edd_register_download_settings_fields([
	 *   'custom_settings' => [
	 *     'title' => 'Custom Settings',
	 *     'description' => 'Additional product settings',
	 *     'fields' => [
	 *       'is_featured' => [
	 *         'type' => 'checkbox_toggle',
	 *         'label' => 'Featured Product',
	 *         'description' => 'Mark as featured',
	 *         'default' => false
	 *       ]
	 *     ]
	 *   ]
	 * ]);
	 */
	function edd_register_download_settings_fields( array $sections ): Settings {
		$registry = new Settings();

		foreach ( $sections as $section_id => $section_config ) {
			$registry->register_section(
				$section_id,
				$section_config['title'],
				$section_config
			);

			foreach ( $section_config['fields'] as $field_id => $field_config ) {
				$registry->add_field_to_section( $section_id, $field_id, $field_config );
			}
		}

		$registry->init();

		return $registry;
	}
}

if ( ! function_exists( 'edd_register_single_price_fields' ) ) {
	/**
	 * Register custom fields for single price options
	 *
	 * This function allows you to add custom fields that appear when
	 * variable pricing is disabled. These fields are shown directly
	 * after the main price field and are perfect for single-price
	 * product options like license limits, support duration, etc.
	 *
	 * @param array $sections Array of section configurations
	 *                        Format: [
	 *                        'section_id' => [
	 *                        'fields' => [
	 *                        'field_id' => [
	 *                        'type' => 'checkbox_toggle|number|text|select|currency',
	 *                        'label' => 'Field Label',
	 *                        'description' => 'Field description',
	 *                        'default' => 'default_value',
	 *                        'prefix' => 'Max:', // Optional prefix
	 *                        'suffix' => 'sites' // Optional suffix
	 *                        ]
	 *                        ]
	 *                        ]
	 *                        ]
	 *
	 * @return ArrayPress\EDD\Register\Downloads\SinglePriceOptions The registry instance
	 *
	 * @since 1.0.0
	 *
	 * @example
	 * edd_register_single_price_fields([
	 *   'license_options' => [
	 *     'fields' => [
	 *       'site_limit' => [
	 *         'type' => 'number',
	 *         'label' => 'Site Limit',
	 *         'default' => 1,
	 *         'prefix' => 'Max:',
	 *         'suffix' => 'sites'
	 *       ],
	 *       'includes_support' => [
	 *         'type' => 'checkbox_toggle',
	 *         'label' => 'Includes Support',
	 *         'default' => true
	 *       ]
	 *     ]
	 *   ]
	 * ]);
	 */
	function edd_register_single_price_fields( array $sections ): SinglePriceOptions {
		$registry = new SinglePriceOptions();

		foreach ( $sections as $section_id => $section_config ) {
			$registry->register_section( $section_id, $section_config );

			foreach ( $section_config['fields'] as $field_id => $field_config ) {
				$registry->add_field_to_section( $section_id, $field_id, $field_config );
			}
		}

		$registry->init();

		return $registry;
	}
}

if ( ! function_exists( 'edd_register_variable_price_fields' ) ) {
	/**
	 * Register custom fields for variable price options
	 *
	 * This function allows you to add custom fields to each variable
	 * price row when variable pricing is enabled. Perfect for per-variation
	 * settings like license limits, support levels, renewal discounts, etc.
	 *
	 * @param array $sections Array of section configurations
	 *                        Format: [
	 *                        'section_id' => [
	 *                        'title' => 'Section Title',
	 *                        'fields' => [
	 *                        'field_id' => [
	 *                        'type' => 'number|checkbox_toggle|select|text',
	 *                        'label' => 'Field Label',
	 *                        'default' => 'default_value',
	 *                        'prefix' => 'Max:', // Optional prefix
	 *                        'suffix' => 'sites' // Optional suffix
	 *                        ]
	 *                        ]
	 *                        ]
	 *                        ]
	 *
	 * @return ArrayPress\EDD\Register\Downloads\VariablePriceOptions The registry instance
	 *
	 * @since 1.0.0
	 *
	 * @example
	 * edd_register_variable_price_fields([
	 *   'license_tiers' => [
	 *     'title' => 'License Tiers',
	 *     'fields' => [
	 *       'license_limit' => [
	 *         'type' => 'number',
	 *         'label' => 'Site Limit',
	 *         'default' => 1
	 *       ],
	 *       'is_popular' => [
	 *         'type' => 'checkbox_toggle',
	 *         'label' => 'Popular Option',
	 *         'default' => false
	 *       ]
	 *     ]
	 *   ]
	 * ]);
	 */
	function edd_register_variable_price_fields( array $sections ): VariablePriceOptions {
		$registry = new VariablePriceOptions();

		foreach ( $sections as $section_id => $section_config ) {
			$registry->register_section(
				$section_id,
				$section_config['title'],
				$section_config
			);

			foreach ( $section_config['fields'] as $field_id => $field_config ) {
				$registry->add_field_to_section( $section_id, $field_id, $field_config );
			}
		}

		$registry->init();

		return $registry;
	}
}

if ( ! function_exists( 'edd_register_sidebar_metaboxes' ) ) {
	/**
	 * Register custom sidebar metaboxes for downloads
	 *
	 * This function allows you to create custom metaboxes that appear
	 * in the sidebar of the download editor. Perfect for additional
	 * settings, SEO options, analytics configuration, etc.
	 *
	 * @param array $metaboxes Array of metabox configurations
	 *                         Format: [
	 *                         'metabox_id' => [
	 *                         'title' => 'Metabox Title',
	 *                         'post_type' => 'download',
	 *                         'priority' => 'high|default|low',
	 *                         'sections' => [
	 *                         'section_id' => [
	 *                         'title' => 'Section Title',
	 *                         'description' => 'Optional description',
	 *                         'fields' => [
	 *                         'field_id' => [
	 *                         'type' => 'text|textarea|select|checkbox_toggle|url|email',
	 *                         'label' => 'Field Label',
	 *                         'description' => 'Field description',
	 *                         'default' => 'default_value'
	 *                         ]
	 *                         ]
	 *                         ]
	 *                         ]
	 *                         ]
	 *                         ]
	 *
	 * @return ArrayPress\EDD\Register\Downloads\SidebarMetaboxes The registry instance
	 *
	 * @since 1.0.0
	 *
	 * @example
	 * edd_register_sidebar_metaboxes([
	 *   'seo_settings' => [
	 *     'title' => 'SEO Settings',
	 *     'priority' => 'high',
	 *     'sections' => [
	 *       'meta_options' => [
	 *         'title' => 'Meta Options',
	 *         'fields' => [
	 *           'meta_title' => [
	 *             'type' => 'text',
	 *             'label' => 'Custom Meta Title',
	 *             'class' => 'widefat'
	 *           ],
	 *           'exclude_sitemap' => [
	 *             'type' => 'checkbox_toggle',
	 *             'label' => 'Exclude from Sitemap',
	 *             'default' => false
	 *           ]
	 *         ]
	 *       ]
	 *     ]
	 *   ]
	 * ]);
	 */
	function edd_register_sidebar_metaboxes( array $metaboxes ): SidebarMetaboxes {
		$registry = new SidebarMetaboxes();

		foreach ( $metaboxes as $metabox_id => $metabox_config ) {
			$registry->register_metabox(
				$metabox_id,
				$metabox_config['title'],
				$metabox_config
			);

			foreach ( $metabox_config['sections'] as $section_id => $section_config ) {
				$registry->register_section(
					$metabox_id,
					$section_id,
					$section_config['title'],
					$section_config
				);

				foreach ( $section_config['fields'] as $field_id => $field_config ) {
					$registry->add_field_to_section( $metabox_id, $section_id, $field_id, $field_config );
				}
			}
		}

		$registry->init();

		return $registry;
	}
}

if ( ! function_exists( 'edd_register_sections' ) ) {
	/**
	 * Register custom sections for EDD download details
	 *
	 * Helper function to register custom sections that appear in the
	 * download editor. These sections can contain custom fields and
	 * are integrated with EDD's section system.
	 *
	 * @param array $sections Array of section class names keyed by section ID
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @example
	 * edd_register_sections([
	 *   'test_section' => TestSection::class,
	 *   'custom_section' => CustomSection::class,
	 * ]);
	 */
	function edd_register_sections( array $sections ): void {
		add_filter( 'edd_download_details_sections', function ( $existing_sections ) use ( $sections ) {
			return array_merge( $existing_sections, $sections );
		} );
	}
}
