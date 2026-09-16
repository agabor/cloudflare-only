<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Logger {

	const MAX_LOG_ENTRIES = 500;

	public static function log( $ip, $request_uri ) {
		$logs = get_option( 'cfow_logs', array() );

		$logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'ip'        => $ip,
			'uri'       => $request_uri,
		);

		if ( count( $logs ) > self::MAX_LOG_ENTRIES ) {
			$logs = array_slice( $logs, -self::MAX_LOG_ENTRIES );
		}

		update_option( 'cfow_logs', $logs );
	}

	public static function get_logs() {
		$logs = get_option( 'cfow_logs', array() );
		return array_reverse( $logs );
	}

	public static function clear_logs() {
		delete_option( 'cfow_logs' );
	}
}