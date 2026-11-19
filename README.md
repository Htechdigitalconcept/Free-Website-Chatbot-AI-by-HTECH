# Free Website Chatbot AI by HTECH

A self-hosted AI chat assistant for WordPress. It answers visitor questions from **your own business content** — no paid AI API, no API key, no subscription, no external server.

Built by HTECH Digital Solutions.

**Repository:** https://github.com/Htechdigitalconcept/Free-Website-Chatbot-AI-by-HTECH


---

## What it actually does

There are two answer engines, and both run **on your own site**:

| Mode | What happens | Requirements |
| --- | --- | --- |
| **Knowledge base mode** (default) | The visitor's question is matched against your business content using a local keyword-scoring engine (BM25), and the answer is assembled from your exact sentences. | Nothing. Works everywhere. |
| **On-device AI mode** (optional) | A small language model is downloaded into the visitor's browser and phrases the answer more naturally — but it may only use the facts retrieved from your content. | Desktop Chrome or Edge 113+ with WebGPU. Downloads roughly 400 MB–1 GB, once, into the visitor's browser cache. |

The second mode is **off by default** and never downloads anything until a visitor explicitly asks for it.

**The important part: the assistant is built not to invent things.** If your content does not contain the answer, it says so:

> I don't have enough verified information to answer that accurately. Please contact the business directly for clarification.

That sentence is enforced in code in three separate places, not just asked for in a prompt.

---

## What you need

- WordPress 6.0 or newer (tested through 7.1)
- PHP 7.4 or newer (tested on PHP 8.4)
- Nothing else. No Node.js, no build step, no external service.

The PDF library, the DOCX library and the AI runtime are bundled or loaded from a free CDN. They are never required for the basic chat to work.

---

## Install

1. In WordPress go to **Plugins → Add New → Upload Plugin**.
2. Choose `htech-ai-assistant.zip` and click **Install Now**.
3. Click **Activate**.
4. You land on **Free Website Chatbot AI by HTECH → Overview**.

The plugin creates its own six database tables and adds six sections of clearly-labelled sample content so the widget can answer something immediately. Replace that sample text with your real information — see the [setup guide](docs/02-setup-guide.md).

### Where the widget appears

Once the plugin is active, the floating chat button appears on your whole site automatically. No shortcode, no code editing, no JavaScript copying.

---

## Getting it right in ten minutes

| Step | Screen | Why it matters |
| --- | --- | --- |
| 1 | **Chatbot Settings** | Your business name, what you do, and the assistant's tone. This frames every answer. |
| 2 | **Knowledge Base** | Paste your services, prices, hours, policies and FAQs. This is what the bot is allowed to say. |
| 3 | **Documents** | Upload your price list (PDF/CSV/DOCX/TXT) if you already have one. |
| 4 | **Appearance** | Your logo, your nine brand colours, your wording. HTECH branding never appears unless you switch attribution on. |
| 5 | **Installation** | Test it, then decide whether to offer the optional on-device AI. |

Full walkthrough: [docs/02-setup-guide.md](docs/02-setup-guide.md)

---

## Documentation

| Guide | What is in it |
| --- | --- |
| [01 · Installation](docs/01-installation.md) | Uploading the ZIP, first-run behaviour, shortcodes, standalone mode, uninstalling |
| [02 · Setup Guide](docs/02-setup-guide.md) | Every setting explained, in the order you should touch them |
| [03 · AI Models](docs/03-ai-models.md) | How on-device AI works, which model to pick, what WebGPU means, honest limits |
| [04 · Document Upload](docs/04-document-upload.md) | Supported formats, how text is extracted and chunked, what makes a PDF unreadable |
| [05 · Troubleshooting](docs/05-troubleshooting.md) | Symptom → cause → fix, including "the bot says it doesn't know" |
| [06 · Security & Privacy](docs/06-security-privacy.md) | What is stored, where, for how long, and how to delete it |
| [07 · Standalone JS](standalone/README.md) | The same widget on a non-WordPress site |
| [08 · Testing Checklist](docs/08-testing-checklist.md) | Exactly what was tested, and how to re-run it yourself |

---

## Shortcode

```
[htech_ai_assistant]
```

Optional attributes — anything you leave out falls back to your saved settings:

```
[htech_ai_assistant mode="inline" width="420" height="620" title="Ask us anything"]
```

- `mode` — `floating` (default) or `inline`
- `width` / `height` — pixels, inline mode only
- `title` — overrides the header name on that page only

`inline` embeds the chat inside the page and suppresses the floating launcher there, so you can put the assistant in a dedicated "Support" page without a duplicate button.

---

## Everything stays on your site

- Conversations, messages, leads and uploaded documents live in **your** WordPress database.
- Uploaded files are stored in `wp-content/uploads/htech-ai-assistant/` behind a directory guard that blocks direct downloads.
- Nothing is sent to HTECH or to any AI provider. There is no central service, no account, and no subscription.
- In on-device AI mode the model is downloaded *to the visitor's browser*. Their question is answered locally. Only the finished answer is saved to your database.

Retention and deletion are configurable — see [docs/06-security-privacy.md](docs/06-security-privacy.md).

---

## Honest notes about limits

Being straight with you about what this is and is not:

- **Knowledge base mode is not a generative AI chatbot.** It retrieves and assembles your own sentences. It reads well for prices, hours, policies, services and FAQs; it will not write creative prose. The widget labels itself accordingly so visitors are not misled.
- **On-device AI needs desktop Chrome or Edge.** Firefox, Safari and most mobile browsers cannot run WebGPU today. Those visitors automatically stay on knowledge base mode; nothing breaks.
- **The model download is large** (hundreds of megabytes) and happens once per browser. Visitors on metered connections should not be pushed into it — that is why it is opt-in.
- **Small local models are not GPT-4.** A 1B–3B model running in a browser is good at rephrasing your facts and poor at reasoning. The guardrails are what keep it honest.
- **Scanned PDFs have no text.** Image-only PDFs cannot be read by any free client-side or PHP parser without OCR. The uploader tells you when this happens instead of silently adding nothing. Upload a TXT version instead.
- **WebGPU itself is free and needs no key**, but it is a browser capability, not a service — so "unlimited" here means "runs as often as the visitor's device allows", not "a free cloud tier."

---

## File map

```
htech-ai-assistant/
├── htech-ai-assistant.php      Plugin header, autoloader, activation
├── uninstall.php               Opt-in data removal
├── includes/                   16 classes — one responsibility each
│   ├── class-plugin.php        Bootstrap and hook wiring
│   ├── class-database.php      Schema, CRUD, statistics, retention
│   ├── class-settings.php      Typed settings with defaults
│   ├── class-security.php      Capability, nonce, sanitising helpers
│   ├── class-admin.php         Menus, form handling, CSV export
│   ├── class-chatbot.php       Answer composition and guardrails
│   ├── class-rest-api.php      Public and admin endpoints
│   ├── class-knowledge-base.php Manual KB and indexing
│   ├── class-document-manager.php Upload, validate, extract, chunk, index
│   ├── class-text-extractor.php PDF / DOCX / TXT / CSV parsing
│   ├── class-chunker.php       Overlapping chunk splitter
│   ├── class-retrieval.php     BM25 scoring, synonyms, corpus statistics
│   ├── class-shortcode.php     [htech_ai_assistant] and mount points
│   ├── class-embed.php         /embed.js and knowledge snapshot export
│   ├── class-limits.php        Rate limiting
│   └── class-upgrader.php      Version migrations
├── admin/                      9 screens, CSS, JS
├── public/                     Chat widget CSS + JS (Shadow DOM)
├── vendor/                     PDF.js 3.11, Mammoth 1.8
└── standalone/                 Separate non-WordPress build
```

---

## Support

Start with [docs/05-troubleshooting.md](docs/05-troubleshooting.md). If you are reporting a problem, the **Overview → AI engine status** and **Installation** screens show the exact state of every subsystem, which makes diagnosis much faster.

## License

GPL-2.0-or-later, matching WordPress. Bundled libraries keep their own licences: PDF.js (Apache-2.0), Mammoth.js (BSD-2-Clause).
