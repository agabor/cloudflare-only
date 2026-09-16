<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Admin_Page {

	public static function init() {
		add_action( 'admin_menu', array( 'CF_Admin_Page', 'add_menu' ) );
		add_action( 'admin_post_cfow_clear_logs', array( 'CF_Admin_Page', 'handle_clear_logs' ) );
	}

	public static function add_menu() {
		add_management_page(
			'Cloudflare Only',
			'Cloudflare Only',
			'manage_options',
			'cloudflare-only-wp',
			array( 'CF_Admin_Page', 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ranges = CF_IP_Manager::get_ip_ranges();
		$last_updated = CF_IP_Manager::get_last_updated();
		$logs = CF_Logger::get_logs();

		?>
		<div class="wrap">
			<h1>Cloudflare Only</h1>

			<h2>IP Ranges</h2>
			<p><strong>Last Updated:</strong> <?php echo esc_html( $last_updated ); ?></p>

			<h3>IPv4 Ranges</h3>
			<textarea readonly rows="10" style="width:100%;"><?php echo esc_textarea( implode( "\n", $ranges['ipv4'] ) ); ?></textarea>

			<h3>IPv6 Ranges</h3>
			<textarea readonly rows="10" style="width:100%;"><?php echo esc_textarea( implode( "\n", $ranges['ipv6'] ) ); ?></textarea>

			<h2>Forbidden Request Logs</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Timestamp</th>
						<th>IP Address</th>
						<th>Request URI</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="3">No logs found.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
								<td><?php echo esc_html( $entry['ip'] ); ?></td>
								<td><?php echo esc_html( $entry['uri'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cfow_clear_logs" />
				<?php wp_nonce_field( 'cfow_clear_logs_action', 'cfow_clear_logs_nonce' ); ?>
				<p class="submit">
					<input type="submit" class="button button-secondary" value="Clear Logs" />
				</p>
			</form>
		</div>
		<?php
	}

	public static function handle_clear_logs() {
		if ( ! isset( $_POST['cfow_clear_logs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfow_clear_logs_nonce'] ) ), 'cfow_clear_logs_action' ) ) {
			wp_die( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to perform this action.' );
		}

		CF_Logger::clear_logs();

		wp_safe_redirect( admin_url( 'tools.php?page=cloudflare-only-wp' ) );
		exit;
	}
}