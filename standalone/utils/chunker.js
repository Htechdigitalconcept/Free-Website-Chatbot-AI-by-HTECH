/**
 * Free Website Chatbot AI by HTECH — text extraction & chunking for the standalone build.
 *
 * Used by the optional "build knowledge" page, which lets a site owner drop PDF,
 * DOCX, TXT or CSV files into the browser and download a ready-to-use
 * knowledge.json. Everything happens locally: files never leave the device.
 *
 * The chunker mirrors the PHP implementation (paragraph blocks, sentence
 * splitting for oversized blocks, overlapping tails) so the standalone index
 * behaves like the WordPress one.
 */
(function (window, document) {
	'use strict';

	function clean(text) {
		return String(text)
			.replace(/\r\n?/g, '\n')
			.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '')
			.replace(/[ \t]+/g, ' ')
			.replace(/\n{3,}/g, '\n\n')
			.trim();
	}

	function splitSentences(block, size) {
		var sentences = block.split(/(?<=[.!?])\s+/);
		var out = [];
		var current = '';

		sentences.forEach(function (sentence) {
			sentence = sentence.trim();
			if (!sentence) { return; }

			if (current.length + sentence.length + 1 > size && current) {
				out.push(current);
				current = sentence;
			} else {
				current += (current ? ' ' : '') + sentence;
			}
		});

		if (current.trim()) { out.push(current); }
		return out;
	}

	/**
	 * Split text into overlapping chunks.
	 *
	 * @param {string} text  Input text.
	 * @param {number} size  Target characters.
	 * @param {number} overlap Overlap characters.
	 * @param {boolean} forceSplit One chunk per blank-line block (CSV rows).
	 * @return {string[]}
	 */
	function split(text, size, overlap, forceSplit) {
		size = Math.max(200, Math.min(4000, size || 900));
		overlap = Math.max(0, Math.min(Math.floor(size / 2), overlap === undefined ? 120 : overlap));

		text = clean(text);
		if (!text) { return []; }

		var blocks = text.split(/\n{2,}/).map(function (b) { return b.trim(); }).filter(Boolean);

		if (forceSplit) {
			return blocks.filter(function (b) { return b.length > 20; });
		}

		if (text.length <= Math.floor(size * 1.2)) { return [text]; }

		var chunks = [];
		var buffer = '';

		blocks.forEach(function (block) {
			if (buffer.length + block.length + 2 <= size) {
				buffer += (buffer ? '\n\n' : '') + block;
				return;
			}

			if (buffer) { chunks.push(buffer.trim()); }

			if (block.length > size) {
				splitSentences(block, size).forEach(function (piece) { chunks.push(piece); });
				buffer = '';
			} else {
				buffer = block;
			}
		});

		if (buffer.trim()) { chunks.push(buffer.trim()); }

		if (overlap > 0 && chunks.length > 1) {
			var overlapped = [chunks[0]];
			for (var i = 1; i < chunks.length; i++) {
				var tail = chunks[i - 1].slice(-overlap);
				var space = tail.indexOf(' ');
				if (space !== -1 && space < tail.length - 1) { tail = tail.slice(space + 1); }
				overlapped.push((tail + ' ' + chunks[i]).trim());
			}
			chunks = overlapped;
		}

		return chunks.filter(function (c) { return c.trim().length > 20; });
	}

	/* ------------------------------------------------------------ extractors */

	function readArrayBuffer(file) {
		return new Promise(function (resolve, reject) {
			var reader = new FileReader();
			reader.onload = function () { resolve(reader.result); };
			reader.onerror = function () { reject(new Error('Could not read ' + file.name)); };
			reader.readAsArrayBuffer(file);
		});
	}

	function readText(file) {
		return new Promise(function (resolve, reject) {
			var reader = new FileReader();
			reader.onload = function () { resolve(String(reader.result)); };
			reader.onerror = function () { reject(new Error('Could not read ' + file.name)); };
			reader.readAsText(file, 'utf-8');
		});
	}

	var loadedScripts = {};

	function loadScript(src) {
		if (loadedScripts[src]) { return loadedScripts[src]; }

		loadedScripts[src] = new Promise(function (resolve, reject) {
			var script = document.createElement('script');
			script.src = src;
			script.onload = resolve;
			script.onerror = function () { reject(new Error('Could not load ' + src)); };
			document.head.appendChild(script);
		});

		return loadedScripts[src];
	}

	function extractPdf(file, assetPath) {
		return loadScript(assetPath + 'pdf.min.js').then(function () {
			window.pdfjsLib.GlobalWorkerOptions.workerSrc = assetPath + 'pdf.worker.min.js';

			return readArrayBuffer(file).then(function (buffer) {
				return window.pdfjsLib.getDocument({ data: new Uint8Array(buffer), isEvalSupported: false }).promise;
			}).then(function (pdf) {
				var pages = [];
				var chain = Promise.resolve();

				for (var n = 1; n <= pdf.numPages; n++) {
					chain = chain.then(function (pageNumber) {
						return pdf.getPage(pageNumber).then(function (page) {
							return page.getTextContent().then(function (content) {
								var lastY = null;
								var line = [];
								var lines = [];

								content.items.forEach(function (item) {
									if (typeof item.str !== 'string') { return; }
									var y = item.transform ? Math.round(item.transform[5]) : lastY;
									if (lastY !== null && y !== null && Math.abs(y - lastY) > 3) {
										if (line.length) { lines.push(line.join(' ')); line = []; }
									}
									line.push(item.str);
									lastY = y;
								});

								if (line.length) { lines.push(line.join(' ')); }
								pages.push(lines.join('\n'));
							});
						});
					}.bind(null, n));
				}

				return chain.then(function () { return pages.join('\n\n'); });
			});
		});
	}

	function extractDocx(file, assetPath) {
		return loadScript(assetPath + 'mammoth.browser.min.js').then(function () {
			return readArrayBuffer(file).then(function (buffer) {
				return window.mammoth.convertToHtml({ arrayBuffer: buffer });
			}).then(function (result) {
				var container = document.createElement('div');
				container.innerHTML = result.value || '';
				container.querySelectorAll('li').forEach(function (li) {
					li.textContent = '- ' + li.textContent.trim();
				});
				container.querySelectorAll('h1,h2,h3,p,li,tr').forEach(function (node) {
					node.appendChild(document.createTextNode('\n'));
				});
				return container.textContent;
			});
		});
	}

	function parseCsv(raw) {
		var firstLine = raw.split(/\r?\n/)[0] || '';
		var candidates = [',', ';', '\t', '|'];
		var delimiter = ',';

		candidates.forEach(function (char) {
			if (firstLine.split(char).length > firstLine.split(delimiter).length) { delimiter = char; }
		});

		var rows = [];
		var row = [];
		var field = '';
		var quoted = false;

		for (var i = 0; i < raw.length; i++) {
			var ch = raw[i];

			if (quoted) {
				if (ch === '"') {
					if (raw[i + 1] === '"') { field += '"'; i++; } else { quoted = false; }
				} else { field += ch; }
				continue;
			}

			if (ch === '"') { quoted = true; }
			else if (ch === delimiter) { row.push(field); field = ''; }
			else if (ch === '\n') { row.push(field); rows.push(row); row = []; field = ''; }
			else if (ch !== '\r') { field += ch; }
		}

		if (field !== '' || row.length) { row.push(field); rows.push(row); }

		return rows.filter(function (r) {
			return r.some(function (cell) { return String(cell).trim() !== ''; });
		});
	}

	function extractCsv(text) {
		var rows = parseCsv(text);
		if (!rows.length) { return ''; }

		var header = rows.shift();

		return rows.map(function (row) {
			return row.map(function (cell, index) {
				var value = String(cell || '').trim();
				if (!value) { return null; }
				return (header[index] || ('Column ' + (index + 1))) + ': ' + value;
			}).filter(Boolean).join('\n');
		}).filter(Boolean).join('\n\n');
	}

	/**
	 * Extract plain text from a file in the browser.
	 *
	 * @param {File}   file      File object.
	 * @param {string} assetPath Where pdf.js / mammoth live.
	 * @return {Promise<string>}
	 */
	function extract(file, assetPath) {
		var ext = (file.name.split('.').pop() || '').toLowerCase();

		switch (ext) {
			case 'pdf':
				return extractPdf(file, assetPath).then(clean);
			case 'docx':
				return extractDocx(file, assetPath).then(clean);
			case 'csv':
				return readText(file).then(function (text) { return clean(extractCsv(text)); });
			case 'txt':
			case 'md':
				return readText(file).then(clean);
			case 'json':
				return readText(file).then(function (text) {
					try {
						var data = JSON.parse(text);
						var lines = [];
						(function walk(value, key) {
							if (Array.isArray(value)) { value.forEach(function (v) { walk(v, key); }); }
							else if (value && typeof value === 'object') { Object.keys(value).forEach(function (k) { walk(value[k], k); }); }
							else if (value !== null && value !== undefined && String(value).trim() !== '') {
								lines.push((key ? key + ': ' : '') + String(value).trim());
							}
						})(data, '');
						return clean(lines.join('\n'));
					} catch (error) {
						return clean(text);
					}
				});
			default:
				return Promise.reject(new Error('Unsupported file type: .' + ext));
		}
	}

	window.HTECH_AI_Chunker = {
		clean: clean,
		split: split,
		extract: extract,
		extractCsv: extractCsv,
		parseCsv: parseCsv
	};
})(window, document);
