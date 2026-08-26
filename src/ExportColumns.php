<?php
/**
 * Export Columns Registry for Easy Digital Downloads
 *
 * @package     ArrayPress\EDD\Register
 * @copyright   Copyright 2024, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EDD\Register;

use Error;
use Exception;
use WP_Error;

class ExportColumns {

	/**
	 * The type of CSV export (e.g., 'downloads', 'customers', etc.).
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * An associative array of custom columns with their configurations.
	 *
	 * @var array
	 */
	private array $columns;

	/**
	 * The field to use as the identifier for each row in the export data.
	 *
	 * @var string
	 */
	private string $id_field;

	/**
	 * Constructor.
	 *
	 * @param string $type     The type of CSV export.
	 * @param array  $columns  An associative array of custom columns.
	 * @param string $id_field The field to use as the identifier.
	 */
	public function __construct( string $type, array $columns = [], string $id_field = 'ID' ) {
		$this->type     = $type;
		$this->columns  = $columns;
		$this->id_field = $id_field;
	}

	/**
	 * Register export columns.
	 *
	 * @param array $columns An associative array of custom columns.
	 *
	 * @return WP_Error|true
	 */
	public function register( array $columns ) {
		if ( empty( $this->type ) ) {
			return new WP_Error(
				'invalid_type',
				__( 'Export type cannot be empty.', 'arraypress' )
			);
		}

		if ( empty( $columns ) ) {
			return new WP_Error(
				'empty_columns',
				__( 'Columns array cannot be empty.', 'arraypress' )
			);
		}

		// Validate columns
		foreach ( $columns as $key => $column ) {
			if ( empty( $key ) ) {
				return new WP_Error(
					'invalid_column_key',
					__( 'Column key cannot be empty.', 'arraypress' )
				);
			}

			if ( ! isset( $column['label'] ) ) {
				return new WP_Error(
					'missing_column_label',
					/* translators: %s: the column key, e.g. "notes". */
					sprintf( __( 'Column "%s" must have a label.', 'arraypress' ), $key )
				);
			}

			if ( ! isset( $column['callback'] ) || ! is_callable( $column['callback'] ) ) {
				return new WP_Error(
					'invalid_column_callback',
					/* translators: %s: the column key, e.g. "notes". */
					sprintf( __( 'Column "%s" must have a valid callback.', 'arraypress' ), $key )
				);
			}
		}

		$this->columns = array_merge( $this->columns, $columns );

		edd_debug_log( sprintf( '[EDD Export Columns] Registered %d export columns for type: %s', count( $columns ), $this->type ) );

		return true;
	}

	/**
	 * Initialize the export columns registration.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( empty( $this->columns ) ) {
			return;
		}

		add_filter( "edd_export_csv_cols_{$this->type}", [ $this, 'register_csv_columns' ] );
		add_filter( "edd_export_get_data_{$this->type}", [ $this, 'filter_csv_data' ] );

		edd_debug_log( sprintf( '[EDD Export Columns] Initialized export columns for type: %s', $this->type ) );
	}

	/**
	 * Register custom columns for the CSV export.
	 *
	 * @param array $cols An array of existing columns for the CSV export.
	 *
	 * @return array An updated array of columns with the custom columns added.
	 */
	public function register_csv_columns( array $cols ): array {
		foreach ( $this->columns as $key => $column ) {
			$cols[ $key ] = $column['label'] ?? '';
		}

		return $cols;
	}

	/**
	 * Filter the CSV data to add custom columns.
	 *
	 * @param array $data The data for the CSV export.
	 *
	 * @return array The modified data for the CSV export.
	 */
	public function filter_csv_data( array $data ): array {
		foreach ( $data as $key => $row ) {
			$id = $this->get_row_id( $row );
			foreach ( $this->columns as $column_key => $column ) {
				if ( isset( $column['callback'] ) && is_callable( $column['callback'] ) ) {
					try {
						$data[ $key ][ $column_key ] = call_user_func( $column['callback'], $id );
					} catch ( Exception $e ) {
						edd_debug_log_exception( $e );
						$data[ $key ][ $column_key ] = '';
					} catch ( Error $e ) {
						edd_debug_log( sprintf( '[EDD Export Columns] Fatal error in column callback for %s: %s', $column_key, $e->getMessage() ), true );
						$data[ $key ][ $column_key ] = '';
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Get the ID value from a row.
	 *
	 * @param array $row The row data.
	 *
	 * @return mixed The ID value.
	 */
	private function get_row_id( array $row ) {
		if ( isset( $row[ $this->id_field ] ) ) {
			return $row[ $this->id_field ];
		}

		// Check for 'ID' if the specified field doesn't exist
		if ( $this->id_field !== 'ID' && isset( $row['ID'] ) ) {
			return $row['ID'];
		}

		// Check for 'id' if neither specified field nor 'ID' exist
		if ( $this->id_field !== 'id' && isset( $row['id'] ) ) {
			return $row['id'];
		}

		return null;
	}
}
