<?php
/**
 * Business knowledge base: manual entries, FAQs and (re)indexing.
 *
 * Manual knowledge is stored as a small, versioned option and re-chunked
 * into the shared chunk table so a single retrieval engine serves documents
 * and hand-written knowledge alike.
 *
 * @package HTECH_AI_Assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HTECH_AI_Knowledge_Base
 */
class HTECH_AI_Knowledge_Base {

	const OPTION = 'htech_ai_kb';

	/**
	 * Canonical manual sections.
	 *
	 * @return array<string,array>
	 */
	public static function sections() {
		return array(
			'overview' => array(
				'title'       => __( 'Company overview', 'htech-ai-assistant' ),
				'placeholder' => __( 'A short paragraph describing what the business does, who it serves and what makes it different.', 'htech-ai-assistant' ),
			),
			'about'    => array(
				'title'       => __( 'About us', 'htech-ai-assistant' ),
				'placeholder' => __( 'History, team, mission, years in business, certifications.', 'htech-ai-assistant' ),
			),
			'services' => array(
				'title'       => __( 'Services', 'htech-ai-assistant' ),
				'placeholder' => __( "List each service with a one-line description, e.g.\nWebsite design — responsive marketing sites from concept to launch.", 'htech-ai-assistant' ),
			),
			'products' => array(
				'title'       => __( 'Products', 'htech-ai-assistant' ),
				'placeholder' => __( "Product names, variants, sizes, materials and availability.", 'htech-ai-assistant' ),
			),
			'pricing'  => array(
				'title'       => __( 'Pricing', 'htech-ai-assistant' ),
				'placeholder' => __( "Always state prices exactly as the business publishes them. If prices are quote-only, say so explicitly.\ne.g. Starter package — ₦150,000. Custom work — request a quote.", 'htech-ai-assistant' ),
			),
			'policies' => array(
				'title'       => __( 'Business policies', 'htech-ai-assistant' ),
				'placeholder' => __( 'Terms of service, cancellations, guarantees, privacy commitments.', 'htech-ai-assistant' ),
			),
			'hours'    => array(
				'title'       => __( 'Opening hours', 'htech-ai-assistant' ),
				'placeholder' => __( "e.g. Monday–Friday 9:00–17:00, Saturday 10:00–14:00, closed Sunday.", 'htech-ai-assistant' ),
			),
			'contact'  => array(
				'title'       => __( 'Contact details', 'htech-ai-assistant' ),
				'placeholder' => __( "Phone, WhatsApp, email, physical address, social handles.", 'htech-ai-assistant' ),
			),
			'delivery' => array(
				'title'       => __( 'Delivery information', 'htech-ai-assistant' ),
				'placeholder' => __( 'Delivery areas, timelines, fees, pickup options.', 'htech-ai-assistant' ),
			),
			'returns'  => array(
				'title'       => __( 'Returns & refunds', 'htech-ai-assistant' ),
				'placeholder' => __( 'Return window, conditions, refund method and timelines.', 'htech-ai-assistant' ),
			),
			'other'    => array(
				'title'       => __( 'Other relevant information', 'htech-ai-assistant' ),
				'placeholder' => __( 'Anything else the assistant should know — booking process, service areas, payment methods.', 'htech-ai-assistant' ),
			),
		);
	}

	/**
	 * Read the whole knowledge store with defaults.
	 *
	 * @return array
	 */
	public function all() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$sections = array();
		foreach ( array_keys( self::sections() ) as $key ) {
			$sections[ $key ] = array(
				'content' => isset( $stored['sections'][ $key ]['content'] ) ? (string) $stored['sections'][ $key ]['content'] : '',
				'active'  => isset( $stored['sections'][ $key ]['active'] ) ? (int) $stored['sections'][ $key ]['active'] : 1,
				'updated' => isset( $stored['sections'][ $key ]['updated'] ) ? (int) $stored['sections'][ $key ]['updated'] : 0,
			);
		}

		return array(
			'sections' => $sections,
			'faqs'     => isset( $stored['faqs'] ) && is_array( $stored['faqs'] ) ? $stored['faqs'] : array(),
			'custom'   => isset( $stored['custom'] ) && is_array( $stored['custom'] ) ? $stored['custom'] : array(),
			'updated'  => isset( $stored['updated'] ) ? (int) $stored['updated'] : 0,
		);
	}

	/**
	 * Persist the store.
	 *
	 * @param array $data Data.
	 * @return void
	 */
	private function save( array $data ) {
		$data['updated'] = time();
		update_option( self::OPTION, $data, 'no' );
		$this->reindex();
	}

	/**
	 * Save one manual section.
	 *
	 * @param string $key     Section key.
	 * @param string $content Content.
	 * @param int    $active  Active flag.
	 * @return void
	 */
	public function save_section( $key, $content, $active = 1 ) {
		if ( ! array_key_exists( $key, self::sections() ) ) {
			return;
		}
		$data                          = $this->all();
		$data['sections'][ $key ]      = array(
			'content' => sanitize_textarea_field( (string) $content ),
			'active'  => $active ? 1 : 0,
			'updated' => time(),
		);
		$this->save( $data );
	}

	/**
	 * Populate a brand-new install with clearly-labelled starter content.
	 *
	 * A fresh plugin with an empty knowledge base answers "I don't have enough
	 * verified information" to everything, which reads like a broken install.
	 * These entries are obvious placeholders the administrator replaces — they
	 * are only written once, and never overwrite existing content.
	 *
	 * @return bool True when sample content was added.
	 */
	public function seed_sample_content() {
		$data = $this->all();

		$has_content = ! empty( $data['faqs'] );

		foreach ( $data['sections'] as $section ) {
			if ( '' !== trim( (string) ( $section['content'] ?? '' ) ) ) {
				$has_content = true;
				break;
			}
		}

		if ( $has_content ) {
			return false; // Never touch a knowledge base that already has content.
		}

		$samples = array(
			'overview' => __( "SAMPLE CONTENT — replace this with your real business description.\n\nWe are a small team helping customers with quality products and friendly service. Tell visitors what you do, who you serve and what makes you different.", 'htech-ai-assistant' ),
			'services' => __( "SAMPLE CONTENT — list your real services, one per line.\n\nExample: Website design — responsive marketing sites built to convert.\nExample: Search engine optimisation — technical and content SEO.", 'htech-ai-assistant' ),
			'hours'    => __( "SAMPLE CONTENT — set your real opening hours.\n\nExample: Monday to Friday, 9am to 5pm. Closed on public holidays.", 'htech-ai-assistant' ),
			'contact'  => __( "SAMPLE CONTENT — add your real contact details.\n\nExample: Phone: +234 800 000 0000\nEmail: hello@example.com\nAddress: 1 Example Street, Lagos", 'htech-ai-assistant' ),
			'pricing'  => __( "SAMPLE CONTENT — publish your real prices, or state clearly that pricing is by quote.\n\nExample: Starter package — 150,000 NGN. Custom work — request a quote.", 'htech-ai-assistant' ),
			'policies' => __( "SAMPLE CONTENT — describe your real refund, cancellation and privacy policies.\n\nExample: Refunds are available within 14 days of purchase if the service has not started.", 'htech-ai-assistant' ),
		);

		foreach ( $samples as $key => $content ) {
			$this->save_section( $key, $content, 1 );
		}

		$this->save_faq(
			array(
				'question' => __( 'How can I contact you?', 'htech-ai-assistant' ),
				'answer'   => __( 'SAMPLE CONTENT — replace with your real contact details, e.g. "Call us on +234 800 000 0000 or email hello@example.com."', 'htech-ai-assistant' ),
				'active'   => 1,
			)
		);

		$this->save_faq(
			array(
				'question' => __( 'What are your opening hours?', 'htech-ai-assistant' ),
				'answer'   => __( 'SAMPLE CONTENT — replace with your real opening hours, e.g. "Monday to Friday, 9am to 5pm."', 'htech-ai-assistant' ),
				'active'   => 1,
			)
		);

		// Fresh chunk rows for the new content.
		$this->reindex();

		update_option( 'htech_ai_sample_content', 1, false );

		return true;
	}

	/**
	 * Add or update a FAQ.
	 *
	 * @param array $faq question, answer, id (0 for new), active.
	 * @return int FAQ id.
	 */
	public function save_faq( array $faq ) {
		$data     = $this->all();
		$question = sanitize_text_field( (string) ( $faq['question'] ?? '' ) );
		$answer   = sanitize_textarea_field( (string) ( $faq['answer'] ?? '' ) );
		$active   = empty( $faq['active'] ) ? 0 : 1;
		$id       = (int) ( $faq['id'] ?? 0 );

		if ( '' === $question || '' === $answer ) {
			return 0;
		}

		if ( $id > 0 ) {
			foreach ( $data['faqs'] as &$existing ) {
				if ( (int) $existing['id'] === $id ) {
					$existing['question'] = $question;
					$existing['answer']   = $answer;
					$existing['active']   = $active;
					unset( $existing );
					$this->save( $data );
					return $id;
				}
			}
			unset( $existing );
		}

		$new_id            = $this->next_id( $data['faqs'] );
		$data['faqs'][]    = array(
			'id'       => $new_id,
			'question' => $question,
			'answer'   => $answer,
			'active'   => $active,
			'created'  => time(),
		);
		$this->save( $data );
		return $new_id;
	}

	/**
	 * Delete a FAQ.
	 *
	 * @param int $id Id.
	 * @return void
	 */
	public function delete_faq( $id ) {
		$data = $this->all();
		$data['faqs'] = array_values(
			array_filter(
				$data['faqs'],
				static function ( $faq ) use ( $id ) {
					return (int) $faq['id'] !== (int) $id;
				}
			)
		);
		$this->save( $data );
	}

	/**
	 * Add or update a custom knowledge entry.
	 *
	 * @param array $entry title, content, id, active.
	 * @return int
	 */
	public function save_custom( array $entry ) {
		$data  = $this->all();
		$title = sanitize_text_field( (string) ( $entry['title'] ?? '' ) );
		$text  = sanitize_textarea_field( (string) ( $entry['content'] ?? '' ) );
		$active = empty( $entry['active'] ) ? 0 : 1;
		$id     = (int) ( $entry['id'] ?? 0 );

		if ( '' === $title || '' === $text ) {
			return 0;
		}

		if ( $id > 0 ) {
			foreach ( $data['custom'] as &$existing ) {
				if ( (int) $existing['id'] === $id ) {
					$existing['title']   = $title;
					$existing['content'] = $text;
					$existing['active']  = $active;
					unset( $existing );
					$this->save( $data );
					return $id;
				}
			}
			unset( $existing );
		}

		$new_id         = $this->next_id( $data['custom'] );
		$data['custom'][] = array(
			'id'      => $new_id,
			'title'   => $title,
			'content' => $text,
			'active'  => $active,
			'created' => time(),
		);
		$this->save( $data );
		return $new_id;
	}

	/**
	 * Delete a custom entry.
	 *
	 * @param int $id Id.
	 * @return void
	 */
	public function delete_custom( $id ) {
		$data = $this->all();
		$data['custom'] = array_values(
			array_filter(
				$data['custom'],
				static function ( $entry ) use ( $id ) {
					return (int) $entry['id'] !== (int) $id;
				}
			)
		);
		$this->save( $data );
	}

	/**
	 * Next numeric id for a list.
	 *
	 * @param array $list List.
	 * @return int
	 */
	private function next_id( array $list ) {
		$max = 0;
		foreach ( $list as $item ) {
			$max = max( $max, (int) ( $item['id'] ?? 0 ) );
		}
		return $max + 1;
	}

	/**
	 * Rebuild manual chunks from the current store.
	 *
	 * @return int Number of chunks written.
	 */
	public function reindex() {
		global $wpdb;

		// Term statistics change whenever the corpus changes.
		HTECH_AI_Retrieval::flush_corpus_cache();

		$table = HTECH_AI_Database::chunks_table();
		$wpdb->delete( $table, array( 'source_type' => 'manual' ) );

		$data   = $this->all();
		$count  = 0;
		$labels = self::sections();

		foreach ( $data['sections'] as $key => $section ) {
			if ( empty( $section['active'] ) || '' === trim( $section['content'] ) ) {
				continue;
			}
			$title = $labels[ $key ]['title'];

			foreach ( HTECH_AI_Chunker::split( $section['content'] ) as $index => $chunk ) {
				$this->insert_chunk( array(
					'document_id'  => 0,
					'kb_entry_id'  => crc32( 'section:' . $key ),
					'source_type'  => 'manual',
					'source_label' => $title,
					'chunk_index'  => $index,
					'content'      => $chunk,
					'boost_text'   => $title . ' ' . $key,
				) );
				$count++;
			}
		}

		foreach ( $data['faqs'] as $faq ) {
			if ( empty( $faq['active'] ) ) {
				continue;
			}
			$question = trim( (string) $faq['question'] );
			$answer   = trim( (string) $faq['answer'] );
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$this->insert_chunk( array(
				'document_id'  => 0,
				'kb_entry_id'  => (int) $faq['id'],
				'source_type'  => 'manual',
				'source_label' => sprintf( __( 'FAQ: %s', 'htech-ai-assistant' ), $question ),
				'chunk_index'  => 0,
				'content'      => sprintf( "Question: %s\nAnswer: %s", $question, $answer ),
			) );
			$count++;
		}

		foreach ( $data['custom'] as $entry ) {
			if ( empty( $entry['active'] ) ) {
				continue;
			}
			foreach ( HTECH_AI_Chunker::split( $entry['content'] ) as $index => $chunk ) {
				$this->insert_chunk( array(
					'document_id'  => 0,
					'kb_entry_id'  => (int) $entry['id'],
					'source_type'  => 'manual',
					'source_label' => (string) $entry['title'],
					'chunk_index'  => $index,
					'content'      => $chunk,
					'boost_text'   => (string) $entry['title'],
				) );
				$count++;
			}
		}

		HTECH_AI_Retrieval::bump_version();
		delete_transient( 'htech_ai_corpus_' . md5( HTECH_AI_Retrieval::version() ) );

		return $count;
	}

	/**
	 * Insert a chunk with its term index.
	 *
	 * @param array $chunk Chunk data.
	 * @return int
	 */
	public function insert_chunk( array $chunk ) {
		global $wpdb;

		// Section titles improve recall but must never appear in the answer, so
		// they are folded into the term index only.
		$boost = isset( $chunk['boost_text'] ) ? ' ' . $chunk['boost_text'] : '';
		$terms = HTECH_AI_Retrieval::build_term_data( $chunk['content'] . $boost );

		$wpdb->insert(
			HTECH_AI_Database::chunks_table(),
			array(
				'document_id'  => (int) $chunk['document_id'],
				'kb_entry_id'  => (int) $chunk['kb_entry_id'],
				'source_type'  => (string) $chunk['source_type'],
				'source_label' => mb_substr( (string) $chunk['source_label'], 0, 190 ),
				'chunk_index'  => (int) $chunk['chunk_index'],
				'content'      => (string) $chunk['content'],
				'token_count'  => (int) $terms['len'],
				'term_data'    => wp_json_encode( $terms ),
				'created_at'   => current_time( 'mysql', true ),
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Compact textual summary of the manual knowledge (used in AI prompts).
	 *
	 * @param int $max_chars Budget.
	 * @return string
	 */
	public function summary_for_prompt( $max_chars = 1800 ) {
		$data    = $this->all();
		$labels  = self::sections();
		$parts   = array();
		$used    = 0;

		foreach ( $data['sections'] as $key => $section ) {
			if ( empty( $section['active'] ) || '' === trim( $section['content'] ) ) {
				continue;
			}
			$block = $labels[ $key ]['title'] . ': ' . trim( $section['content'] );
			if ( $used + mb_strlen( $block ) > $max_chars ) {
				$block = mb_substr( $block, 0, max( 0, $max_chars - $used ) );
			}
			$parts[] = $block;
			$used   += mb_strlen( $block );
			if ( $used >= $max_chars ) {
				break;
			}
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Suggested questions for the widget: admin list plus active FAQ questions.
	 *
	 * @param int $limit Max items.
	 * @return string[]
	 */
	public function suggestions( $limit = 4 ) {
		$raw = (string) HTECH_AI_Settings::get( 'suggested_questions', '' );
		$out = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( wp_strip_all_tags( $line ) );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}

		$data = $this->all();
		foreach ( $data['faqs'] as $faq ) {
			if ( ! empty( $faq['active'] ) && ! empty( $faq['question'] ) ) {
				$out[] = (string) $faq['question'];
			}
		}

		$out = array_values( array_unique( $out ) );
		return array_slice( $out, 0, max( 1, (int) $limit ) );
	}

	/**
	 * How much knowledge is available? Drives honest admin messaging.
	 *
	 * @return array{chunks:int,sections:int,faqs:int,custom:int,words:int}
	 */
	public function coverage() {
		global $wpdb;

		$data     = $this->all();
		$sections = 0;
		$words    = 0;

		foreach ( $data['sections'] as $section ) {
			if ( ! empty( $section['active'] ) && '' !== trim( $section['content'] ) ) {
				$sections++;
				$words += str_word_count( $section['content'] );
			}
		}
		foreach ( $data['faqs'] as $faq ) {
			if ( ! empty( $faq['active'] ) ) {
				$words += str_word_count( (string) $faq['answer'] );
			}
		}

		$table  = HTECH_AI_Database::chunks_table();
		$chunks = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE source_type = %s", 'manual' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'chunks'   => $chunks,
			'sections' => $sections,
			'faqs'     => count( array_filter( $data['faqs'], static function ( $f ) { return ! empty( $f['active'] ); } ) ),
			'custom'   => count( array_filter( $data['custom'], static function ( $c ) { return ! empty( $c['active'] ); } ) ),
			'words'    => $words,
		);
	}

	/**
	 * Export the knowledge base as JSON.
	 *
	 * @return string
	 */
	public function export_json() {
		return (string) wp_json_encode( $this->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Import knowledge base JSON produced by export_json().
	 *
	 * @param string $json JSON string.
	 * @return array{ok:bool,message:string}
	 */
	public function import_json( $json ) {
		$decoded = json_decode( (string) $json, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['sections'] ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'That file is not a valid HTECH knowledge export.', 'htech-ai-assistant' ),
			);
		}

		$clean = array( 'sections' => array(), 'faqs' => array(), 'custom' => array() );

		foreach ( $decoded['sections'] as $key => $section ) {
			if ( array_key_exists( $key, self::sections() ) ) {
				$clean['sections'][ $key ] = array(
					'content' => sanitize_textarea_field( (string) ( $section['content'] ?? '' ) ),
					'active'  => empty( $section['active'] ) ? 0 : 1,
					'updated' => time(),
				);
			}
		}

		foreach ( (array) ( $decoded['faqs'] ?? array() ) as $index => $faq ) {
			$clean['faqs'][] = array(
				'id'       => $index + 1,
				'question' => sanitize_text_field( (string) ( $faq['question'] ?? '' ) ),
				'answer'   => sanitize_textarea_field( (string) ( $faq['answer'] ?? '' ) ),
				'active'   => empty( $faq['active'] ) ? 0 : 1,
				'created'  => time(),
			);
		}

		foreach ( (array) ( $decoded['custom'] ?? array() ) as $index => $entry ) {
			$clean['custom'][] = array(
				'id'      => $index + 1,
				'title'   => sanitize_text_field( (string) ( $entry['title'] ?? '' ) ),
				'content' => sanitize_textarea_field( (string) ( $entry['content'] ?? '' ) ),
				'active'  => empty( $entry['active'] ) ? 0 : 1,
				'created' => time(),
			);
		}

		$this->save( $clean );

		return array(
			'ok'      => true,
			'message' => __( 'Knowledge base imported and re-indexed.', 'htech-ai-assistant' ),
		);
	}
}
