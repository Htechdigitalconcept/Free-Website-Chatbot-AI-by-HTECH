/**
 * HTECH AI Assistant — admin application.
 *
 * Plain ES5-compatible-ish JS (no build step required in production).
 * Every request goes through the WordPress REST API with a wp_rest nonce.
 */
(function () {
	'use strict';

	var ADMIN = window.HTECH_AI_ADMIN || {};
	var $ = window.jQuery;

	/* ------------------------------------------------------------ helpers */

	function api(path, options) {
		options = options || {};
		var url = ADMIN.restUrl.replace(/\/$/, '') + path;
		var headers = { 'X-WP-Nonce': ADMIN.nonce };

		if (options.body && !(options.body instanceof FormData)) {
			headers['Content-Type'] = 'application/json';
			options.body = JSON.stringify(options.body);
		}

		return fetch(url, {
			method: options.method || 'GET',
			headers: headers,
			credentials: 'same-origin',
			body: options.body
		}).then(function (response) {
			return response.json().then(function (data) {
				if (!response.ok) {
					var message = (data && (data.message || (data.data && data.data.message))) || 'Request failed';
					var error = new Error(message);
					error.payload = data;
					throw error;
				}
				return data;
			});
		});
	}

	function toast(message, type) {
		var root = document.getElementById('htech-ai-toasts');
		if (!root) {
			root = document.createElement('div');
			root.id = 'htech-ai-toasts';
			root.className = 'htech-ai-toasts';
			document.body.appendChild(root);
		}

		var el = document.createElement('div');
		el.className = 'htech-toast' + (type ? ' htech-toast--' + type : '');

		var icon = document.createElement('span');
		icon.className = 'dashicons ' + (type === 'error' ? 'dashicons-warning' : 'dashicons-yes-alt');

		var text = document.createElement('span');
		text.textContent = message;

		el.appendChild(icon);
		el.appendChild(text);
		root.appendChild(el);

		setTimeout(function () {
			el.style.opacity = '0';
			el.style.transform = 'translateY(6px)';
			setTimeout(function () { el.remove(); }, 300);
		}, type === 'error' ? 6500 : 3800);
	}

	function busy(button, isBusy, label) {
		if (!button) { return; }
		if (isBusy) {
			button.dataset.originalLabel = button.textContent;
			button.disabled = true;
			if (label) { button.textContent = label; }
		} else {
			button.disabled = false;
			if (button.dataset.originalLabel) { button.textContent = button.dataset.originalLabel; }
		}
	}

	/* --------------------------------------------------------------- tabs */

	function initTabs() {
		document.querySelectorAll('[data-htech-tabs]').forEach(function (group) {
			var buttons = group.querySelectorAll('[data-tab]');

			buttons.forEach(function (button) {
				button.addEventListener('click', function () {
					var target = button.getAttribute('data-tab');

					buttons.forEach(function (b) { b.classList.toggle('is-active', b === button); });

					document.querySelectorAll('[data-tab-panel]').forEach(function (panel) {
						if (panel.getAttribute('data-tab-panel') === target) {
							panel.removeAttribute('hidden');
						} else {
							panel.setAttribute('hidden', 'hidden');
						}
					});

					if (window.history && window.history.replaceState) {
						var url = new URL(window.location.href);
						url.searchParams.set('tab', target);
						window.history.replaceState({}, '', url.toString());
					}
				});
			});
		});

		var initial = new URL(window.location.href).searchParams.get('tab');
		if (initial) {
			var trigger = document.querySelector('[data-tab="' + initial.replace(/"/g, '') + '"]');
			if (trigger) { trigger.click(); }
		}
	}

	/* --------------------------------------------------------- copy buttons */

	function initCopy() {
		document.querySelectorAll('.htech-copy-btn').forEach(function (button) {
			button.addEventListener('click', function () {
				var target = document.getElementById(button.getAttribute('data-copy-target'));
				if (!target) { return; }

				var text = target.textContent;

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(function () {
						toast(ADMIN.i18n ? ADMIN.i18n.copied : 'Copied', 'success');
					});
				} else {
					var area = document.createElement('textarea');
					area.value = text;
					document.body.appendChild(area);
					area.select();
					document.execCommand('copy');
					area.remove();
					toast('Copied', 'success');
				}
			});
		});
	}

	/* ---------------------------------------------------- dependency toggles */

	function initConditionals() {
		document.querySelectorAll('[data-toggle-target]').forEach(function (input) {
			var target = document.getElementById(input.getAttribute('data-toggle-target'));
			if (!target) { return; }

			var refresh = function () {
				var isCheckbox = input.type === 'checkbox';
				var active = isCheckbox ? input.checked : input.value !== '';
				target.style.display = active ? '' : 'none';
			};

			input.addEventListener('change', refresh);
			refresh();
		});
	}

	/* --------------------------------------------------------- colour pickers */

	function initColourPickers() {
		if (!$ || !$.fn.wpColorPicker) { return; }

		$('.htech-ai-color').each(function () {
			var input = $(this);

			input.wpColorPicker({
				change: function (event, ui) {
					input.val(ui.color.toString());
					input.trigger('input');
					updatePreview();
				},
				clear: function () {
					updatePreview();
				}
			});

			input.on('input change', updatePreview);
		});
	}

	/* --------------------------------------------------------- colour fields */

	function normaliseHex(value, fallback) {
		var v = String(value || '').trim();
		if (!v) { return fallback; }
		if (v.charAt(0) !== '#') { v = '#' + v; }
		if (/^#[0-9a-f]{3}$/i.test(v)) {
			v = '#' + v.charAt(1) + v.charAt(1) + v.charAt(2) + v.charAt(2) + v.charAt(3) + v.charAt(3);
		}
		return /^#[0-9a-f]{6}$/i.test(v) ? v.toUpperCase() : fallback;
	}

	function initColourField(field) {
		var swatch = field.querySelector('.htech-color-field__swatch');
		var hex = field.querySelector('.htech-color-field__hex');
		var hidden = field.querySelector('input[type=hidden]');
		var fallback = field.getAttribute('data-default') || '#000000';

		if (!swatch || !hex || !hidden) { return; }

		var apply = function (value, source) {
			var colour = normaliseHex(value, fallback);
			hidden.value = colour;
			if (source !== 'hex') { hex.value = colour; }
			if (source !== 'swatch') { swatch.value = colour; }

			// Preview is optional: never let a preview error block colour edits.
			if (typeof updatePreview === 'function') { updatePreview(); }
		};

		swatch.addEventListener('input', function () { apply(swatch.value, 'swatch'); });
		hex.addEventListener('input', function () {
			var cleaned = normaliseHex(hex.value, '');
			if (cleaned) { apply(cleaned, 'hex'); }
		});
		hex.addEventListener('blur', function () { apply(hex.value, 'blur'); });
	}

	function initColourFields() {
		document.querySelectorAll('.htech-color-field').forEach(initColourField);
	}

	/* -------------------------------------------------------- live preview */

	function updatePreview() {
		var root = document.getElementById('htech-preview-root');
		if (!root) { return; }

		/*
		 * Colour fields render a swatch + hex box and submit a hidden input.
		 * The swatch holds the id, so read the hidden input for the real value.
		 */
		var get = function (id) {
			var el = document.getElementById(id);
			if (!el) { return ''; }

			if (el.classList.contains('htech-color-field__hex')) {
				var field = el.closest('.htech-color-field');
				var hidden = field ? field.querySelector('input[type=hidden]') : null;
				if (hidden) { return hidden.value; }
			}

			return el.value;
		};
		var checked = function (id) {
			var el = document.getElementById(id);
			return el ? el.checked : false;
		};

		var headerBg = get('htech-field-color_header_bg') || '#0A1F44';
		var secondary = get('htech-field-color_secondary') || '#00B4D8';
		var chatBg = get('htech-field-color_chat_bg') || '#F4F7FB';
		var userBg = get('htech-field-color_user_bubble') || '#00B4D8';
		var botBg = get('htech-field-color_bot_bubble') || '#FFFFFF';
		var textColour = get('htech-field-color_text') || '#0F172A';
		var buttonColour = get('htech-field-color_button') || '#00B4D8';
		var borderColour = get('htech-field-color_border') || '#E2E8F0';
		var font = get('htech-field-font_family') || 'Inter';
		var fontSize = get('htech-field-font_size') || 14;
		var radius = get('htech-field-border_radius') || 16;
		var headerStyle = get('htech-field-header_style') || 'gradient';
		var logoSize = get('htech-field-logo_size') || 34;
		var name = get('htech-field-chatbot_name') || 'Assistant';
		var welcome = document.getElementById('htech-preview-welcome');

		// #htech-preview-root is the preview *window* in the markup; the header is
		// its first child rather than a nested .htech-preview__head element.
		var head = root.querySelector('.htech-preview__head') || root.firstElementChild;
		var win = root.querySelector('.htech-preview__window') || root;
		var body = root.querySelector('.htech-preview__body');
		var userBubble = root.querySelector('.htech-preview__bubble--user');
		var botBubble = root.querySelector('.htech-preview__bubble--bot');
		var inputWrap = root.querySelector('.htech-preview__input');
		var send = root.querySelector('.htech-preview__send');
		// First child of .htech-preview__input is the fake text field; the send
		// button is also a div, so target the direct child explicitly.
		var inputField = root.querySelector('.htech-preview__input > div:first-child');
		var launcher = document.querySelector('.htech-preview__launcher');
		var nameEl = root.querySelector('.htech-preview__name');
		var logoEl = root.querySelector('.htech-preview__logo');

		// One missing node should never abort the whole preview.
		if (!win) { return; }

		win.style.borderRadius = radius + 'px';
		win.style.fontFamily = font + ', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
		win.style.fontSize = fontSize + 'px';

		if (headerStyle === 'minimal') {
			head.style.background = '#fff';
			head.style.color = textColour;
			head.style.borderBottom = '1px solid ' + borderColour;
		} else if (headerStyle === 'solid') {
			head.style.background = headerBg;
			head.style.color = '#fff';
			head.style.borderBottom = 'none';
		} else {
			head.style.background = 'linear-gradient(135deg, ' + headerBg + ' 0%, ' + mixColours(headerBg, secondary, 0.55) + ' 100%)';
			head.style.color = '#fff';
			head.style.borderBottom = 'none';
		}

		body.style.background = chatBg;
		userBubble.style.background = userBg;
		userBubble.style.color = contrastColour(userBg);
		botBubble.style.background = botBg;
		botBubble.style.color = textColour;
		botBubble.style.border = '1px solid ' + borderColour;

		inputWrap.style.borderTopColor = borderColour;
		inputWrap.style.background = botBg;
		inputField.style.borderColor = borderColour;
		send.style.background = buttonColour;
		send.style.color = contrastColour(buttonColour);

		if (launcher) {
			launcher.style.background = 'linear-gradient(135deg, ' + headerBg + ', ' + secondary + ')';
		}

		root.querySelectorAll('.htech-preview__chip').forEach(function (chip) {
			chip.style.color = secondary;
			chip.style.borderColor = borderColour;
		});

		if (nameEl) { nameEl.textContent = name; }
		if (logoEl) {
			logoEl.style.width = logoSize + 'px';
			logoEl.style.height = logoSize + 'px';
			var img = logoEl.querySelector('img');
			if (img) {
				img.style.width = logoSize + 'px';
				img.style.height = logoSize + 'px';
			}
		}
		if (welcome) { welcome.textContent = welcome.dataset.original || welcome.textContent; }

		var welcomeField = document.getElementById('htech-field-welcome_message');
		if (welcome && welcomeField && welcomeField.value) {
			welcome.textContent = welcomeField.value;
		}

		var darkToggle = document.getElementById('htech-field-dark_mode');
		if (darkToggle && darkToggle.checked) {
			if (win) { win.style.background = '#0b1626'; }
			body.style.background = '#0f1e33';
			botBubble.style.background = '#152a44';
			botBubble.style.color = '#e2e8f0';
			botBubble.style.borderColor = '#22384f';
			inputWrap.style.background = '#0b1626';
			inputField.style.color = '#8ba3bd';
		}

		var suggestionField = document.getElementById('htech-field-suggested_questions');
		var chips = root.querySelector('.htech-preview__chips');
		if (suggestionField && chips) {
			var lines = suggestionField.value.split(/\r?\n/).map(function (line) { return line.trim(); }).filter(Boolean).slice(0, 3);
			chips.innerHTML = '';
			lines.forEach(function (line) {
				var chip = document.createElement('span');
				chip.className = 'htech-preview__chip';
				chip.textContent = line;
				chip.style.color = secondary;
				chip.style.borderColor = borderColour;
				chips.appendChild(chip);
			});
		}
	}

	function hexToRgb(hex) {
		var clean = (hex || '').replace('#', '');
		if (clean.length === 3) {
			clean = clean[0] + clean[0] + clean[1] + clean[1] + clean[2] + clean[2];
		}
		var num = parseInt(clean, 16);
		return { r: (num >> 16) & 255, g: (num >> 8) & 255, b: num & 255 };
	}

	function rgbToHex(rgb) {
		return '#' + [rgb.r, rgb.g, rgb.b].map(function (v) {
			var hex = Math.max(0, Math.min(255, Math.round(v))).toString(16);
			return hex.length === 1 ? '0' + hex : hex;
		}).join('');
	}

	function mixColours(a, b, ratio) {
		var first = hexToRgb(a);
		var second = hexToRgb(b);
		return rgbToHex({
			r: first.r * (1 - ratio) + second.r * ratio,
			g: first.g * (1 - ratio) + second.g * ratio,
			b: first.b * (1 - ratio) + second.b * ratio
		});
	}

	function contrastColour(hex) {
		var rgb = hexToRgb(hex);
		var luminance = (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) / 255;
		return luminance > 0.6 ? '#0F172A' : '#FFFFFF';
	}

	function initPreview() {
		if (!document.getElementById('htech-preview-root')) { return; }

		document.querySelectorAll('#htech-ai-app input, #htech-ai-app select, #htech-ai-app textarea').forEach(function (input) {
			input.addEventListener('input', updatePreview);
			input.addEventListener('change', updatePreview);
		});

		document.querySelectorAll('[data-preview-device]').forEach(function (button) {
			button.addEventListener('click', function () {
				var device = button.getAttribute('data-preview-device');
				var stage = document.getElementById('htech-preview-stage');

				document.querySelectorAll('[data-preview-device]').forEach(function (b) {
					b.classList.toggle('button-primary', b === button);
				});

				if (stage) { stage.setAttribute('data-device', device); }

				var frame = document.getElementById('htech-preview-frame');
				if (frame) {
					frame.className = device === 'mobile' ? 'htech-preview__phone' : '';
				}
			});
		});

		/* Logo picker (uses the WP media modal). */
		var logoButton = document.getElementById('htech-logo-select');
		if (logoButton && window.wp && window.wp.media) {
			var frame = null;

			logoButton.addEventListener('click', function (event) {
				event.preventDefault();

				if (!frame) {
					frame = window.wp.media({
						title: (ADMIN.i18n && ADMIN.i18n.selectLogo) || 'Select logo',
						button: { text: (ADMIN.i18n && ADMIN.i18n.useLogo) || 'Use image' },
						multiple: false
					});

					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						var idField = document.getElementById('htech-field-logo_id');
						if (idField) { idField.value = attachment.id; }

						var preview = document.getElementById('htech-logo-preview');
						if (preview) {
							var size = (document.getElementById('htech-field-logo_size') || {}).value || 48;
							preview.innerHTML = '';
							var img = document.createElement('img');
							img.src = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
							img.style.width = size + 'px';
							img.style.height = size + 'px';
							img.style.objectFit = 'cover';
							img.style.borderRadius = '10px';
							preview.appendChild(img);
						}

						var previewLogo = document.querySelector('.htech-preview__logo');
						if (previewLogo) {
							var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
							previewLogo.innerHTML = '<img src="' + url + '" alt="" />';
						}

						var removeBtn = document.getElementById('htech-logo-remove');
						if (removeBtn) { removeBtn.style.display = ''; }

						toast('Logo selected. Save changes to apply it.', 'success');
					});
				}

				frame.open();
			});
		}

		var removeLogo = document.getElementById('htech-logo-remove');
		if (removeLogo) {
			removeLogo.addEventListener('click', function (event) {
				event.preventDefault();
				var idField = document.getElementById('htech-field-logo_id');
				if (idField) { idField.value = '0'; }

				var preview = document.getElementById('htech-logo-preview');
				if (preview) { preview.innerHTML = '<span class="htech-muted">No logo — the launcher icon is used.</span>'; }

				var previewLogo = document.querySelector('.htech-preview__logo');
				if (previewLogo) { previewLogo.textContent = 'AI'; }

				removeLogo.style.display = 'none';
			});
		}

		updatePreview();
	}

	/* ------------------------------------------------------- knowledge base */

	function initKnowledge() {
		if (ADMIN.page !== 'htech-ai-knowledge') { return; }

		document.querySelectorAll('[data-kb-section-form]').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				event.preventDefault();
				var button = form.querySelector('button[type="submit"]');
				busy(button, true, 'Saving…');

				api('/admin/kb/section', {
					method: 'POST',
					body: {
						section: form.getAttribute('data-kb-section'),
						content: form.querySelector('textarea').value,
						active: form.querySelector('input[type="checkbox"]') ? form.querySelector('input[type="checkbox"]').checked : true
					}
				}).then(function (data) {
					toast(data.message, 'success');
					refreshCoverage(data.coverage);
				}).catch(function (error) {
					toast(error.message, 'error');
				}).finally(function () {
					busy(button, false);
				});
			});
		});

		var faqForm = document.getElementById('htech-faq-form');
		if (faqForm) {
			faqForm.addEventListener('submit', function (event) {
				event.preventDefault();
				var button = faqForm.querySelector('button[type="submit"]');
				busy(button, true, 'Saving…');

				api('/admin/kb/faq', {
					method: 'POST',
					body: {
						id: parseInt(faqForm.querySelector('[name="faq_id"]').value, 10) || 0,
						question: faqForm.querySelector('[name="faq_question"]').value,
						answer: faqForm.querySelector('[name="faq_answer"]').value,
						active: faqForm.querySelector('[name="faq_active"]').checked
					}
				}).then(function () {
					toast('FAQ saved. Reloading…', 'success');
					setTimeout(function () { window.location.reload(); }, 600);
				}).catch(function (error) {
					toast(error.message, 'error');
					busy(button, false);
				});
			});
		}

		var customForm = document.getElementById('htech-custom-form');
		if (customForm) {
			customForm.addEventListener('submit', function (event) {
				event.preventDefault();
				var button = customForm.querySelector('button[type="submit"]');
				busy(button, true, 'Saving…');

				api('/admin/kb/custom', {
					method: 'POST',
					body: {
						id: parseInt(customForm.querySelector('[name="custom_id"]').value, 10) || 0,
						title: customForm.querySelector('[name="custom_title"]').value,
						content: customForm.querySelector('[name="custom_content"]').value,
						active: customForm.querySelector('[name="custom_active"]').checked
					}
				}).then(function () {
					toast('Entry saved. Reloading…', 'success');
					setTimeout(function () { window.location.reload(); }, 600);
				}).catch(function (error) {
					toast(error.message, 'error');
					busy(button, false);
				});
			});
		}

		document.querySelectorAll('[data-kb-delete-faq]').forEach(function (button) {
			button.addEventListener('click', function () {
				if (!window.confirm((ADMIN.i18n && ADMIN.i18n.confirmDelete) || 'Delete?')) { return; }

				api('/admin/kb/faq/delete', { method: 'POST', body: { id: parseInt(button.getAttribute('data-kb-delete-faq'), 10) } })
					.then(function () {
						var row = button.closest('tr');
						if (row) { row.remove(); }
						toast('FAQ deleted.', 'success');
					})
					.catch(function (error) { toast(error.message, 'error'); });
			});
		});

		document.querySelectorAll('[data-kb-delete-custom]').forEach(function (button) {
			button.addEventListener('click', function () {
				if (!window.confirm((ADMIN.i18n && ADMIN.i18n.confirmDelete) || 'Delete?')) { return; }

				api('/admin/kb/custom/delete', { method: 'POST', body: { id: parseInt(button.getAttribute('data-kb-delete-custom'), 10) } })
					.then(function () {
						var row = button.closest('tr');
						if (row) { row.remove(); }
						toast('Entry deleted.', 'success');
					})
					.catch(function (error) { toast(error.message, 'error'); });
			});
		});

		document.querySelectorAll('[data-kb-edit-faq]').forEach(function (button) {
			button.addEventListener('click', function () {
				var payload = JSON.parse(button.getAttribute('data-payload'));
				faqForm.querySelector('[name="faq_id"]').value = payload.id;
				faqForm.querySelector('[name="faq_question"]').value = payload.question;
				faqForm.querySelector('[name="faq_answer"]').value = payload.answer;
				faqForm.querySelector('[name="faq_active"]').checked = !!payload.active;
				faqForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
			});
		});

		document.querySelectorAll('[data-kb-edit-custom]').forEach(function (button) {
			button.addEventListener('click', function () {
				var payload = JSON.parse(button.getAttribute('data-payload'));
				customForm.querySelector('[name="custom_id"]').value = payload.id;
				customForm.querySelector('[name="custom_title"]').value = payload.title;
				customForm.querySelector('[name="custom_content"]').value = payload.content;
				customForm.querySelector('[name="custom_active"]').checked = !!payload.active;
				customForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
			});
		});

		var reindex = document.getElementById('htech-reindex');
		if (reindex) {
			reindex.addEventListener('click', function () {
				busy(reindex, true, 'Indexing…');
				api('/admin/kb/reindex', { method: 'POST' })
					.then(function (data) {
						toast(data.message, 'success');
						refreshCoverage(data.coverage);
					})
					.catch(function (error) { toast(error.message, 'error'); })
					.finally(function () { busy(reindex, false); });
			});
		}

		/* Website importer: fetch → review → save. */
		var importerForm = document.getElementById('htech-importer-form');
		if (importerForm) {
			importerForm.addEventListener('submit', function (event) {
				event.preventDefault();
				var button = importerForm.querySelector('button[type="submit"]');
				busy(button, true, 'Fetching…');

				api('/admin/importer/site', { method: 'POST', body: { url: importerForm.querySelector('[name="import_url"]').value } })
					.then(function (data) {
						var review = document.getElementById('htech-importer-review');
						review.removeAttribute('hidden');
						review.querySelector('[name="import_text"]').value = data.text;
						review.querySelector('[name="import_title"]').value = data.title || new URL(data.url).hostname;
						document.getElementById('htech-importer-meta').textContent =
							data.title + ' — ' + data.characters.toLocaleString() + ' characters extracted. ' + data.notice;
						review.scrollIntoView({ behavior: 'smooth', block: 'start' });
					})
					.catch(function (error) { toast(error.message, 'error'); })
					.finally(function () { busy(button, false); });
			});

			var saveImported = document.getElementById('htech-importer-save');
			if (saveImported) {
				saveImported.addEventListener('click', function () {
					var review = document.getElementById('htech-importer-review');
					busy(saveImported, true, 'Saving…');

					api('/admin/kb/custom', {
						method: 'POST',
						body: {
							id: 0,
							title: review.querySelector('[name="import_title"]').value,
							content: review.querySelector('[name="import_text"]').value,
							active: true
						}
					}).then(function () {
						toast('Imported content saved to the knowledge base.', 'success');
						setTimeout(function () { window.location.reload(); }, 700);
					}).catch(function (error) {
						toast(error.message, 'error');
						busy(saveImported, false);
					});
				});
			}
		}
	}

	function refreshCoverage(coverage) {
		if (!coverage) { return; }

		var map = {
			'htech-coverage-chunks': coverage.chunks,
			'htech-coverage-sections': coverage.sections,
			'htech-coverage-faqs': coverage.faqs,
			'htech-coverage-custom': coverage.custom,
			'htech-coverage-words': coverage.words
		};

		Object.keys(map).forEach(function (id) {
			var el = document.getElementById(id);
			if (el) { el.textContent = map[id]; }
		});
	}

	/* ----------------------------------------------------------- retrieval */

	function initRetrievalTest() {
		var form = document.getElementById('htech-retrieval-form');
		if (!form) { return; }

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			var button = form.querySelector('button[type="submit"]');
			var output = document.getElementById('htech-retrieval-output');
			busy(button, true, (ADMIN.i18n && ADMIN.i18n.searching) || 'Searching…');
			output.innerHTML = '<div class="htech-skeleton" style="width:70%"></div>';

			api('/admin/test-retrieval', { method: 'POST', body: { query: form.querySelector('[name="query"]').value } })
				.then(function (data) {
					var html = '';

					html += '<div class="htech-alert htech-alert--info"><span class="dashicons dashicons-lightbulb"></span><div><strong>Fallback answer the chatbot would give</strong><p>' +
						escapeHtml(data.answer) + '</p></div></div>';

					if (!data.results.length) {
						html += '<div class="htech-alert htech-alert--warn"><span class="dashicons dashicons-warning"></span><div>' +
							((ADMIN.i18n && ADMIN.i18n.noResults) || 'No matching knowledge found.') +
							' Add a manual entry or upload a document that covers this topic.</div></div>';
					} else {
						html += '<table class="htech-table"><thead><tr><th>Score</th><th>Source</th><th>Matched text</th></tr></thead><tbody>';
						data.results.forEach(function (result) {
							html += '<tr><td><strong>' + result.score.toFixed(2) + '</strong></td><td>' +
								escapeHtml(result.source) + '<br><span class="htech-badge">' + escapeHtml(result.type) + '</span></td><td>' +
								escapeHtml(result.snippet) + '</td></tr>';
						});
						html += '</tbody></table>';
					}

					output.innerHTML = html;
				})
				.catch(function (error) {
					output.innerHTML = '<div class="htech-alert htech-alert--error">' + escapeHtml(error.message) + '</div>';
				})
				.finally(function () { busy(button, false); });
		});
	}

	function escapeHtml(value) {
		var div = document.createElement('div');
		div.textContent = value == null ? '' : String(value);
		return div.innerHTML;
	}

	/* ------------------------------------------------------------- documents */

	function initDocuments() {
		if (ADMIN.page !== 'htech-ai-documents') { return; }
		if (!window.HTECH_AI_Processor) { return; }

		var processor = window.HTECH_AI_Processor;
		var dropzone = document.getElementById('htech-dropzone');
		var fileInput = document.getElementById('htech-file-input');
		var list = document.getElementById('htech-upload-list');

		if (!dropzone || !fileInput) { return; }

		dropzone.addEventListener('click', function () { fileInput.click(); });

		['dragenter', 'dragover'].forEach(function (name) {
			dropzone.addEventListener(name, function (event) {
				event.preventDefault();
				dropzone.classList.add('is-over');
			});
		});

		['dragleave', 'drop'].forEach(function (name) {
			dropzone.addEventListener(name, function (event) {
				event.preventDefault();
				dropzone.classList.remove('is-over');
			});
		});

		dropzone.addEventListener('drop', function (event) {
			if (event.dataTransfer && event.dataTransfer.files.length) {
				handleFiles(event.dataTransfer.files);
			}
		});

		fileInput.addEventListener('change', function () {
			handleFiles(fileInput.files);
			fileInput.value = '';
		});

		function handleFiles(files) {
			var array = Array.prototype.slice.call(files);

			if (array.length > ADMIN.maxFiles) {
				toast((ADMIN.i18n && ADMIN.i18n.tooManyFiles) || 'Too many files.', 'error');
				array = array.slice(0, ADMIN.maxFiles);
			}

			array.forEach(function (file) { upload(file); });
		}

		function upload(file) {
			var ext = (file.name.split('.').pop() || '').toLowerCase();

			if (ADMIN.extensions.indexOf(ext) === -1) {
				toast(file.name + ': .' + ext + ' is not an enabled file type.', 'error');
				return;
			}
			if (file.size > ADMIN.maxUpload) {
				toast(file.name + ': ' + ((ADMIN.i18n && ADMIN.i18n.fileTooLarge) || 'File too large.'), 'error');
				return;
			}

			var row = document.createElement('div');
			row.className = 'htech-upload-item';
			row.innerHTML = '<span class="dashicons dashicons-media-document"></span>' +
				'<span style="min-width:140px;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escapeHtml(file.name) + '</span>' +
				'<span class="htech-upload-item__bar"><i></i></span>' +
				'<span class="htech-upload-status htech-muted">' + ((ADMIN.i18n && ADMIN.i18n.uploading) || 'Uploading…') + '</span>';
			list.appendChild(row);

			var bar = row.querySelector('.htech-upload-item__bar i');
			var status = row.querySelector('.htech-upload-status');

			var setStatus = function (text, progress, cls) {
				status.textContent = text;
				if (typeof progress === 'number') { bar.style.width = progress + '%'; }
				row.classList.remove('is-done', 'is-error');
				if (cls) { row.classList.add(cls); }
			};

			// Step 1: for PDF/DOCX the browser extracts the text first (PDF.js /
			// Mammoth), which is far more reliable than server-side parsing on
			// shared hosting. TXT/CSV are also handled here for consistency.
			setStatus((ADMIN.i18n && ADMIN.i18n.extracting) || 'Extracting…', 15);

			processor.extract(file, function (progress) {
				var pct = 15 + Math.round(progress * 45);
				setStatus((ADMIN.i18n && ADMIN.i18n.extracting) || 'Extracting…', pct);
			}).then(function (extracted) {
				var form = new FormData();
				form.append('file', file);
				if (extracted && extracted.text) {
					form.append('client_text', extracted.text);
				}

				setStatus((ADMIN.i18n && ADMIN.i18n.indexing) || 'Indexing…', 70);

				return api('/admin/documents/upload', { method: 'POST', body: form });
			}).then(function (data) {
				if (data && data.ok) {
					setStatus((data.chunks || 0) + ' chunks indexed', 100, 'is-done');
					toast(file.name + ' indexed successfully.', 'success');
					setTimeout(function () { window.location.reload(); }, 900);
				} else {
					setStatus((data && data.message) || 'Failed', 100, 'is-error');
					toast((data && data.message) || 'Processing failed.', 'error');
				}
			}).catch(function (error) {
				setStatus(error.message || 'Failed', 100, 'is-error');
				toast(error.message || 'Upload failed.', 'error');
			});
		}

		/* Row actions */
		document.querySelectorAll('[data-doc-toggle]').forEach(function (button) {
			button.addEventListener('click', function () {
				busy(button, true);
				api('/admin/documents/toggle', { method: 'POST', body: { document_id: parseInt(button.getAttribute('data-doc-toggle'), 10) } })
					.then(function (data) {
						toast(data.is_active ? 'Document activated.' : 'Document deactivated — it is no longer searchable.', 'success');
						setTimeout(function () { window.location.reload(); }, 600);
					})
					.catch(function (error) { toast(error.message, 'error'); busy(button, false); });
			});
		});

		document.querySelectorAll('[data-doc-delete]').forEach(function (button) {
			button.addEventListener('click', function () {
				if (!window.confirm((ADMIN.i18n && ADMIN.i18n.confirmDelete) || 'Delete?')) { return; }
				busy(button, true);

				api('/admin/documents/delete', { method: 'POST', body: { document_id: parseInt(button.getAttribute('data-doc-delete'), 10) } })
					.then(function (data) {
						toast(data.message || 'Deleted.', 'success');
						setTimeout(function () { window.location.reload(); }, 600);
					})
					.catch(function (error) { toast(error.message, 'error'); busy(button, false); });
			});
		});

		document.querySelectorAll('[data-doc-reprocess]').forEach(function (button) {
			button.addEventListener('click', function () {
				var id = parseInt(button.getAttribute('data-doc-reprocess'), 10);
				busy(button, true, 'Processing…');

				api('/admin/documents/reprocess', { method: 'POST', body: { document_id: id } })
					.then(function (data) {
						if (data.needs_browser_parse) {
							toast(data.message, 'error');
							busy(button, false);
							return;
						}
						toast(data.message || 'Reprocessed.', 'success');
						setTimeout(function () { window.location.reload(); }, 700);
					})
					.catch(function (error) { toast(error.message, 'error'); busy(button, false); });
			});
		});
	}

	/* ------------------------------------------------------------ tools etc. */

	function initTools() {
		document.querySelectorAll('[data-tool]').forEach(function (button) {
			button.addEventListener('click', function () {
				var tool = button.getAttribute('data-tool');
				var scope = button.getAttribute('data-scope') || '';

				var confirmMessage = button.getAttribute('data-confirm');
				if (confirmMessage && !window.confirm(confirmMessage)) { return; }

				var paths = {
					'flush-limits': '/admin/tools/flush-limits',
					'reset-settings': '/admin/tools/reset-settings',
					'purge': '/admin/tools/purge-data'
				};

				if (!paths[tool]) { return; }

				busy(button, true, 'Working…');

				api(paths[tool], { method: 'POST', body: scope ? { scope: scope } : {} })
					.then(function (data) {
						toast(data.message || 'Done.', 'success');
						if (tool !== 'flush-limits') {
							setTimeout(function () { window.location.reload(); }, 900);
						} else {
							busy(button, false);
						}
					})
					.catch(function (error) { toast(error.message, 'error'); busy(button, false); });
			});
		});

		document.querySelectorAll('[data-delete-conversation]').forEach(function (button) {
			button.addEventListener('click', function () {
				if (!window.confirm((ADMIN.i18n && ADMIN.i18n.confirmDelete) || 'Delete?')) { return; }
				busy(button, true);

				api('/admin/conversations/delete', { method: 'POST', body: { id: parseInt(button.getAttribute('data-delete-conversation'), 10) } })
					.then(function (data) {
						toast(data.message, 'success');
						var row = button.closest('tr');
						if (row) { row.remove(); }
						busy(button, false);
					})
					.catch(function (error) { toast(error.message, 'error'); busy(button, false); });
			});
		});
	}

	/* ------------------------------------------------------------- app start */

	function boot() {
		initTabs();
		initCopy();
		initConditionals();
		initColourFields();
		initColourPickers();
		initPreview();
		initKnowledge();
		initRetrievalTest();
		initDocuments();
		initTools();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
