<?php

namespace DevKabir\WPDebugger;

use Throwable;

class ErrorPage {
	public function __construct() {
		register_shutdown_function( array( $this, 'handle_shutdown' ) );
		set_exception_handler( array( $this, 'handle' ) );
		ini_set( 'display_errors', 'off' );
		error_reporting( - 1 );
	}

	public static function dump( array $values ) {

	}

	public function handle( Throwable $throwable ): void {
		if ( $this->isJsonRequest() || wp_doing_ajax() ) {
			$this->jsonHandler( $throwable );
		}

		$this->render( $throwable );
		die;
	}

	public function handle_shutdown(): void {
		$last_error = error_get_last();
		$layout     = Template::get_part( 'layout' );
	}

	private function isJsonRequest(): bool {
		return ( isset( $_SERVER['CONTENT_TYPE'] ) && $_SERVER['CONTENT_TYPE'] === 'application/json' ) || ( isset( $_SERVER['HTTP_ACCEPT'] ) && strpos( $_SERVER['HTTP_ACCEPT'], 'application/json' ) !== false );
	}

	/**
	 * @param \Throwable $throwable
	 *
	 * @return void
	 */
	public function jsonHandler( Throwable $throwable ): void {
		echo json_encode(
			array(
				'message' => $throwable->getMessage(),
				'file'    => $throwable->getFile(),
				'line'    => $throwable->getLine(),
				'trace'   => array_column( $throwable->getTrace(), 'file', 'function' ),
			),
			JSON_PRETTY_PRINT
		);
	}

	/**
	 * Renders the exception by loading the HTML template and replacing placeholders.
	 */
	private function render( Throwable $throwable ) {
		$layout    = Template::get_layout();
		$data      = array(
			'{{exception_message}}' => htmlspecialchars( $throwable->getMessage() ),
			'{{code_snippets}}'     => $this->generate_code_snippets( $throwable->getTrace() ),
			'{{superglobals}}'      => $this->generateSuperglobals(),
		);
		$exception = Template::get_part( 'exception' );
		$exception = Template::compile( $data, $exception );
		$output    = Template::compile( [ '{{content}}'          => $exception, ], $layout );
		http_response_code( 500 );
		echo $output;
	}

	/**
	 * Generates the HTML for code snippets based on the exception trace.
	 *
	 * @return string The HTML content for the code snippets.
	 */
	private function generate_code_snippets( array $trace ): string {
		$code_snippet_template = Template::get_part( 'code' );
		$code_snippets         = '';

		foreach ( $trace as $index => $frame ) {
			if ( ! isset( $frame['file'] ) || ! is_readable( $frame['file'] ) ) {
				continue;
			}

			$file_path    = $frame['file'];
			$line         = $frame['line'];
			$file_name    = basename( $file_path );
			$editor       = "vscode://file/$file_path:$line";
			$file_content = file_get_contents( $file_path ) ?? '';
			$lines        = explode( "\n", $file_content );
			$start_line   = max( 0, $frame['line'] - 5 );
			$end_line     = min( count( $lines ), $frame['line'] + 5 );
			$snippet      = implode( "\n", array_slice( $lines, $start_line, $end_line - $start_line ) );

			$snippet_placeholders = array(
				'{{open}}'         => $index ? '' : 'open',
				'{{even}}'         => $index % 2 ? '' : 'bg-gray-200',
				'{{editor_link}}'  => htmlspecialchars( $editor ),
				'{{file_path}}'    => htmlspecialchars( $file_path ),
				'{{start_line}}'   => $start_line,
				'{{end_line}}'     => $end_line,
				'{{line_number}}'  => $frame['line'],
				'{{code_snippet}}' => htmlspecialchars( $snippet ),
			);

			$code_snippets .= Template::compile( $snippet_placeholders, $code_snippet_template );
		}

		return $code_snippets;
	}

	/**
	 * Generates the HTML for the superglobals section.
	 *
	 * @return string The HTML content for the superglobals.
	 */
	private function generateSuperglobals(): string {
		$superglobals = array(
			'$_GET'     => $_GET,
			'$_POST'    => $_POST,
			'$_SERVER'  => $_SERVER,
			'$_FILES'   => $_FILES,
			'$_COOKIE'  => $_COOKIE,
			'$_SESSION' => $_SESSION ?? array(),
			'$_ENV'     => $_ENV,
		);

		$template = Template::get_part( 'variable' );
		$output   = '';
		$index    = 0;
		foreach ( $superglobals as $name => $value ) {
			if ( empty( $value ) ) {
				continue;
			}
			$data = array(
				'{{open}}'  => $index ? '' : 'open',
				'{{name}}'  => $name,
				'{{value}}' => var_export( $value, true ),
			);

			++$index;
			$output .= Template::compile( $data, $template );
		}

		return $output;
	}
}