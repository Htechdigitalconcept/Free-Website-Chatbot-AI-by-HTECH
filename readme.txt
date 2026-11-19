=== Free Website Chatbot AI by HTECH ===
Contributors: htechdigital
Tags: chatbot, ai, knowledge base, customer support, lead generation
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Plugin URI: https://github.com/Htechdigitalconcept/Free-Website-Chatbot-AI-by-HTECH

A self-hosted AI chatbot with a business knowledge base, private document retrieval and optional on-device AI. No paid APIs, no subscriptions, no central server.

== Description ==

Free Website Chatbot AI by HTECH adds a premium floating chat widget to your WordPress site and answers visitor questions using **your own business information** — not a generic model guessing at facts.

**Core features**

* Business knowledge base with dedicated fields for overview, services, products, pricing, policies, hours, contact details, delivery and returns.
* FAQ builder with curated question/answer pairs that take priority in search.
* Document library for PDF, DOCX, TXT, CSV, Markdown and JSON. PDF text is extracted with PDF.js and DOCX with Mammoth.js — both run in your browser, in your admin session. Nothing is sent to a third-party parser.
* Local BM25 retrieval engine with relevance scoring. No vector database, no embeddings API, no licence fees.
* Optional on-device generative AI through WebLLM + WebGPU. The model downloads in the visitor's browser and runs on their GPU. No API key, no per-message cost.
* Knowledge-base fallback that works everywhere, including browsers without WebGPU.
* Strict anti-hallucination rules: the bot never invents prices, policies or product specifications, and says so when information is missing.
* Full appearance control: logo, nine brand colours, six fonts, widget size, position, corner radius, header style, suggested questions, dark mode and a live desktop/mobile preview.
* Optional lead capture with CSV export and email notification through `wp_mail()`.
* Conversation transcripts with search, date filters, delete, per-conversation export and automatic retention cleanup.
* `[htech_ai_assistant]` shortcode for inline placement.
* A separate standalone JavaScript package for non-WordPress sites.

**What this plugin does not do**

* It does not call OpenAI, Anthropic, Google or any other paid AI API, and it never will unless you add your own gateway.
* It is not a SaaS platform. Every installation is fully independent, with its own database, settings and documents.
* It cannot make a 1B-parameter browser model as capable as a hosted frontier model. That is the honest trade-off for a $0 running cost.

== Installation ==

1. In your WordPress dashboard go to **Plugins → Add New → Upload Plugin**.
2. Choose `htech-ai-assistant.zip` and click **Install Now**.
3. Click **Activate**.
4. Open **Free Website Chatbot AI by HTECH → Chatbot Settings** and enter your business details.
5. Open **Knowledge Base** and fill in at least Company overview, Services, Pricing (or "quote only") and Contact details.
6. Upload any PDF/DOCX price lists in **Documents**.
7. Set your logo and colours in **Appearance & Branding**.
8. Make sure **Floating widget** is enabled in **Chatbot Settings → Behaviour**.

== Frequently Asked Questions ==

= Does this need an API key or a paid subscription? =

No. Retrieval runs on your own server and, if you enable AI, the model runs in the visitor's browser. There is no paid dependency anywhere in the chain.

= Is the AI really free? =

The software is free and there is no per-message billing. The cost is paid in the visitor's hardware: the model is a few hundred megabytes to download once per browser and runs on their GPU. Weak devices fall back to knowledge-base answers automatically.

= Where are my documents stored? =

In `wp-content/uploads/htech-ai-assistant/`, protected by `.htaccess`, `web.config` and `index.php` guards with hashed filenames. There is no public URL for them.

= Will the chatbot invent prices? =

It is instructed not to, and a server-side validator rejects generated answers that contain monetary amounts or percentages which do not appear in the retrieved business text. When nothing reliable matches, it returns a fixed "I don't have enough verified information" message.

= Can I use it on more than one site? =

Yes. Install a copy on each site. Each installation has its own settings, knowledge base and database — that is the point of the design.

== Screenshots ==

1. Overview dashboard with real statistics from your database.
2. Knowledge base editor.
3. Document library with processing status.
4. Appearance with live preview.

== Changelog ==

= 1.0.0 =
* Initial release: dashboard, knowledge base, document library, local retrieval, optional WebLLM AI, appearance, conversations, leads, shortcode and standalone package.
