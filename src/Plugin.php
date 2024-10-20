<?php

namespace DevKabir\WPDebugger;

use Whoops\Handler\JsonResponseHandler;
use Whoops\Run;
use DebugBar\DebugBar;
use Whoops\Handler\PrettyPageHandler;
/**
 * Plugin class.
 */
class Plugin {

	/**
	 * Constructor.
	 *
	 * Check if the plugin should be enabled based on the constant in wp-config.php.
	 * If ENABLE_MOCK_HTTP_INTERCEPTOR is defined and true, adds a filter to intercept HTTP requests.
	 * Initializes the DebugBar.
	 */
	public function __construct() {
		// Check if the plugin should be enabled based on the constant in wp-config.php.
		if ( defined( 'ENABLE_MOCK_HTTP_INTERCEPTOR' ) && ENABLE_MOCK_HTTP_INTERCEPTOR ) {
			add_filter( 'pre_http_request', array( $this, 'intercept_http_requests' ), 10, 3 );
		}
		$this->debugbar = new DebugBar();
	}

	/**
	 * Initializes the error page.
	 *
	 * This registers the error page with Whoops. The error page is a
	 * PrettyPageHandler with the editor set to VSCode.
	 *
	 * @return $this
	 */
	public function init_error_page() {
		$whoops = new Run();
		if ( wp_doing_ajax() ) {
			$page = new JsonResponseHandler();
		} else {
			$page = new PrettyPageHandler();
			$page->setEditor( 'vscode' );
		}
		$whoops->pushHandler( $page );
		$whoops->register();
		return $this;
	}

	/**
	 * Intercepts outgoing HTTP requests and serves mock responses for predefined URLs.
	 * Stores POST request data in a transient for testing purposes.
	 *
	 * @param bool|array $preempt The preemptive response to return if available.
	 * @param array      $args    Array of HTTP request arguments, including method and body.
	 * @param string     $url     The URL of the outgoing HTTP request.
	 *
	 * @return mixed Mock response or false to allow original request.
	 */
	public function intercept_http_requests( $preempt, $args, $url ) {
		if ( strpos( $url, 'https://wpmudev.com/api/' ) === false ) {
			return $preempt;
		}

		$mock_logs_dir = WP_CONTENT_DIR . '/mock-logs';
		if ( ! is_dir( $mock_logs_dir ) ) {
			wp_mkdir_p( $mock_logs_dir );
		}

		$mock_urls = array(
			'/hosting' =>
				array(
					'is_enabled' => false,
					'waf'        => array(
						'is_active' => false,
					),
				),
		);

		foreach ( $mock_urls as $mock_url => $mock_response ) {
			if ( strpos( $url, $mock_url ) !== false ) {
				if ( isset( $args['method'] ) && strtoupper( $args['method'] ) === 'POST' && isset( $args['body'] ) ) {
					$post_data     = wp_parse_args( $args['body'] );
					$transient_key = 'mock_post_data_' . md5( $url . $args['method'] );
					set_transient( $transient_key, $post_data, 60 * 60 ); // Store for 1 hour
				}
				return json_encode(
					array(
						'body'          => $mock_response,
						'response'      => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'headers'       => array(),
						'cookies'       => array(),
						'http_response' => null,
					)
				);
			}
		}

		return new \WP_Error( '404', 'Interceptor enabled by wp-logger plugin.' );
	}

	/**
	 * Initializes the DebugBar.
	 *
	 * Instantiates the DebugBar\StandardDebugBar class which sets up the
	 * DebugBar with default collectors and renders the bar.
	 *
	 * @return static
	 */
	public function init_debugbar() {
		new Bar();
		return $this;
	}

	/**
	 * Formats a message with the current timestamp for logging.
	 *
	 * @param  mixed $message The message to be formatted.
	 * @return string The formatted message with the timestamp.
	 */
	public static function format_log_message( $message ) {
		if ( is_array( $message ) || is_object( $message ) || is_iterable( $message ) ) {
			$message = wp_json_encode( $message, 128 );
		} else {
			$decoded = json_decode( $message, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$message = wp_json_encode( $decoded, 128 );
			}
		}
		return gmdate( 'Y-m-d H:i:s' ) . ' - ' . $message;
	}
}
