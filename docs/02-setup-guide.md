# Setup guide

Settings are grouped exactly as they appear in the admin menu. Follow them in this order — each step makes the next one more useful.

**The mental model:** *Chatbot Settings* decides who the assistant is. *Knowledge Base* and *Documents* decide what it is allowed to say. *Appearance* decides what it looks like. Nothing else changes the quality of an answer.

---

## 1 · Chatbot Settings

**Where:** Free Website Chatbot AI by HTECH → Chatbot Settings

| Field | What it does | Good value |
| --- | --- | --- |
| **Assistant name** | Shown in the widget header. | `Ask HTECH`, `Support Bot`, your brand name |
| **Business name** | Used in every prompt and in the "who are you" answer. | Your registered or trading name |
| **Business description** | One or two sentences. This is the assistant's sense of what you do. | "A Lagos-based digital agency offering websites, branding and marketing to growing businesses." |
| **Business category** | Nudges tone and expected questions. | Digital agency, Retail, Clinic, Restaurant, Professional services… |
| **Website URL** | Used in contact fallbacks and the standalone bundle. | `https://example.com` |
| **Welcome message** | The first bubble a visitor sees. | "Hi! Ask me about our services, prices or delivery." |
| **Language** | Answer language. | `English` unless you serve another market |
| **Personality** | Tone instruction for the AI mode; also drives phrase selection. | Professional, Friendly, Concise, Formal |
| **Purpose** | Shapes what the assistant pushes toward. | Support, Sales, Business info, Lead generation… |

> **Pick a purpose deliberately.** "Sales" makes the assistant end answers with an invitation to get a quote; "Support" keeps answers purely factual and points at contact details. It changes the closing line of answers, not the facts.

---

## 2 · Knowledge Base

**Where:** Free Website Chatbot AI by HTECH → Knowledge Base

This is the single most important screen. **The assistant can only state what is written here** (or in your uploaded documents). It cannot look at your website, your product database, or the internet.

There are eleven sections plus a FAQ list:

| Section | Put here |
| --- | --- |
| Company overview | Two or three sentences on what you do and who for |
| About us | History, team, years active, credentials |
| Services | One service per line, with a short description |
| Products | Names, variants, sizes, materials, availability |
| Pricing | Exact prices, in the exact currency format you publish |
| Business policies | Deposits, cancellations, guarantees, terms |
| Opening hours | Days and times, plus holiday notes |
| Contact details | Phone, email, address, WhatsApp |
| Delivery | Areas, times, fees |
| Returns | Refund window, conditions, process |
| Other | Anything that does not fit above |

### Writing content that answers well

The retrieval engine scores your sentences against the visitor's question, so sentence shape matters more than markup.

**Do**

- Write one fact per sentence.
- Repeat the noun the visitor will use: "A roll-up banner costs 65,000 NGN", not "It costs 65,000."
- State prices exactly. `Starter Website: 450,000 NGN` beats `from 450k`.
- Put the product name and the number in the same sentence.

**Don't**

- Don't paste a whole brochure — long undifferentiated blocks rank poorly.
- Don't write "Contact us for pricing" if you actually have prices; that is the answer the bot will give, forever.
- Don't use internal jargon the visitor will not type.

### FAQs

Each FAQ is a question and an answer pair. A FAQ **beats** a plain section when a visitor's question matches it closely (two or more shared meaningful words), because curated answers are worth more than assembled ones.

Use FAQs for the questions your team answers on the phone every day:

- "How long does a website take?"
- "Do you work outside Lagos?"
- "Are prices negotiable?"

### After editing

Click **Reindex knowledge base** (or it happens automatically on save). The **Coverage** panel shows the resulting chunk, section, FAQ and word counts — if chunk count stays at zero, your content is not being indexed.

### Test it

The **Test retrieval** box on the same screen lets you type a real visitor question and see which chunks come back with what score. This is the fastest way to diagnose a bad answer:

- **No results** → the concept is missing from your content.
- **Wrong chunk on top** → the wording does not match how visitors ask. Rewrite using their words.
- **Right chunk, low score** → add the specific term the visitor used.

---

## 3 · Documents

**Where:** Free Website Chatbot AI by HTECH → Documents

Upload existing files instead of retyping them: price lists, service catalogues, policy PDFs, product CSVs.

1. Drag a file onto the upload area (or click to browse).
2. The row appears with status **Processing**, then **Ready** with a chunk count.
3. If it fails, the row shows a specific reason — see [Document Upload](04-document-upload.md).

Practical notes:

- **Always keep your originals.** The plugin stores a copy, never modifies your source file.
- **Toggle a document off** instead of deleting it when you want the bot to stop using it temporarily — the file and chunks stay.
- **Reprocess** after changing chunk size, and **replace** a file when a new version arrives (the old file is removed).
- Documents and manual sections are treated equally by retrieval, so you can mix both.

---

## 4 · Appearance & Branding

**Where:** Free Website Chatbot AI by HTECH → Appearance & Branding

Everything here is live-previewed on the right, in desktop and mobile widths, before you save.

### Identity

- **Logo** — pick from the media library, or remove it and the launcher shows your initials. PNG, JPG and safe SVG are accepted; SVG upload is restricted to Administrators to keep SVG XSS out of reach of lower roles.
- **Logo size** — 28–40 px suits most designs.

### Colours

Nine independent colour slots, each with a native swatch and a hex field:

| Slot | Affects |
| --- | --- |
| Primary | Headings and strong text inside the widget |
| Secondary / accent | Gradient partner, focus rings |
| Header background | The header bar |
| Chat window background | The message area |
| User message | Visitor bubbles |
| Assistant message | Bot bubbles |
| Text | Body text inside bubbles |
| Send button | The send control |
| Borders | Dividers and input outlines |

The plugin's own navy/cyan palette is a starting point only. **Nothing brand-related from HTECH is rendered on your widget** unless you switch attribution on in the widget settings.

**Your colours win everywhere** — the widget sets all nine as CSS custom properties inside its Shadow DOM, so your theme cannot override them and they cannot leak into your site's stylesheet.

### Typography

Inter, Roboto, Poppins, Open Sans, Lato, Nunito, or the system font stack. Google Fonts are loaded only on pages where the widget renders. Choose **System font** if you want zero third-party requests.

### Widget layout

Floating launcher vs inline, corner position, width, height, corner radius, header style (gradient / solid / minimal), animation, and mobile behaviour. Below 640 px wide the widget becomes full-screen regardless of the width setting, which is what visitors expect on a phone.

### Content and behaviour

- **Suggested questions** — one per line, shown as tappable chips before the first message. Keep to three or four.
- **Online / offline status** — the green dot and its label.
- **Display name** — overrides the assistant name in the header for this widget instance.
- **Clear conversation on close** — off by default; on suits kiosks and shared devices.
- **Attribution** — the optional "Powered by Free Website Chatbot AI by HTECH" footer line. Off by default.

---

## 5 · Conversations and Leads

**Conversations** lists every session with timestamps, message counts, the transcript, and whether a lead was captured. You can read, search, filter by date, and delete.

**Leads** appears when lead capture is enabled. The form is always optional for the visitor — it is offered, never forced, and is shown after a few turns or when the question suggests buying intent.

Fields: name, email, phone, service, description. Each can be turned on or off independently, and you choose which are required. At least one contact method (email or phone) is always required so a lead is never unreachable.

Captured leads are stored in your WordPress database and exportable as CSV.

---

## 6 · Installation

**Where:** Free Website Chatbot AI by HTECH → Installation

- **Enable the chat widget** — the master on/off switch.
- **Shortcode reference** — copy-paste examples with a one-click copy button.
- **Standalone snippet** — the embed loader for non-WordPress sites.
- **Knowledge snapshot download** — the JSON bundle the standalone build uses.

---

## 7 · General Settings

- **Data retention** — how many days conversations and leads are kept. A daily WP-Cron job deletes anything older. Setting `0` keeps data indefinitely; that is your choice, but it is not the default.
- **Storage of transcripts** — whether conversations are written to the database at all. Turning this off makes the assistant fully ephemeral.
- **Rate limiting** — messages per minute per IP. Defaults to 20, which is generous for a human and blocks scripted abuse.
- **Upload limits** — maximum file size and allowed extensions.
- **Danger zone** — delete conversations, leads or documents, or reset the knowledge base, without uninstalling.
- **Uninstall cleanup** — the explicit opt-in that lets the plugin delete its data when removed.

---

## A fifteen-minute configuration that actually works

1. **Chatbot Settings** — name, business name, one-sentence description, purpose = Support.
2. **Knowledge Base** — fill Services, Pricing, Opening hours, Contact details. Nothing else yet.
3. **Knowledge Base → Test retrieval** — ask three questions a real customer would. Fix wording until the right chunk wins.
4. **Documents** — upload your price list if you have one in PDF or CSV.
5. **Appearance** — set your two brand colours and your logo. Save.
6. **Installation** — open your site, click the launcher, and confirm it answers correctly on desktop and on a phone.
7. Come back in a week and read **Conversations**: the questions it could not answer are your missing knowledge base entries.
