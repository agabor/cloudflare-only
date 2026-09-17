<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CF_Request_Filter {

	public static function init() {
		add_action( 'init', array( 'CF_Request_Filter', 'check_request' ), 1 );
	}

	public static function check_request() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		$client_ip = self::get_client_ip();

		if ( empty( $client_ip ) ) {
			return;
		}

		$is_cloudflare = self::is_cloudflare_ip( $client_ip );

		if ( ! $is_cloudflare && self::is_reduced_security_mode() ) {
			$forwarded_ips = self::get_forwarded_for_ips();
			foreach ( $forwarded_ips as $forwarded_ip ) {
				if ( ! empty( $forwarded_ip ) && self::is_cloudflare_ip( $forwarded_ip ) ) {
					$is_cloudflare = true;
					break;
				}
			}
		}

		if ( ! $is_cloudflare ) {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			$forbidden_headers = self::get_forbidden_headers();
			CF_Logger::log( $client_ip, $request_uri, $user_agent, $forbidden_headers );

			if ( ! self::is_test_mode() ) {
				self::deny_access();
			}
		}
	}

	public static function is_test_mode() {
		return '1' === get_option( 'cfow_test_mode', '1' );
	}

	public static function is_reduced_security_mode() {
		return '1' === get_option( 'cfow_reduced_security_mode', '0' );
	}

	public static function get_client_ip() {
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '';
	}

	public static function get_forwarded_for_ips() {
		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ips = explode( ',', $header );
			$trimmed_ips = array();

			foreach ( $ips as $ip ) {
				$trimmed_ip = trim( $ip );
				if ( ! empty( $trimmed_ip ) ) {
					$trimmed_ips[] = $trimmed_ip;
				}
			}

			return $trimmed_ips;
		}
		return array();
	}

	public static function get_forbidden_headers() {
		$header_map = array(
			'CF-Connecting-IP'    => 'HTTP_CF_CONNECTING_IP',
			'True-Client-IP'      => 'HTTP_TRUE_CLIENT_IP',
			'X-Forwarded-For'     => 'HTTP_X_FORWARDED_FOR',
			'X-Real-IP'           => 'HTTP_X_REAL_IP',
			'Forwarded'           => 'HTTP_FORWARDED',
			'X-Client-IP'         => 'HTTP_X_CLIENT_IP',
			'X-Cluster-Client-IP' => 'HTTP_X_CLUSTER_CLIENT_IP',
		);

		$headers = array();

		foreach ( $header_map as $label => $server_key ) {
			if ( isset( $_SERVER[ $server_key ] ) ) {
				$headers[ $label ] = sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
			}
		}

		return $headers;
	}

	public static function is_cloudflare_ip( $ip ) {
		$ranges = CF_IP_Manager::get_ip_ranges();

		$is_ipv6 = strpos( $ip, ':' ) !== false;

		$cidr_list = $is_ipv6 ? $ranges['ipv6'] : $ranges['ipv4'];

		if ( empty( $cidr_list ) ) {
			return true;
		}

		foreach ( $cidr_list as $cidr ) {
			if ( self::is_ip_in_range( $ip, $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	public static function is_ip_in_range( $ip, $cidr ) {
		if ( strpos( $cidr, '/' ) === false ) {
			return $ip === $cidr;
		}

		list( $subnet, $mask_bits ) = explode( '/', $cidr );
		$mask_bits = (int) $mask_bits;

		$is_ipv6 = strpos( $ip, ':' ) !== false;

		if ( $is_ipv6 ) {
			$ip_bin = @inet_pton( $ip );
			$subnet_bin = @inet_pton( $subnet );

			if ( false === $ip_bin || false === $subnet_bin ) {
				return false;
			}

			$ip_bits = '';
			$subnet_bits = '';

			foreach ( str_split( $ip_bin ) as $char ) {
				$ip_bits .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
			}

			foreach ( str_split( $subnet_bin ) as $char ) {
				$subnet_bits .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
			}

			$ip_prefix = substr( $ip_bits, 0, $mask_bits );
			$subnet_prefix = substr( $subnet_bits, 0, $mask_bits );

			return $ip_prefix === $subnet_prefix;
		} else {
			$ip_long = ip2long( $ip );
			$subnet_long = ip2long( $subnet );

			if ( false === $ip_long || false === $subnet_long ) {
				return false;
			}

			$mask = -1 << ( 32 - $mask_bits );
			$mask = $mask & 0xFFFFFFFF;

			return ( $ip_long & $mask ) === ( $subnet_long & $mask );
		}
	}

	public static function deny_access() {
		status_header( 403 );
		header( 'Content-Type: text/plain' );
		echo 'Forbidden';
		exit;
	}
}