<?php
/**
 * Server-side text extraction for plain-text formats.
 *
 * PDF and DOCX are parsed in the browser with PDF.js and Mammoth.js (see
 * admin/assets/js/htech-processor.js) because:
 *   - pure-PHP PDF text extraction depends on the pdftotext binary or on
 *     large Composer libraries, neither of which is available at $0 on
 *     arbitrary shared hosting;
 *   - the browser already has the file, and PDF.js/Mammoth are the same
 *     libraries the standalone widget ships.
 *
 * This class therefore handles TXT/CSV/MD/JSON (and CSV row flattening), and
 * provides a minimal, dependency-free PDF fallback that recovers text from
 * uncompressed content streams. It is honest about its limits: when the
 * fallback cannot read a file, the administrator is told to use the browser
 * parser in the admin UI.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Text_Extractor
 */
class HTECH_AI_Text_Extractor {

	/**
	 * Extract text from a local file path.
	 *
	 * @param string $path Absolute path.
	 * @param string $ext  Lowercase extension.
	 * @return array{ok:bool,text:string,method:string,error:string}
	 */
	public static function from_file( $path, $ext ) {
		$ext = strtolower( (string) $ext );

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return self::fail( __( 'The file could not be read from the server.', 'htech-ai-assistant' ) );
		}

		if ( filesize( $path ) > 1024 * 1024 * 24 ) {
			return self::fail( __( 'File is larger than the 24 MB server-side parsing limit.', 'htech-ai-assistant' ) );
		}

		switch ( $ext ) {
			case 'txt':
			case 'md':
			case 'json':
				return self::from_plain( $path, $ext );

			case 'csv':
				return self::from_csv( $path );

			case 'pdf':
				return self::from_pdf_fallback( $path );

			case 'docx':
				return self::from_docx_fallback( $path );

			default:
				return self::fail( __( 'Unsupported file type for server-side extraction.', 'htech-ai-assistant' ) );
		}
	}

	/**
	 * Build a failure response.
	 *
	 * @param string $message Message.
	 * @return array
	 */
	private static function fail( $message ) {
		return array(
			'ok'     => false,
			'text'   => '',
			'method' => '',
			'error'  => $message,
		);
	}

	/**
	 * Read a text file, handling BOM and encoding.
	 *
	 * @param string $path Path.
	 * @param string $ext  Extension.
	 * @return array
	 */
	private static function from_plain( $path, $ext ) {
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return self::fail( __( 'The text file could not be read.', 'htech-ai-assistant' ) );
		}

		// UTF-8 BOM.
		if ( 0 === strpos( $raw, "\xEF\xBB\xBF" ) ) {
			$raw = substr( $raw, 3 );
		}

		if ( ! mb_check_encoding( $raw, 'UTF-8' ) ) {
			$converted = mb_convert_encoding( $raw, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8' );
			$raw       = is_string( $converted ) ? $converted : $raw;
		}

		if ( 'json' === $ext ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$raw = self::flatten( $decoded );
			}
		}

		$text = HTECH_AI_Chunker::clean( $raw );
		if ( '' === $text ) {
			return self::fail( __( 'The file contained no readable text.', 'htech-ai-assistant' ) );
		}

		return array(
			'ok'     => true,
			'text'   => $text,
			'method' => 'php-plain',
			'error'  => '',
		);
	}

	/**
	 * Flatten a nested array into readable lines.
	 *
	 * @param mixed  $value Value.
	 * @param string $key   Current key label.
	 * @return string
	 */
	private static function flatten( $value, $key = '' ) {
		if ( is_array( $value ) ) {
			$lines = array();
			foreach ( $value as $k => $v ) {
				$lines[] = self::flatten( $v, is_string( $k ) ? $k : (string) $key );
			}
			return implode( "\n", array_filter( $lines ) );
		}
		if ( is_scalar( $value ) ) {
			$text = trim( (string) $value );
			if ( '' === $text ) {
				return '';
			}
			return $key ? $key . ': ' . $text : $text;
		}
		return '';
	}

	/**
	 * Parse CSV into "header: value" blocks per row.
	 *
	 * @param string $path Path.
	 * @return array
	 */
	private static function from_csv( $path ) {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return self::fail( __( 'The CSV file could not be opened.', 'htech-ai-assistant' ) );
		}

		$header = fgetcsv( $handle, 0, ',', '"', '\\' );
		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return self::fail( __( 'The CSV file is empty or malformed.', 'htech-ai-assistant' ) );
		}

		// Detect semicolon-delimited export.
		if ( 1 === count( $header ) && false !== strpos( (string) $header[0], ';' ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$header = fgetcsv( $handle, 0, ';', '"', '\\' );
		}

		$header = array_map(
			static function ( $h ) {
				return trim( (string) $h );
			},
			(array) $header
		);

		$blocks = array();
		$rows   = 0;

		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			if ( ! is_array( $row ) || ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) ) {
				continue;
			}
			$lines = array();
			foreach ( $row as $index => $cell ) {
				$label   = isset( $header[ $index ] ) && '' !== $header[ $index ] ? $header[ $index ] : 'Column ' . ( $index + 1 );
				$value   = trim( (string) $cell );
				if ( '' !== $value ) {
					$lines[] = $label . ': ' . $value;
				}
			}
			if ( $lines ) {
				$blocks[] = implode( "\n", $lines );
			}
			$rows++;
			if ( $rows > 5000 ) {
				break;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( ! $blocks ) {
			return self::fail( __( 'No data rows were found in the CSV file.', 'htech-ai-assistant' ) );
		}

		return array(
			'ok'     => true,
			'text'   => HTECH_AI_Chunker::clean( implode( "\n\n", $blocks ) ),
			'method' => 'php-csv',
			'error'  => '',
		);
	}

	/**
	 * Minimal dependency-free PDF text recovery.
	 *
	 * Handles the common case of PDFs whose content streams are uncompressed
	 * (many exports from LibreOffice/Word "plain" settings, LaTeX with
	 * uncompressed streams, and generated invoices). Returns a clear error
	 * for compressed or scanned PDFs so the admin knows to rely on the
	 * browser extractor (PDF.js) which handles those perfectly.
	 *
	 * @param string $path Path.
	 * @return array
	 */
	private static function from_pdf_fallback( $path ) {
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return self::fail( __( 'The PDF could not be read.', 'htech-ai-assistant' ) );
		}

		if ( 0 !== strpos( ltrim( $raw ), '%PDF' ) ) {
			return self::fail( __( 'That file is not a valid PDF document.', 'htech-ai-assistant' ) );
		}

		$chunks = array();

		if ( preg_match_all( '/stream\r?\n?(.*?)\r?\n?endstream/s', $raw, $matches ) ) {
			foreach ( $matches[1] as $stream ) {
				// Inflate deflate-compressed streams when zlib is available.
				$decoded = false;
				if ( "\x78" === substr( $stream, 0, 1 ) && function_exists( 'gzuncompress' ) ) {
					$try = @gzuncompress( $stream ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					if ( false === $try ) {
						$try = @gzinflate( substr( $stream, 2 ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					}
					$decoded = is_string( $try ) ? $try : false;
				}
				$text = self::extract_pdf_text_operators( false === $decoded ? $stream : $decoded );
				if ( '' !== $text ) {
					$chunks[] = $text;
				}
			}
		}

		$text = HTECH_AI_Chunker::clean( implode( "\n\n", $chunks ) );

		// Heuristic: real prose has letters and spaces in a sane ratio.
		$letters = preg_match_all( '/[A-Za-z]/', $text );
		if ( mb_strlen( $text ) < 40 || $letters < 20 ) {
			return self::fail(
				__( 'This PDF uses compressed or scanned text streams that cannot be parsed without PDF.js. Open the Documents screen and use the browser extractor, or upload a DOCX/TXT copy.', 'htech-ai-assistant' )
			);
		}

		return array(
			'ok'     => true,
			'text'   => $text,
			'method' => 'php-pdf-basic',
			'error'  => '',
		);
	}

	/**
	 * Pull literal strings out of PDF content-stream text operators.
	 *
	 * @param string $content Stream content.
	 * @return string
	 */
	private static function extract_pdf_text_operators( $content ) {
		$lines = array();

		// Tj / TJ arrays with parenthesised literals and hex strings.
		if ( preg_match_all( '/\((?:\\.|[^\\\)])*\)|<[0-9A-Fa-f\s]+>/', $content, $m ) ) {
			$buffer = '';
			foreach ( $m[0] as $token ) {
				if ( '<' === $token[0] ) {
					$hex  = preg_replace( '/[^0-9A-Fa-f]/', '', substr( $token, 1, -1 ) );
					$hex .= ( strlen( $hex ) % 2 ) ? '0' : '';
					$decoded = '';
					for ( $i = 0; $i < strlen( $hex ); $i += 2 ) {
						$decoded .= chr( hexdec( substr( $hex, $i, 2 ) ) );
					}
					$buffer .= $decoded;
					continue;
				}

				$literal = substr( $token, 1, -1 );
				$literal = preg_replace_callback(
					'/\\\\([nrtbf()\\\\]|[0-7]{1,3})/',
					static function ( $esc ) {
						$map = array( 'n' => "\n", 'r' => '', 't' => ' ', 'b' => '', 'f' => '', '(' => '(', ')' => ')', '\\' => '\\' );
						if ( isset( $map[ $esc[1] ] ) ) {
							return $map[ $esc[1] ];
						}
						if ( preg_match( '/^[0-7]{1,3}$/', $esc[1] ) ) {
							return chr( octdec( $esc[1] ) );
						}
						return $esc[1];
					},
					$literal
				);
				$buffer .= $literal;
			}

			// Split on PDF text-positioning operators where possible.
			$buffer = preg_replace( '/(\))\s*(Td|TD|T\*|ET)/', "$1\n", $buffer );
			$buffer = str_replace( array( ')Tj', 'Tj', ') TJ', 'TJ' ), '', $buffer );

			// Adjacent text runs are separate words; without a separator they
			// concatenate into unreadable strings like "LocationOur office".
			$buffer = preg_replace( '/(\S)\n(\S)/u', "$1\n$2", $buffer );
			$buffer = preg_replace( '/([a-z0-9.,;:!?])([A-Z])/u', "$1 $2", $buffer );

			$clean = trim( preg_replace( '/[^\P{C}\n]+/u', '', $buffer ) );
			if ( mb_strlen( $clean ) > 1 ) {
				$lines[] = $clean;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * DOCX fallback: read word/document.xml straight from the ZIP.
	 *
	 * PHP's bundled ZipArchive can read DOCX without any external library,
	 * so DOCX extraction genuinely works server-side. The browser path
	 * (Mammoth.js) is still preferred because it preserves list structure.
	 *
	 * @param string $path Path.
	 * @return array
	 */
	private static function from_docx_fallback( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return self::fail( __( 'The PHP zip extension is not available on this server. Use the browser extractor on the Documents screen.', 'htech-ai-assistant' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return self::fail( __( 'The DOCX file is corrupted and could not be opened.', 'htech-ai-assistant' ) );
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml || '' === $xml ) {
			return self::fail( __( 'This is not a valid DOCX document (no document.xml).', 'htech-ai-assistant' ) );
		}

		// Paragraphs and line breaks become newlines; tabs become spaces.
		$xml = preg_replace( '/<w:tab[^>]*\/>/u', ' ', $xml );
		$xml = preg_replace( '/<w:br[^>]*\/>/u', "\n", $xml );
		$xml = preg_replace( '/<\/w:p>/u', "\n\n", $xml );

		$text = wp_strip_all_tags( (string) $xml, false );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
		$text = HTECH_AI_Chunker::clean( $text );

		if ( mb_strlen( $text ) < 5 ) {
			return self::fail( __( 'The DOCX file contains no readable text.', 'htech-ai-assistant' ) );
		}

		return array(
			'ok'     => true,
			'text'   => $text,
			'method' => 'php-docx',
			'error'  => '',
		);
	}

	/**
	 * Extract readable text from an HTML string (website importer).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function from_html( $html ) {
		$html = (string) $html;

		// Remove non-content elements wholesale.
		$html = preg_replace( '#<(script|style|noscript|svg|iframe|form|nav|footer|header)\b[^>]*>.*?</\1>#is', ' ', $html );
		$html = preg_replace( '#<!--.*?-->#s', ' ', (string) $html );

		// Keep heading/paragraph structure as line breaks.
		$html = preg_replace( '#</(p|div|li|h[1-6]|tr|section|article|br)>#i', "\n", (string) $html );
		$html = preg_replace( '#<li[^>]*>#i', "\n- ", (string) $html );

		$text = wp_strip_all_tags( (string) $html, false );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return HTECH_AI_Chunker::clean( $text );
	}
}
