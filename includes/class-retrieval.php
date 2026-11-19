<?php
/**
 * Local retrieval engine: BM25-style keyword scoring with a cached
 * document-frequency index. No vector database, no paid API.
 *
 * Why BM25 instead of embeddings?
 *  - Embeddings need either a paid API or a ~25MB+ model download in the
 *    browser; BM25 runs in plain SQL/PHP on the host and answers most
 *    business questions ("price", "opening hours", "do you deliver to X")
 *    extremely well because the vocabulary is small and repetitive.
 *  - It keeps the $0 budget real and works on any shared host.
 *
 * The scoring function is textbook BM25 (k1=1.2, b=0.75) over term
 * frequencies, with extra credit for exact phrase matches and FAQ entries.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Retrieval
 */
class HTECH_AI_Retrieval {

	const K1 = 1.2;
	const B  = 0.75;

	/**
	 * English stop words. Short function words only – never business nouns.
	 *
	 * @var string[]
	 */
	private static $stopwords = array(
		'a', 'about', 'above', 'after', 'again', 'all', 'am', 'an', 'and', 'any', 'are', 'as', 'at',
		'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by',
		'can', 'could', 'did', 'do', 'does', 'doing', 'done', 'down', 'during',
		'each', 'few', 'for', 'from', 'further', 'had', 'has', 'have', 'having', 'he', 'her', 'here',
		'hers', 'him', 'his', 'how', 'i', 'if', 'in', 'into', 'is', 'it', 'its', 'just',
		'me', 'more', 'most', 'my', 'no', 'nor', 'not', 'now', 'of', 'off', 'on', 'once', 'only',
		'or', 'other', 'our', 'out', 'over', 'own', 'please', 'same', 'she', 'should', 'so', 'some',
		'such', 'than', 'that', 'the', 'their', 'them', 'then', 'there', 'these', 'they', 'this',
		'those', 'through', 'to', 'too', 'under', 'until', 'up', 'very', 'was', 'we', 'were', 'what',
		'when', 'where', 'which', 'while', 'who', 'whom', 'why', 'will', 'with', 'would', 'you',
		'your', 'yours', 'hi', 'hello', 'hey', 'thanks', 'thank', 'ok', 'okay',
	);

	/**
	 * Generic query words that say nothing about *what* is being asked.
	 *
	 * These are ignored by the unknown-entity guard, because "do you offer X"
	 * is a valid question even when the corpus never uses the word "offer".
	 *
	 * @var string[]
	 */
	private static $generic_query_words = array(
		'offer', 'offered', 'offering', 'provide', 'provided', 'available', 'have', 'has', 'get',
		'need', 'want', 'looking', 'look', 'sell', 'sold', 'help', 'support', 'info', 'information',
		'detail', 'details', 'tell', 'know', 'ask', 'question', 'answer', 'work', 'worked', 'run',
		'type', 'kind', 'sort', 'list', 'option', 'choose', 'recommend', 'best', 'good', 'many',
		'much', 'cost', 'price', 'charge', 'fee', 'require', 'requirement', 'process', 'step',
		'able', 'possible', 'long', 'soon', 'usually', 'generally', 'exactly', 'really', 'actually',
	);

	/**
	 * Small synonym map for the intents businesses get asked most.
	 *
	 * @var array<string,string[]>
	 */
	private static $synonyms = array(
		'price'    => array( 'cost', 'pricing', 'fee', 'fees', 'rate', 'rates', 'charge', 'how much', 'quote' ),
		'hours'    => array( 'open', 'opening', 'closed', 'closing', 'time', 'times', 'schedule' ),
		'contact'  => array( 'phone', 'email', 'call', 'reach', 'number', 'whatsapp', 'address' ),
		'delivery' => array( 'shipping', 'deliver', 'ship', 'courier', 'dispatch' ),
		'return'   => array( 'refund', 'returns', 'refunds', 'exchange', 'guarantee', 'warranty' ),
		'service'  => array( 'services', 'solution', 'solutions', 'offering', 'offerings' ),
		'location' => array( 'address', 'located', 'office', 'branch', 'where' ),
		'payment'  => array( 'pay', 'card', 'transfer', 'invoice', 'checkout' ),
	);

	/**
	 * Default index version when none stored.
	 */
	const VERSION_OPTION = 'htech_ai_index_version';

	/* ---------------------------------------------------------------------
	 * Text analysis
	 * ------------------------------------------------------------------ */

	/**
	 * Lowercase, strip punctuation/diacritics and split into tokens.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function tokenize( $text ) {
		$text = (string) $text;
		$text = wp_check_invalid_utf8( $text );
		$text = function_exists( 'remove_accents' ) ? remove_accents( $text ) : $text;
		$text = mb_strtolower( $text );
		$text = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text );

		$tokens = preg_split( '/\s+/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $tokens ) ? $tokens : array();
	}

	/**
	 * Very light stemmer (plural / verb-ending folding) to improve recall.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function stem( $token ) {
		$length = mb_strlen( $token );
		if ( $length > 4 && 'ies' === mb_substr( $token, -3 ) ) {
			return mb_substr( $token, 0, -3 ) . 'y';
		}
		if ( $length > 4 && 'es' === mb_substr( $token, -2 ) ) {
			return mb_substr( $token, 0, -2 );
		}
		if ( $length > 3 && 's' === mb_substr( $token, -1 ) && 's' !== mb_substr( $token, -2, 1 ) ) {
			return mb_substr( $token, 0, -1 );
		}
		if ( $length > 5 && 'ing' === mb_substr( $token, -3 ) ) {
			return mb_substr( $token, 0, -3 );
		}
		if ( $length > 4 && 'ed' === mb_substr( $token, -2 ) ) {
			return mb_substr( $token, 0, -2 );
		}
		return $token;
	}

	/**
	 * Remove stop words and stem.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function normalize_tokens( $text ) {
		$out = array();
		foreach ( self::tokenize( $text ) as $token ) {
			if ( mb_strlen( $token ) < 2 ) {
				continue;
			}
			if ( in_array( $token, self::$stopwords, true ) ) {
				continue;
			}
			if ( preg_match( '/^\d+$/', $token ) ) {
				$out[] = $token;
				continue;
			}
			$out[] = self::stem( $token );
		}
		return $out;
	}

	/**
	 * Build the stored term payload for a chunk.
	 *
	 * @param string $content Chunk text.
	 * @return array{terms:array<string,int>,len:int}
	 */
	public static function build_term_data( $content ) {
		$terms = array();
		$all   = self::normalize_tokens( $content );

		foreach ( $all as $token ) {
			$terms[ $token ] = isset( $terms[ $token ] ) ? $terms[ $token ] + 1 : 1;
		}

		return array(
			'terms' => $terms,
			'len'   => count( $all ),
		);
	}

	/**
	 * Expand a query with synonyms.
	 *
	 * @param string[] $tokens Query tokens.
	 * @return array<string,float> token => weight
	 */
	public static function expand_query( array $tokens ) {
		$weighted = array();

		foreach ( $tokens as $token ) {
			$weighted[ $token ] = isset( $weighted[ $token ] ) ? $weighted[ $token ] : 1.0;

			if ( isset( self::$synonyms[ $token ] ) ) {
				foreach ( self::$synonyms[ $token ] as $extra ) {
					foreach ( self::normalize_tokens( $extra ) as $syn ) {
						$weighted[ $syn ] = isset( $weighted[ $syn ] ) ? $weighted[ $syn ] : 0.45;
					}
				}
			}

			foreach ( self::$synonyms as $canonical => $list ) {
				if ( in_array( $token, $list, true ) ) {
					foreach ( self::normalize_tokens( $canonical ) as $syn ) {
						$weighted[ $syn ] = isset( $weighted[ $syn ] ) ? $weighted[ $syn ] : 0.45;
					}
				}
			}
		}

		return $weighted;
	}

	/* ---------------------------------------------------------------------
	 * Corpus statistics (cached; invalidated by index version)
	 * ------------------------------------------------------------------ */

	/**
	 * Current index version.
	 *
	 * @return string
	 */
	public static function version() {
		return (string) get_option( self::VERSION_OPTION, '1' );
	}

	/**
	 * Bump index version so cached statistics are discarded.
	 *
	 * @return void
	 */
	public static function bump_version() {
		update_option( self::VERSION_OPTION, (string) microtime( true ), false );
	}

	/**
	 * Number of chunks and average chunk length.
	 *
	 * @return array{n:int,avgdl:float}
	 */
	public static function corpus_stats() {
		global $wpdb;

		$key    = 'htech_ai_corpus_' . md5( self::version() );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = HTECH_AI_Database::chunks_table();
		$n     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery

		$lens = $wpdb->get_col( "SELECT token_count FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$sum  = 0;
		foreach ( (array) $lens as $len ) {
			$sum += (int) $len;
		}

		$stats = array(
			'n'     => $n,
			'avgdl' => $n > 0 ? max( 1.0, $sum / $n ) : 1.0,
		);

		set_transient( $key, $stats, 6 * HOUR_IN_SECONDS );
		return $stats;
	}

	/**
	 * Document frequency for a term (number of chunks containing it).
	 *
	 * @param string $term Term.
	 * @return int
	 */
	public static function doc_frequency( $term ) {
		global $wpdb;

		$key    = 'htech_ai_df_' . md5( self::version() . '|' . $term );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table = HTECH_AI_Database::chunks_table();
		$needle = '%' . $wpdb->esc_like( '"' . $term . '":' ) . '%';

		$df = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE term_data LIKE %s", $needle ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		set_transient( $key, $df, 6 * HOUR_IN_SECONDS );
		return $df;
	}

	/**
	 * IDF with the standard BM25 probabilistic formula (always positive).
	 *
	 * @param string $term Term.
	 * @param int    $n    Corpus size.
	 * @return float
	 */
	/**
	 * Document frequency for every indexed term.
	 *
	 * Term data is stored as JSON on the chunk row rather than one row per
	 * token, so document frequencies are computed once per corpus and memoised
	 * — a single pass over the column, then an O(1) lookup.
	 *
	 * @return array term => number of chunks containing it
	 */
	public static function document_frequencies() {
		$cache_key = 'htech_ai_df_' . self::corpus_signature();
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = HTECH_AI_Database::chunks_table();
		$rows  = $wpdb->get_col( "SELECT term_data FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$counts = array();

		foreach ( (array) $rows as $json ) {
			$data = json_decode( (string) $json, true );

			if ( empty( $data['terms'] ) || ! is_array( $data['terms'] ) ) {
				continue;
			}

			foreach ( array_keys( $data['terms'] ) as $term ) {
				$counts[ $term ] = ( $counts[ $term ] ?? 0 ) + 1;
			}
		}

		set_transient( $cache_key, $counts, 10 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Drop memoised corpus statistics after any index mutation.
	 *
	 * @return void
	 */
	public static function flush_corpus_cache() {
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_htech_ai_df_%' OR option_name LIKE '_transient_timeout_htech_ai_df_%'"
		);

		foreach ( array( 'htech_ai_corpus_stats' ) as $key ) {
			delete_transient( $key );
		}
	}

	/**
	 * Cheap fingerprint so term-frequency caches invalidate after a reindex.
	 *
	 * @return string
	 */
	public static function corpus_signature() {
		$stats = self::corpus_stats();

		return md5( (string) ( $stats['n'] ?? 0 ) . '|' . (string) ( $stats['avgdl'] ?? 0 ) );
	}

	public static function idf( $term, $n ) {
		$df = self::doc_frequency( $term );
		return log( 1 + ( max( 0, $n - $df ) + 0.5 ) / ( $df + 0.5 ) );
	}

	/* ---------------------------------------------------------------------
	 * Search
	 * ------------------------------------------------------------------ */

	/**
	 * Search the knowledge base.
	 *
	 * @param string $query Visitor question.
	 * @param array  $args  top_k, min_score, source_type.
	 * @return array<int,array> Ranked results.
	 */
	public function search( $query, array $args = array() ) {
		global $wpdb;

		$query = trim( (string) $query );
		if ( '' === $query ) {
			return array();
		}

		$args = wp_parse_args(
			$args,
			array(
				'top_k'       => (int) HTECH_AI_Settings::get( 'retrieval_top_k', 4 ),
				'min_score'   => (float) HTECH_AI_Settings::get( 'retrieval_min_score', 0.18 ),
				'source_type' => '',
			)
		);

		$tokens = self::normalize_tokens( $query );
		if ( ! $tokens ) {
			// Numbers or symbols only – fall back to a literal LIKE search.
			$tokens = array_filter( self::tokenize( $query ), 'strlen' );
		}
		$weighted = self::expand_query( $tokens );
		if ( ! $weighted ) {
			return array();
		}

		/**
		 * Filter the weighted query terms before scoring.
		 *
		 * @param array  $weighted Term => weight map.
		 * @param string $query    Original query.
		 */
		$weighted = apply_filters( 'htech_ai_retrieval_query_terms', $weighted, $query );

		$table = HTECH_AI_Database::chunks_table();
		$docs  = HTECH_AI_Database::documents_table();

		$where  = '1=1';
		$params = array();
		if ( $args['source_type'] ) {
			$where   .= ' AND k.source_type = %s';
			$params[] = $args['source_type'];
		}

		$sql = "SELECT k.id, k.document_id, k.kb_entry_id, k.source_type, k.source_label, k.chunk_index,
					k.content, k.token_count, k.term_data
				FROM {$table} k
				LEFT JOIN {$docs} d ON d.id = k.document_id
				WHERE {$where}
				  AND ( k.source_type <> 'document' OR d.is_active = 1 )";

		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		if ( ! $rows ) {
			return array();
		}

		$corpus  = self::corpus_stats();
		$n       = max( 1, $corpus['n'] );
		$avgdl   = $corpus['avgdl'];
		$idf_map         = array();
		$total_query_idf = 0.0;
		$missing_query   = 0.0;

		foreach ( $weighted as $term => $weight ) {
			$idf_map[ $term ] = self::idf( $term, $n ) * (float) $weight;
			$total_query_idf += $idf_map[ $term ];
		}

		/*
		 * Unknown-entity guard.
		 *
		 * A term that appears in *no* indexed chunk is something this business
		 * has never documented ("president", "Abuja", "iPhone"). If such terms
		 * dominate the question, the remaining matches are coincidental function
		 * words and answering would mean guessing — so we refuse instead. This
		 * is the anti-hallucination rule enforced at retrieval time rather than
		 * relying on the model to admit ignorance.
		 *
		 * Generic question words ("do you offer…") are excluded so ordinary
		 * questions still resolve; digits are ignored so product codes and
		 * prices in the query never trigger a false refusal.
		 */
		if ( $total_query_idf > 0 ) {
			$df_map    = self::document_frequencies();
			$unknown   = 0.0;
			$considered = 0.0;

			foreach ( $idf_map as $term => $idf ) {
				if ( in_array( $term, self::$generic_query_words, true ) || is_numeric( $term ) ) {
					continue;
				}

				$considered += $idf;

				if ( empty( $df_map[ $term ] ) ) {
					$unknown += $idf;
				}
			}

			if ( $considered > 0 && ( $unknown / $considered ) > 0.6 ) {
				return array();
			}
		}

		$phrase   = mb_strtolower( trim( preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $query ) ) );

		/*
		 * Bigrams of the *literal* query tokens.
		 *
		 * "price of a business website" contains the pair "business website",
		 * and a chunk that says "Business Website: 850,000 NGN" is a far better
		 * answer than one that merely contains the words "business" and "price"
		 * separately (e.g. "Business Card Print … Price NGN: 25000"). Single
		 * term BM25 cannot tell those apart; adjacent-pair matching can.
		 */
		$query_tokens = self::normalize_tokens( $query );
		$bigrams      = array();

		for ( $i = 0; $i < count( $query_tokens ) - 1; $i++ ) {
			$pair = $query_tokens[ $i ] . ' ' . $query_tokens[ $i + 1 ];

			if ( mb_strlen( $pair ) > 6 ) {
				$bigrams[] = $pair;
			}
		}
		$results  = array();

		foreach ( $rows as $row ) {
			$term_data = json_decode( (string) $row['term_data'], true );
			if ( ! is_array( $term_data ) || empty( $term_data['terms'] ) ) {
				continue;
			}

			$tf_map  = $term_data['terms'];
			$dl      = max( 1, (int) ( $term_data['len'] ?? $row['token_count'] ) );
			$raw     = 0.0;
			$matched = 0;

			foreach ( $idf_map as $term => $idf ) {
				if ( empty( $tf_map[ $term ] ) ) {
					continue;
				}
				$tf     = (int) $tf_map[ $term ];
				$raw   += $idf * ( ( $tf * ( self::K1 + 1 ) ) / ( $tf + self::K1 * ( 1 - self::B + self::B * ( $dl / $avgdl ) ) ) );
				$matched++;
			}

			if ( $raw <= 0 ) {
				continue;
			}

			// Coverage: reward chunks that answer multiple distinct query terms.
			$coverage = $matched / max( 1, count( $idf_map ) );
			$raw     *= ( 0.75 + 0.5 * $coverage );

			// Exact phrase bonus.
			$haystack = mb_strtolower( preg_replace( '/\s+/u', ' ', (string) $row['content'] ) );

			if ( mb_strlen( $phrase ) > 2 && false !== mb_strpos( $haystack, $phrase ) ) {
				$raw += 2.5;
			}

			// Adjacent-pair bonus (stemmed comparison, so "websites" matches).
			if ( $bigrams ) {
				$stemmed_haystack = implode( ' ', self::normalize_tokens( $haystack ) );

				foreach ( $bigrams as $pair ) {
					if ( false !== mb_strpos( $stemmed_haystack, $pair ) ) {
						$raw += 2.0;
					}
				}
			}

			// FAQ / manual entries are curated answers, so give them a nudge.
			if ( 'manual' === $row['source_type'] ) {
				$raw += 0.6;
			}

			$normalised = $raw / ( $raw + 2.2 );

			$results[] = array(
				'id'           => (int) $row['id'],
				'document_id'  => (int) $row['document_id'],
				'kb_entry_id'  => (int) $row['kb_entry_id'],
				'source_type'  => (string) $row['source_type'],
				'source_label' => (string) $row['source_label'],
				'chunk_index'  => (int) $row['chunk_index'],
				'content'      => (string) $row['content'],
				'score'        => round( $normalised, 4 ),
				'raw_score'    => round( $raw, 4 ),
				'matched'      => $matched,
			);
		}

		usort(
			$results,
			static function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return 0;
				}
				return ( $a['score'] < $b['score'] ) ? 1 : -1;
			}
		);

		$results = array_values(
			array_filter(
				$results,
				static function ( $item ) use ( $args ) {
					return $item['score'] >= $args['min_score'];
				}
			)
		);

		$results = array_slice( $results, 0, max( 1, (int) $args['top_k'] ) );

		return apply_filters( 'htech_ai_retrieval_results', $results, $query );
	}

	/**
	 * Build the context block handed to the language model.
	 *
	 * Retrieved text is clearly delimited and labelled as untrusted reference
	 * material so a document cannot impersonate system instructions.
	 *
	 * @param array $results Search results.
	 * @param int   $max_chars Character budget.
	 * @return array{context:string,sources:array}
	 */
	public function build_context( array $results, $max_chars = 6000 ) {
		$parts   = array();
		$sources = array();
		$used    = 0;

		foreach ( $results as $result ) {
			$label = $result['source_label'] ? $result['source_label'] : __( 'Business knowledge', 'htech-ai-assistant' );
			$text  = trim( preg_replace( '/\s+/u', ' ', $result['content'] ) );

			if ( mb_strlen( $text ) > 1500 ) {
				$text = mb_substr( $text, 0, 1500 ) . '…';
			}

			$block = sprintf( "[SOURCE: %s]\n%s", $label, $text );
			if ( $used + mb_strlen( $block ) > $max_chars ) {
				break;
			}
			$parts[] = $block;
			$used   += mb_strlen( $block );

			$sources[] = array(
				'label' => $label,
				'type'  => $result['source_type'],
				'score' => $result['score'],
				'id'    => $result['document_id'] ? $result['document_id'] : $result['kb_entry_id'],
			);
		}

		return array(
			'context' => implode( "\n\n", $parts ),
			'sources' => $sources,
		);
	}

	/**
	 * Keyword fingerprint of a text, stored on chunks for quick display.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max keywords.
	 * @return string
	 */
	public static function keywords( $text, $max = 6 ) {
		$counts = array();
		foreach ( self::normalize_tokens( $text ) as $token ) {
			if ( mb_strlen( $token ) < 4 ) {
				continue;
			}
			$counts[ $token ] = isset( $counts[ $token ] ) ? $counts[ $token ] + 1 : 1;
		}
		arsort( $counts );
		return implode( ', ', array_slice( array_keys( $counts ), 0, $max ) );
	}
}
