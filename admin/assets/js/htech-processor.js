/**
 * HTECH AI Assistant — browser document processor.
 *
 * Extracts text from PDF (PDF.js), DOCX (Mammoth.js), TXT, MD, CSV and JSON
 * locally in the administrator's browser. Documents themselves never leave
 * the site: only the stored file is uploaded, and the extracted text is sent
 * with it so the server does not need a heavyweight PHP PDF parser.
 *
 * Libraries are lazy-loaded the first time they are needed.
 */
(function (window, document) {
	'use strict';

	var ADMIN = window.HTECH_AI_ADMIN || {};
	var vendor = ADMIN.vendorUrl || '';
	var loaded = {};

	/* --------------------------------------------------------- script loader */

	function loadScript(src) {
		if (loaded[src]) {
			return loaded[src];
		}

		loaded[src] = new Promise(function (resolve, reject) {
			var script = document.createElement('script');
			script.src = src;
			script.async = true;
			script.onload = function () { resolve(); };
			script.onerror = function () {
				reject(new Error('Could not load ' + src + ' — check that the vendor folder was uploaded.'));
			};
			document.head.appendChild(script);
		});

		return loaded[src];
	}

	function loadPdfJs() {
		if (window.pdfjsLib) {
			return Promise.resolve(window.pdfjsLib);
		}

		return loadScript(vendor + 'pdfjs/pdf.min.js').then(function () {
			// The worker must be same-origin to satisfy CSP and avoid CDN use.
			window.pdfjsLib.GlobalWorkerOptions.workerSrc = vendor + 'pdfjs/pdf.worker.min.js';
			return window.pdfjsLib;
		});
	}

	function loadMammoth() {
		if (window.mammoth) {
			return Promise.resolve(window.mammoth);
		}
		return loadScript(vendor + 'mammoth/mammoth.browser.min.js').then(function () {
			return window.mammoth;
		});
	}

	/* ------------------------------------------------------------- file readers */

	function readArrayBuffer(file) {
		return new Promise(function (resolve, reject) {
			var reader = new FileReader();
			reader.onload = function () { resolve(reader.result); };
			reader.onerror = function () { reject(new Error('The file could not be read.')); };
			reader.readAsArrayBuffer(file);
		});
	}

	function readText(file) {
		return new Promise(function (resolve, reject) {
			var reader = new FileReader();
			reader.onload = function () { resolve(String(reader.result)); };
			reader.onerror = function () { reject(new Error('The file could not be read.')); };
			reader.readAsText(file, 'utf-8');
		});
	}

	/* ----------------------------------------------------------------- PDF */

	function extractPdf(file, onProgress) {
		return loadPdfJs().then(function (pdfjs) {
			return readArrayBuffer(file).then(function (buffer) {
				var loadingTask = pdfjs.getDocument({
					data: new Uint8Array(buffer),
					// No external resources needed for text extraction.
					isEvalSupported: false,
					disableAutoFetch: true,
					disableStream: true
				});

				return loadingTask.promise.then(function (pdf) {
					var pages = [];
					var total = pdf.numPages;
					var chain = Promise.resolve();

					for (var pageNumber = 1; pageNumber <= total; pageNumber++) {
						chain = chain.then(function (number) {
							return pdf.getPage(number).then(function (page) {
								return page.getTextContent().then(function (content) {
									var lastY = null;
									var line = [];
									var lines = [];

									content.items.forEach(function (item) {
										if (typeof item.str !== 'string') { return; }

										var y = item.transform ? Math.round(item.transform[5]) : lastY;
										if (lastY !== null && y !== null && Math.abs(y - lastY) > 3) {
											if (line.length) {
												lines.push(line.join(' '));
												line = [];
											}
										}
										line.push(item.str);
										lastY = y;
									});

									if (line.length) {
										lines.push(line.join(' '));
									}

									pages.push(lines.join('\n'));

									if (onProgress) {
										onProgress(number / total);
									}
								});
							});
						}.bind(null, pageNumber));
					}

					return chain.then(function () {
						return { text: pages.join('\n\n'), pages: total };
					});
				});
			});
		});
	}

	/* ---------------------------------------------------------------- DOCX */

	function extractDocx(file) {
		return loadMammoth().then(function (mammoth) {
			return readArrayBuffer(file).then(function (buffer) {
				return mammoth.convertToHtml({ arrayBuffer: buffer }, {
					styleMap: [
						"p[style-name='Heading 1'] => h1:fresh",
						"p[style-name='Heading 2'] => h2:fresh"
					]
				}).then(function (result) {
					var html = result.value || '';
					var container = document.createElement('div');
					container.innerHTML = html;

					// Keep list/table structure as readable plain text.
					container.querySelectorAll('li').forEach(function (li) {
						li.textContent = '- ' + li.textContent.trim();
					});
					container.querySelectorAll('h1,h2,h3,p,li,tr').forEach(function (node) {
						node.appendChild(document.createTextNode('\n'));
					});

					return {
						text: container.textContent.replace(/\n{3,}/g, '\n\n').trim(),
						messages: result.messages || []
					};
				});
			});
		});
	}

	/* ----------------------------------------------------------------- CSV */

	function extractCsv(file) {
		return readText(file).then(function (raw) {
			var delimiter = detectDelimiter(raw);
			var rows = parseDelimited(raw, delimiter);

			if (!rows.length) {
				throw new Error('The CSV file appears to be empty.');
			}

			var header = rows.shift();
			var blocks = rows.map(function (row) {
				return row.map(function (cell, index) {
					var label = header[index] || ('Column ' + (index + 1));
					var value = (cell || '').trim();
					return value ? label + ': ' + value : null;
				}).filter(Boolean).join('\n');
			}).filter(Boolean);

			if (!blocks.length) {
				throw new Error('No data rows were found in the CSV file.');
			}

			return { text: blocks.join('\n\n'), rows: blocks.length };
		});
	}

	function detectDelimiter(raw) {
		var firstLine = raw.split(/\r?\n/)[0] || '';
		var counts = { ',': 0, ';': 0, '\t': 0, '|': 0 };
		Object.keys(counts).forEach(function (char) {
			counts[char] = firstLine.split(char).length - 1;
		});
		return Object.keys(counts).reduce(function (best, char) {
			return counts[char] > counts[best] ? char : best;
		}, ',');
	}

	/** RFC4180-ish CSV parser with quoted-field support. */
	function parseDelimited(text, delimiter) {
		var rows = [];
		var row = [];
		var field = '';
		var quoted = false;

		for (var i = 0; i < text.length; i++) {
			var char = text[i];

			if (quoted) {
				if (char === '"') {
					if (text[i + 1] === '"') {
						field += '"';
						i++;
					} else {
						quoted = false;
					}
				} else {
					field += char;
				}
				continue;
			}

			if (char === '"') {
				quoted = true;
			} else if (char === delimiter) {
				row.push(field);
				field = '';
			} else if (char === '\n') {
				row.push(field);
				rows.push(row);
				row = [];
				field = '';
			} else if (char === '\r') {
				// handled by the following \n
			} else {
				field += char;
			}
		}

		if (field !== '' || row.length) {
			row.push(field);
			rows.push(row);
		}

		return rows.filter(function (r) {
			return r.some(function (cell) { return String(cell).trim() !== ''; });
		});
	}

	/* ---------------------------------------------------------------- plain */

	function extractPlain(file) {
		return readText(file).then(function (text) {
			if (/^\s*[[{]/.test(text) && /\.json$/i.test(file.name)) {
				try {
					var data = JSON.parse(text);
					var lines = [];
					(function walk(value, key) {
						if (Array.isArray(value)) {
							value.forEach(function (item) { walk(item, key); });
						} else if (value && typeof value === 'object') {
							Object.keys(value).forEach(function (k) { walk(value[k], k); });
						} else if (value !== null && value !== undefined && String(value).trim() !== '') {
							lines.push((key ? key + ': ' : '') + String(value).trim());
						}
					})(data, '');
					return { text: lines.join('\n') };
				} catch (error) {
					// Fall through to raw text.
				}
			}

			return { text: text.replace(/\r\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim() };
		});
	}

	/* ---------------------------------------------------------------- public */

	function extract(file, onProgress) {
		var extension = (file.name.split('.').pop() || '').toLowerCase();

		var promise;

		switch (extension) {
			case 'pdf':
				promise = extractPdf(file, onProgress);
				break;
			case 'docx':
				promise = extractDocx(file);
				break;
			case 'csv':
				promise = extractCsv(file);
				break;
			case 'txt':
			case 'md':
			case 'json':
				promise = extractPlain(file);
				break;
			default:
				return Promise.reject(new Error('Files of type .' + extension + ' cannot be parsed.'));
		}

		return promise.then(function (result) {
			var text = (result && result.text ? result.text : '').trim();

			if (text.length < 20) {
				// Not fatal: the server may still be able to parse it.
				return { text: '', warning: 'Little or no text was extracted in the browser; the server will try instead.', meta: result };
			}

			return {
				text: text,
				chars: text.length,
				meta: result
			};
		});
	}

	window.HTECH_AI_Processor = {
		extract: extract,
		extractPdf: extractPdf,
		extractDocx: extractDocx,
		extractCsv: extractCsv,
		parseDelimited: parseDelimited
	};
})(window, document);
