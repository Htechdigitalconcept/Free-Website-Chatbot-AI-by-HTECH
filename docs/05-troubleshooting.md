# Troubleshooting

Symptom → cause → fix. Start here before anything else.

**First stop:** HTECH AI Assistant → **Overview**. The *Setup checklist* and *AI engine status* panels report the live state of every subsystem. Most problems are visible there.

---

## The widget does not appear

**1. Is it enabled?**
HTECH AI Assistant → Installation → *Enable the chat widget*. The Overview header should read `Widget live`.

**2. Is the page cached?**
Your page cache or CDN is serving HTML from before activation. Purge it, and exclude pages from full-page caching while debugging.

**3. Does your theme call `wp_footer()`?**
View the page source and search for `htech-ai-launcher`. If it is absent, the theme never fires `wp_footer`, so nothing can be injected. Fix the theme, or use the inline shortcode `[htech_ai_assistant mode="inline"]` in a page.

**4. Is another plugin breaking JavaScript?**
Open the browser console (F12). A JavaScript error from an unrelated plugin stops everything after it. Disable plugins one by one to find it.

**5. Are you logged in as an administrator with an ad blocker?**
Some blockers hide elements with widget-like IDs. Test in a private window with extensions off.

**6. Multisite?**
The plugin must be active on *that* site, not just network-activated.

---

## The widget appears but does not answer

**1. Check the REST endpoint directly.**

Visit `https://yoursite.com/?rest_route=/htech-ai/v1/health`. You should see:

```json
{"ok":true,"version":"1.0.0","storage":true}
```

- **404** → "plain" permalinks are off/broken, or a security plugin is blocking REST. The widget already uses `?rest_route=` form, which works with any permalink setting.
- **403** → a security plugin or hosting WAF is blocking `/wp-json/` or the `htech-ai` namespace. Allow-list it.
- **500** → a PHP error. Enable `WP_DEBUG` and check `wp-content/debug.log`.

**2. Nonce rejected?**
The public widget sends a plugin-specific header, `X-Htech-Nonce`. If a security plugin strips unknown headers, requests return `403 htech_nonce`. The widget retries once automatically after refreshing its nonce; persistent failure means the header is being stripped.

**3. Rate limited?**
More than 20 messages per minute from one IP returns HTTP 429. Raise the limit in **General Settings** if you have a shared office IP or a busy kiosk.

---

## "I don't have enough verified information to answer that accurately"

**This is the correct behaviour, not a bug.** It means your content does not contain the answer. It is the anti-hallucination rule doing its job.

Work through this:

**1. Reproduce it in the retrieval tester.**
Knowledge Base → *Test retrieval*. Type the visitor's exact question.

| Result | Diagnosis | Fix |
| --- | --- | --- |
| No results at all | The concept is missing | Add it to the knowledge base |
| Right content, low score | Wording mismatch | Rewrite using the visitor's vocabulary |
| Wrong content wins | Ambiguous or conflicting entries | Make the entry more specific; add the distinguishing word |
| Empty knowledge base | Nothing indexed | Fill in sections, then **Reindex** |

**2. Is the document active?**
A toggled-off document is invisible to retrieval even though it still appears in the list.

**3. Are unknown terms dominating?**
Deliberate behaviour: if a question is mostly words your content has never contained ("who is the president of Nigeria"), the assistant refuses rather than matching on the one word it does know. Add the topic to your content if it is legitimately in scope.

**4. Did you reindex after editing?**
Manual content is reindexed on save. If you edited the database directly, click **Reindex knowledge base**.

**5. Read real questions.**
Conversations → filter by *no verified answer*. Those are your missing knowledge base entries, in the visitors' own words. This is the single most effective improvement loop.

---

## The answer is correct but reads badly

Knowledge base mode assembles your sentences; it does not paraphrase them.

- **Bullet-list style answers** come from multi-line sections. That is intended for services and products.
- **Choppy answers** mean your source sentences are fragments. Write complete sentences.
- **The answer stops early** means it hit the length cap. Split the fact into a shorter sentence.
- **"You can also reach the team directly…"** is appended when the answer is under 80 characters. Put more detail in the entry.

If you want natural phrasing, enable on-device AI — see [AI Models](03-ai-models.md). It costs the visitor a large download.

---

## Documents fail to upload or process

| Message | Cause | Fix |
| --- | --- | --- |
| `That file type is not allowed` | Extension not allow-listed | Convert to PDF/DOCX/TXT/CSV, or add the extension in settings |
| `larger than the limit` | File too big | Raise the limit, or split the file |
| `does not look like the format its name claims` | Corrupt file or wrong extension | Re-export the file; do not just rename it |
| `No readable text was found` | Scanned/image-only PDF | Upload the digital original, or retype the key facts |
| `could not be verified as a genuine file transfer` | Upload arrived malformed (proxy, file-size limit, mod_security) | Check `post_max_size` and `upload_max_filesize` in PHP; raise them |
| Processing stuck on *Processing* | PHP timeout on a very large file | Raise `max_execution_time`, or split the file |
| `.doc` rejected | Legacy binary Word format | Save as `.docx` |

Also check that `wp-content/uploads/` is writable by PHP — the Documents screen reports this when it is not.

---

## On-device AI never becomes available

**1. Check WebGPU.**
Visit `chrome://gpu` and confirm *WebGPU: Hardware accelerated*. If it is disabled, the browser cannot run the model — this is a device/browser limit, not a plugin fault.

**2. Browser support.**
Chrome/Edge 113+ on desktop only. Firefox, Safari, and most Android/iOS browsers cannot run it. Those visitors correctly stay on knowledge base mode.

**3. Download fails or stalls.**
The model comes from a public CDN. Corporate networks, school filters, and some regions block it. The widget shows the failure and reverts to knowledge base mode; the chat keeps working.

**4. Do not pre-enable auto-load on a public site.**
It forces every visitor to download hundreds of megabytes. Keep it opt-in.

**5. GPU out of memory.**
Pick a smaller model: SmolLM2-360M or Llama-3.2-1B.

---

## Styling looks wrong

- **Your theme is overriding the widget** — it cannot. The widget renders in a Shadow DOM. If it still looks off, it is a saved setting, not CSS.
- **Brand colours not applied** — check the nine colour fields in Appearance. Each has a hex box; an invalid hex silently falls back to the built-in default.
- **Font not loading** — "System font" intentionally makes zero external requests. A Google Font needs outbound access to `fonts.googleapis.com`.
- **Widget covers a cookie banner** — change the corner, or use inline mode on that page.
- **Widget is huge on a phone** — below 640 px it goes full-screen by design.

---

## Admin screens are blank or throw a 500

1. Enable `WP_DEBUG` in `wp-config.php`:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', false );
   ```
2. Reproduce the problem, then read `wp-content/debug.log`.
3. Deactivate other plugins to rule out a conflict.
4. If the tables are missing (for example after a manual database restore), deactivate and reactivate the plugin — the schema is recreated idempotently and existing data is preserved.

---

## Emails, leads and exports

- **No notification email on a new lead** — the plugin deliberately does not send email; it stores leads in your database. WordPress's mail function is unreliable on most shared hosting, and a silently dropped lead is worse than a visible one. Check **Leads** in the admin menu.
- **Lead form never appears** — capture must be enabled in settings, and the form is offered only after a few turns or on buying-intent questions. It is always optional for the visitor.
- **Lead rejected as duplicate** — the same email within 24 hours is suppressed to stop double submissions. Wait, or use a different address when testing.
- **CSV export downloads a page instead of a file** — a security plugin stripped the nonce, so WordPress redirected to an error page. Allow `admin-post.php` for administrators.

---

## Performance

- **Slow first load** — check that page caching is on. The widget's CSS and JS are only enqueued on pages where it renders, are small, and are not render-blocking.
- **Slow answers** — knowledge base mode is in-memory and returns in milliseconds. If it is slow, something else is loading the server; check the *Messages* count on Overview for a runaway script.
- **High database load** — tighten retention in General Settings. Conversations are indexed on `created_at` and `session_key`.

---

## Reporting a problem

Include:

1. WordPress, PHP and plugin versions (Overview shows the plugin version).
2. What you expected, and what happened.
3. The exact text of any error message.
4. The relevant tail of `wp-content/debug.log`.
5. The output of `?rest_route=/htech-ai/v1/health`.

That set usually identifies the cause immediately.
