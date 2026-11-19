/**
 * HTECH AI Assistant — visitor chat widget (standalone build).
 *
 * This one file powers three delivery modes:
 *   1. the WordPress plugin (config injected via window.HTECH_AI_CONFIG),
 *   2. the standalone JS package (config in window.HTECH_AI_CONFIG too),
 *   3. embedded installs via HTECH_AI.mount(config).
 *
 * The UI lives in a Shadow DOM, so page CSS cannot break it and widget CSS
 * cannot leak into the page. The page only ever sees two host elements.
 *
 * On-device AI (WebLLM + WebGPU) is loaded lazily and only when the visitor
 * asks for it, or when the administrator configured auto-load. If WebGPU is
 * unavailable the widget silently uses the server-side knowledge-base
 * fallback, which needs no model at all.
 */
(function (window, document) {
	'use strict';

	if (window.HTECH_AI && window.HTECH_AI.__version) {
		return;
	}

	var DEFAULTS = {
		name: 'Assistant',
		business: '',
		welcome: 'Hi! How can I help you today?',
		position: 'bottom-right',
		width: 380,
		height: 560,
		radius: 16,
		headerStyle: 'gradient',
		launcher: 'spark',
		launcherLabel: 'Chat with us',
		showLauncherLabel: false,
		online: true,
		onlineText: 'Online',
		offlineText: 'Away',
		logo: '',
		logoSize: 34,
		font: 'Inter',
		fontSize: 14,
		darkMode: false,
		attribution: false,
		contact: '',
		suggestions: [],
		maxLength: 1000,
		colors: {
			primary: '#0A1F44',
			secondary: '#00B4D8',
			header: '#0A1F44',
			chat: '#F4F7FB',
			user: '#00B4D8',
			bot: '#FFFFFF',
			text: '#0F172A',
			button: '#00B4D8',
			border: '#E2E8F0'
		},
		ai: {
			enabled: true,
			mode: 'webllm',
			model: 'Qwen2.5-1.5B-Instruct-q4f16_1-MLC',
			autoLoad: 'manual',
			libUrl: 'https://cdn.jsdelivr.net/npm/@mlc-ai/web-llm@0.2.89/+esm',
			temperature: 0.6,
			maxTokens: 512
		},
		lead: { enabled: false, trigger: 'manual', after: 4, fields: ['name', 'email'], required: ['name', 'email'] },
		i18n: {
			placeholder: 'Type your message…',
			send: 'Send',
			close: 'Close',
			clear: 'New chat',
			retry: 'Retry',
			thinking: 'Thinking…',
			loadingModel: 'Loading AI model…',
			fallbackBadge: 'Knowledge base mode',
			fallbackNote: 'Not generative AI — answers are matched from the business knowledge base.',
			aiBadge: 'AI answer',
			askAi: 'Enable AI answers (downloads a model)',
			noWebGpu: 'This device cannot run the on-device AI model, so answers come from the business knowledge base.',
			errorGeneric: 'Something went wrong. Please try again.',
			errorNetwork: 'Network error — check your connection and retry.',
			leadCta: 'Share my details',
			leadTitle: 'Let us contact you',
			leadName: 'Your name',
			leadEmail: 'Email address',
			leadPhone: 'Phone number',
			leadService: 'Service of interest',
			leadMessage: 'How can we help?',
			leadSubmit: 'Send details',
			leadCancel: 'Cancel',
			sources: 'Source',
			poweredBy: 'Powered by HTECH AI Assistant',
			modelReady: 'AI ready'
		}
	};

	var STORAGE_KEY = 'htech_ai_widget_v1';
	var state = {
		config: null,
		open: false,
		messages: [],
		conversationId: 0,
		sending: false,
		lastUserMessage: '',
		leadShown: false,
		engine: null,
		aiState: 'idle', // idle | checking | downloading | ready | unavailable | failed
		aiError: '',
		aiProgress: 0,
		enginePromise: null
	};

	/* ------------------------------------------------------------- utilities */

	function assign(target) {
		for (var i = 1; i < arguments.length; i++) {
			var source = arguments[i];
			if (!source) { continue; }
			Object.keys(source).forEach(function (key) {
				if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
					target[key] = assign({}, target[key] || {}, source[key]);
				} else if (source[key] !== undefined) {
					target[key] = source[key];
				}
			});
		}
		return target;
	}

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (key) {
				if (key === 'class') { node.className = attrs[key]; }
				else if (key === 'text') { node.textContent = attrs[key]; }
				else if (key.indexOf('on') === 0 && typeof attrs[key] === 'function') { node.addEventListener(key.slice(2), attrs[key]); }
				else if (attrs[key] !== null && attrs[key] !== undefined) { node.setAttribute(key, attrs[key]); }
			});
		}
		(children || []).forEach(function (child) {
			if (child === null || child === undefined) { return; }
			node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
		});
		return node;
	}

	function timeNow() {
		var date = new Date();
		return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
	}

	function escapeText(value) {
		return String(value == null ? '' : value);
	}

	/** Minimal, safe formatting: links, bold, line breaks. No HTML injection. */
	function renderRichText(container, text) {
		container.textContent = '';
		var lines = String(text).split(/\n{2,}/);

		lines.forEach(function (block, index) {
			if (index > 0) { container.appendChild(el('br')); }
			block.split('\n').forEach(function (line, lineIndex) {
				if (lineIndex > 0) { container.appendChild(el('br')); }

				var pattern = /(\*\*[^*]+\*\*|https?:\/\/[^\s]+)/g;
				var lastIndex = 0;
				var match;

				while ((match = pattern.exec(line)) !== null) {
					if (match.index > lastIndex) {
						container.appendChild(document.createTextNode(line.slice(lastIndex, match.index)));
					}

					if (match[0].indexOf('**') === 0) {
						container.appendChild(el('strong', { text: match[0].slice(2, -2) }));
					} else {
						container.appendChild(el('a', {
							href: match[0],
							target: '_blank',
							rel: 'noopener noreferrer nofollow',
							text: match[0]
						}));
					}

					lastIndex = match.index + match[0].length;
				}

				if (lastIndex < line.length) {
					container.appendChild(document.createTextNode(line.slice(lastIndex)));
				}
			});
		});
	}

	/* ------------------------------------------------------------ persistence */

	function saveSession() {
		try {
			// Only the transcript is kept locally, never anything sensitive.
			window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
				messages: state.messages.slice(-40),
				conversationId: state.conversationId
			}));
		} catch (error) { /* private mode */ }
	}

	function restoreSession() {
		try {
			var raw = window.sessionStorage.getItem(STORAGE_KEY);
			if (!raw) { return false; }
			var data = JSON.parse(raw);
			if (data && Array.isArray(data.messages) && data.messages.length) {
				state.messages = data.messages;
				state.conversationId = data.conversationId || 0;
				return true;
			}
		} catch (error) { /* ignore */ }
		return false;
	}

	function clearSession() {
		try { window.sessionStorage.removeItem(STORAGE_KEY); } catch (error) { /* ignore */ }
	}

	/* ------------------------------------------------------------------- API */

	function apiPath(path) {
		var base = (state.config.restUrl || '').replace(/\/+$/, '');
		return base + path;
	}

	/** Fetch a fresh bootstrap payload (also issues a nonce for this session). */
	function refreshConfig() {
		var url = apiPath('/bootstrap');

		return fetch(url, { method: 'GET', credentials: 'same-origin' }).then(function (response) {
			if (!response.ok) { throw new Error('bootstrap'); }
			return response.json();
		}).then(function (data) {
			if (data && data.nonce) {
				state.config.nonce = data.nonce;
				state.config.restUrl = data.restUrl || state.config.restUrl;
			}
			return data;
		});
	}

	function apiCall(path, body, isRetry) {
		var headers = { 'Content-Type': 'application/json' };
		var nonce = state.config.nonce ||
			(window.HTECH_AI_BOOT && window.HTECH_AI_BOOT.config ? window.HTECH_AI_BOOT.config.nonce : null);

		// Custom header: X-WP-Nonce would trigger core's cookie check and 403
		// anonymous visitors.
		if (nonce) { headers['X-Htech-Nonce'] = nonce; }

		return fetch(apiPath(path), {
			method: 'POST',
			headers: headers,
			credentials: 'same-origin',
			body: JSON.stringify(body || {})
		}).then(function (response) {
			return response.json().then(function (data) {
				if (!response.ok) {
					var message = (data && (data.message || (data.data && data.data.message))) || state.config.i18n.errorGeneric;
					var error = new Error(message);
					error.status = response.status;
					error.code = (data && (data.code || (data.data && data.data.status))) || '';
					throw error;
				}
				return data;
			});
		}).catch(function (error) {
			if (error && error.status) {
				// Expired/invalid nonce (e.g. a long-lived embed or a cached page):
				// fetch a fresh one once, then replay the request.
				if (!isRetry && 403 === error.status && /nonce|session/i.test(error.message || '')) {
					return refreshConfig().then(function () {
						return apiCall(path, body, true);
					});
				}
				throw error;
			}
			throw new Error(state.config.i18n.errorNetwork);
		});
	}

	/* ------------------------------------------------------------- WebLLM AI */

	function hasWebGPU() {
		return !!(navigator.gpu && navigator.gpu.requestAdapter);
	}

	function loadWebLLM() {
		if (window.webllm) {
			return Promise.resolve(window.webllm);
		}

		if (state.enginePromise) {
			return state.enginePromise;
		}

		var url = state.config.ai.libUrl;

		state.enginePromise = import(/* webpackIgnore: true */ url).then(function (lib) {
			return lib;
		}).catch(function (firstError) {
			state.enginePromise = null;

			// Distinguish "library missing" from "browser blocked the import".
			var hint = hasWebGPU()
				? ' The AI library could not be loaded from ' + url + ' — the administrator can host it locally in General Settings.'
				: ' Your browser does not support WebGPU.';

			throw new Error(hint);
		});

		return state.enginePromise;
	}

	function updateAiBadge() {
		if (!ui || !ui.statusBadge) { return; }

		var labels = {
			idle: state.config.ai.enabled ? state.config.i18n.askAi : '',
			checking: '…',
			downloading: state.config.i18n.loadingModel + ' ' + state.aiProgress + '%',
			ready: state.config.i18n.modelReady,
			unavailable: '',
			failed: ''
		};

		var text = labels[state.aiState] || '';
		ui.statusBadge.textContent = text;
		ui.statusBadge.hidden = !text;
		ui.statusBadge.dataset.state = state.aiState;
	}

	function initAi(trigger) {
		if (!state.config.ai.enabled || state.config.ai.mode !== 'webllm') {
			state.aiState = 'unavailable';
			updateAiBadge();
			return Promise.resolve(null);
		}

		if (state.engine) {
			return Promise.resolve(state.engine);
		}

		if (!hasWebGPU()) {
			state.aiState = 'unavailable';
			state.aiError = state.config.i18n.noWebGpu;
			updateAiBadge();
			if (trigger === 'manual') { showNotice(state.config.i18n.noWebGpu); }
			return Promise.resolve(null);
		}

		if (state.enginePromise && state.aiState === 'downloading') {
			return state.enginePromise;
		}

		state.aiState = 'checking';
		updateAiBadge();

		return loadWebLLM().then(function (webllm) {
			state.aiState = 'downloading';
			state.aiProgress = 0;
			if (ui && ui.modeNote) { ui.modeNote.hidden = true; }
			updateAiBadge();

			var engine = new webllm.MLCEngine();

			engine.setInitProgressCallback(function (report) {
				if (report && typeof report.progress === 'number') {
					state.aiProgress = Math.round(report.progress * 100);
				} else if (report && typeof report.text === 'string') {
					var digits = report.text.match(/(\d+)%/);
					if (digits) { state.aiProgress = parseInt(digits[1], 10); }
				}
				updateAiBadge();
			});

			return engine.reload(state.config.ai.model).then(function () {
				state.engine = engine;
				state.aiState = 'ready';
				if (ui && ui.modeNote) { ui.modeNote.hidden = true; }
				state.aiProgress = 100;
				updateAiBadge();
				return engine;
			});
		}).catch(function (error) {
			state.engine = null;
			state.aiState = 'failed';
			state.aiError = error && error.message ? error.message : state.config.i18n.errorGeneric;
			updateAiBadge();
			showNotice(state.aiError);
			return null;
		});
	}

	/**
	 * Ask the model, grounded with the server-provided system prompt.
	 * Returns the generated text, or null so the caller can fall back.
	 */
	function generate(engine, payload, onToken) {
		var messages = [{ role: 'system', content: payload.system }];

		(payload.history || []).forEach(function (turn) {
			messages.push({ role: turn.role, content: turn.content });
		});

		messages.push({ role: 'user', content: state.lastUserMessage });

		var chunks = [];
		var options = {
			temperature: Math.max(0, Math.min(1.5, payload.temperature || 0.6)),
			max_tokens: Math.max(64, Math.min(2048, payload.max_tokens || 512))
		};

		return engine.chat.completions.create({
			stream: true,
			messages: messages,
			...options
		}).then(function (stream) {
			return new Promise(function (resolve) {
				var finalText = '';

				function pump() {
					stream.next().then(function (result) {
						if (result.done) {
							resolve(finalText || chunks.join(''));
							return;
						}

						var delta = result.value && result.value.choices && result.value.choices[0] && result.value.choices[0].delta;
						var piece = delta && delta.content ? delta.content : '';

						if (piece) {
							finalText += piece;
							chunks.push(piece);
							if (onToken) { onToken(chunks.join('')); }
						}

						pump();
					}).catch(function () {
						resolve(finalText || chunks.join(''));
					});
				}

				pump();
			});
		});
	}

	/* ------------------------------------------------------------------- UI */

	var ui = null;

	function cssVariables() {
		var c = state.config.colors;
		var dark = state.config.darkMode;

		var vars = [
			'--ht-primary:' + c.primary,
			'--ht-secondary:' + c.secondary,
			'--ht-header:' + c.header,
			'--ht-chat:' + (dark ? '#0F1E33' : c.chat),
			'--ht-user:' + c.user,
			'--ht-bot:' + (dark ? '#152A44' : c.bot),
			'--ht-text:' + (dark ? '#E2E8F0' : c.text),
			'--ht-muted:' + (dark ? '#94A8C0' : '#5B6B85'),
			'--ht-button:' + c.button,
			'--ht-border:' + (dark ? '#22384F' : c.border),
			'--ht-radius:' + state.config.radius + 'px',
			'--ht-width:' + state.config.width + 'px',
			'--ht-height:' + state.config.height + 'px',
			'--ht-font:' + "'" + state.config.font + "', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
			'--ht-font-size:' + state.config.fontSize + 'px',
			'--ht-logo-size:' + state.config.logoSize + 'px'
		];

		if (state.config.headerStyle === 'gradient') {
			vars.push('--ht-header-bg:linear-gradient(135deg,' + c.header + ' 0%,' + c.secondary + ' 135%)');
		} else if (state.config.headerStyle === 'minimal') {
			vars.push('--ht-header-bg:' + (dark ? '#152A44' : '#FFFFFF'));
		} else {
			vars.push('--ht-header-bg:' + c.header);
		}

		return vars.join(';');
	}

	function shadowStyles() {
		return [
			':host{all:initial;}',
			'*,*::before,*::after{box-sizing:border-box;}',
			'.root{',
			'  font-family:var(--ht-font);font-size:var(--ht-font-size);color:var(--ht-text);',
			'  line-height:1.55;-webkit-font-smoothing:antialiased;',
			'}',
			'.launcher{',
			'  position:relative;display:flex;align-items:center;gap:10px;border:0;cursor:pointer;',
			'  padding:0;background:none;font-family:inherit;',
			'}',
			'.launcher__btn{',
			'  width:58px;height:58px;border-radius:50%;border:0;cursor:pointer;',
			'  display:grid;place-items:center;color:#fff;',
			'  background:var(--ht-header-bg);',
			'  box-shadow:0 12px 28px -10px rgba(10,31,68,.7), 0 2px 6px rgba(10,31,68,.2);',
			'  transition:transform .18s cubic-bezier(.2,.8,.3,1), box-shadow .18s ease;',
			'}',
			'.launcher__btn:hover{transform:translateY(-2px) scale(1.04);box-shadow:0 18px 34px -12px rgba(10,31,68,.8);}',
			'.launcher__btn:active{transform:scale(.97);}',
			'.launcher__btn:focus-visible{outline:3px solid var(--ht-secondary);outline-offset:3px;}',
			'.launcher__btn svg{width:26px;height:26px;display:block;}',
			'.launcher__btn img{width:30px;height:30px;object-fit:contain;border-radius:8px;}',
			'.launcher__label{',
			'  background:#fff;color:var(--ht-text);border:1px solid var(--ht-border);',
			'  border-radius:999px;padding:7px 14px;font-size:12.5px;font-weight:600;white-space:nowrap;',
			'  box-shadow:0 8px 22px -12px rgba(10,31,68,.6);',
			'}',
			'.launcher__dot{',
			'  position:absolute;top:2px;right:2px;width:13px;height:13px;border-radius:50%;',
			'  background:#10B981;border:2.5px solid #fff;',
			'}',
			'.launcher__badge{',
			'  position:absolute;top:-2px;right:-2px;min-width:20px;height:20px;border-radius:999px;',
			'  background:#EF4444;color:#fff;font-size:11px;font-weight:700;display:grid;place-items:center;',
			'  padding:0 5px;border:2px solid #fff;',
			'}',

			'.panel{',
			'  pointer-events:auto;display:flex;flex-direction:column;',
			'  width:var(--ht-width);height:var(--ht-height);max-height:calc(100vh - 40px);',
			'  background:var(--ht-chat);border-radius:var(--ht-radius);overflow:hidden;',
			'  border:1px solid var(--ht-border);',
			'  box-shadow:0 28px 70px -28px rgba(10,31,68,.65), 0 6px 18px -10px rgba(10,31,68,.3);',
			'  opacity:0;transform:translateY(14px) scale(.98);',
			'  transition:opacity .22s ease, transform .22s cubic-bezier(.2,.8,.3,1);',
			'  visibility:hidden;',
			'}',
			'.panel.is-open{opacity:1;transform:none;visibility:visible;}',
			'.panel--inline{position:static !important;width:100%;height:100%;max-height:none;opacity:1;transform:none;visibility:visible;box-shadow:none;border-radius:var(--ht-radius);}',

			'.header{',
			'  display:flex;align-items:center;gap:11px;padding:14px 15px;',
			'  background:var(--ht-header-bg);color:#fff;flex:0 0 auto;position:relative;',
			'}',
			'.panel[data-header="minimal"] .header{color:var(--ht-text);border-bottom:1px solid var(--ht-border);}',
			'.header__logo{',
			'  width:var(--ht-logo-size);height:var(--ht-logo-size);border-radius:10px;flex:0 0 auto;',
			'  display:grid;place-items:center;overflow:hidden;',
			'  background:rgba(255,255,255,.18);font-weight:700;font-size:calc(var(--ht-logo-size) * .38);',
			'}',
			'.panel[data-header="minimal"] .header__logo{background:rgba(10,31,68,.08);color:var(--ht-primary);}',
			'.header__logo img{width:100%;height:100%;object-fit:cover;display:block;}',
			'.header__meta{flex:1 1 auto;min-width:0;}',
			'.header__name{font-weight:700;font-size:14px;line-height:1.25;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
			'.header__status{font-size:11px;opacity:.9;display:flex;align-items:center;gap:5px;margin-top:2px;}',
			'.header__status i{width:6px;height:6px;border-radius:50%;background:#4ADE80;display:inline-block;}',
			'.header__actions{display:flex;gap:4px;flex:0 0 auto;}',
			'.icon-btn{',
			'  width:30px;height:30px;border-radius:8px;border:0;cursor:pointer;',
			'  background:rgba(255,255,255,.14);color:inherit;display:grid;place-items:center;',
			'  transition:background .15s ease;',
			'}',
			'.panel[data-header="minimal"] .icon-btn{background:rgba(10,31,68,.06);}',
			'.icon-btn:hover{background:rgba(255,255,255,.28);}',
			'.panel[data-header="minimal"] .icon-btn:hover{background:rgba(10,31,68,.12);}',
			'.icon-btn:focus-visible{outline:2px solid currentColor;outline-offset:2px;}',
			'.icon-btn svg{width:15px;height:15px;}',

			'.mode-note{',
			'margin:0;padding:7px 16px 9px;font-size:11.5px;line-height:1.45;',
			'color:var(--ht-muted);background:transparent;border-bottom:1px solid var(--ht-border);',
			'}',
			'.badges{',
			'  display:flex;gap:6px;flex-wrap:wrap;padding:8px 14px 0;align-items:center;',
			'}',
			'.badge{',
			'  font-size:10.5px;font-weight:700;letter-spacing:.2px;padding:3px 8px;border-radius:999px;',
			'  background:rgba(0,180,216,.12);color:#0369A1;border:1px solid rgba(0,180,216,.3);',
			'  cursor:default;',
			'}',
			'button.badge{cursor:pointer;font-family:inherit;}',
			'.badge[data-state="ready"]{background:rgba(16,185,129,.14);color:#047857;border-color:rgba(16,185,129,.35);}',
			'.badge[data-state="downloading"],.badge[data-state="checking"]{background:rgba(245,158,11,.14);color:#B45309;border-color:rgba(245,158,11,.35);}',
			'.badge[hidden]{display:none;}',

			'.messages{',
			'  flex:1 1 auto;overflow-y:auto;overscroll-behavior:contain;padding:14px;',
			'  display:flex;flex-direction:column;gap:10px;scroll-behavior:smooth;',
			'}',
			'.messages::-webkit-scrollbar{width:8px;}',
			'.messages::-webkit-scrollbar-thumb{background:rgba(15,23,42,.18);border-radius:99px;}',

			'.msg{display:flex;flex-direction:column;max-width:86%;animation:msg-in .2s ease both;}',
			'.msg--user{align-self:flex-end;align-items:flex-end;}',
			'.msg--bot{align-self:flex-start;align-items:flex-start;}',
			'.bubble{',
			'  padding:10px 13px;border-radius:14px;font-size:var(--ht-font-size);line-height:1.55;',
			'  word-wrap:break-word;overflow-wrap:anywhere;white-space:pre-wrap;',
			'}',
			'.msg--user .bubble{background:var(--ht-user);color:#fff;border-bottom-right-radius:5px;}',
			'.msg--bot .bubble{background:var(--ht-bot);color:var(--ht-text);border:1px solid var(--ht-border);border-bottom-left-radius:5px;}',
			'.bubble a{color:inherit;text-decoration:underline;}',
			'.bubble strong{font-weight:700;}',
			'.msg__meta{font-size:10px;color:var(--ht-muted);margin-top:4px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;}',
			'.msg__source{',
			'  font-size:10px;padding:1px 6px;border-radius:999px;background:rgba(0,180,216,.1);',
			'  color:#0369A1;border:1px solid rgba(0,180,216,.25);max-width:100%;overflow:hidden;',
			'  text-overflow:ellipsis;white-space:nowrap;',
			'}',
			'.msg__notice{font-size:11px;color:var(--ht-muted);margin-top:5px;font-style:italic;}',

			'.typing{display:inline-flex;gap:4px;align-items:center;padding:4px 2px;}',
			'.typing i{width:6px;height:6px;border-radius:50%;background:var(--ht-muted);opacity:.5;animation:blink 1.2s infinite;}',
			'.typing i:nth-child(2){animation-delay:.18s;}',
			'.typing i:nth-child(3){animation-delay:.36s;}',

			'.suggestions{display:flex;flex-wrap:wrap;gap:6px;padding:0 14px 10px;}',
			'.chip{',
			'  font-family:inherit;font-size:11.5px;padding:6px 11px;border-radius:999px;cursor:pointer;',
			'  background:var(--ht-bot);color:var(--ht-secondary);border:1px solid var(--ht-border);',
			'  transition:border-color .15s ease, transform .12s ease;',
			'}',
			'.chip:hover{border-color:var(--ht-secondary);transform:translateY(-1px);}',
			'.chip:focus-visible{outline:2px solid var(--ht-secondary);outline-offset:1px;}',

			'.composer{',
			'  flex:0 0 auto;display:flex;align-items:flex-end;gap:8px;padding:10px 12px;',
			'  background:var(--ht-bot);border-top:1px solid var(--ht-border);',
			'}',
			'.composer textarea{',
			'  flex:1 1 auto;resize:none;border:1px solid var(--ht-border);border-radius:12px;',
			'  padding:9px 12px;font-family:inherit;font-size:var(--ht-font-size);line-height:1.45;',
			'  max-height:110px;min-height:38px;background:var(--ht-chat);color:var(--ht-text);',
			'  outline:none;transition:border-color .15s ease, box-shadow .15s ease;',
			'}',
			'.composer textarea:focus{border-color:var(--ht-secondary);box-shadow:0 0 0 3px rgba(0,180,216,.16);}',
			'.composer textarea::placeholder{color:var(--ht-muted);opacity:.85;}',
			'.send{',
			'  width:38px;height:38px;border-radius:12px;border:0;cursor:pointer;flex:0 0 auto;',
			'  background:var(--ht-button);color:#fff;display:grid;place-items:center;',
			'  transition:transform .12s ease, opacity .15s ease;',
			'}',
			'.send:hover{transform:translateY(-1px);}',
			'.send:disabled{opacity:.45;cursor:not-allowed;transform:none;}',
			'.send svg{width:17px;height:17px;}',

			'.footer{',
			'  flex:0 0 auto;padding:6px 12px 8px;text-align:center;font-size:10px;',
			'  color:var(--ht-muted);background:var(--ht-bot);border-top:1px solid var(--ht-border);',
			'}',
			'.footer a{color:inherit;}',

			'.notice{',
			'  margin:0 14px 8px;padding:9px 11px;border-radius:10px;font-size:11.5px;line-height:1.5;',
			'  background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.35);color:#92400E;',
			'}',
			'.notice--error{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#991B1B;}',
			'.notice__actions{display:flex;gap:8px;margin-top:7px;}',
			'.notice button{',
			'  font-family:inherit;font-size:11px;font-weight:700;padding:4px 10px;border-radius:7px;',
			'  border:1px solid currentColor;background:transparent;color:inherit;cursor:pointer;',
			'}',

			'.lead{',
			'  position:absolute;inset:0;background:rgba(10,31,68,.5);display:flex;align-items:flex-end;',
			'  padding:14px;z-index:5;backdrop-filter:blur(2px);',
			'}',
			'.lead__card{',
			'  width:100%;background:var(--ht-bot);border-radius:14px;padding:16px;',
			'  box-shadow:0 20px 50px -20px rgba(10,31,68,.7);max-height:100%;overflow-y:auto;',
			'}',
			'.lead__card h3{margin:0 0 4px;font-size:14px;color:var(--ht-text);}',
			'.lead__card p{margin:0 0 12px;font-size:11.5px;color:var(--ht-muted);line-height:1.5;}',
			'.lead__field{margin-bottom:9px;}',
			'.lead__field label{display:block;font-size:11px;font-weight:600;margin-bottom:3px;color:var(--ht-text);}',
			'.lead__field input,.lead__field textarea{',
			'  width:100%;border:1px solid var(--ht-border);border-radius:9px;padding:7px 10px;',
			'  font-family:inherit;font-size:12.5px;background:var(--ht-chat);color:var(--ht-text);outline:none;',
			'}',
			'.lead__field textarea{resize:vertical;min-height:58px;}',
			'.lead__field input:focus,.lead__field textarea:focus{border-color:var(--ht-secondary);}',
			'.lead__actions{display:flex;gap:8px;margin-top:12px;}',
			'.lead__submit{',
			'  flex:1;border:0;border-radius:9px;padding:9px;font-family:inherit;font-size:12.5px;font-weight:700;',
			'  background:var(--ht-button);color:#fff;cursor:pointer;',
			'}',
			'.lead__cancel{',
			'  border:1px solid var(--ht-border);background:transparent;border-radius:9px;padding:9px 14px;',
			'  font-family:inherit;font-size:12.5px;font-weight:600;color:var(--ht-muted);cursor:pointer;',
			'}',
			'.lead__error{font-size:11.5px;color:#B91C1C;margin-top:8px;}',

			'@keyframes blink{0%,60%,100%{opacity:.28;transform:translateY(0);}30%{opacity:.9;transform:translateY(-2px);}}',
			'@keyframes msg-in{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}',
			'@media (prefers-reduced-motion: reduce){*{animation-duration:.001s !important;transition-duration:.001s !important;}}',

			'@media (max-width:520px){',
			'  .panel{width:100vw;height:100vh;height:100dvh;max-height:100dvh;border-radius:0;border:0;}',
			'  .panel.is-open{transform:none;}',
			'  .messages{padding:12px;}',
			'}'
		].join('\n');
	}

	var LAUNCHER_ICONS = {
		spark: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 15.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z"/></svg>',
		chat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.8-.9L3 21l1.9-5.1A8.4 8.4 0 0 1 12 3.1a8.4 8.4 0 0 1 9 8.4z"/></svg>',
		help: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.6 2.2c-.7.4-1.1 1-1.1 1.8v.3"/><circle cx="12" cy="17" r=".9" fill="currentColor" stroke="none"/></svg>'
	};

	var ICON_CLOSE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>';
	var ICON_SEND = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>';
	var ICON_REFRESH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>';

	function buildUi(config) {
		var isInline = config.mode === 'inline';
		var panelHost = document.getElementById(config.panelMount || 'htech-ai-panel');
		var launcherHost = document.getElementById(config.launcherMount || 'htech-ai-launcher');

		if (!panelHost) {
			panelHost = el('div', { id: config.panelMount || 'htech-ai-panel' });
			document.body.appendChild(panelHost);
		}

		if (!launcherHost && !isInline) {
			launcherHost = el('div', { id: config.launcherMount || 'htech-ai-launcher' });
			document.body.appendChild(launcherHost);
		}

		panelHost.setAttribute('data-position', config.position);
		if (launcherHost) {
			launcherHost.setAttribute('data-position', config.position);
		}

		var shadow = panelHost.attachShadow({ mode: 'open' });
		var style = document.createElement('style');
		style.textContent = shadowStyles();
		shadow.appendChild(style);

		var root = el('div', { class: 'root' });
		root.setAttribute('style', cssVariables());
		shadow.appendChild(root);

		/* ---------------- panel ---------------- */
		var logoContent;
		if (config.logo) {
			logoContent = el('img', { src: config.logo, alt: '' });
		} else {
			logoContent = document.createTextNode((config.business || config.name || 'AI').slice(0, 2).toUpperCase());
		}

		var statusDot = el('i');
		var statusText = el('span', { text: config.online ? config.onlineText : config.offlineText });
		if (!config.online) { statusDot.style.background = '#94A3B8'; }

		var closeBtn = el('button', {
			class: 'icon-btn',
			type: 'button',
			title: config.i18n.close,
			'aria-label': config.i18n.close
		});
		closeBtn.innerHTML = ICON_CLOSE;

		var refreshBtn = el('button', {
			class: 'icon-btn',
			type: 'button',
			title: config.i18n.clear,
			'aria-label': config.i18n.clear
		});
		refreshBtn.innerHTML = ICON_REFRESH;

		var header = el('div', { class: 'header' }, [
			el('div', { class: 'header__logo' }, [logoContent]),
			el('div', { class: 'header__meta' }, [
				el('p', { class: 'header__name', text: config.name }),
				el('div', { class: 'header__status' }, [statusDot, statusText])
			]),
			el('div', { class: 'header__actions' }, [refreshBtn, closeBtn])
		]);

		var statusBadge = el('button', {
			class: 'badge',
			type: 'button',
			title: config.i18n.askAi
		});
		statusBadge.hidden = true;

		var modeBadge = el('span', { class: 'badge', text: config.i18n.fallbackBadge });
		var modeNote = el('p', { class: 'mode-note', text: config.i18n.fallbackNote });

		// Show the "not generative AI" disclosure from the start whenever the
		// on-device model is not active, rather than only after the first reply.
		modeNote.hidden = !!(config.ai.enabled && config.ai.mode === 'webllm' && config.ai.loaded);
		var badges = el('div', { class: 'badges' }, [statusBadge, modeBadge]);

		var messages = el('div', { class: 'messages', role: 'log', 'aria-live': 'polite' });
		var suggestions = el('div', { class: 'suggestions' });

		var textarea = el('textarea', {
			rows: 1,
			placeholder: config.i18n.placeholder,
			'aria-label': config.i18n.placeholder,
			maxlength: config.maxLength
		});

		var sendBtn = el('button', {
			class: 'send',
			type: 'button',
			title: config.i18n.send,
			'aria-label': config.i18n.send
		});
		sendBtn.innerHTML = ICON_SEND;

		var composer = el('div', { class: 'composer' }, [textarea, sendBtn]);
		var footer = config.attribution
			? el('div', { class: 'footer' }, [el('span', { text: config.i18n.poweredBy })])
			: null;

		var panel = el('div', {
			class: 'panel' + (isInline ? ' panel--inline' : ''),
			role: 'dialog',
			'aria-label': config.name,
			'data-header': config.headerStyle
		}, [header, badges, modeNote, messages, suggestions, composer, footer]);

		root.appendChild(panel);

		/* ---------------- launcher ---------------- */
		var launcherBtn = null;
		var launcherDot = null;

		if (launcherHost && !isInline) {
			var shadowLauncher = launcherHost.attachShadow({ mode: 'open' });
			var launcherStyle = document.createElement('style');
			launcherStyle.textContent = shadowStyles();
			shadowLauncher.appendChild(launcherStyle);

			var launcherRoot = el('div', { class: 'root' });
			launcherRoot.setAttribute('style', cssVariables());

			launcherBtn = el('button', {
				class: 'launcher__btn',
				type: 'button',
				'aria-label': config.launcherLabel || config.name,
				'aria-expanded': 'false'
			});

			if (config.launcher === 'logo' && config.logo) {
				launcherBtn.appendChild(el('img', { src: config.logo, alt: '' }));
			} else {
				launcherBtn.innerHTML = LAUNCHER_ICONS[config.launcher] || LAUNCHER_ICONS.spark;
			}

			if (config.online) {
				launcherDot = el('span', { class: 'launcher__dot' });
			}

			var wrapper = el('div', { class: 'launcher' }, [launcherBtn, launcherDot]);

			if (config.showLauncherLabel && config.launcherLabel) {
				wrapper.appendChild(el('span', { class: 'launcher__label', text: config.launcherLabel }));
			}

			launcherRoot.appendChild(wrapper);
			shadowLauncher.appendChild(launcherRoot);
		}

		ui = {
			panelHost: panelHost,
			launcherHost: launcherHost,
			shadow: shadow,
			panel: panel,
			messages: messages,
			suggestions: suggestions,
			textarea: textarea,
			sendBtn: sendBtn,
			closeBtn: closeBtn,
			refreshBtn: refreshBtn,
			statusBadge: statusBadge,
			modeBadge: modeBadge,
			modeNote: modeNote,
			badges: badges,
			launcherBtn: launcherBtn,
			isInline: isInline,
			leadOverlay: null
		};

		/* ---------------- behaviour ---------------- */

		closeBtn.addEventListener('click', function () { closePanel(); });

		if (isInline) {
			closeBtn.hidden = true;
		}

		if (launcherBtn) {
			launcherBtn.addEventListener('click', function () {
				if (state.open) { closePanel(); } else { openPanel(); }
			});
		}

		refreshBtn.addEventListener('click', function () {
			resetConversation();
		});

		sendBtn.addEventListener('click', submitMessage);

		textarea.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' && !event.shiftKey) {
				event.preventDefault();
				submitMessage();
			}
		});

		textarea.addEventListener('input', function () {
			textarea.style.height = 'auto';
			textarea.style.height = Math.min(110, textarea.scrollHeight) + 'px';
		});

		statusBadge.addEventListener('click', function () {
			if (state.aiState === 'idle' || state.aiState === 'failed') {
				initAi('manual');
			}
		});

		/* Keyboard: Escape closes. */
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && state.open && !isInline) {
				closePanel();
			}
		});

		return ui;
	}

	/* -------------------------------------------------------------- rendering */

	function scrollToBottom() {
		if (!ui) { return; }
		ui.messages.scrollTop = ui.messages.scrollHeight;
	}

	function renderMessage(message) {
		var isUser = message.role === 'user';
		var bubble = el('div', { class: 'bubble' });

		if (message.pending) {
			bubble.appendChild(el('span', { class: 'typing' }, [el('i'), el('i'), el('i')]));
		} else {
			renderRichText(bubble, message.content);
		}

		var meta = el('div', { class: 'msg__meta' }, [
			el('span', { text: message.time || timeNow() })
		]);

		if (message.mode && !isUser) {
			var labels = {
				'fallback': state.config.i18n.fallbackBadge,
				'ai': state.config.i18n.aiBadge,
				'ai-rag': state.config.i18n.aiBadge,
				'no-answer': state.config.i18n.fallbackBadge
			};
			if (labels[message.mode]) {
				meta.appendChild(el('span', { class: 'msg__source', text: labels[message.mode] }));
			}
		}

		if (message.sources && message.sources.length) {
			var source = message.sources[0];
			if (source.label) {
				meta.appendChild(el('span', {
					class: 'msg__source',
					title: message.sources.map(function (s) { return s.label; }).join(', '),
					text: state.config.i18n.sources + ': ' + source.label
				}));
			}
		}

		if (message.notice) {
			var notice = el('div', { class: 'msg__notice', text: message.notice });
			meta.appendChild(notice);
		}

		var wrapper = el('div', { class: 'msg ' + (isUser ? 'msg--user' : 'msg--bot') }, [bubble, meta]);
		ui.messages.appendChild(wrapper);
		scrollToBottom();

		return { wrapper: wrapper, bubble: bubble };
	}

	function renderTranscript() {
		ui.messages.textContent = '';

		if (!state.messages.length) {
			state.messages.push({
				role: 'assistant',
				content: state.config.welcome,
				time: timeNow(),
				mode: 'welcome'
			});
		}

		state.messages.forEach(renderMessage);
		renderSuggestions(state.config.suggestions);
		scrollToBottom();
	}

	function renderSuggestions(list) {
		ui.suggestions.textContent = '';

		if (!list || !list.length) { return; }

		list.slice(0, 4).forEach(function (text) {
			var chip = el('button', { class: 'chip', type: 'button', text: text });
			chip.addEventListener('click', function () {
				ui.textarea.value = text;
				submitMessage();
			});
			ui.suggestions.appendChild(chip);
		});
	}

	function showNotice(text, options) {
		if (!ui) { return; }

		var existing = ui.panel.querySelector('.notice');
		if (existing) { existing.remove(); }

		var notice = el('div', { class: 'notice' + (options && options.error ? ' notice--error' : '') }, [
			el('div', { text: text })
		]);

		if (options && options.retry) {
			var actions = el('div', { class: 'notice__actions' });

			var retry = el('button', { type: 'button', text: state.config.i18n.retry });
			retry.addEventListener('click', function () {
				notice.remove();
				if (state.lastUserMessage) {
					ui.textarea.value = state.lastUserMessage;
					submitMessage();
				}
			});

			var dismiss = el('button', { type: 'button', text: '×' });
			dismiss.addEventListener('click', function () { notice.remove(); });

			actions.appendChild(retry);
			actions.appendChild(dismiss);
			notice.appendChild(actions);
		}

		ui.panel.insertBefore(notice, ui.badges.nextSibling);

		if (!options || !options.persist) {
			setTimeout(function () {
				if (notice.parentNode) { notice.remove(); }
			}, 9000);
		}
	}

	function setModeBadge(mode) {
		if (!ui) { return; }

		if (mode === 'ai' || mode === 'ai-rag') {
			ui.modeBadge.textContent = state.config.i18n.aiBadge;
			ui.modeBadge.style.background = 'rgba(16,185,129,.14)';
			ui.modeBadge.style.color = '#047857';
			ui.modeBadge.style.borderColor = 'rgba(16,185,129,.35)';

			if (ui.modeNote) { ui.modeNote.hidden = true; }
		} else {
			ui.modeBadge.textContent = state.config.i18n.fallbackBadge;
			ui.modeBadge.style.background = '';
			ui.modeBadge.style.color = '';
			ui.modeBadge.style.borderColor = '';

			// Be explicit that these answers are looked up, not generated.
			if (ui.modeNote) { ui.modeNote.hidden = false; }
		}
	}

	/* -------------------------------------------------------- open / close */

	function openPanel() {
		if (!ui) { return; }

		state.open = true;
		ui.panel.classList.add('is-open');
		if (ui.launcherBtn) { ui.launcherBtn.setAttribute('aria-expanded', 'true'); }

		renderTranscript();
		setTimeout(function () { ui.textarea.focus(); }, 220);

		if (state.config.ai.enabled && state.config.ai.autoLoad === 'open' && state.aiState === 'idle') {
			initAi('auto');
		}
	}

	function closePanel() {
		if (!ui) { return; }

		state.open = false;
		ui.panel.classList.remove('is-open');
		if (ui.launcherBtn) { ui.launcherBtn.setAttribute('aria-expanded', 'false'); }

		if (state.config.clearOnClose) {
			resetConversation(true);
		}
	}

	function resetConversation(keepOpen) {
		state.messages = [];
		state.conversationId = 0;
		state.lastUserMessage = '';
		state.leadShown = false;
		clearSession();

		if (ui) {
			ui.messages.textContent = '';
			if (ui.leadOverlay) { ui.leadOverlay.remove(); ui.leadOverlay = null; }
		}

		apiCall('/chat/reset', {}).then(function (data) {
			if (data && data.conversation_id) {
				state.conversationId = data.conversation_id;
			}
			if (data && data.suggestions) {
				state.config.suggestions = data.suggestions;
			}
			renderTranscript();
		}).catch(function () {
			renderTranscript();
		});

		if (keepOpen && ui) {
			ui.textarea.focus();
		}
	}

	/* ------------------------------------------------------------- sending */

	function submitMessage() {
		if (!ui || state.sending) { return; }

		var text = (ui.textarea.value || '').trim();
		if (!text) { return; }

		if (text.length > state.config.maxLength) {
			text = text.slice(0, state.config.maxLength);
		}

		ui.textarea.value = '';
		ui.textarea.style.height = 'auto';
		state.lastUserMessage = text;
		state.sending = true;
		ui.sendBtn.disabled = true;

		var userMessage = { role: 'user', content: text, time: timeNow() };
		state.messages.push(userMessage);
		renderMessage(userMessage);

		ui.suggestions.textContent = '';

		var typing = { role: 'assistant', content: '', pending: true, time: timeNow() };
		var rendered = renderMessage(typing);

		sendToServer(text, rendered)
			.finally(function () {
				state.sending = false;
				ui.sendBtn.disabled = false;
				saveSession();
				scrollToBottom();
			});
	}

	function sendToServer(text, rendered) {
		var engineReady = state.aiState === 'ready' && !!state.engine;
		var userTurns = state.messages.filter(function (m) { return m.role === 'user'; }).length;

		return apiCall('/chat/message', {
			message: text,
			conversation_id: state.conversationId || 0,
			ai_ready: engineReady,
			page_url: window.location.href
		}).then(function (data) {
			if (data.conversation_id) {
				state.conversationId = data.conversation_id;
			}

			if (data.mode === 'ai' && data.ai && engineReady) {
				return runAiAnswer(data, rendered, text);
			}

			return finalizeAnswer(data, rendered, text, userTurns);
		}).catch(function (error) {
			rendered.wrapper.remove();
			showNotice(error.message || state.config.i18n.errorGeneric, { error: true, retry: true });
		});
	}

	function runAiAnswer(payload, rendered, userMessage) {
		rendered.bubble.textContent = '';
		rendered.bubble.appendChild(el('span', { class: 'typing' }, [el('i'), el('i'), el('i')]));
		state.lastUserMessage = userMessage;

		var started = Date.now();

		return generate(state.engine, payload.ai, function (partial) {
			renderRichText(rendered.bubble, partial);
			scrollToBottom();
		}).then(function (answer) {
			var latency = Date.now() - started;

			// Even if the model produced nothing usable, the server supplied an
			// extractive fallback answer built from the same context.
			var text = (answer || '').trim();

			if (!text) {
				return finalizeAnswer({
					answer: payload.fallback_answer || state.config.i18n.errorGeneric,
					mode: 'fallback',
					sources: payload.sources || []
				}, rendered, userMessage, 0);
			}

			// Server-side guardrails run again on generated text.
			return apiCall('/chat/complete', {
				conversation_id: state.conversationId || 0,
				answer: text,
				context: payload.ai.context || '',
				sources: payload.sources || [],
				latency_ms: latency
			}).then(function (data) {
				return finalizeAnswer(data, rendered, userMessage, 0, true);
			}).catch(function () {
				return finalizeAnswer({
					answer: text,
					mode: 'ai',
					sources: payload.sources || []
				}, rendered, userMessage, 0);
			});
		}).catch(function (error) {
			state.aiState = 'failed';
			updateAiBadge();
			return finalizeAnswer({
				answer: payload.fallback_answer || '',
				mode: 'fallback',
				sources: payload.sources || [],
				notice: (error && error.message) ? error.message + ' — answering from the business knowledge base instead.' : ''
			}, rendered, userMessage, 0);
		});
	}

	function finalizeAnswer(data, rendered, userMessage, userTurns, alreadyRendered) {
		var answer = (data && data.answer) || state.config.i18n.errorGeneric;

		if (!alreadyRendered) {
			renderRichText(rendered.bubble, answer);
		}

		var message = {
			role: 'assistant',
			content: answer,
			time: timeNow(),
			mode: data.mode,
			sources: data.sources || [],
			notice: data.notice || ''
		};

		state.messages.push(message);

		// Rebuild meta line with mode + source chips.
		var meta = rendered.wrapper.querySelector('.msg__meta');
		if (meta) {
			meta.textContent = '';
			meta.appendChild(el('span', { text: message.time }));

			if (data.mode_label) {
				meta.appendChild(el('span', { class: 'msg__source', text: data.mode_label }));
			}

			if (message.sources.length && message.sources[0].label) {
				meta.appendChild(el('span', {
					class: 'msg__source',
					title: message.sources.map(function (s) { return s.label; }).join(', '),
					text: state.config.i18n.sources + ': ' + message.sources[0].label
				}));
			}
		}

		if (message.notice) {
			rendered.wrapper.appendChild(el('div', { class: 'msg__notice', text: message.notice }));
		}

		setModeBadge(data.mode);
		renderSuggestions(data.suggestions || state.config.suggestions);

		// Lead capture: offer on explicit action, after N turns, or when the
		// bot could not answer and lead collection is on.
		if (state.config.lead.enabled && !state.leadShown) {
			var trigger = state.config.lead.trigger;
			var turnCount = state.messages.filter(function (m) { return m.role === 'user'; }).length;
			var shouldOffer =
				data.action === 'lead' ||
				((trigger === 'auto' || trigger === 'both') && turnCount >= state.config.lead.after) ||
				(data.mode === 'no-answer' && trigger !== 'manual');

			if (shouldOffer) {
				offerLead();
			}
		}

		scrollToBottom();
		return data;
	}

	/* ------------------------------------------------------------ lead form */

	function offerLead() {
		if (!ui || state.leadShown || !state.config.lead.enabled) { return; }
		state.leadShown = true;

		var fields = state.config.lead.fields.length ? state.config.lead.fields : ['name', 'email'];
		var required = state.config.lead.required || [];
		var labels = {
			name: state.config.i18n.leadName,
			email: state.config.i18n.leadEmail,
			phone: state.config.i18n.leadPhone,
			service: state.config.i18n.leadService,
			description: state.config.i18n.leadMessage
		};

		var inputs = {};
		var form = el('form', { class: 'lead__card' });

		form.appendChild(el('h3', { text: state.config.i18n.leadTitle }));
		form.appendChild(el('p', { text: 'We will only use these details to reply to your enquiry.' }));

		fields.forEach(function (field) {
			if (!labels[field]) { return; }

			var control;
			if (field === 'description') {
				control = el('textarea', { name: field, rows: 3 });
			} else {
				control = el('input', {
					name: field,
					type: field === 'email' ? 'email' : (field === 'phone' ? 'tel' : 'text'),
					autocomplete: field === 'email' ? 'email' : (field === 'phone' ? 'tel' : 'name')
				});
			}

			if (required.indexOf(field) !== -1) {
				control.setAttribute('required', 'required');
			}

			inputs[field] = control;

			form.appendChild(el('div', { class: 'lead__field' }, [
				el('label', { text: labels[field] + (required.indexOf(field) !== -1 ? ' *' : '') }),
				control
			]));
		});

		var errorLine = el('div', { class: 'lead__error' });
		var submit = el('button', { class: 'lead__submit', type: 'submit', text: state.config.i18n.leadSubmit });
		var cancel = el('button', { class: 'lead__cancel', type: 'button', text: state.config.i18n.leadCancel });

		form.appendChild(el('div', { class: 'lead__actions' }, [submit, cancel]));
		form.appendChild(errorLine);

		var overlay = el('div', { class: 'lead' }, [form]);

		cancel.addEventListener('click', function () {
			overlay.remove();
			ui.leadOverlay = null;
		});

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			submit.disabled = true;
			errorLine.textContent = '';

			var payload = { conversation_id: state.conversationId || 0 };
			Object.keys(inputs).forEach(function (key) {
				payload[key] = inputs[key].value;
			});

			apiCall('/lead', payload).then(function (data) {
				overlay.remove();
				ui.leadOverlay = null;

				var message = {
					role: 'assistant',
					content: data.message || state.config.lead.success,
					time: timeNow(),
					mode: 'lead'
				};

				state.messages.push(message);
				renderMessage(message);
				scrollToBottom();
			}).catch(function (error) {
				errorLine.textContent = error.message || state.config.i18n.errorGeneric;
				submit.disabled = false;
			});
		});

		ui.panel.appendChild(overlay);
		ui.leadOverlay = overlay;
		inputs[Object.keys(inputs)[0]].focus();
	}

	/* ----------------------------------------------------------------- mount */

	function mount(config) {
		if (!config) { return null; }

		state.config = assign({}, DEFAULTS, config);
		state.config.i18n = assign({}, DEFAULTS.i18n, config.i18n || {});

		buildUi(state.config);

		if (state.config.mode === 'inline') {
			state.open = true;
			ui.panel.classList.add('is-open');
			renderTranscript();
		} else {
			// Restore the previous transcript in this tab, if any.
			if (restoreSession()) {
				ui.messages.textContent = '';
				state.messages.forEach(renderMessage);
				renderSuggestions(state.config.suggestions);
			}

			// Auto-load the model only when the administrator asked for it.
			if (state.config.ai.enabled && state.config.ai.autoLoad === 'eager') {
				initAi('auto');
			} else if (state.config.ai.enabled) {
				state.aiState = 'idle';
				updateAiBadge();
			} else {
				state.aiState = 'unavailable';
				updateAiBadge();
			}
		}

		return { open: openPanel, close: closePanel, reset: resetConversation };
	}

	/* ------------------------------------------------------------------ boot */

	function boot() {
		var config = window.HTECH_AI_CONFIG ||
			(window.HTECH_AI_BOOT && window.HTECH_AI_BOOT.config) || null;

		if (!config) { return; }

		// The plugin inlines a nonce. Standalone/embed installs may not, in
		// which case the first API call fetches one automatically.
		mount(config);
	}

	window.HTECH_AI = {
		__version: '1.0.0',
		mount: mount,
		open: function () { if (ui) { openPanel(); } },
		close: function () { if (ui) { closePanel(); } },
		reset: function () { resetConversation(); },
		getState: function () { return { open: state.open, messages: state.messages.length, ai: state.aiState }; }
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})(window, document);
