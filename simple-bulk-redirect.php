<?php
/**
 * Simple Bulk Redirect
 *
 * @package           SimpleBulkRedirect
 * @author            Kaan Hanoğlu
 * @copyright         2024 Kaan Hanoğlu
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Simple Bulk Redirect
 * Plugin URI:        https://github.com/netsevdam/simple-bulk-redirect
 * Description:       Manage 301 redirects in WordPress with bulk CSV import/export, wildcard, and domain-to-domain support.
 * Version:           1.4.5
 * Author:            Kaan Hanoğlu
 * Author URI:        https://esenyurtkorsantaksici.blog/
 * Text Domain:       simple-bulk-redirect
 * Tags:              redirect, 301 redirect, SEO, CSV, wildcard
 * Requires at least: 5.0
 * Tested up to:      6.8
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// === CONSTANTS ===
define( 'SIMPLE_BULK_REDIRECT_VERSION', '1.4.5' );
define( 'SIMPLE_BULK_REDIRECT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_BULK_REDIRECT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// === MAIN REDIRECT HOOK ===
add_action( 'init', 'simple_bulk_redirect_handle_redirects', 1 );

/**
 * Handle redirects based on stored rules
 */
function simple_bulk_redirect_handle_redirects() {
	if ( is_admin() ) {
		return;
	}

	$redirects = get_option( 'simple_bulk_redirects', array() );
	if ( empty( $redirects ) ) {
		return;
	}

	// Sanitize and validate server variables using WordPress functions
	$http_host = '';
	if ( isset( $_SERVER['HTTP_HOST'] ) ) {
		$http_host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
	}

	$request_uri = '';
	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	if ( empty( $http_host ) || empty( $request_uri ) ) {
		return;
	}

	// Normalize request
	$requested_url = ( is_ssl() ? 'https://' : 'http://' ) . $http_host . $request_uri;
	$current_path  = strtok( trim( $request_uri, '/' ), '?' );

	foreach ( $redirects as $old_url => $new_url ) {
		$old_url = trim( $old_url );
		$new_url = trim( $new_url );
		if ( empty( $old_url ) || empty( $new_url ) ) {
			continue;
		}

		// Validate URLs aren't dangerous using WordPress functions
		if ( simple_bulk_redirect_is_dangerous_url( $old_url ) || simple_bulk_redirect_is_dangerous_url( $new_url ) ) {
			continue;
		}

		// === FULL DOMAIN MATCH ===
		if ( preg_match( '#^https?://#i', $old_url ) ) {
			if ( rtrim( $requested_url, '/' ) === rtrim( $old_url, '/' ) ) {
				simple_bulk_redirect_do_redirect( $new_url );
				exit;
			}
		} 
		// === WILDCARD MATCH ===
		elseif ( strpos( $old_url, '*' ) !== false ) {
			$pattern = str_replace( '\*', '(.*)', preg_quote( trim( $old_url, '/' ), '/' ) );
			if ( preg_match( '/^' . $pattern . '$/i', $current_path, $matches ) ) {
				$target = $new_url;
				if ( ! empty( $matches[1] ) ) {
					$target = str_replace( '$1', $matches[1], $target );
					$target = str_replace( '*', $matches[1], $target );
				}
				// Sanitize the target after replacement
				$target = simple_bulk_redirect_sanitize_redirect_url( $target );
				simple_bulk_redirect_do_redirect( $target );
				exit;
			}
		} 
		// === PATH MATCH ===
		else {
			$old_path = trim( $old_url, '/' );
			if ( rtrim( $current_path, '/' ) === rtrim( $old_path, '/' ) ) {
				simple_bulk_redirect_do_redirect( $new_url );
				exit;
			}
		}
	}
}

// === SECURITY HELPER FUNCTIONS ===

/**
 * Check if URL contains dangerous protocols or patterns using WordPress functions
 *
 * @param string $url URL to check.
 * @return bool
 */
function simple_bulk_redirect_is_dangerous_url( $url ) {
	$dangerous_patterns = array(
		'javascript:',
		'vbscript:',
		'data:',
		'file:',
		'ftp:',
		'<!--',
		'-->',
		'<script',
		'</script>',
		'onload=',
		'onerror=',
		'onclick=',
	);

	$lower_url = strtolower( $url );
	foreach ( $dangerous_patterns as $pattern ) {
		if ( strpos( $lower_url, $pattern ) !== false ) {
			return true;
		}
	}

	return false;
}

/**
 * Sanitize redirect URL using WordPress functions
 *
 * @param string $url URL to sanitize.
 * @return string
 */
function simple_bulk_redirect_sanitize_redirect_url( $url ) {
	$url = trim( $url );

	// For internal URLs, use WordPress sanitization
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = sanitize_text_field( $url );
	} else {
		$url = esc_url_raw( $url );
	}

	return $url;
}

// === REDIRECT EXECUTION FUNCTION ===
function simple_bulk_redirect_do_redirect( $redirect_url ) {
    if ( empty( $redirect_url ) ) {
        return;
    }

    $redirect_url = simple_bulk_redirect_sanitize_redirect_url( $redirect_url );

    if ( simple_bulk_redirect_is_dangerous_url( $redirect_url ) ) {
        return;
    }

    // External URL
    if ( preg_match( '#^https?://#i', $redirect_url ) ) {
        $validated_url = wp_http_validate_url( $redirect_url );
        if ( $validated_url ) {
            wp_safe_redirect( $validated_url, 301 );
            exit;
        }
    }

    // Internal redirect
    $redirect_url = ltrim( $redirect_url, '/' );
    $safe_path    = sanitize_text_field( $redirect_url );
    wp_safe_redirect( home_url( '/' . $safe_path ), 301 );
    exit;
}

// === ADMIN MENU ===
add_action( 'admin_menu', 'simple_bulk_redirect_admin_menu' );
function simple_bulk_redirect_admin_menu() {
	add_menu_page(
		'Bulk Redirects',
		'Bulk Redirects',
		'manage_options',
		'simple-bulk-redirect',
		'simple_bulk_redirect_admin_page',
		'dashicons-admin-links',
		90
	);
}

// === ADMIN PAGE SCRIPTS & STYLES ===
add_action( 'admin_enqueue_scripts', 'simple_bulk_redirect_admin_scripts' );
function simple_bulk_redirect_admin_scripts( $hook ) {
	if ( 'toplevel_page_simple-bulk-redirect' !== $hook ) {
		return;
	}

	wp_enqueue_script(
		'simple-bulk-redirect-admin',
		SIMPLE_BULK_REDIRECT_PLUGIN_URL . 'assets/admin.js', // Updated path
		array(),
		SIMPLE_BULK_REDIRECT_VERSION,
		true
	);

	wp_enqueue_style(
		'simple-bulk-redirect-admin',
		SIMPLE_BULK_REDIRECT_PLUGIN_URL . 'assets/admin.css', // Updated path
		array(),
		SIMPLE_BULK_REDIRECT_VERSION
	);
}

// === ADMIN PAGE ===
function simple_bulk_redirect_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'simple-bulk-redirect' ) );
	}
	
    // Temporary debug info
      echo '<!-- Debug: Plugin URL: ' . esc_html(SIMPLE_BULK_REDIRECT_PLUGIN_URL) . ' -->';
      echo '<!-- Debug: JS URL: ' . esc_html(SIMPLE_BULK_REDIRECT_PLUGIN_URL . 'assets/admin.js') . ' -->';
      echo '<!-- Debug: CSS URL: ' . esc_html(SIMPLE_BULK_REDIRECT_PLUGIN_URL . 'assets/admin.css') . ' -->';
      
	// === SAVE REDIRECTS ===
	if ( isset( $_POST['sbr_save_redirects'] ) ) {
		check_admin_referer( 'simple_bulk_redirect_save_verify' );

		$redirects = array();
		$old_urls  = isset( $_POST['old_url'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['old_url'] ) ) : array();
		$new_urls  = isset( $_POST['new_url'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['new_url'] ) ) : array();

		foreach ( $old_urls as $i => $old ) {
			$new = $new_urls[ $i ] ?? '';
			$old = trim( $old );
			$new = trim( $new );

			// Validate both URLs
			if ( ! empty( $old ) && ! empty( $new ) && $old !== $new ) {
				// Check for dangerous URLs
				if ( ! simple_bulk_redirect_is_dangerous_url( $old ) && ! simple_bulk_redirect_is_dangerous_url( $new ) ) {
					$redirects[ $old ] = $new;
				}
			}
		}

		update_option( 'simple_bulk_redirects', $redirects );
		echo '<div class="updated"><p>' . esc_html__( 'Redirects saved successfully.', 'simple-bulk-redirect' ) . '</p></div>';
	}

	// === IMPORT CSV ===
	if ( isset( $_POST['sbr_import_csv'] ) && ! empty( $_FILES['sbr_csv_file']['tmp_name'] ) ) {
		check_admin_referer( 'simple_bulk_redirect_import_verify' );

		$file_name = isset( $_FILES['sbr_csv_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['sbr_csv_file']['name'] ) ) : '';
		$file_type = wp_check_filetype( $file_name );
		$file_size = isset( $_FILES['sbr_csv_file']['size'] ) ? intval( $_FILES['sbr_csv_file']['size'] ) : 0;

		// Validate file type
		if ( 'csv' !== $file_type['ext'] ) {
			echo '<div class="error"><p>' . esc_html__( 'Please upload a valid CSV file.', 'simple-bulk-redirect' ) . '</p></div>';
		} 
		// Validate file size (5MB max)
		elseif ( $file_size > 5 * 1024 * 1024 ) {
			echo '<div class="error"><p>' . esc_html__( 'File too large. Maximum 5MB allowed.', 'simple-bulk-redirect' ) . '</p></div>';
		} else {
			$tmp_name = isset( $_FILES['sbr_csv_file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['sbr_csv_file']['tmp_name'] ) ) : '';

			if ( ! empty( $tmp_name ) && is_uploaded_file( $tmp_name ) && file_exists( $tmp_name ) ) {
				$csv_data     = file_get_contents( $tmp_name );
				$csv_data     = preg_replace( '/^=/', "'=", $csv_data );
				$lines        = explode( "\n", $csv_data );
				$redirects    = get_option( 'simple_bulk_redirects', array() );
				$import_count = 0;

				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( empty( $line ) ) {
						continue;
					}

					$fields = str_getcsv( $line );
					if ( isset( $fields[0], $fields[1] ) ) {
						$old = sanitize_text_field( trim( $fields[0] ) );
						$new = sanitize_text_field( trim( $fields[1] ) );

						// Validate URLs and check they're not dangerous
						if ( ! empty( $old ) && ! empty( $new ) && $old !== $new &&
							! simple_bulk_redirect_is_dangerous_url( $old ) &&
							! simple_bulk_redirect_is_dangerous_url( $new ) ) {
							$redirects[ $old ] = $new;
							$import_count++;
						}
					}
				}

				update_option( 'simple_bulk_redirects', $redirects );

				$message = sprintf(
					/* translators: %d: number of redirects imported */
					esc_html__( 'CSV imported successfully! %d redirects processed.', 'simple-bulk-redirect' ),
					intval( $import_count )
				);
				echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';

				// Use WordPress file deletion function
				wp_delete_file( $tmp_name );
			} else {
				echo '<div class="error"><p>' . esc_html__( 'Invalid file upload.', 'simple-bulk-redirect' ) . '</p></div>';
			}
		}
	}
  // === EXPORT CSV ===
  if ( isset( $_POST['sbr_export_csv'] ) ) {
    check_admin_referer( 'simple_bulk_redirect_export_verify' );

    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'simple-bulk-redirect' ) );
    }

    $redirects = get_option( 'simple_bulk_redirects', array() );

    if ( empty( $redirects ) ) {
      echo '<div class="error"><p>' . esc_html__( 'No redirects to export.', 'simple-bulk-redirect' ) . '</p></div>';
      return;
    }

    // Initialize WP_Filesystem
    global $wp_filesystem;
    if ( empty( $wp_filesystem ) ) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
      WP_Filesystem();
    }

    // Create temporary file
    $tmp_file = wp_tempnam( 'redirects-export.csv' );

    if ( ! $tmp_file ) {
      wp_die( esc_html__( 'Unable to create temporary file for export.', 'simple-bulk-redirect' ) );
    }

    // Build CSV
    $csv_lines = array();
    $csv_lines[] = "\xEF\xBB\xBF"; // UTF-8 BOM

    $headers = array(
      esc_html__( 'Old URL', 'simple-bulk-redirect' ),
      esc_html__( 'New URL', 'simple-bulk-redirect' ),
    );
    $csv_lines[] = '"' . implode( '","', array_map( 'simple_bulk_redirect_esc_csv', $headers ) ) . '"' . "\n";

    foreach ( $redirects as $old => $new ) {
      $row = '"' . implode( '","', array_map( 'simple_bulk_redirect_esc_csv', array( $old, $new ) ) ) . '"' . "\n";
      $csv_lines[] = $row;
    }

    $wp_filesystem->put_contents( $tmp_file, implode( '', $csv_lines ), FS_CHMOD_FILE );

    // Headers
    nocache_headers();
    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="redirects-' . gmdate( 'Y-m-d' ) . '.csv"' );

    // Read safely
    $file_content = $wp_filesystem->get_contents( $tmp_file );

    if ( false !== $file_content ) {
      // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe, non-HTML CSV output for download.
      echo $file_content;
    }

    wp_delete_file( $tmp_file );
    exit;
  }

  /**
   * Escape string for safe CSV context.
   */
  function simple_bulk_redirect_esc_csv( $text ) {
      return str_replace( '"', '""', $text );
  }


	$redirects = get_option( 'simple_bulk_redirects', array() );
	?>
	<div class="wrap simple-bulk-redirect-admin">
		<h1><?php esc_html_e( 'Simple Bulk Redirect', 'simple-bulk-redirect' ); ?></h1>
		<p><?php esc_html_e( 'Manage all your redirects below. Wildcards supported (e.g.,', 'simple-bulk-redirect' ); ?> <code>/old/* → /new/$1</code>).</p>

		<form method="post" id="simple-bulk-redirect-form">
			<?php wp_nonce_field( 'simple_bulk_redirect_save_verify' ); ?>

			<table class="widefat striped" id="sbr_table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Old URL', 'simple-bulk-redirect' ); ?></th>
						<th><?php esc_html_e( 'New URL', 'simple-bulk-redirect' ); ?></th>
						<th><?php esc_html_e( 'Action', 'simple-bulk-redirect' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $redirects as $old => $new ) : ?>
						<tr>
							<td><input type="text" name="old_url[]" value="<?php echo esc_attr( $old ); ?>" class="regular-text" placeholder="/old-path or https://domain.com/old-path" required></td>
							<td><input type="text" name="new_url[]" value="<?php echo esc_attr( $new ); ?>" class="regular-text" placeholder="/new-path or https://domain.com/new-path" required></td>
							<td><button type="button" class="button link-delete simple-bulk-redirect-remove-row"><?php esc_html_e( 'Remove', 'simple-bulk-redirect' ); ?></button></td>
						</tr>
					<?php endforeach; ?>

					<?php if ( empty( $redirects ) ) : ?>
						<tr>
							<td><input type="text" name="old_url[]" class="regular-text" placeholder="/old-url" required></td>
							<td><input type="text" name="new_url[]" class="regular-text" placeholder="/new-url" required></td>
							<td><button type="button" class="button link-delete simple-bulk-redirect-remove-row"><?php esc_html_e( 'Remove', 'simple-bulk-redirect' ); ?></button></td>
						</tr>
					<?php endif; ?>

					<tr class="sbr-actions-row">
						<td colspan="3">
							<button type="button" class="button" id="simple_bulk_redirect_add_row"><?php esc_html_e( 'Add Redirect', 'simple-bulk-redirect' ); ?></button>
							<input type="submit" name="sbr_save_redirects" class="button button-primary" id="simple_bulk_redirect_save_btn" value="<?php esc_attr_e( 'Save Redirects', 'simple-bulk-redirect' ); ?>">
						</td>
					</tr>
				</tbody>
			</table>
		</form>

		<hr class="wp-header-end">

		<div class="sbr-csv-section">
			<h2><?php esc_html_e( 'CSV Operations', 'simple-bulk-redirect' ); ?></h2>

			<div class="sbr-csv-actions">
				<div class="sbr-card sbr-import-section">
					<h3><?php esc_html_e( 'Import CSV', 'simple-bulk-redirect' ); ?></h3>
					<form method="post" enctype="multipart/form-data">
						<?php wp_nonce_field( 'simple_bulk_redirect_import_verify' ); ?>
						<p><input type="file" name="sbr_csv_file" accept=".csv" required class="regular-text"></p>
						<p class="description"><?php esc_html_e( 'Maximum file size: 5MB. Format: Old URL,New URL', 'simple-bulk-redirect' ); ?></p>
						<p><input type="submit" name="sbr_import_csv" class="button button-secondary" value="<?php esc_attr_e( 'Import CSV', 'simple-bulk-redirect' ); ?>"></p>
					</form>
				</div>

				<div class="sbr-card sbr-export-section">
					<h3><?php esc_html_e( 'Export CSV', 'simple-bulk-redirect' ); ?></h3>
					<form method="post">
						<?php wp_nonce_field( 'simple_bulk_redirect_export_verify' ); ?>
						<p><input type="submit" name="sbr_export_csv" class="button button-secondary" value="<?php esc_attr_e( 'Export CSV', 'simple-bulk-redirect' ); ?>"></p>
					</form>
				</div>
			</div>
		</div>

		<p class="sbr-footer">
			<?php esc_html_e( 'Developed by', 'simple-bulk-redirect' ); ?> 
			<a href="https://koctaksi.com/" target="_blank">Kaan Hanoğlu</a>
		</p>
	</div>
	<?php
}

// === MIGRATION SUPPORT ===
add_action( 'plugins_loaded', 'simple_bulk_redirect_migrate_options' );
function simple_bulk_redirect_migrate_options() {
	$old_redirects = get_option( 'sbr_redirects', array() );
	$new_redirects = get_option( 'simple_bulk_redirects', array() );

	if ( ! empty( $old_redirects ) && empty( $new_redirects ) ) {
		// Sanitize old data during migration using WordPress functions
		$sanitized_redirects = array();
		foreach ( $old_redirects as $old_url => $new_url ) {
			$old_url = sanitize_text_field( $old_url );
			$new_url = sanitize_text_field( $new_url );

			if ( ! empty( $old_url ) && ! empty( $new_url ) &&
				! simple_bulk_redirect_is_dangerous_url( $old_url ) &&
				! simple_bulk_redirect_is_dangerous_url( $new_url ) ) {
				$sanitized_redirects[ $old_url ] = $new_url;
			}
		}

		update_option( 'simple_bulk_redirects', $sanitized_redirects );
		delete_option( 'sbr_redirects' );
	}

	// Additional cleanup
	if ( get_option( 'sbr_redirects' ) !== false ) {
		delete_option( 'sbr_redirects' );
	}
}
