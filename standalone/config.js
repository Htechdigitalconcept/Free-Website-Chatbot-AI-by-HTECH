/**
 * HTECH AI Assistant — standalone configuration.
 *
 * This is the ONLY file most installs need to edit. Everything else in this
 * folder is the widget itself.
 *
 * Quick start
 * -----------
 * 1. Open this file and change the business details, colours and knowledge URL.
 * 2. Make sure `knowledge.json` sits next to `standalone.js`.
 * 3. Add the two tags to your page (see README.md):
 *      <link rel="stylesheet" href="htech/chatbot-widget.css">
 *      <script src="htech/config.js"></script>
 *      <script src="htech/standalone.js"></script>
 *
 * Nothing here talks to a server. The knowledge file is downloaded once and
 * searched in the visitor's browser, so this works on any static host
 * (Netlify, Cloudflare Pages, GitHub Pages, cPanel, even a USB stick).
 */
window.HTECH_AI_CONFIG = {
	/* ------------------------------------------------------------- identity */
	name: 'Assistant',                       // Shown in the widget header.
	business: 'Your Business Name',          // Used in the system prompt.
	welcome: 'Hi! Ask me anything about our products, prices or policies.',
	language: 'en',
	website: 'https://example.com',

	/* ------------------------------------------------------------ appearance */
	position: 'bottom-right',                 // bottom-right | bottom-left
	width: 400,
	height: 620,
	radius: 18,
	headerStyle: 'gradient',                  // gradient | solid | light
	launcher: 'chat',                         // chat | bubble | round | logo
	launcherLabel: 'Chat with us',
	showLauncherLabel: true,
	online: true,
	onlineText: 'Online now',
	offlineText: 'Leave a message',
	logo: '',                                 // e.g. 'logo.png' next to this file
	logoSize: 36,
	font: 'Inter',
	fontSize: 15,
	darkMode: false,
	attribution: false,                       // "Powered by HTECH" footer.

	/* 9 colour slots — replace with the client's brand palette. */
	colors: {
		primary: '#0A1F44',
		secondary: '#00B4D8',
		header: '#0A1F44',
		chat: '#F5F7FA',
		user: '#00B4D8',
		bot: '#FFFFFF',
		text: '#0A1F44',
		button: '#00B4D8',
		border: '#DCE3EC'
	},

	/* -------------------------------------------------------------- behaviour */
	contact: 'Phone: +234 800 000 0000 · hello@example.com',
	clearOnClose: false,
	maxLength: 1000,
	contextMessages: 6,                       // Previous turns given to the model.
	storeChats: false,                        // Keep the transcript in localStorage.
	unknownText: "I don't have enough verified information to answer that accurately. Please contact the business directly for clarification.",

	suggestions: [
		'What services do you offer?',
		'What are your prices?',
		'What are your opening hours?'
	],

	/* --------------------------------------------------------------- retrieval */
	retrieval: {
		topK: 4,                              // Chunks pulled per question.
		minScore: 0.18                        // Below this → "I don't know".
	},

	/* ------------------------------------------------------------------- AI */
	/*
	 * off      → retrieval-only answers from knowledge.json (always works).
	 * webllm   → same retrieval, then a local model writes a natural answer.
	 *
	 * WebLLM downloads the model to the visitor's browser cache the first time
	 * it is used (hundreds of MB). It needs desktop Chrome/Edge 113+ with
	 * WebGPU. Firefox, Safari and most mobile browsers cannot run it, which is
	 * why the widget never downloads the model automatically: `autoLoad: false`
	 * keeps everyone on the fast retrieval answers until they ask for more.
	 */
	ai: {
		enabled: false,
		mode: 'off',
		model: 'Llama-3.2-1B-Instruct-q4f16_1-MLC',
		autoLoad: false,                      // true = start download on first open.
		libUrl: 'https://cdn.jsdelivr.net/npm/@mlc-ai/web-llm@0.2.89/+esm',
		temperature: 0.6,
		maxTokens: 512
	},

	/* ---------------------------------------------------------------- knowledge */
	/*
	 * The knowledge file exported from WordPress (HTECH AI Assistant →
	 * Installation → Standalone bundle) or produced by build.html.
	 * Set `knowledge` inline instead if you prefer to inline the data.
	 */
	knowledgeUrl: 'knowledge.json',
	knowledge: null,

	/* ------------------------------------------------------------------ leads */
	lead: {
		enabled: false,
		fields: ['name', 'email', 'phone', 'service', 'description'],
		required: ['name', 'email'],
		buttonText: 'Send my details',
		success: 'Thank you! Our team will be in touch shortly.',
		triggers: ['contact', 'quote', 'price', 'phone'],   // Show after these intents.
		showAfterMessages: 3,

		// Pick ONE delivery method:
		endpoint: '',        // Any URL accepting JSON POST (plugin REST route, Formspree, Worker).
		headers: {},         // Extra headers, e.g. { 'Authorization': 'Bearer …' }
		onSubmit: null,      // function (payload) { return Promise.resolve({ message: '…' }); }
		mailto: ''           // Last resort: opens the visitor's mail client.
	},

	/* ------------------------------------------------------------------ strings */
	i18n: {
		send: 'Send',
		inputPlaceholder: 'Type your message…',
		clear: 'Clear conversation',
		close: 'Close chat',
		open: 'Open chat',
		typing: 'Typing…',
		errorGeneric: 'Something went wrong. Please try again.',
		errorNetwork: 'Connection problem. Please check your internet and try again.',
		askAi: 'Ask the AI for a more detailed answer',
		loadingModel: 'Loading on-device AI',
		modelReady: 'On-device AI ready',
		noWebGpu: 'Your browser cannot run the on-device AI. Answers come from the business knowledge base.',
		aiBadge: 'AI-generated — verify important details',
		retry: 'Retry',
		leadName: 'Your name',
		leadEmail: 'Email address',
		leadPhone: 'Phone number',
		leadService: 'What do you need?',
		leadMessage: 'Tell us a little more',
		notGenerative: 'Answers are matched from the business knowledge base, not generated by AI.'
	}
};
