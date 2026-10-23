<?php
/**
 * Just enough of WordPress for the plugin's sync, link check and export to run
 * from the command line. Options live in memory; build.php loads them from
 * data/ before a run and writes them back afterwards.
 *
 * HTTP goes through cURL. The response carries the final URL after redirects,
 * because the link check compares it with the requested one.
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['nnz_options'] = array();

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_code() {
		return $this->code;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

// Hooks and registration are irrelevant outside WordPress.
function add_shortcode() {}
function add_action() {}
function add_filter() {}
function register_activation_hook() {}
function register_deactivation_hook() {}

function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/\\' ) . '/';
}
function plugin_dir_url( $file ) {
	return '';
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['nnz_options'] ) ? $GLOBALS['nnz_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['nnz_options'][ $name ] = $value;
	return true;
}

function wp_json_encode( $data ) {
	return json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

function current_time( $format ) {
	// The catalogue is a New Zealand resource: date its checks in NZ time.
	$tz = new DateTimeZone( 'Pacific/Auckland' );
	return ( new DateTime( 'now', $tz ) )->format( $format );
}

function add_query_arg( $args, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}

function wp_parse_url( $url ) {
	return parse_url( $url );
}

function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $url ) {
	return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
}

/** Mirrors what the link check reads from a WordPress HTTP response. */
class NNZ_Shim_Response {
	private $obj;
	public function __construct( $final_url ) {
		$this->obj = (object) array( 'url' => $final_url );
	}
	public function get_response_object() {
		return $this->obj;
	}
}

function nnz_shim_request( $method, $url, $args ) {
	$ch = curl_init( $url );
	$headers = array();
	foreach ( isset( $args['headers'] ) ? $args['headers'] : array() as $k => $v ) {
		$headers[] = $k . ': ' . $v;
	}
	$redirects = isset( $args['redirection'] ) ? (int) $args['redirection'] : 5;
	curl_setopt_array( $ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_NOBODY         => 'HEAD' === $method,
		CURLOPT_FOLLOWLOCATION => $redirects > 0,
		CURLOPT_MAXREDIRS      => $redirects,
		CURLOPT_TIMEOUT        => isset( $args['timeout'] ) ? (int) $args['timeout'] : 20,
		CURLOPT_USERAGENT      => isset( $args['user-agent'] ) ? $args['user-agent'] : 'NeuroNZ-catalogue/1.0',
		CURLOPT_HTTPHEADER     => $headers,
		CURLOPT_ENCODING       => '',
	) );
	$body = curl_exec( $ch );
	if ( false === $body ) {
		// One retry for transient DNS or connection failures, which would
		// otherwise be recorded as a site having gone away.
		sleep( 3 );
		$body = curl_exec( $ch );
	}
	if ( false === $body ) {
		$err = curl_error( $ch );
		curl_close( $ch );
		return new WP_Error( 'http_request_failed', $err );
	}
	$code  = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	$final = curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );
	curl_close( $ch );
	return array(
		'response'      => array( 'code' => $code ),
		'body'          => (string) $body,
		'http_response' => new NNZ_Shim_Response( $final ),
	);
}

function wp_remote_get( $url, $args = array() ) {
	return nnz_shim_request( 'GET', $url, $args );
}
function wp_remote_head( $url, $args = array() ) {
	return nnz_shim_request( 'HEAD', $url, $args );
}
function wp_remote_retrieve_response_code( $res ) {
	return is_array( $res ) ? $res['response']['code'] : '';
}
function wp_remote_retrieve_body( $res ) {
	return is_array( $res ) ? $res['body'] : '';
}
