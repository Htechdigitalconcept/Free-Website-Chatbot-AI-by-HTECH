/**
 * Free Website Chatbot AI by HTECH — client-side retrieval engine (standalone build).
 *
 * A faithful re-implementation of the plugin's server-side BM25 scorer so a
 * static site gets the same answer quality without any backend:
 *
 *   - tokenisation, stop words and light stemming (identical rules),
 *   - BM25 (k1 = 1.2, b = 0.75) over term frequencies,
 *   - synonym expansion for common business intents,
 *   - phrase bonus and FAQ priority,
 *   - the same score normalisation, so `minScore` behaves like the plugin.
 *
 * No dependencies. Works from file:// as well as http(s).
 */
(function (window) {
	'use strict';

	var K1 = 1.2;
	var B = 0.75;

	var STOPWORDS = ('a about above after again all am an and any are as at be because been before being below ' +
		'between both but by can could did do does doing done down during each few for from further had has have ' +
		'having he her here hers him his how i if in into is it its just me more most my no nor not now of off on ' +
		'once only or other our out over own please same she should so some such than that the their them then ' +
		'there these they this those through to too under until up very was we were what when where which while ' +
		'who whom why will with would you your yours hi hello hey thanks thank ok okay').split(' ');

	// Must stay identical to HTECH_AI_Retrieval::$generic_query_words in PHP.
	var GENERIC_QUERY_WORDS = ('offer offered offering provide provided available have has get ' +
		'need want looking look sell sold help support info information detail details tell know ' +
		'ask question answer work worked run type kind sort list option choose recommend best good ' +
		'many much cost price charge fee require requirement process step able possible long soon ' +
		'usually generally exactly really actually').split(' ');

	var SYNONYMS = {
		price: ['cost', 'pricing', 'fee', 'fees', 'rate', 'rates', 'charge', 'how much', 'quote'],
		hours: ['open', 'opening', 'closed', 'closing', 'time', 'times', 'schedule'],
		contact: ['phone', 'email', 'call', 'reach', 'number', 'whatsapp', 'address'],
		delivery: ['shipping', 'deliver', 'ship', 'courier', 'dispatch'],
		return: ['refund', 'returns', 'refunds', 'exchange', 'guarantee', 'warranty'],
		service: ['services', 'solution', 'solutions', 'offering', 'offerings'],
		location: ['address', 'located', 'office', 'branch', 'where'],
		payment: ['pay', 'card', 'transfer', 'invoice', 'checkout']
	};

	function removeAccents(text) {
		return String(text).normalize ? String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '') : String(text);
	}

	function tokenize(text) {
		return removeAccents(text)
			.toLowerCase()
			.replace(/[^\p{L}\p{N}\s]+/gu, ' ')
			.split(/\s+/)
			.filter(Boolean);
	}

	function stem(token) {
		var length = token.length;
		if (length > 4 && token.slice(-3) === 'ies') { return token.slice(0, -3) + 'y'; }
		if (length > 4 && token.slice(-2) === 'es') { return token.slice(0, -2); }
		if (length > 3 && token.slice(-1) === 's' && token.slice(-2, -1) !== 's') { return token.slice(0, -1); }
		if (length > 5 && token.slice(-3) === 'ing') { return token.slice(0, -3); }
		if (length > 4 && token.slice(-2) === 'ed') { return token.slice(0, -2); }
		return token;
	}

	function normalizeTokens(text) {
		var out = [];
		tokenize(text).forEach(function (token) {
			if (token.length < 2) { return; }
			if (STOPWORDS.indexOf(token) !== -1) { return; }
			out.push(/^\d+$/.test(token) ? token : stem(token));
		});
		return out;
	}

	function buildTermData(content) {
		var terms = {};
		var all = normalizeTokens(content);
		all.forEach(function (token) {
			terms[token] = (terms[token] || 0) + 1;
		});
		return { terms: terms, len: all.length };
	}

	function expandQuery(tokens) {
		var weighted = {};

		tokens.forEach(function (token) {
			if (weighted[token] === undefined) { weighted[token] = 1.0; }

			if (SYNONYMS[token]) {
				SYNONYMS[token].forEach(function (extra) {
					normalizeTokens(extra).forEach(function (syn) {
						if (weighted[syn] === undefined) { weighted[syn] = 0.45; }
					});
				});
			}

			Object.keys(SYNONYMS).forEach(function (canonical) {
				if (SYNONYMS[canonical].indexOf(token) !== -1) {
					normalizeTokens(canonical).forEach(function (syn) {
						if (weighted[syn] === undefined) { weighted[syn] = 0.45; }
					});
				}
			});
		});

		return weighted;
	}

	/**
	 * Build a searchable index from the exported knowledge snapshot.
	 *
	 * @param {Array} items  Items: { s: 'kb'|'doc', l: label, t: text, f: terms, n: len }
	 * @param {Array} faqs   FAQ entries (optional, already folded into items).
	 * @return {object} index
	 */
	function buildIndex(items, faqs) {
		var docs = [];
		var df = {};
		var totalLength = 0;

		(items || []).forEach(function (item) {
			var text = String(item.t || '');
			if (text.trim().length < 10) { return; }

			var termData = item.f && typeof item.f === 'object' && Object.keys(item.f).length
				? { terms: item.f, len: item.n || 0 }
				: buildTermData(text);

			Object.keys(termData.terms).forEach(function (term) {
				df[term] = (df[term] || 0) + 1;
			});

			totalLength += termData.len;

			docs.push({
				source: item.s === 'doc' ? 'document' : 'manual',
				label: String(item.l || 'Business knowledge'),
				text: text,
				terms: termData.terms,
				len: Math.max(1, termData.len),
				lower: text.toLowerCase()
			});
		});

		var corpus = {
			n: Math.max(1, docs.length),
			avgdl: docs.length ? Math.max(1, totalLength / docs.length) : 1
		};

		return {
			docs: docs,
			df: df,
			corpus: corpus,
			faqs: Array.isArray(faqs) ? faqs : [],
			builtAt: new Date().toISOString()
		};
	}

	function idf(index, term) {
		var df = index.df[term] || 0;
		return Math.log(1 + (Math.max(0, index.corpus.n - df) + 0.5) / (df + 0.5));
	}

	/**
	 * Search the index.
	 *
	 * @param {object} index  From buildIndex().
	 * @param {string} query  Visitor question.
	 * @param {object} options topK, minScore.
	 * @return {Array} ranked results
	 */
	function search(index, query, options) {
		options = options || {};
		var topK = options.topK || 4;
		var minScore = options.minScore === undefined ? 0.18 : options.minScore;

		var tokens = normalizeTokens(query);
		if (!tokens.length) { tokens = tokenize(query).filter(function (t) { return t.length; }); }

		var weighted = expandQuery(tokens);
		var qs = Object.keys(weighted);
		if (!qs.length) { return []; }

		var idfMap = {};
		var totalQueryIdf = 0;
		var unknownIdf = 0;
		var consideredIdf = 0;

		qs.forEach(function (term) {
			var termIdf = idf(index, term);
			idfMap[term] = termIdf * weighted[term];
			totalQueryIdf += idfMap[term];
		});

		/*
		 * Unknown-entity guard (mirrors the PHP engine).
		 *
		 * A term found in no indexed chunk means this business never documented
		 * it ("president", "Abuja", "iPhone"). When such terms dominate the
		 * question, any surviving match is coincidence, so we refuse instead of
		 * guessing. Generic question words and digits are excluded so ordinary
		 * questions still resolve.
		 */
		if (totalQueryIdf > 0) {
			qs.forEach(function (term) {
				if (GENERIC_QUERY_WORDS.indexOf(term) !== -1 || /^\d+$/.test(term)) { return; }

				consideredIdf += idfMap[term];

				if (!index.df[term]) { unknownIdf += idfMap[term]; }
			});

			if (consideredIdf > 0 && unknownIdf / consideredIdf > 0.6) {
				return [];
			}
		}

		var phrase = query.toLowerCase().replace(/[^\p{L}\p{N}\s]+/gu, ' ').replace(/\s+/g, ' ').trim();

		// Adjacent-pair matching, mirroring the PHP engine: "business website"
		// should beat a chunk that merely contains "business" and "price".
		var rawTokens = normalizeTokens(query);
		var bigrams = [];
		for (var b = 0; b < rawTokens.length - 1; b++) {
			var pair = rawTokens[b] + ' ' + rawTokens[b + 1];
			if (pair.length > 6) { bigrams.push(pair); }
		}

		var results = [];

		index.docs.forEach(function (doc, docIndex) {
			var raw = 0;
			var matched = 0;

			Object.keys(idfMap).forEach(function (term) {
				var tf = doc.terms[term];
				if (!tf) { return; }

				raw += idfMap[term] * ((tf * (K1 + 1)) / (tf + K1 * (1 - B + B * (doc.len / index.corpus.avgdl))));
				matched++;
			});

			if (raw <= 0) { return; }

			var coverage = matched / Math.max(1, qs.length);
			raw *= (0.75 + 0.5 * coverage);

			if (phrase.length > 2 && doc.lower.indexOf(phrase) !== -1) { raw += 2.5; }

			if (bigrams.length) {
				var stemmed = ` ${normalizeTokens(doc.text).join(' ')} `;

				bigrams.forEach(function (pair) {
					if (stemmed.indexOf(' ' + pair) !== -1) { raw += 2.0; }
				});
			}
			if (doc.source === 'manual') { raw += 0.6; }

			var score = raw / (raw + 2.2);

			if (score < minScore) { return; }

			results.push({
				text: doc.text,
				label: doc.label,
				sourceType: doc.source,
				score: Math.round(score * 10000) / 10000,
				matched: matched,
				order: docIndex
			});
		});

		results.sort(function (a, b) {
			if (b.score !== a.score) { return b.score - a.score; }
			return a.order - b.order;
		});

		return results.slice(0, topK);
	}

	window.HTECH_AI_Retrieval = {
		buildIndex: buildIndex,
		search: search,
		normalizeTokens: normalizeTokens,
		tokenize: tokenize,
		buildTermData: buildTermData
	};
})(window);
