<?php
/**
 * Text chunking and cleaning helpers.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Chunker
 */
class HTECH_AI_Chunker {

	/**
	 * Normalise whitespace/boilerplate in extracted text.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function clean( $text ) {
		$text = wp_check_invalid_utf8( (string) $text );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );

		// Drop control chars except newline/tab.
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );

		// Collapse runs of blank lines and horizontal whitespace.
		$text = preg_replace( '/[ \t]+/u', ' ', $text );
		$text = preg_replace( "/\n{3,}/u", "\n\n", $text );

		// Strip repeated page furniture PDF extraction leaves behind.
		$text = preg_replace( '/^\s*(page\s+\d+(\s+of\s+\d+)?)\s*$/imu', '', $text );

		return trim( (string) $text );
	}

	/**
	 * Split text into overlapping chunks on natural boundaries.
	 *
	 * @param string $text       Cleaned text.
	 * @param int    $size        Target characters per chunk.
	 * @param int    $overlap     Overlap characters.
	 * @param bool   $force_split Split by blank lines even when the document is
	 *                            short (used for tabular CSV data).
	 * @return string[]
	 */
	public static function split( $text, $size = null, $overlap = null, $force_split = false ) {
		$size    = null === $size ? (int) HTECH_AI_Settings::get( 'chunk_size', 900 ) : (int) $size;
		$overlap = null === $overlap ? (int) HTECH_AI_Settings::get( 'chunk_overlap', 120 ) : (int) $overlap;

		$size    = max( 200, min( 4000, $size ) );
		$overlap = max( 0, min( (int) ( $size / 2 ), $overlap ) );

		$text = self::clean( $text );
		if ( '' === $text ) {
			return array();
		}

		// Very small documents stay as one chunk (unless row-based splitting
		// was requested for tabular data).
		if ( ! $force_split && mb_strlen( $text ) <= (int) ( $size * 1.2 ) ) {
			return array( $text );
		}

		$blocks = preg_split( "/\n{2,}/u", $text );

		// Tabular data (CSV rows): one record per chunk, no stitching.
		if ( $force_split ) {
			$rows = array();
			foreach ( (array) $blocks as $block ) {
				$block = trim( $block );
				if ( mb_strlen( $block ) > 20 ) {
					$rows[] = $block;
				}
			}
			return $rows;
		}

		$chunks = array();
		$buffer = '';

		foreach ( (array) $blocks as $block ) {
			$block = trim( $block );
			if ( '' === $block ) {
				continue;
			}

			if ( mb_strlen( $buffer ) + mb_strlen( $block ) + 2 <= $size ) {
				$buffer .= ( '' === $buffer ? '' : "\n\n" ) . $block;
				continue;
			}

			if ( '' !== $buffer ) {
				$chunks[] = trim( $buffer );
			}

			// A single block larger than the target gets split by sentence.
			if ( mb_strlen( $block ) > $size ) {
				foreach ( self::split_sentences( $block, $size ) as $piece ) {
					$chunks[] = $piece;
				}
				$buffer = '';
			} else {
				$buffer = $block;
			}
		}

		if ( '' !== trim( $buffer ) ) {
			$chunks[] = trim( $buffer );
		}

		if ( $overlap > 0 && count( $chunks ) > 1 ) {
			$chunks = self::apply_overlap( $chunks, $overlap );
		}

		return array_values( array_filter( $chunks, static function ( $c ) {
			return mb_strlen( trim( $c ) ) > 20;
		} ) );
	}

	/**
	 * Split an oversized block on sentence boundaries.
	 *
	 * @param string $block Block text.
	 * @param int    $size  Target size.
	 * @return string[]
	 */
	private static function split_sentences( $block, $size ) {
		$sentences = preg_split( '/(?<=[.!?])\s+/u', $block );
		$out       = array();
		$current   = '';

		foreach ( (array) $sentences as $sentence ) {
			$sentence = trim( $sentence );
			if ( '' === $sentence ) {
				continue;
			}
			if ( mb_strlen( $current ) + mb_strlen( $sentence ) + 1 > $size && '' !== $current ) {
				$out[]   = $current;
				$current = $sentence;
				continue;
			}
			$current .= ( '' === $current ? '' : ' ' ) . $sentence;
		}

		if ( '' !== trim( $current ) ) {
			$out[] = $current;
		}

		return $out;
	}

	/**
	 * Prepend the tail of the previous chunk so answers spanning a boundary
	 * are still retrievable.
	 *
	 * @param string[] $chunks  Chunks.
	 * @param int      $overlap Overlap characters.
	 * @return string[]
	 */
	private static function apply_overlap( array $chunks, $overlap ) {
		$out = array();
		foreach ( $chunks as $index => $chunk ) {
			if ( 0 === $index ) {
				$out[] = $chunk;
				continue;
			}
			$previous = $chunks[ $index - 1 ];
			$tail     = mb_substr( $previous, max( 0, mb_strlen( $previous ) - $overlap ) );
			// Start the tail at a word boundary.
			$space = mb_strpos( $tail, ' ' );
			if ( false !== $space && $space < mb_strlen( $tail ) - 1 ) {
				$tail = mb_substr( $tail, $space + 1 );
			}
			$out[] = trim( $tail . ' ' . $chunk );
		}
		return $out;
	}
}
