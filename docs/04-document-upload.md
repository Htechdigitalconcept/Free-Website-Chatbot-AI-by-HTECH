# Document upload

Turn files you already have — a price list PDF, a product CSV, a policy Word document — into content the assistant can answer from.

All processing happens **on your own server**. Files are never uploaded to HTECH, to an AI provider, or to any third party.

---

## Supported formats

| Format | Extension | Extraction | Notes |
| --- | --- | --- | --- |
| PDF | `.pdf` | Built-in parser, including compressed (FlateDecode) streams | Text-based PDFs only. See "scanned PDFs" below. |
| Word | `.docx` | Mammoth.js | `.docx` only — legacy `.doc` is **not** supported and is rejected with a clear message. |
| Plain text | `.txt` | Direct read | Encoding detected (UTF-8 with/without BOM, Windows-1252 fallback). |
| CSV | `.csv` | Row parser with delimiter sniffing | Comma, semicolon, tab and pipe all detected. Each row becomes its own chunk. |
| Markdown | `.md` | Direct read | Markers kept; they help retrieval. |
| JSON | `.json` | Structure flattened to readable lines | Useful for exported product catalogues. |

**Not supported:** `.doc`, `.xls`, `.xlsx`, `.ppt`, `.pptx`, `.rtf`, `.odt`, and images. Export to one of the supported formats first. For Excel, use *Save As → CSV*.

---

## How a file becomes an answer

```
1. Validate      Extension allow-list, size limit, real MIME type, magic-byte check
2. Store         Original saved to uploads/htech-ai-assistant/ (never modified)
3. Extract       Format-specific parser → plain text
4. Normalise     Whitespace, control characters, line reconstruction
5. Chunk         Split at paragraph and sentence boundaries with overlap
6. Index         Each chunk stored with its term frequencies for BM25 scoring
7. Searchable    Retrieval treats it exactly like manual knowledge base content
```

### Why chunking matters

A 40-page PDF cannot be searched as one unit — nothing would match well. It is split into overlapping pieces (default ~900 characters, 120 characters of overlap). The overlap stops a sentence from being cut in half at a boundary and losing its meaning.

You can change chunk size in the document settings:

- **Smaller chunks (400–600)** — sharper, more precise matches for price lists and FAQs.
- **Larger chunks (1200–1500)** — keeps surrounding context, better for narrative policy documents.
- **More overlap** — safer for dense prose, more storage.

Reprocess an existing document after changing these values; the setting is applied at processing time, not search time.

---

## Validation and rejection

Every upload is checked before it is processed. You get a specific reason, not a generic failure:

| Rejection | Meaning |
| --- | --- |
| `That file type is not allowed.` | Extension not in the allow-list |
| `That file is larger than the 10 MB limit.` | Exceeds your configured limit (change in settings) |
| `This file does not look like the format its name claims.` | Magic-byte check failed — e.g. a `.pdf` that is actually a ZIP |
| `The file could not be verified as a genuine file transfer.` | Not a real HTTP upload (this is the correct behaviour when called incorrectly) |
| `No readable text was found in this file.` | Extraction succeeded but produced nothing — usually a scanned PDF |
| `That document has been deleted.` | Processing a document that no longer exists |

The MIME type and the magic bytes are both checked, and the check is deliberate: a `.txt` file containing PHP code is rejected, and an executable renamed to `.pdf` is rejected, because the content does not match the claimed type.

---

## Scanned PDFs — the one honest limitation

A PDF produced by a scanner or a phone app contains **photographs of pages**, not text. There is nothing to extract. Every free, server-side, no-dependency extractor has this limitation, because reading images requires OCR.

What the plugin does: it tells you clearly that no readable text was found, instead of silently adding an empty document.

What you should do:

- If you have the original document, upload that instead of a scan.
- If you only have the scan, ask whoever produced it for the source file.
- Otherwise, retype the key facts into **Knowledge Base** — a scan's useful content is usually a price list or a policy, which is short.
- Do not add OCR unless you can self-host it; a hosted OCR API would break the no-external-service requirement.

A PDF that extracts *some* text (a digital PDF with an embedded image of a signature, for example) works fine — only fully image-based files fail.

---

## Managing documents

The document list shows name, format, upload date, status, chunk count, an active toggle and a delete action.

| Action | Effect |
| --- | --- |
| **Toggle off** | The document stops being used for answers. Chunks and the original file are kept. Fastest way to take content offline. |
| **Reprocess** | Re-runs extraction and chunking with current settings. Use after changing chunk size, or if a file was processed by an older version. |
| **Replace** | Upload a new file in place of the old one. The old file is deleted and all its chunks are rebuilt. |
| **Delete** | Removes the database rows for the document, its chunks, **and** the stored original file. Cannot be undone. |
| **Delete all** | In document settings — clears every document and its chunks. Manual knowledge base content is untouched. |

A deleted document's chunks are removed from retrieval immediately, so you never get an answer citing a file that no longer exists.

---

## Storage and access protection

- Files are stored in `wp-content/uploads/htech-ai-assistant/`.
- That directory gets an `index.php` silence file and an `.htaccess` deny rule on Apache/LiteSpeed.
- Filenames are randomised with a hash and a timestamp; the original filename is stored only in the database.
- There is no public URL that serves a stored document. On Nginx, add a deny rule for that path — the plugin cannot write Nginx configuration itself, which is why the Installation screen reminds you.

---

## Practical tips

- **CSV is the best format for price lists.** Each row becomes its own chunk with its column headers repeated, so "Roll-up Banner — 65000 NGN" stays intact and is answered precisely.
- **One topic per file** beats one giant file. A separate `prices.csv` and `policies.pdf` retrieves better than a 60-page company handbook.
- **Keep documents current.** A stale price list is worse than no price list, because the assistant will state the old price confidently.
- **Test after uploading.** Use **Knowledge Base → Test retrieval** with a question the document should answer.
- **Keep your originals.** The plugin never modifies your source file, and deleting a document deletes the stored copy too.
