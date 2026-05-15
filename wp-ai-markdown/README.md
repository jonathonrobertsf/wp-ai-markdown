# WP AI Markdown

A WordPress plugin that converts any post or page into clean Markdown for AI agents and LLMs.

---

## Features

- **`?format=markdown` URL parameter** — append to any post/page URL to instantly view the Markdown version in your browser.
- **AI crawler auto-detection** — GPTBot, ClaudeBot, PerplexityBot, Google-Extended, and 20+ other known AI crawlers automatically receive Markdown instead of HTML.
- **YAML front matter** — every document includes structured metadata (title, URL, date, author, tags, categories, excerpt, featured image).
- **`<link rel="alternate">` discovery tag** — added to every page `<head>` so AI tools can discover the Markdown endpoint.
- **Site index** — visit `/?format=markdown` on the home page to get a Markdown list of all recent posts with links to their Markdown versions.
- **Admin settings page** — toggle features individually under **Settings → AI Markdown**.
- **No external dependencies** — pure PHP, uses PHP's built-in `DOMDocument`.

---

## Installation

1. Upload the `wp-ai-markdown` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Visit **Settings → AI Markdown** to configure.

---

## Usage

### View a post as Markdown
Append `?format=markdown` to any post or page URL:

```
https://yoursite.com/my-post/?format=markdown
```

### Site-wide index
```
https://yoursite.com/?format=markdown
```

### AI crawlers
Requests from GPTBot, ClaudeBot, PerplexityBot, Google-Extended (and many others) automatically receive Markdown. No URL changes needed.

### Discovery link tag
Every page `<head>` contains:
```html
<link rel="alternate" type="text/markdown" href="https://yoursite.com/my-post/?format=markdown" title="Markdown version" />
```

---

## Supported Markdown elements

| HTML element | Markdown output |
|---|---|
| `h1`–`h6` | ATX headings `#`–`######` |
| `p` | Paragraph |
| `strong`, `b` | `**bold**` |
| `em`, `i` | `_italic_` |
| `s`, `del` | `~~strikethrough~~` |
| `a` | `[text](url)` |
| `img` | `![alt](src)` |
| `code` | `` `inline code` `` |
| `pre > code` | Fenced code block with language |
| `blockquote` | `> quoted text` |
| `ul` / `ol` / `li` | Unordered / ordered lists |
| `table` | GFM pipe table |
| `hr` | `---` |
| `br` | Hard line break |

---

## Detected AI User-Agents

`GPTBot`, `ChatGPT-User`, `OAI-SearchBot`, `ClaudeBot`, `anthropic-ai`, `Claude-Web`, `Google-Extended`, `Googlebot-AI`, `Gemini`, `FacebookBot`, `meta-externalagent`, `Bingbot`, `BingPreview`, `PerplexityBot`, `cohere-ai`, `YouBot`, `CCBot`, `DataForSeoBot`, `Diffbot`, `AI2Bot`, `Timpibot`, `omgili`, `omgilibot`, `PetalBot`, `Bytespider`, `ImagesiftBot`

---

## License

GPL v2 or later.
