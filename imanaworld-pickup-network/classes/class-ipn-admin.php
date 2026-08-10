<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level "IPN" wp-admin menu and its submenus (Dashboard,
 * Partners, Branches, Staff, Stock, Catalogue Import, Orders, Disputes &
 * Returns, Daily Digest, Audit Trail, Reports, Settings). Each submenu
 * renders a template under templates/admin/ with real data where a backing
 * data model already exists; screens with no data model yet (Disputes,
 * Digest, most of Reports) render an honest empty state instead.
 */
class IPN_Admin {

	public function register_hooks( IPN_Loader $loader ) {
		$loader->add_action( 'admin_menu', $this, 'register_menu' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );
		$loader->add_filter( 'woocommerce_order_actions', $this, 'add_resend_otp_order_action', 10, 2 );
		$loader->add_action( 'woocommerce_order_action_ipn_resend_otp', $this, 'handle_resend_otp_order_action' );
		$loader->add_action( 'admin_post_ipn_download_import_template', $this, 'download_import_template' );
		$loader->add_action( 'admin_post_ipn_export_reports', $this, 'export_reports' );
		$loader->add_action( 'admin_post_ipn_export_audit_log', $this, 'export_audit_log' );
		$loader->add_action( 'admin_post_ipn_preview_digest_email', $this, 'preview_digest_email' );
	}

	/**
	 * Shows exactly what the scheduled daily digest email would send,
	 * without actually sending it — opens in a new tab as plain text.
	 */
	public function preview_digest_email() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'ipn' ) );
		}

		check_admin_referer( 'ipn_preview_digest_email' );

		$content = IPN_Notifications::build_digest_content();

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo "Subject: {$content['subject']}\n\n{$content['body']}"; // phpcs:ignore WordPress.Security.EscapeOutput -- text/plain response, not rendered as HTML.

		exit;
	}

	/**
	 * Adds "Resend IPN collection code" to the Order Actions dropdown on the
	 * wp-admin edit-order screen, but only for orders that actually have a
	 * branch selected — plain (non-Click & Collect) orders don't get it.
	 */
	public function add_resend_otp_order_action( $actions, $order = null ) {
		$order = is_a( $order, 'WC_Order' ) ? $order : ( $order ? wc_get_order( $order ) : null );

		if ( ! $order ) {
			return $actions;
		}

		$meta = IPN_Order::get_meta( $order->get_id() );

		if ( $meta && $meta->branch_id ) {
			$actions['ipn_resend_otp'] = __( 'Resend IPN collection code', 'ipn' );
		}

		return $actions;
	}

	public function handle_resend_otp_order_action( $order ) {
		IPN_Notifications::resend( $order->get_id() );
	}

	public function register_menu() {
		$capability = 'manage_woocommerce';

		add_menu_page(
			__( 'IPN', 'ipn' ),
			__( 'IPN', 'ipn' ),
			$capability,
			'ipn-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-store',
			56
		);

		$submenus = array(
			'ipn-dashboard'   => __( 'Dashboard', 'ipn' ),
			'ipn-partners'    => __( 'Partners', 'ipn' ),
			'ipn-branches'    => __( 'Branches', 'ipn' ),
			'ipn-staff'       => __( 'Staff', 'ipn' ),
			'ipn-stock'       => __( 'Stock', 'ipn' ),
			'ipn-import'      => __( 'Catalogue Import', 'ipn' ),
			'ipn-orders'      => __( 'Orders & Disputes', 'ipn' ),
			'ipn-disputes'    => __( 'Disputes & Returns', 'ipn' ),
			'ipn-digest'      => __( 'Daily Digest', 'ipn' ),
			'ipn-audit-log'   => __( 'Audit Trail', 'ipn' ),
			'ipn-reports'     => __( 'Reports', 'ipn' ),
			'ipn-settings'    => __( 'Settings', 'ipn' ),
		);

		foreach ( $submenus as $slug => $label ) {
			$callback = 'render_' . str_replace( array( 'ipn-', '-' ), array( '', '_' ), $slug );

			add_submenu_page(
				'ipn-dashboard',
				$label,
				$label,
				$capability,
				$slug,
				array( $this, $callback )
			);
		}
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'ipn-' ) === false && 'toplevel_page_ipn-dashboard' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'ipn-admin', IPN_PLUGIN_URL . 'assets/css/admin.css', array(), IPN_VERSION );
		wp_enqueue_script( 'ipn-admin', IPN_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), IPN_VERSION, true );
	}

	protected function view( $template, array $vars = array() ) {
		extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract
		include IPN_PLUGIN_DIR . 'templates/admin/' . $template . '.php';
	}

	public function render_dashboard() {
		$this->view( 'dashboard' );
	}

	public function render_partners() {
		$this->view( 'partners' );
	}

	public function render_branches() {
		$this->maybe_handle_closure_actions();
		$save_result = $this->maybe_handle_branch_save();

		$this->view( 'branches', array(
			'branches'    => IPN_Branch::get_all(),
			'vendors'     => $this->get_dokan_vendors(),
			'save_result' => $save_result,
		) );
	}

	/**
	 * One-off closure dates (public holidays etc.), managed from their own
	 * modal on the Branches screen — kept out of the branch add/edit
	 * form so its per-row "Delete" actions don't end up as forms nested
	 * inside that form (invalid HTML). Delete follows the same GET+nonce
	 * pattern WP's own list-table row actions use, since it's a capability-
	 * gated idempotent action, not a state-changing form with real fields.
	 */
	protected function maybe_handle_closure_actions() {
		if ( isset( $_GET['ipn_delete_closure'], $_GET['_wpnonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ipn_delete_closure' ) ) {
			IPN_Branch::delete_closure( absint( $_GET['ipn_delete_closure'] ) );
			wp_safe_redirect( remove_query_arg( array( 'ipn_delete_closure', '_wpnonce' ) ) );
			exit;
		}

		if ( ! empty( $_POST['ipn_add_closure'] ) ) {
			check_admin_referer( 'ipn_add_closure' );

			$branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;
			$date      = isset( $_POST['closure_date'] ) ? sanitize_text_field( wp_unslash( $_POST['closure_date'] ) ) : '';
			$note      = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';

			if ( $branch_id && $date ) {
				IPN_Branch::add_closure( $branch_id, $date, $note );
			}
		}
	}

	/**
	 * Dokan vendor accounts (WP users with the 'seller' role) to populate
	 * the branch modal's Vendor dropdown — every branch belongs to exactly
	 * one, per the Partner Adapter architecture (each C&C partner is a
	 * Dokan vendor; branches are that vendor's collection points).
	 */
	protected function get_dokan_vendors() {
		return get_users( array(
			'role'    => 'seller',
			'orderby' => 'display_name',
			'fields'  => array( 'ID', 'display_name' ),
		) );
	}

	/**
	 * Handles the branch add/edit modal's form submission: creates or
	 * updates the branch row, then replaces its full week of operating
	 * hours in one go.
	 *
	 * @return int|WP_Error|null Saved branch ID, an error, or null if nothing was posted this request.
	 */
	protected function maybe_handle_branch_save() {
		if ( empty( $_POST['ipn_save_branch'] ) ) {
			return null;
		}

		check_admin_referer( 'ipn_save_branch' );

		$branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;
		$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$code      = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;

		if ( '' === $name || '' === $code || ! $vendor_id ) {
			return new WP_Error( 'ipn_branch_invalid', __( 'Branch name, branch code, and vendor are all required.', 'ipn' ) );
		}

		$latitude  = null;
		$longitude = null;
		$gps       = isset( $_POST['gps'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['gps'] ) ) ) : '';

		if ( $gps && preg_match( '/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/', $gps, $matches ) ) {
			$latitude  = (float) $matches[1];
			$longitude = (float) $matches[2];
		}

		$data = array(
			'name'            => $name,
			'code'            => $code,
			'vendor_id'       => $vendor_id,
			'address'         => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
			'phone'           => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'email'           => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'latitude'        => $latitude,
			'longitude'       => $longitude,
			'status'          => empty( $_POST['active'] ) ? 'inactive' : 'active',
			'disabled_reason' => isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '',
		);

		if ( $branch_id ) {
			IPN_Branch::update( $branch_id, $data );
		} else {
			$branch_id = IPN_Branch::create( $data );
		}

		if ( ! $branch_id ) {
			return new WP_Error( 'ipn_branch_save_failed', __( 'Could not save the branch — the branch code may already be in use by another branch.', 'ipn' ) );
		}

		$days = array();

		for ( $day_of_week = 0; $day_of_week <= 6; $day_of_week++ ) {
			$days[ $day_of_week ] = array(
				'is_closed'  => ! empty( $_POST[ 'hours_closed_' . $day_of_week ] ),
				'open_time'  => isset( $_POST[ 'hours_open_' . $day_of_week ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'hours_open_' . $day_of_week ] ) ) : '',
				'close_time' => isset( $_POST[ 'hours_close_' . $day_of_week ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'hours_close_' . $day_of_week ] ) ) : '',
			);
		}

		IPN_Branch::set_hours( $branch_id, $days );

		return $branch_id;
	}

	public function render_staff() {
		$result = $this->maybe_handle_staff_branch_assign();

		$this->view( 'staff', array(
			'branches'      => IPN_Branch::get_all(),
			'assign_result' => $result,
		) );
	}

	/**
	 * The Staff screen's per-row branch assignment — wires up
	 * IPN_Roles::set_branch_id(), which existed but was never called from
	 * anywhere (branch assignment was DB-only before this).
	 */
	protected function maybe_handle_staff_branch_assign() {
		if ( empty( $_POST['ipn_assign_staff_branch'] ) ) {
			return null;
		}

		check_admin_referer( 'ipn_assign_staff_branch' );

		$user_id   = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$branch_id = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;

		if ( ! $user_id || ! IPN_Roles::is_branch_staff( $user_id ) ) {
			return new WP_Error( 'ipn_staff_invalid', __( 'Invalid staff account.', 'ipn' ) );
		}

		IPN_Roles::set_branch_id( $user_id, $branch_id );

		return true;
	}

	public function render_stock() {
		$result = $this->maybe_handle_stock_adjust();

		$this->view( 'stock', array(
			'branches'      => IPN_Branch::get_all(),
			'adjust_result' => $result,
		) );
	}

	/**
	 * Handles the Stock screen's "Adjust" modal — the one write path onto
	 * ipn_branch_stock that existed only as a toast stub before.
	 *
	 * @return true|WP_Error|null
	 */
	protected function maybe_handle_stock_adjust() {
		if ( empty( $_POST['ipn_adjust_stock'] ) ) {
			return null;
		}

		check_admin_referer( 'ipn_adjust_stock' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$branch_id  = isset( $_POST['branch_id'] ) ? absint( $_POST['branch_id'] ) : 0;
		$total      = isset( $_POST['total_stock'] ) && '' !== $_POST['total_stock'] ? absint( $_POST['total_stock'] ) : null;

		if ( ! $product_id || ! $branch_id || null === $total ) {
			return new WP_Error( 'ipn_stock_invalid', __( 'Invalid stock adjustment — a product, branch, and stock quantity are all required.', 'ipn' ) );
		}

		IPN_Branch_Stock::set_total( $product_id, $branch_id, $total );

		IPN_Audit_Log::log( 'stock_adjusted', array(
			'branch_id' => $branch_id,
			'data'      => array(
				'product_id' => $product_id,
				'new_total'  => $total,
			),
		) );

		return true;
	}

	public function render_import() {
		$result = $this->maybe_handle_import_upload();
		$runs   = IPN_CSV_Import::get_recent_runs();

		foreach ( $runs as $run ) {
			$run->failed_rows = $run->failed_count > 0 ? IPN_CSV_Import::get_failed_rows( $run->id ) : array();
		}

		$this->view( 'import', array(
			'recent_runs'   => $runs,
			'import_result' => $result,
		) );
	}

	/**
	 * Handles the catalogue-import upload form. Saves to a private
	 * uploads/ipn-imports/ subfolder, processes it, then deletes the file —
	 * catalogue files aren't kept around once their data is in the DB.
	 *
	 * @return int|WP_Error|null Import log ID, an error, or null if no upload was posted this request.
	 */
	protected function maybe_handle_import_upload() {
		if ( empty( $_POST['ipn_run_import'] ) ) {
			return null;
		}

		check_admin_referer( 'ipn_catalogue_import' );

		if ( empty( $_FILES['ipn_catalogue_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['ipn_catalogue_file']['tmp_name'] ) ) {
			return new WP_Error( 'ipn_no_file', __( 'Please choose a file to upload.', 'ipn' ) );
		}

		$original_name = sanitize_file_name( wp_unslash( $_FILES['ipn_catalogue_file']['name'] ) );
		$ext           = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'csv', 'xlsx' ), true ) ) {
			return new WP_Error( 'ipn_bad_extension', __( 'Please upload a .csv or .xlsx file.', 'ipn' ) );
		}

		$upload_dir = wp_upload_dir();
		$dest_dir   = trailingslashit( $upload_dir['basedir'] ) . 'ipn-imports/';
		wp_mkdir_p( $dest_dir );

		$dest_path = $dest_dir . wp_unique_filename( $dest_dir, $original_name );

		if ( ! move_uploaded_file( $_FILES['ipn_catalogue_file']['tmp_name'], $dest_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_move_uploaded_file
			return new WP_Error( 'ipn_upload_failed', __( 'Could not save the uploaded file.', 'ipn' ) );
		}

		$result = IPN_CSV_Import::process_file( $dest_path, get_current_user_id() );

		wp_delete_file( $dest_path );

		return $result;
	}

	/**
	 * Serves a sample CSV matching IPN_CSV_Import's expected columns —
	 * linked from the Catalogue Import screen and meant to be handed to
	 * Choppies as-is so they can prepare their real file against it.
	 */
	public function download_import_template() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'ipn' ) );
		}

		check_admin_referer( 'ipn_download_import_template' );

		$rows = array(
			array( 'SKU', 'Product Name', 'Description', 'Category', 'Regular Price', 'Sale Price', 'Branch Code', 'Stock Quantity', 'Image URL' ),
			array( 'CHP-00123', 'Sunlight Dishwashing Liquid 750ml', 'Sunlight dishwashing liquid, 750ml bottle.', 'Household', '28.50', '', 'CHP-GBE-01', '40', 'https://example.com/images/sunlight-750ml.jpg' ),
			array( 'CHP-00123', 'Sunlight Dishwashing Liquid 750ml', 'Sunlight dishwashing liquid, 750ml bottle.', 'Household', '28.50', '', 'CHP-FTW-01', '15', '' ),
			array( 'CHP-00456', 'White Star Maize Meal 2.5kg', 'White Star super maize meal, 2.5kg.', 'Groceries,Staples', '32.00', '29.99', 'CHP-GBE-01', '60', '' ),
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ipn-catalogue-import-template.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		foreach ( $rows as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		exit;
	}

	public function render_orders() {
		$this->view( 'orders', array( 'orders' => $this->get_all_ipn_orders() ) );
	}

	public function render_disputes() {
		$orders = array_values( array_filter( $this->get_all_ipn_orders(), function ( $order ) {
			return 'disputed' === $order->status;
		} ) );

		$this->view( 'disputes', array( 'orders' => $orders ) );
	}

	/**
	 * Every IPN order (has _ipn_branch_id order meta), most recent first —
	 * backs both the Orders & Disputes list and the Disputes & Returns
	 * queue (which just filters this down to status === 'disputed').
	 * Same wc_get_orders() + real order meta pattern IPN_Reports uses, so
	 * this works on HPOS and legacy post-based orders alike.
	 *
	 * @return object[]
	 */
	protected function get_all_ipn_orders( $limit = 200 ) {
		$order_ids = wc_get_orders( array(
			'return'     => 'ids',
			'limit'      => $limit,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_ipn_branch_id',
					'compare' => 'EXISTS',
				),
			),
		) );

		$rows = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			$status = IPN_Order::display_status( $order->get_status() );

			if ( ! $status ) {
				continue;
			}

			$meta   = IPN_Order::get_meta( $order_id );
			$branch = ( $meta && $meta->branch_id ) ? IPN_Branch::get( $meta->branch_id ) : null;

			$items = array();
			foreach ( $order->get_items() as $item ) {
				$items[] = array(
					'name' => $item->get_name(),
					'qty'  => $item->get_quantity(),
				);
			}

			$recipient = null;
			if ( $meta && ! empty( $meta->nominated_name ) ) {
				$recipient = array(
					'name'      => $meta->nominated_name,
					'phone'     => $meta->nominated_phone,
					'id_number' => $meta->nominated_id_number,
				);
			}

			$audit = array();
			foreach ( IPN_Audit_Log::for_order( $order_id ) as $entry ) {
				$audit[] = array(
					'text' => IPN_Audit_Log::describe_event( $entry->event_type ),
					'time' => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry->created_at ),
				);
			}

			$rows[] = (object) array(
				'order_id'       => $order_id,
				'order_number'   => $order->get_order_number(),
				'branch_name'    => $branch ? $branch->name : '',
				'customer_name'  => IPN_Order::customer_name( $order ),
				'type'           => ( $meta && $meta->collection_type ) ? $meta->collection_type : 'standard',
				'status'         => $status,
				'total'          => $order->get_total(),
				'dispute_reason' => ( $meta && $meta->dispute_reason ) ? IPN_Order::dispute_reason_label( $meta->dispute_reason ) : '',
				'edit_url'       => $order->get_edit_order_url(),
				'items'          => $items,
				'recipient'      => $recipient,
				'audit'          => $audit,
			);
		}

		return $rows;
	}

	public function render_digest() {
		$this->view( 'digest', array( 'expired_orders' => $this->get_expired_orders_digest() ) );
	}

	/**
	 * Orders IPN_Uncollected_Workflow auto-expired in the last 24 hours,
	 * for the admin daily digest. Refund is always manual (confirmed
	 * decision) — the "Refund" column just reflects whether an admin has
	 * already processed one through WooCommerce's own refund tools.
	 */
	protected function get_expired_orders_digest() {
		$rows = IPN_Audit_Log::query( array(
			'event_type' => 'collection_expired',
			'date_from'  => gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ),
		) );

		$digest = array();

		foreach ( $rows as $row ) {
			$order = wc_get_order( $row->order_id );

			if ( ! $order ) {
				continue;
			}

			$meta   = IPN_Order::get_meta( $row->order_id );
			$branch = ( $meta && $meta->branch_id ) ? IPN_Branch::get( $meta->branch_id ) : null;
			$name   = trim( $order->get_formatted_billing_full_name() );

			$digest[] = (object) array(
				'order_number'  => $order->get_order_number(),
				'branch_name'   => $branch ? $branch->name : '',
				'customer_name' => $name ? $name : $order->get_billing_email(),
				'ready_since'   => ( $meta && $meta->ready_at ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $meta->ready_at . ' UTC' ) ) : '',
				'expired_at'    => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row->created_at ) ),
				'total'         => $order->get_total(),
				'refunded'      => 'refunded' === $order->get_status(),
			);
		}

		return $digest;
	}

	public function render_audit_log() {
		$this->view( 'audit-log', array(
			'entries'  => IPN_Audit_Log::query(),
			'branches' => IPN_Branch::get_all(),
		) );
	}

	public function render_reports() {
		$filters = $this->get_report_filters();
		$this->view( 'reports', array_merge( $filters, $this->get_report_data( $filters ) ) );
	}

	/**
	 * Range/branch selects on the Reports screen are plain GET params —
	 * consistent with the rest of the admin (server-rendered, no SPA).
	 */
	protected function get_report_filters() {
		$range     = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '7'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$range     = in_array( $range, array( '7', '30', '90' ), true ) ? $range : '7';
		$branch_id = isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return array(
			'range'     => $range,
			'branch_id' => $branch_id,
			'date_from' => gmdate( 'Y-m-d', strtotime( '-' . $range . ' days' ) ),
			'date_to'   => gmdate( 'Y-m-d' ),
		);
	}

	/**
	 * Shared by the Reports screen and the CSV export handler so both work
	 * from identical numbers.
	 */
	protected function get_report_data( array $filters ) {
		$date_from = $filters['date_from'];
		$date_to   = $filters['date_to'];
		$branch_id = $filters['branch_id'];

		return array(
			'orders_by_branch'    => IPN_Reports::orders_by_branch( $date_from, $date_to, $branch_id ),
			'collection_success'  => IPN_Reports::collection_success_rate( $date_from, $date_to, $branch_id ),
			'uncollected'         => IPN_Reports::uncollected_orders( $date_from, $date_to, $branch_id ),
			'prep_time'           => IPN_Reports::average_preparation_time( $date_from, $date_to, $branch_id ),
			'turnaround'          => IPN_Reports::collection_turnaround_time( $date_from, $date_to, $branch_id ),
			'product_performance' => IPN_Reports::product_performance_by_branch( $date_from, $date_to, $branch_id ),
			'branch_sales'        => IPN_Reports::branch_sales_performance( $date_from, $date_to ),
			'express_split'       => IPN_Reports::express_vs_standard_split( $date_from, $date_to, $branch_id ),
		);
	}

	public function render_settings() {
		$this->view( 'settings' );
	}

	/**
	 * CSV export for the Audit Trail screen — same 500-row query the page
	 * itself renders (branch/type filters there are client-side only, over
	 * already-rendered rows, so there's no separate server-side filter to
	 * match here).
	 */
	public function export_audit_log() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'ipn' ) );
		}

		check_admin_referer( 'ipn_export_audit_log' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ipn-audit-log-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		fputcsv( $out, array( 'Date/time', 'Event', 'Order ID', 'Branch', 'Actor type', 'Actor ID', 'Detail' ) );

		foreach ( IPN_Audit_Log::query() as $entry ) {
			$branch = $entry->branch_id ? IPN_Branch::get( $entry->branch_id ) : null;

			fputcsv( $out, array(
				$entry->created_at,
				IPN_Audit_Log::describe_event( $entry->event_type ),
				$entry->order_id ? $entry->order_id : '',
				$branch ? $branch->name : '',
				$entry->actor_type,
				$entry->actor_id ? $entry->actor_id : '',
				$entry->data,
			) );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		exit;
	}

	/**
	 * CSV export for the currently filtered report set (same range/branch
	 * GET params as the Reports screen) — one combined file covering all
	 * 8 reports rather than a separate download per panel.
	 */
	public function export_reports() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'ipn' ) );
		}

		check_admin_referer( 'ipn_export_reports' );

		$filters = $this->get_report_filters();
		$data    = $this->get_report_data( $filters );

		$sales_by_branch      = array();
		$turnaround_by_branch = array();
		$uncollected_by_branch = array();

		foreach ( $data['branch_sales'] as $row ) {
			$sales_by_branch[ $row->branch_id ] = $row;
		}
		foreach ( $data['turnaround'] as $row ) {
			$turnaround_by_branch[ $row->branch_id ] = $row;
		}
		foreach ( $data['uncollected'] as $row ) {
			$key = $row->branch_name;
			$uncollected_by_branch[ $key ] = ( isset( $uncollected_by_branch[ $key ] ) ? $uncollected_by_branch[ $key ] : 0 ) + 1;
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ipn-report-' . $filters['date_from'] . '-to-' . $filters['date_to'] . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		fputcsv( $out, array( 'IPN Operational Report', $filters['date_from'] . ' to ' . $filters['date_to'] ) );
		fputcsv( $out, array() );

		fputcsv( $out, array( 'Orders by branch' ) );
		fputcsv( $out, array( 'Branch', 'Orders', 'Revenue (BWP)', 'Avg prep (minutes)', 'Avg turnaround (minutes)', 'Uncollected now' ) );

		foreach ( $data['orders_by_branch'] as $row ) {
			$sales      = isset( $sales_by_branch[ $row->branch_id ] ) ? $sales_by_branch[ $row->branch_id ] : null;
			$turnaround = isset( $turnaround_by_branch[ $row->branch_id ] ) ? $turnaround_by_branch[ $row->branch_id ] : null;
			$prep       = null;

			foreach ( $data['prep_time'] as $prep_row ) {
				if ( $prep_row->branch_id === $row->branch_id ) {
					$prep = $prep_row;
					break;
				}
			}

			fputcsv( $out, array(
				$row->branch_name,
				$row->count,
				$sales ? number_format( (float) $sales->revenue, 2, '.', '' ) : '0.00',
				( $prep && null !== $prep->avg_seconds ) ? round( $prep->avg_seconds / 60 ) : '',
				( $turnaround && null !== $turnaround->avg_seconds ) ? round( $turnaround->avg_seconds / 60 ) : '',
				isset( $uncollected_by_branch[ $row->branch_name ] ) ? $uncollected_by_branch[ $row->branch_name ] : 0,
			) );
		}

		fputcsv( $out, array() );
		fputcsv( $out, array( 'Collection success rate' ) );
		fputcsv( $out, array( 'Collected', 'Cancelled / expired / disputed', 'Total', 'Success rate %' ) );
		fputcsv( $out, array(
			$data['collection_success']['collected'],
			$data['collection_success']['lost'],
			$data['collection_success']['total'],
			null === $data['collection_success']['success_rate'] ? '' : $data['collection_success']['success_rate'],
		) );

		fputcsv( $out, array() );
		fputcsv( $out, array( 'Express vs Standard split' ) );
		fputcsv( $out, array( 'Type', 'Orders', 'Revenue (BWP)' ) );
		fputcsv( $out, array( 'Standard', $data['express_split']['standard']['count'], number_format( (float) $data['express_split']['standard']['revenue'], 2, '.', '' ) ) );
		fputcsv( $out, array( 'Express', $data['express_split']['express']['count'], number_format( (float) $data['express_split']['express']['revenue'], 2, '.', '' ) ) );

		fputcsv( $out, array() );
		fputcsv( $out, array( 'Top sellers' ) );
		fputcsv( $out, array( 'Product', 'Qty sold' ) );
		foreach ( $data['product_performance'] as $row ) {
			fputcsv( $out, array( $row->name, $row->qty_sold ) );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		exit;
	}
}
