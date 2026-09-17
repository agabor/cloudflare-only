<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Admin_Page {

	public static function init() {
		add_action( 'admin_menu', array( 'CF_Admin_Page', 'add_menu' ) );
		add_action( 'admin_post_cfow_clear_logs', array( 'CF_Admin_Page', 'handle_clear_logs' ) );
		add_action( 'admin_post_cfow_toggle_test_mode', array( 'CF_Admin_Page', 'handle_toggle_test_mode' ) );
		add_action( 'admin_post_cfow_toggle_reduced_security_mode', array( 'CF_Admin_Page', 'handle_toggle_reduced_security_mode' ) );
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
		$is_test_mode = CF_Request_Filter::is_test_mode();
		$is_reduced_security_mode = CF_Request_Filter::is_reduced_security_mode();
		$current_ip = CF_Request_Filter::get_client_ip();
		$current_ip_is_cloudflare = ! empty( $current_ip ) ? CF_Request_Filter::is_cloudflare_ip( $current_ip ) : false;
		$forwarded_ips = CF_Request_Filter::get_forwarded_for_ips();
		$forwarded_ip_is_cloudflare = CF_Request_Filter::any_forwarded_ip_is_cloudflare( $forwarded_ips );
		$can_disable_test_mode = CF_Request_Filter::is_effective_request_cloudflare();

		$forwarded_for_header = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';

		if ( isset( $_GET['cfow_notice'] ) && 'test_mode_blocked' === sanitize_text_field( wp_unslash( $_GET['cfow_notice'] ) ) ) {
			echo '<div class="notice notice-error"><p>Test mode cannot be disabled because your current IP address is not within Cloudflare\'s IP range.</p></div>';
		}

		?>
		<div class="wrap">
			<h1>Cloudflare Only</h1>

			<h2>Test Mode</h2>
			<p>
				<?php if ( $is_test_mode ) : ?>
					Test mode is currently <strong>ON</strong>. Requests outside Cloudflare's IP ranges are logged but not blocked.
				<?php else : ?>
					Test mode is currently <strong>OFF</strong>. Requests outside Cloudflare's IP ranges are blocked.
				<?php endif; ?>
			</p>
			<?php if ( ! $can_disable_test_mode ) : ?>
				<p><em>Your current IP is not within Cloudflare's IP range, so test mode cannot be disabled.</em></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cfow_toggle_test_mode" />
				<?php wp_nonce_field( 'cfow_toggle_test_mode_action', 'cfow_toggle_test_mode_nonce' ); ?>
				<label>
					<input type="checkbox" name="cfow_test_mode" value="1" <?php checked( $is_test_mode ); ?> <?php disabled( ! $can_disable_test_mode ); ?> />
					Enable Test Mode
				</label>
				<p class="submit">
					<input type="submit" class="button button-primary" value="Save" <?php disabled( ! $can_disable_test_mode ); ?> />
				</p>
			</form>

			<h2>Reduced Security Mode</h2>
			<div class="notice notice-warning inline">
				<p><strong>Warning:</strong> The <code>X-Forwarded-For</code> header can be forged by visitors unless your infrastructure guarantees it is set correctly (for example, by a trusted reverse proxy that overwrites or appends to it). Enabling this mode may allow attackers to bypass Cloudflare IP restrictions by spoofing this header. Only enable this if you understand the risks and have verified that this header cannot be forged in your environment.</p>
			</div>
			<p>
				<?php if ( $is_reduced_security_mode ) : ?>
					Reduced security mode is currently <strong>ON</strong>. Requests whose <code>X-Forwarded-For</code> IP is within Cloudflare's ranges are also treated as coming from Cloudflare.
				<?php else : ?>
					Reduced security mode is currently <strong>OFF</strong>. Only the direct connection IP address is checked against Cloudflare's ranges.
				<?php endif; ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cfow_toggle_reduced_security_mode" />
				<?php wp_nonce_field( 'cfow_toggle_reduced_security_mode_action', 'cfow_toggle_reduced_security_mode_nonce' ); ?>
				<label>
					<input type="checkbox" name="cfow_reduced_security_mode" value="1" <?php checked( $is_reduced_security_mode ); ?> />
					Enable Reduced Security Mode
				</label>
				<p class="submit">
					<input type="submit" class="button button-primary" value="Save" />
				</p>
			</form>

			<h2>Your Current IP</h2>
			<p>
                <strong>IP Address:</strong> <?php echo esc_html( $current_ip ); ?><br />
                <strong>X-Forwarded-For:</strong> <?php echo esc_html( $forwarded_for_header ); ?><br />
				<strong>Within Cloudflare's IP Range:</strong>
				<?php echo $current_ip_is_cloudflare ? 'Yes' : 'No'; ?><br />
				<strong>Any X-Forwarded-For IP Within Cloudflare's IP Range:</strong>
				<?php echo $forwarded_ip_is_cloudflare ? 'Yes' : 'No'; ?>
			</p>

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
						<th>User Agent</th>
						<th>Headers</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="5">No logs found.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
								<td><?php echo esc_html( $entry['ip'] ); ?></td>
								<td><?php echo esc_html( $entry['uri'] ); ?></td>
								<td><?php echo esc_html( isset( $entry['user_agent'] ) ? $entry['user_agent'] : '' ); ?></td>
								<td><?php echo self::format_log_headers( isset( $entry['headers'] ) ? $entry['headers'] : array() ); ?></td>
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

	private static function format_log_headers( $headers ) {
		if ( empty( $headers ) || ! is_array( $headers ) ) {
			return '';
		}

		$lines = array();

		foreach ( $headers as $label => $value ) {
			$lines[] = '<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value );
		}

		return implode( '<br />', $lines );
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

	public static function handle_toggle_test_mode() {
		if ( ! isset( $_POST['cfow_toggle_test_mode_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfow_toggle_test_mode_nonce'] ) ), 'cfow_toggle_test_mode_action' ) ) {
			wp_die( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to perform this action.' );
		}

		$requested_test_mode = isset( $_POST['cfow_test_mode'] ) ? '1' : '0';

		$can_disable_test_mode = CF_Request_Filter::is_effective_request_cloudflare();

		if ( '0' === $requested_test_mode && ! $can_disable_test_mode ) {
			wp_safe_redirect( admin_url( 'tools.php?page=cloudflare-only-wp&cfow_notice=test_mode_blocked' ) );
			exit;
		}

		update_option( 'cfow_test_mode', $requested_test_mode );

		wp_safe_redirect( admin_url( 'tools.php?page=cloudflare-only-wp' ) );
		exit;
	}

	public static function handle_toggle_reduced_security_mode() {
		if ( ! isset( $_POST['cfow_toggle_reduced_security_mode_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfow_toggle_reduced_security_mode_nonce'] ) ), 'cfow_toggle_reduced_security_mode_action' ) ) {
			wp_die( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to perform this action.' );
		}

		$requested_reduced_security_mode = isset( $_POST['cfow_reduced_security_mode'] ) ? '1' : '0';

		update_option( 'cfow_reduced_security_mode', $requested_reduced_security_mode );

		wp_safe_redirect( admin_url( 'tools.php?page=cloudflare-only-wp' ) );
		exit;
	}
}