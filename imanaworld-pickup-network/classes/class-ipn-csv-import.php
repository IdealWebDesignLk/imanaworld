<?php
defined( 'ABSPATH' ) || exit;

/**
 * CSV/Excel catalogue import (Section 3.3). Creates/updates Choppies Dokan
 * products by SKU from an uploaded file, and writes per-branch stock via
 * IPN_Branch_Stock. Built against IPN's own defined column format (no real
 * Choppies sample file exists yet — see IPN_CSV_Import_Specification.md,
 * which is what gets handed to the client so they prepare their real file
 * against it) rather than blocking on one.
 *
 * One row = one (SKU x Branch) stock line, so a product sold at three
 * branches needs three rows. Product-level fields (name, price, etc.) are
 * only required the first time a SKU appears; the product is created once,
 * every subsequent row for that SKU just sets stock at another branch.
 *
 * XLSX is read with a minimal native reader (ZipArchive + SimpleXML) —
 * first sheet, shared strings and inline strings, cached cell values only.
 * No PhpSpreadsheet/Composer dependency. Good enough for a flat catalogue
 * export; anything with formulas, multiple sheets, or exotic formatting
 * should be saved as CSV instead.
 */
class IPN_CSV_Import {

	const REQUIRED_COLUMNS = array( 'sku', 'branch code', 'stock quantity' );

	public static function log_table() {
		return IPN_Install::table( 'import_log' );
	}

	public static function log_rows_table() {
		return IPN_Install::table( 'import_log_rows' );
	}

	/**
	 * @param string $file_path Path to the uploaded CSV/XLSX file.
	 * @param int    $run_by    User ID that triggered the import.
	 * @return int|WP_Error Import log ID on success.
	 */
	public static function process_file( $file_path, $run_by = 0 ) {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		if ( 'csv' === $extension ) {
			$rows = self::parse_csv( $file_path );
		} elseif ( 'xlsx' === $extension ) {
			$rows = self::parse_xlsx( $file_path );
		} else {
			return new WP_Error( 'ipn_unsupported_file', __( 'Please upload a .csv or .xlsx file.', 'ipn' ) );
		}

		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		if ( ! $rows ) {
			return new WP_Error( 'ipn_empty_file', __( 'The file has no data rows, or its header row is missing required columns (SKU, Branch Code, Stock Quantity).', 'ipn' ) );
		}

		global $wpdb;
		$log_id = self::start_log( basename( $file_path ), count( $rows ), $run_by );
		$counts = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'failed'  => 0,
		);

		foreach ( $rows as $i => $row ) {
			$result             = self::process_row( $row );
			$counts[ $result['status'] ]++;

			$wpdb->insert( self::log_rows_table(), array( // phpcs:ignore WordPress.DB.PreparedSQL
				'import_id'  => $log_id,
				'row_number' => $i + 2, // +1 for zero-index, +1 for the header row.
				'sku'        => isset( $row['sku'] ) ? substr( $row['sku'], 0, 100 ) : '',
				'status'     => $result['status'],
				'message'    => substr( $result['message'], 0, 255 ),
			) );
		}

		self::finish_log( $log_id, $counts );

		return $log_id;
	}

	/**
	 * Creates or updates one product and sets its stock at one branch.
	 */
	protected static function process_row( array $row ) {
		$sku       = isset( $row['sku'] ) ? sanitize_text_field( $row['sku'] ) : '';
		$branch_code = isset( $row['branch code'] ) ? sanitize_text_field( $row['branch code'] ) : '';
		$stock_qty = isset( $row['stock quantity'] ) && '' !== $row['stock quantity'] ? (int) $row['stock quantity'] : null;

		if ( '' === $sku ) {
			return self::row_result( 'failed', __( 'Missing SKU.', 'ipn' ) );
		}

		if ( '' === $branch_code ) {
			return self::row_result( 'failed', __( 'Missing Branch Code.', 'ipn' ) );
		}

		if ( null === $stock_qty || $stock_qty < 0 ) {
			return self::row_result( 'failed', __( 'Missing or invalid Stock Quantity (must be a whole number, 0 or more).', 'ipn' ) );
		}

		$branch = IPN_Branch::get_by_code( $branch_code );

		if ( ! $branch ) {
			/* translators: %s: branch code from the import file */
			return self::row_result( 'failed', sprintf( __( 'Unknown Branch Code "%s" — check IPN > Branches for valid codes.', 'ipn' ), $branch_code ) );
		}

		if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
			return self::row_result( 'failed', __( 'WooCommerce is not active.', 'ipn' ) );
		}

		$product_id = wc_get_product_id_by_sku( $sku );
		$is_new     = ! $product_id;

		if ( $is_new ) {
			$name  = isset( $row['product name'] ) ? sanitize_text_field( $row['product name'] ) : '';
			$price = isset( $row['regular price'] ) ? trim( $row['regular price'] ) : '';

			if ( '' === $name ) {
				return self::row_result( 'failed', __( 'New SKU needs a Product Name.', 'ipn' ) );
			}

			if ( '' === $price || ! is_numeric( $price ) ) {
				return self::row_result( 'failed', __( 'New SKU needs a numeric Regular Price.', 'ipn' ) );
			}

			$product = new WC_Product_Simple();
			$product->set_sku( $sku );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'visible' );
			// IPN's per-branch stock table is the source of truth for
			// IPN-enabled vendor products — WooCommerce's own stock fields
			// are left unmanaged rather than set to a single global number.
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );
			$product->set_name( $name );
			$product->set_regular_price( (string) floatval( $price ) );
		} else {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				return self::row_result( 'failed', __( 'Existing SKU could not be loaded as a product.', 'ipn' ) );
			}

			if ( ! empty( $row['product name'] ) ) {
				$product->set_name( sanitize_text_field( $row['product name'] ) );
			}

			if ( isset( $row['regular price'] ) && '' !== $row['regular price'] && is_numeric( $row['regular price'] ) ) {
				$product->set_regular_price( (string) floatval( $row['regular price'] ) );
			}
		}

		if ( ! empty( $row['description'] ) ) {
			$product->set_description( wp_kses_post( $row['description'] ) );
		}

		if ( isset( $row['sale price'] ) && '' !== $row['sale price'] ) {
			$product->set_sale_price( is_numeric( $row['sale price'] ) ? (string) floatval( $row['sale price'] ) : '' );
		}

		$product_id = $product->save();

		if ( ! $product_id ) {
			return self::row_result( 'failed', __( 'Could not save the product.', 'ipn' ) );
		}

		if ( $is_new ) {
			wp_update_post( array(
				'ID'          => $product_id,
				'post_author' => (int) $branch->vendor_id,
			) );

			if ( ! empty( $row['category'] ) ) {
				self::assign_categories( $product_id, $row['category'] );
			}

			if ( ! empty( $row['image url'] ) ) {
				self::sideload_image( $product_id, $row['image url'] );
			}
		}

		IPN_Branch_Stock::set_total( $product_id, $branch->id, $stock_qty );

		return $is_new
			? self::row_result( 'created', __( 'Product created and stock set.', 'ipn' ) )
			: self::row_result( 'updated', __( 'Product updated and stock set.', 'ipn' ) );
	}

	protected static function row_result( $status, $message ) {
		return array(
			'status'  => $status,
			'message' => $message,
		);
	}

	protected static function assign_categories( $product_id, $category_string ) {
		$names    = array_filter( array_map( 'trim', explode( ',', $category_string ) ) );
		$term_ids = array();

		foreach ( $names as $name ) {
			$term = term_exists( $name, 'product_cat' );

			if ( ! $term ) {
				$term = wp_insert_term( $name, 'product_cat' );
			}

			if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
				$term_ids[] = (int) $term['term_id'];
			}
		}

		if ( $term_ids ) {
			wp_set_object_terms( $product_id, $term_ids, 'product_cat' );
		}
	}

	protected static function sideload_image( $product_id, $url ) {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = media_sideload_image( esc_url_raw( $url ), $product_id, null, 'id' );

		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $product_id, $attachment_id );
		}
	}

	protected static function start_log( $filename, $total_rows, $run_by ) {
		global $wpdb;

		$wpdb->insert( self::log_table(), array( // phpcs:ignore WordPress.DB.PreparedSQL
			'filename'   => $filename,
			'total_rows' => $total_rows,
			'run_by'     => $run_by ? $run_by : null,
			'created_at' => current_time( 'mysql' ),
		) );

		return (int) $wpdb->insert_id;
	}

	protected static function finish_log( $log_id, array $counts ) {
		global $wpdb;

		$wpdb->update( self::log_table(), array( // phpcs:ignore WordPress.DB.PreparedSQL
			'created_count' => $counts['created'],
			'updated_count' => $counts['updated'],
			'skipped_count' => $counts['skipped'],
			'failed_count'  => $counts['failed'],
		), array( 'id' => $log_id ) );
	}

	public static function get_recent_runs( $limit = 20 ) {
		global $wpdb;
		$table = self::log_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function get_failed_rows( $import_id ) {
		global $wpdb;
		$table = self::log_rows_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE import_id = %d AND status = 'failed' ORDER BY row_number ASC", $import_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	// ---- file parsing ----

	/**
	 * @return array[]|WP_Error Array of header-keyed rows (lowercased header names).
	 */
	protected static function parse_csv( $file_path ) {
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		if ( ! $handle ) {
			return new WP_Error( 'ipn_csv_unreadable', __( 'Could not open the CSV file.', 'ipn' ) );
		}

		$header_line = fgetcsv( $handle );

		if ( ! $header_line ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
			return array();
		}

		// Excel-exported CSVs commonly prefix the first cell with a UTF-8 BOM.
		$header_line[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header_line[0] );
		$header         = self::normalize_header( $header_line );

		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
			return array();
		}

		$rows = array();

		while ( ( $line = fgetcsv( $handle ) ) !== false ) {
			if ( 1 === count( $line ) && ( null === $line[0] || '' === trim( (string) $line[0] ) ) ) {
				continue; // Blank line.
			}

			$rows[] = self::map_row( $header, $line );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		return $rows;
	}

	/**
	 * Minimal XLSX reader: first sheet only, shared strings + inline
	 * strings, cached cell values (<v>) rather than re-evaluating formulas.
	 * Sufficient for a flat catalogue export; not a full spreadsheet engine.
	 *
	 * @return array[]|WP_Error
	 */
	protected static function parse_xlsx( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'ipn_xlsx_unsupported', __( 'XLSX import requires the PHP zip extension, which is not available on this server. Please save the file as CSV and upload that instead.', 'ipn' ) );
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $file_path ) ) {
			return new WP_Error( 'ipn_xlsx_unreadable', __( 'Could not open the XLSX file — it may be corrupted.', 'ipn' ) );
		}

		$shared_strings = array();
		$shared_xml     = $zip->getFromName( 'xl/sharedStrings.xml' );

		if ( $shared_xml ) {
			$xml = simplexml_load_string( $shared_xml );

			if ( $xml ) {
				foreach ( $xml->si as $si ) {
					if ( isset( $si->t ) ) {
						$shared_strings[] = (string) $si->t;
					} else {
						$parts = array();
						foreach ( $si->r as $run ) {
							$parts[] = (string) $run->t;
						}
						$shared_strings[] = implode( '', $parts );
					}
				}
			}
		}

		$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		$zip->close();

		if ( ! $sheet_xml ) {
			return new WP_Error( 'ipn_xlsx_empty', __( 'No worksheet found in the XLSX file.', 'ipn' ) );
		}

		$xml = simplexml_load_string( $sheet_xml );

		if ( ! $xml || ! isset( $xml->sheetData ) ) {
			return new WP_Error( 'ipn_xlsx_parse_error', __( 'Could not parse the XLSX worksheet.', 'ipn' ) );
		}

		$sheet_rows = array();

		foreach ( $xml->sheetData->row as $row_xml ) {
			$cells = array();

			foreach ( $row_xml->c as $cell ) {
				$ref        = (string) $cell['r'];
				$col_letter = preg_replace( '/[0-9]/', '', $ref );
				$col_index  = self::column_letters_to_index( $col_letter );
				$type       = (string) $cell['t'];

				if ( 's' === $type ) {
					$idx           = isset( $cell->v ) ? (int) $cell->v : -1;
					$cells[ $col_index ] = isset( $shared_strings[ $idx ] ) ? $shared_strings[ $idx ] : '';
				} elseif ( 'inlineStr' === $type ) {
					$cells[ $col_index ] = isset( $cell->is->t ) ? (string) $cell->is->t : '';
				} else {
					$cells[ $col_index ] = isset( $cell->v ) ? (string) $cell->v : '';
				}
			}

			$sheet_rows[] = $cells;
		}

		if ( ! $sheet_rows ) {
			return array();
		}

		$header_cells = array_shift( $sheet_rows );

		if ( ! $header_cells ) {
			return array();
		}

		$max_col = max( array_keys( $header_cells ) );
		$header  = array();

		for ( $i = 0; $i <= $max_col; $i++ ) {
			$header[] = isset( $header_cells[ $i ] ) ? $header_cells[ $i ] : '';
		}

		$header = self::normalize_header( $header );

		if ( ! $header ) {
			return array();
		}

		$rows = array();

		foreach ( $sheet_rows as $cells ) {
			$line = array();
			for ( $i = 0; $i <= $max_col; $i++ ) {
				$line[] = isset( $cells[ $i ] ) ? $cells[ $i ] : '';
			}

			if ( ! array_filter( $line, function ( $v ) {
				return '' !== trim( (string) $v );
			} ) ) {
				continue; // Blank row.
			}

			$rows[] = self::map_row( $header, $line );
		}

		return $rows;
	}

	protected static function column_letters_to_index( $letters ) {
		$letters = strtoupper( $letters );
		$index   = 0;

		for ( $i = 0; $i < strlen( $letters ); $i++ ) {
			$index = $index * 26 + ( ord( $letters[ $i ] ) - 64 );
		}

		return $index - 1;
	}

	/**
	 * Lowercases/trims header cells and confirms every required column
	 * (see REQUIRED_COLUMNS) is present. Returns an empty array — treated
	 * upstream as "no data" — if the header is missing something required,
	 * so a malformed file fails fast with one clear error rather than 500
	 * confusing per-row failures.
	 */
	protected static function normalize_header( array $header_line ) {
		$header = array_map( function ( $cell ) {
			return strtolower( trim( (string) $cell ) );
		}, $header_line );

		foreach ( self::REQUIRED_COLUMNS as $required ) {
			if ( ! in_array( $required, $header, true ) ) {
				return array();
			}
		}

		return $header;
	}

	protected static function map_row( array $header, array $line ) {
		$row = array();

		foreach ( $header as $i => $key ) {
			if ( '' === $key ) {
				continue;
			}
			$row[ $key ] = isset( $line[ $i ] ) ? trim( (string) $line[ $i ] ) : '';
		}

		return $row;
	}
}
