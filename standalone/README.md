# HTECH AI Assistant — standalone build

The same chat widget for sites that are **not** WordPress: static HTML, a landing page, a React/Vue app, or a single hand-written page.

No server, no PHP, no database, no API key. The knowledge file is downloaded once and searched **in the visitor's browser**, so it works on Netlify, Cloudflare Pages, GitHub Pages, cPanel, or even a folder opened from disk.

---

## What is in the box

```
htech/
├── config.js            ← the only file you normally edit
├── knowledge.json       ← your business content (exported or built)
├── standalone.js        ← the widget
├── chatbot-widget.css
├── utils/
│   ├── retrieval.js     ← BM25 search engine (no dependencies)
│   └── chunker.js       ← PDF/DOCX/TXT/CSV text extraction
├── assets/
│   ├── pdf.min.js
│   ├── pdf.worker.min.js
│   └── mammoth.browser.min.js
├── build.html           ← point-and-click knowledge builder
├── demo.html            ← working example with a live status panel
└── README.md
```

---

## Quick start (3 minutes)

**1. Copy the folder to your site**, e.g. `https://example.com/htech/`.

**2. Edit `config.js`** — at minimum:

```js
name: 'Ask Acme',
business: 'Acme Ltd',
welcome: 'Hi! Ask me about our products, prices or delivery.',
colors: { primary: '#0A1F44', secondary: '#00B4D8', /* … */ }
```

**3. Add three tags before `</body>`:**

```html
<link rel="stylesheet" href="/htech/chatbot-widget.css">
<script src="/htech/utils/retrieval.js"></script>
<script src="/htech/config.js"></script>
<script src="/htech/standalone.js"></script>
```

That is it. The launcher appears bottom-right.

> `utils/retrieval.js` **must** load before `standalone.js`. `config.js` must load before `standalone.js` too. Order matters.

---

## Getting your `knowledge.json`

### Option A — export from WordPress (best)

If you also run the WordPress plugin, you have already written and tested your content there:

1. WP Admin → **HTECH AI Assistant → Installation**
2. Click **Download knowledge snapshot**
3. Save as `htech/knowledge.json`

The file contains your indexed chunks, pre-computed term data, and your FAQs — so the standalone widget answers exactly like the WordPress one.

### Option B — build it in the browser

No WordPress needed:

1. Open `htech/build.html` in your browser (double-click is fine).
2. Fill in your business name and description.
3. Drag in your PDF / DOCX / TXT / CSV files.
4. Click **Preview** to test a search against what you just built.
5. Click **Download knowledge.json** and save it next to `standalone.js`.

Everything happens locally — files never leave the machine.

### Option C — write it by hand

```json
{
  "version": "1.0.0",
  "items": [
    { "s": "kb",  "l": "Company overview", "t": "Acme Ltd is a printing company in Lagos." },
    { "s": "kb",  "l": "Pricing",          "t": "Roll-up banner: 65,000 NGN. Tote bag: 12,000 NGN." },
    { "s": "doc", "l": "Delivery",         "t": "Delivery inside Lagos takes 3 working days." }
  ],
  "faqs": [
    { "question": "How long does delivery take?", "answer": "Three working days inside Lagos.", "active": true }
  ]
}
```

`l` is the label shown as the source; `t` is the text. Term data (`f`, `n`) is optional — it is computed at load time if missing.

---

## How the answers work

1. `knowledge.json` is fetched once (or read from `window.HTECH_AI_KNOWLEDGE` if you inline it).
2. It is indexed in memory with BM25, the same scoring the WordPress plugin uses.
3. Each question is scored in **under 2 ms**; matching sentences from your content are assembled into the reply.
4. If nothing scores above `retrieval.minScore`, the widget replies with the standard refusal instead of guessing.

It is deliberately identical in behaviour to the WordPress build: same tokenisation, same synonyms, same unknown-entity guard, so the same content produces the same answers.

**It cannot invent facts.** There is no language model in the default path — every sentence shown comes from your file.

---

## Configuration reference

`config.js` is commented inline. The frequently changed keys:

### Identity

| Key | Meaning |
| --- | --- |
| `name` | Header title |
| `business` | Business name (used in the AI system prompt) |
| `welcome` | First bubble |
| `contact` | Contact line appended to short answers |
| `suggestions` | Tappable starter questions (array) |

### Appearance

| Key | Meaning |
| --- | --- |
| `position` | `bottom-right` or `bottom-left` |
| `width` / `height` | Panel size in px (full-screen below 640 px viewport) |
| `radius` | Corner radius |
| `headerStyle` | `gradient`, `solid`, `minimal` |
| `launcher` | `chat`, `bubble`, `round`, `logo` |
| `logo` | Path to an image, or `''` for initials |
| `darkMode` | Dark variant |
| `attribution` | "Powered by HTECH" footer — off by default |
| `colors` | Nine slots: primary, secondary, header, chat, user, bot, text, button, border |

### Behaviour

| Key | Meaning |
| --- | --- |
| `retrieval.topK` | Chunks pulled per question (4 is good) |
| `retrieval.minScore` | Below this, refuse (0.18 default) |
| `clearOnClose` | Wipe the transcript when the panel closes |
| `storeChats` | Keep the transcript in `localStorage` |
| `maxLength` | Input cap |
| `contextMessages` | Previous turns given to the AI (0 in retrieval-only mode) |

### Knowledge

```js
knowledgeUrl: 'knowledge.json',   // fetched at boot
knowledge: null,                  // or pass an object directly
```

To skip the network request entirely, inline the data:

```html
<script>window.HTECH_AI_KNOWLEDGE = { items: [ … ], faqs: [ … ] };</script>
```

### AI (optional)

```js
ai: {
  enabled: false,        // stays off unless you turn it on
  mode: 'off',           // 'off' | 'webllm'
  model: 'Llama-3.2-1B-Instruct-q4f16_1-MLC',
  autoLoad: false,       // true = start downloading on first open
  libUrl: 'https://cdn.jsdelivr.net/npm/@mlc-ai/web-llm@0.2.89/+esm'
}
```

With `enabled: true` and a WebGPU-capable browser, the visitor sees an opt-in control; the model downloads only after they click it. Without WebGPU the control is not shown and everything keeps working on the local index.

Being honest about it: WebLLM needs **desktop Chrome/Edge 113+**, and the download is hundreds of megabytes per browser. Leave it off unless natural phrasing is worth that to you.

### Leads

```js
lead: {
  enabled: true,
  fields: ['name', 'email', 'phone', 'service', 'description'],
  required: ['name', 'email'],
  endpoint: 'https://formspree.io/f/xxxxxxx',  // any JSON-accepting endpoint
  // or onSubmit: (payload) => fetch(...),
  // or mailto: 'hello@example.com'
}
```

Three delivery options, in priority order:

1. **`onSubmit(payload)`** — a callback you supply; return a promise.
2. **`endpoint`** — any URL that accepts a JSON `POST`. Also works pointed at the WordPress plugin's `/lead` route on another domain.
3. **`mailto`** — opens the visitor's mail client. Last resort.

If none is configured, the form refuses to submit rather than pretending the lead was delivered.

---

## JavaScript API

```js
HTECH_AI.open();          // open the panel
HTECH_AI.close();
HTECH_AI.reset();         // clear the conversation
HTECH_AI.getState();      // { open, messages, ai, indexed, error }
HTECH_AI.mount(config);   // mount with a config object instead of config.js
```

`getState().indexed` is the honest signal that your knowledge file loaded — if it is `0`, check `getState().error`.

---

## Inline mode

Put the chat inside a page instead of a floating button:

```js
// config.js
mode: 'inline'
```

…and place a mount point where you want it:

```html
<div id="htech-ai-panel"></div>
```

---

## Manual mounting (SPAs, React, Vue)

Do not include `config.js`; pass the object yourself:

```js
await import('/htech/utils/retrieval.js');
await import('/htech/standalone.js');

window.HTECH_AI.mount({ name: 'Support', business: 'Acme', knowledgeUrl: '/htech/knowledge.json' });
```

---

## Deploying

**Static host (Netlify, Vercel, Cloudflare Pages, GitHub Pages):** copy the folder, done. No build step, no environment variables.

**cPanel / shared hosting:** upload the folder via FTP. Make sure `.json` is served as `application/json` — some hosts serve it as a download. If `knowledge.json` downloads instead of loading, rename it to `knowledge.js` and wrap it:

```js
window.HTECH_AI_KNOWLEDGE = { /* paste contents */ };
```

**Offline / file://:** works. `fetch()` of a local JSON file is blocked by some browsers under `file://`, so use the inline `window.HTECH_AI_KNOWLEDGE` form there.

**CORS:** the knowledge file must be same-origin. If you host it elsewhere, that server needs `Access-Control-Allow-Origin`.

---

## Verifying it works

Open `demo.html` in the same folder. It shows a live status panel reading the real engine state, so you can confirm `knowledge.json` loaded and indexed before you trust it on a live site.

Try:

- "What services do you offer?" → should answer
- "How much is a roll-up banner?" → should quote the exact price
- "Who is the president of Nigeria?" → should **refuse**, not invent

If `indexed` is 0, the knowledge file is missing, misnamed, nested incorrectly, or not same-origin.

---

## Differences from the WordPress build

| | WordPress plugin | Standalone |
| --- | --- | --- |
| Search engine | Server-side BM25 | Identical BM25 in the browser |
| Knowledge source | Knowledge Base + Documents screens | `knowledge.json` |
| Conversations stored | Yes, in your database | In memory (optionally `localStorage`) |
| Leads | Stored in WordPress | POSTed to your endpoint |
| Admin UI | 9 screens | Edit `config.js` |
| Credentials needed | None | None |

Answers behave the same because the matching logic is the same. What you lose is the admin UI and the conversation history.
