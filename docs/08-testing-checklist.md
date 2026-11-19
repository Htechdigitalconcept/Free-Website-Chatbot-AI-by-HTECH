# Testing checklist

What was actually executed, against what, and with what result. Nothing in the shipped feature list is marked working unless it appears below as a recorded pass.

**Environment:** WordPress 7.1.5, PHP 8.4.27, SQLite (via sqlite-database-integration 3.1.3), Apache-less built-in PHP server, Chromium 159 driven by Playwright. Two installations were used: an **existing** site with content and history, and a **completely clean** site installed from the built ZIP.

---

## Phase 1 — core plugin

| # | Test | Result |
| --- | --- | --- |
| 1 | ZIP installs through WordPress's plugin uploader on a clean site | **PASS** — "Plugin installed successfully", unpacked to `plugins/htech-ai-assistant/` |
| 2 | Activation is fatal-free and creates all 6 tables | **PASS** — 6 tables, 83 settings keys, upload guard files written |
| 3 | Activation seeds starter content on first run only | **PASS** — 6 sections + 2 FAQs; verified it never overwrites real content on a second run |
| 4 | `GET /health` responds | **PASS** — `{"ok":true,"version":"1.0.0","storage":true}` |
| 5 | `GET /bootstrap` returns config + nonce to anonymous visitors | **PASS** |
| 6 | Manual knowledge base saves, indexes, and reindexes | **PASS** — coverage `{chunks:16, sections:11, faqs:5, words:357}` |
| 7 | PDF upload (with FlateDecode compression) | **PASS** — `php-pdf-basic`, text extracted with correct spacing |
| 8 | DOCX upload | **PASS** — `php-docx` (ZIP signature check fixed during development) |
| 9 | TXT upload | **PASS** — `php-plain` |
| 10 | CSV upload, one chunk per row | **PASS** — `php-csv`, 4 row chunks, header repeated per row |
| 11 | JSON + Markdown upload | **PASS** |
| 12 | Fake PDF (wrong magic bytes) rejected | **PASS** — specific message |
| 13 | PHP code disguised as `.txt` rejected | **PASS** |
| 14 | `.exe` rejected | **PASS** |
| 15 | `.doc` (legacy) rejected with clear message | **PASS** |
| 16 | Oversized file rejected | **PASS** |
| 17 | Retrieval answers a services question | **PASS** — bullet list from the Services section |
| 18 | Retrieval answers an hours question | **PASS** — "…open Monday to Friday from 9am to 5pm." |
| 19 | Retrieval returns an exact price from a CSV row | **PASS** — "Price NGN: 65000" |
| 20 | Retrieval returns an exact price from a TXT price list | **PASS** |
| 21 | All 9 admin screens render with no PHP notices (WP_DEBUG on) | **PASS** — zero `debug.log` lines across every screen and endpoint |
| 22 | Overview chart shows 14 real days | **PASS** — 14 bars, correct totals, no label collisions |
| 23 | Appearance live preview reflects colour fields | **PASS** — navy gradient header, cyan bubbles, launcher gradient |
| 24 | Desktop / mobile preview toggle | **PASS** |

## Phase 2 — anti-hallucination and on-device AI

| # | Test | Result |
| --- | --- | --- |
| 25 | Out-of-corpus question refused with the exact mandated sentence | **PASS** — "capital of France", "president of Nigeria", "iPhone 17", "drone training in Abuja" all refuse |
| 26 | Unknown-entity guard does not break valid questions | **PASS** — "do you offer…" style questions still answer |
| 27 | Prompt-injection text in a question does not escape into behaviour | **PASS** |
| 28 | Invented price in a generated answer is blocked | **PASS** — `invented-price` |
| 29 | Invented percentage / figure blocked | **PASS** — `invented-figure` |
| 30 | System-prompt leakage blocked | **PASS** — `leak` |
| 31 | Fabricated citation blocked when the named document is not in context | **PASS** — `fake-citation` |
| 32 | Legitimate citation of a real source passes | **PASS** |
| 33 | Unsupported policy claim blocked when no context exists | **PASS** — `unsupported-policy` |
| 34 | Grounded answer passes unchanged | **PASS** |
| 35 | WebGPU absent → no download attempted, honest label shown | **PASS** |
| 36 | WebGPU present → model library **not** fetched on page open | **PASS** — zero jsDelivr requests until opt-in |
| 37 | Opt-in control appears only when WebGPU exists | **PASS** |
| 38 | Clicking opt-in triggers the lazy `import()` | **PASS** — jsDelivr request observed only after the click |
| 39 | Load failure degrades to knowledge base mode without breaking chat | **PASS** |
| 40 | Fallback mode is explicitly labelled "not generative AI" | **PASS** — visible on mount whenever AI is inactive |
| 41 | Sample seeding does not force AI download | **PASS** |

## Phase 3 — conversations, leads, privacy

| # | Test | Result |
| --- | --- | --- |
| 42 | Conversation context resolves a vague follow-up to the previous topic | **PASS** — "Tell me about your branding packages" → "How much does that cost?" stays on branding (reciprocal-rank fusion) |
| 43 | Conversation view renders user/assistant turns | **PASS** |
| 44 | Lead capture disabled by default | **PASS** — `400` "Lead collection is not enabled" |
| 45 | Required-field validation | **PASS** — missing name/email produces the specific message |
| 46 | Invalid email rejected | **PASS** |
| 47 | Optional fields genuinely optional | **PASS** — name+email only accepted |
| 48 | Duplicate lead within 24h suppressed | **PASS** |
| 49 | Lead status change persists | **PASS** — `new` → `contacted`/`closed`, verified in DB |
| 50 | Invalid status rejected, not silently stored | **PASS** — admin notice "That lead status is not valid." |
| 51 | Admin notice on successful status change | **PASS** |
| 52 | CSV export streams with correct headers and data | **PASS** — `text/csv`, `Content-Disposition: attachment` |
| 53 | CSV injection neutralised on export | **PASS** — `=HYPERLINK(...)` exported as `'=HYPERLINK(...)` |
| 54 | Retention cron deletes only age-expired records | **PASS** — exactly 1 conversation and 1 lead removed, others untouched |
| 55 | Uninstall with cleanup **off** leaves all data | **PASS** — 6 tables and settings intact |
| 56 | Uninstall with cleanup **on** removes everything | **PASS** — 0 tables, 0 options, cron cleared, uploads directory removed |
| 57 | Reinstall after full cleanup works | **PASS** — 6 tables recreated, defaults written, sample content seeded |

## Phase 4 — front end and standalone

| # | Test | Result |
| --- | --- | --- |
| 58 | Floating launcher renders on the front end | **PASS** — inside Shadow DOM, visible, 58 px |
| 59 | Clicking the launcher opens the panel | **PASS** |
| 60 | A real question gets a real answer through REST in a browser | **PASS** — "12,000" for tote bags |
| 61 | Client brand colours drive the widget's CSS variables | **PASS** — `--ht-primary` equals the saved hex |
| 62 | Shadow DOM isolation: page CSS cannot see or affect widget classes | **PASS** |
| 63 | Shortcode `mode="inline"` renders in page flow at the given height | **PASS** — 600 px |
| 64 | Inline mode suppresses the floating launcher | **PASS** |
| 65 | Inline mode answers in place | **PASS** |
| 66 | Mobile viewport: panel fits, full-screen, usable | **PASS** — 390×780 exactly |
| 67 | No console errors on any tested page | **PASS** |
| 68 | Standalone build indexes a real knowledge export in the browser | **PASS** — 16 docs |
| 69 | Standalone retrieval accuracy (9 questions incl. 2 refusals) | **PASS** — 9/9 |
| 70 | Standalone widget answers correctly in a real browser | **PASS** — exact price, correct refusal |
| 71 | Standalone `getState()` reports honest engine status | **PASS** |
| 72 | Website importer rejects `127.0.0.1` and `file://` | **PASS** — SSRF guard |

## Security

| # | Test | Result |
| --- | --- | --- |
| 73 | Admin REST route without capability → 403 | **PASS** |
| 74 | Admin REST route without nonce → 403 | **PASS** |
| 75 | Public write without nonce → 403 | **PASS** |
| 76 | Wrong nonce → 403 | **PASS** |
| 77 | Rate limit: 20/min then 429 | **PASS** — 20×200, then 429 |
| 78 | `/standalone-config` limited to 30/hour | **PASS** |
| 79 | `admin-post.php` action without nonce → 403 | **PASS** |
| 80 | Logged-out request with a valid nonce → 400 (no session) | **PASS** |
| 81 | Deleted document's chunks disappear from retrieval immediately | **PASS** |

---

## What is *not* tested, and why

Stating these plainly rather than implying coverage:

1. **On-device AI with a real GPU.** Headless Chromium has no WebGPU adapter, so the model was never actually downloaded and run to completion. Everything around it is verified — capability detection, lazy loading, opt-in gating, progress reporting, failure fallback — but "a 1B model produced a phrased answer on real hardware" is **not** claimed. Test this on a desktop Chrome/Edge machine before promising it to a client.
2. **A scanned PDF.** Not testable without an image-only fixture; the code path reports "no readable text" and is documented as a known limitation rather than handled.
3. **Nginx.** The upload-directory guard writes `.htaccess`; Nginx hardening is documented for you to apply.
4. **Multisite.** The per-site path exists but was not exercised on a network install.
5. **Real email delivery.** The plugin intentionally does not send email, so there is nothing to test.
6. **Very large documents** (>10 MB, or hundreds of pages) — the timeout path is coded and documented, not stress-tested.

---

## How to re-run the core checks yourself

```bash
# Health
curl "https://yoursite.com/?rest_route=/htech-ai/v1/health"

# Bootstrap (public config + nonce)
curl "https://yoursite.com/?rest_route=/htech-ai/v1/bootstrap"

# Ask a question (replace NONCE with the value from /bootstrap)
curl -X POST "https://yoursite.com/?rest_route=/htech-ai/v1/chat/message" \
  -H "Content-Type: application/json" \
  -H "X-Htech-Nonce: NONCE" \
  -d '{"message":"What are your opening hours?","conversation_id":0}'
```

A correct refusal looks like:

```json
{"mode":"no-answer","answer":"I don't have enough verified information to answer that accurately. Please contact the business directly for clarification."}
```

If a nonsense question returns a *specific* answer, your content somewhere contains something misleading — find it in the retrieval tester before going live.
