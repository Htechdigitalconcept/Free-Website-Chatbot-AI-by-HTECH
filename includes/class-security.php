<?php
/**
 * Security helpers.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Security
 */
class HTECH_AI_Security {

	/**
	 * Public REST nonce action name.
	 */
	const NONCE_ACTION = 'htech_ai_public';

	/**
	 * Admin AJAX nonce action name.
	 */
	const ADMIN_NONCE = 'htech_ai_admin';

	/**
	 * Create (or reuse) the public nonce exposed to the widget.
	 *
	 * @return string
	 */
	public static function public_nonce() {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Verify a public nonce sent by the widget.
	 *
	 * @param string $nonce Nonce value.
	 * @return bool
	 */
	public static function verify_public_nonce( $nonce ) {
		return (bool) wp_verify_nonce( (string) $nonce, self::NONCE_ACTION );
	}

	/**
	 * Verify an admin nonce.
	 *
	 * @param string $nonce Nonce.
	 * @return bool
	 */
	public static function verify_admin_nonce( $nonce ) {
		return (bool) wp_verify_nonce( (string) $nonce, self::ADMIN_NONCE );
	}

	/**
	 * Capability required to manage the plugin.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filter the capability required to manage HTECH AI Assistant.
		 *
		 * @param string $capability Capability name.
		 */
		return apply_filters( 'htech_ai_capability', 'manage_options' );
	}

	/**
	 * Whether the current user may manage the plugin.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::capability() );
	}

	/**
	 * Fail fast if the current request lacks permission (admin screens/AJAX).
	 *
	 * @return void
	 */
	public static function require_manage() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'htech-ai-assistant' ), 403 );
		}
	}

	/**
	 * Client IP address (first valid value from the proxy headers most hosts set).
	 *
	 * @return string
	 */
	public static function client_ip() {
		$candidates = array();

		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ) as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
			foreach ( explode( ',', $value ) as $part ) {
				$part = trim( $part );
				if ( filter_var( $part, FILTER_VALIDATE_IP ) ) {
					$candidates[] = $part;
				}
			}
		}

		return $candidates ? $candidates[0] : '0.0.0.0';
	}

	/**
	 * Stable, salted IP hash (raw IPs are never stored).
	 *
	 * @return string
	 */
	public static function ip_hash() {
		return hash_hmac( 'sha256', self::client_ip(), wp_salt( 'auth' ) );
	}

	/**
	 * Visitor/session key for a conversation.
	 *
	 * @return string
	 */
	public static function session_key() {
		return 'sk_' . substr( hash_hmac( 'sha256', self::ip_hash() . '|' . ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), wp_salt( 'nonce' ) ), 0, 40 );
	}

	/**
	 * Strip control characters and normalise whitespace in visitor text.
	 *
	 * @param string $text  Raw text.
	 * @param int    $limit Max characters.
	 * @return string
	 */
	public static function clean_text( $text, $limit = 4000 ) {
		$text = wp_check_invalid_utf8( (string) $text );
		// Remove C0/C1 control characters except newline and tab.
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
		$text = wp_strip_all_tags( $text );
		$text = trim( preg_replace( "/[ \t]+/u", ' ', $text ) );
		return mb_substr( $text, 0, $limit );
	}

	/**
	 * Neutralise common prompt-injection patterns before the text reaches a model.
	 *
	 * This cannot make an LLM bullet-proof, but it removes the obvious
	 * "ignore previous instructions" class of payloads and, combined with the
	 * output guards in the chatbot service, keeps business rules intact.
	 *
	 * @param string $text Text to check.
	 * @return array{text:string,flagged:bool}
	 */
	public static function scrub_injection( $text ) {
		$patterns = array(
			'/ignore\s+(all\s+)?(previous|prior|above)\s+(instructions|prompts?|rules)/iu',
			'/disregard\s+(all\s+)?(previous|prior|above)/iu',
			'/you\s+are\s+now\s+(a|an|in)\b/iu',
			'/(reveal|show|print|dump|repeat)\s+(your\s+)?(system|hidden|initial)\s+(prompt|instructions)/iu',
			'/<\/?script[^>]*>/iu',
			'/\b(api[_\s-]?key|password|secret\s+key)\b\s*[:=]/iu',
		);

		$flagged = false;
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				$flagged = true;
				$text    = preg_replace( $pattern, '[removed]', $text );
			}
		}

		return array(
			'text'    => $text,
			'flagged' => $flagged,
		);
	}

	/**
	 * Detect questions that try to extract administration data.
	 *
	 * @param string $text Visitor message.
	 * @return bool
	 */
	public static function is_admin_probe( $text ) {
		$needles = array(
			'admin password', 'administrator password', 'wp-config', 'wp-admin',
			'database password', 'api key', 'secret key', 'system prompt',
			'your instructions', 'other visitors', 'conversation history of',
			'personal data', 'credit card', 'list all conversations',
		);
		$lower = mb_strtolower( $text );
		foreach ( $needles as $needle ) {
			if ( false !== mb_strpos( $lower, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Sanitise an RSA/plain serialised structure recursively for admin output.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function deep_kses( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'deep_kses' ), $value );
		}
		return is_string( $value ) ? wp_kses_post( $value ) : $value;
	}
}
