# Installation

## Before you start

You need:

- WordPress 6.0+ (tested to 7.1)
- PHP 7.4+ (tested on PHP 8.4)
- The ability to install a plugin (an Administrator account)

You do **not** need: an OpenAI key, a paid subscription, a Node.js build step, a cloud account, or any external service.

---

## Step 1 — Upload the plugin

1. Sign in to WordPress as an Administrator.
2. Go to **Plugins → Add New**.
3. Click **Upload Plugin** at the top of the page.
4. Click **Choose File** and select `htech-ai-assistant.zip`.
5. Click **Install Now**.
6. When it finishes, click **Activate Plugin**.

If your host blocks ZIP uploads, the alternative is to extract the ZIP and copy the `htech-ai-assistant` folder into `wp-content/plugins/` using FTP or your file manager, then activate it from **Plugins → Installed Plugins**.

## Step 2 — Confirm the install

On activation the plugin:

- creates six database tables,
- writes its default settings,
- protects its upload directory,
- adds clearly-labelled **sample content** to the knowledge base,
- sends you to **Free Website Chatbot AI by HTECH → Overview**.

The Overview screen should show `Widget live` next to the title and non-zero numbers for Knowledge Chunks. If it shows zeros everywhere, see [Troubleshooting](05-troubleshooting.md).

> **The sample content is a placeholder, not a demo.** It literally begins with "SAMPLE CONTENT — replace this…". The assistant will happily quote it back to visitors until you replace it, so do that before you tell anyone the site is live.

## Step 3 — Check the front end

Open your website in a new tab. A round chat button should appear in the bottom-right corner.

Click it and ask something the sample content covers, like *"What are your opening hours?"*. You should get an answer immediately.

If you see no button, work through these in order:

1. **Free Website Chatbot AI by HTECH → Installation** — is the widget toggle on?
2. View the page source and search for `htech-ai-launcher`. If it is missing, the shortcode/footer hook is not running — usually a theme that does not call `wp_footer`.
3. Clear any page cache or CDN cache.
4. Check that a JavaScript error in another plugin is not breaking the page (open the browser console).

---

## Where the widget appears

By default the floating launcher is printed on **every front-end page** via `wp_footer`. No shortcode is needed, and there is nothing to copy into your theme.

To restrict it, use the **Installation** screen's display settings to hide it on specific post types, or use the shortcode in `inline` mode on a dedicated page and turn the floating launcher off.

---

## Shortcode reference

```
[htech_ai_assistant]
```

| Attribute | Values | Default | Notes |
| --- | --- | --- | --- |
| `mode` | `floating`, `inline` | `floating` | `inline` puts the chat inside the page content. |
| `width` | pixels | saved setting | Inline mode only. |
| `height` | pixels | saved setting | Inline mode only. |
| `title` | text | saved setting | Overrides the header name for this page only. |

Examples:

```
[htech_ai_assistant mode="inline" height="640"]
```

```
[htech_ai_assistant mode="inline" width="420" height="600" title="Talk to sales"]
```

In `inline` mode the floating launcher is suppressed on that page, so you never get two chat buttons.

**You do not need to copy any JavaScript into your theme for the normal WordPress setup.** The shortcode handles everything.

---

## Standalone mode (non-WordPress sites)

If you also need the widget on a static site, a landing page builder, or a site running another platform, use the separate `htech-ai-standalone.zip` package. It has its own configuration file, its own knowledge builder, and does not need PHP at all.

See [standalone/README.md](../standalone/README.md).

There is also an `embed.js` loader for one-line third-party embedding of a WordPress-hosted assistant — see the **Installation** screen for the generated snippet.

---

## Updating

Every database change uses `dbDelta` and runs through a version check, so updating is a normal plugin update.

**Data is never removed by an update, a deactivation, or a reinstall.** The only thing that deletes business data is uninstalling with the explicit cleanup checkbox enabled (below).

---

## Deactivating vs uninstalling

| Action | Effect on your data |
| --- | --- |
| **Deactivate** | Nothing is deleted. The widget stops showing, the tables stay, the cron job is unscheduled. |
| **Update** | Nothing is deleted. |
| **Delete plugin (cleanup off)** | Nothing is deleted. Tables and settings stay for a future reinstall. |
| **Delete plugin (cleanup on)** | Everything is removed: tables, options, uploaded documents, scheduled jobs. |
| **Uninstall from the Plugins screen** | WordPress only runs `uninstall.php` after the plugin is deleted, and only if cleanup was enabled. |

### Removing all data on uninstall

1. Go to **Free Website Chatbot AI by HTECH → General Settings**.
2. Enable **Delete all data when the plugin is uninstalled**.
3. Save.
4. Deactivate and delete the plugin.

The cleanup removes the six tables, all `htech_ai_*` options and transients, the scheduled retention event, and the `wp-content/uploads/htech-ai-assistant/` directory including every uploaded original.

You can also delete data without uninstalling, from **General Settings → Danger zone**, which offers separate "delete conversations", "delete leads", "delete documents" and "reset knowledge base" actions.

---

## Multisite

The plugin works on multisite when activated per-site. Network activation creates the tables for each site on first load, but settings, knowledge and conversations are per-site — there is no shared content across the network.

---

## Uninstalling the standalone build

It is plain static files. Delete the folder. There is no database, no server component and nothing to clean up beyond the files themselves and the visitor's browser cache (which holds the AI model if a visitor enabled it).
