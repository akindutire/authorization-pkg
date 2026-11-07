"""Usage: python3 docs/build.py

Re-wrap each docs page's existing content in the new docs shell.

Reads the inner HTML of <div class="doc-content"> (old shell) or
<article class="prose" ...> (new shell, so the script is re-runnable),
then writes the page back with sidebar, TOC, pager and search index.
"""
import html
import json
import re
import sys
from pathlib import Path

DOCS = Path(sys.argv[1]) if len(sys.argv) > 1 and not sys.argv[1].startswith("--") else Path(__file__).parent

PAGES = [
    # file, nav label, group, blurb
    ("index.html", "Overview", "Get started", "What the package does and how a check runs"),
    ("installation.html", "Installation", "Get started", "Requirements, migrations, traits and middleware"),
    ("quickstart.html", "Quick start", "Get started", "Protect your first controller action in a few minutes"),
    ("api.html", "API reference", "Reference", "Attributes, trait methods, facade and Artisan commands"),
    ("configuration.html", "Configuration", "Reference", "Ability templates, caching, indexing and limits"),
    ("best-practices.html", "Best practices", "Guides", "Production patterns, benchmarks and troubleshooting"),
]
GROUPS = ["Get started", "Reference", "Guides"]
DOC_PAGES = [p for p in PAGES if p[0] != "index.html"]

ICON = {
    "search": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
    "sun": '<svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
    "moon": '<svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>',
    "menu": '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
    "github": '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 .5a11.5 11.5 0 0 0-3.64 22.41c.58.1.79-.25.79-.56v-2c-3.2.7-3.88-1.37-3.88-1.37-.52-1.33-1.28-1.68-1.28-1.68-1.05-.72.08-.7.08-.7 1.16.08 1.77 1.19 1.77 1.19 1.03 1.77 2.7 1.26 3.36.96.1-.75.4-1.26.73-1.55-2.55-.29-5.24-1.28-5.24-5.69 0-1.26.45-2.29 1.19-3.1-.12-.29-.52-1.46.11-3.05 0 0 .97-.31 3.17 1.18a11 11 0 0 1 5.77 0c2.2-1.49 3.17-1.18 3.17-1.18.63 1.59.23 2.76.11 3.05.74.81 1.19 1.84 1.19 3.1 0 4.42-2.7 5.4-5.26 5.68.41.36.78 1.06.78 2.14v3.17c0 .31.21.67.8.56A11.5 11.5 0 0 0 12 .5z"/></svg>',
}

BRAND_MARK = '''<svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true"><rect x="1" y="1" width="30" height="30" rx="8" fill="var(--brand)"/><path d="M10 16.5l4 4 8-9" fill="none" stroke="var(--paper)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>'''

HEAD = '''<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{title}</title>
    <meta name="description" content="{description}">
    <script>try{{var t=localStorage.getItem('docs-theme');if(t)document.documentElement.dataset.theme=t;}}catch(e){{}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Schibsted+Grotesk:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="css/styles.css">
</head>'''


def topbar(current):
    return f'''<a class="skip-link" href="#main">Skip to content</a>
    <header class="topbar">
        <button class="icon-btn menu-btn" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar">{ICON["menu"]}</button>
        <a class="brand" href="index.html">{BRAND_MARK}<span class="brand-name">Authorization<small>for Laravel</small></span></a>
        <div class="topbar-spacer"></div>
        <nav class="topbar-links" aria-label="Primary">
            <a href="quickstart.html"{' aria-current="page"' if current == "quickstart.html" else ""}>Guide</a>
            <a href="api.html"{' aria-current="page"' if current == "api.html" else ""}>API</a>
        </nav>
        <button class="search-trigger" type="button" data-search-open aria-label="Search documentation">{ICON["search"]}<span>Search docs</span><kbd>⌘K</kbd></button>
        <button class="icon-btn theme-btn" type="button" aria-label="Toggle colour theme">{ICON["sun"]}{ICON["moon"]}</button>
        <a class="icon-btn" href="https://github.com/akindutire/authorization-pkg" aria-label="GitHub repository">{ICON["github"]}</a>
    </header>'''


SEARCH_DIALOG = f'''<dialog class="search-dialog" aria-label="Search documentation">
        <div class="search-field">{ICON["search"]}<input type="search" placeholder="Search methods, commands, config keys…" aria-label="Search" aria-controls="search-results" autocomplete="off" spellcheck="false"></div>
        <ul class="search-results" id="search-results" role="listbox"></ul>
        <div class="search-foot"><span><kbd>↑</kbd> <kbd>↓</kbd> to move</span><span><kbd>Enter</kbd> to open</span><span><kbd>Esc</kbd> to close</span></div>
    </dialog>'''

SCRIPTS = '''<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/bash.min.js"></script>
    <script src="js/search-index.js"></script>
    <script src="js/main.js"></script>'''


def strip_tags(s):
    return html.unescape(re.sub(r"<[^>]+>", "", s)).strip()


EMOJI = re.compile(r"^[\s✅⚠️❌️]+")


def slugify(text, used):
    base = re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-") or "section"
    slug, n = base, 2
    while slug in used:
        slug = f"{base}-{n}"
        n += 1
    used.add(slug)
    return slug


def extract(src):
    m = re.search(r'<article class="prose"[^>]*>(.*?)</article>', src, re.S)
    if m:
        return m.group(1)
    start = src.index('<div class="doc-content">') + len('<div class="doc-content">')
    end = src.index("<footer", start)
    body = src[start:end]
    # drop the three closing wrappers (doc-content, doc-container, doc-page)
    for _ in range(3):
        body = body[: body.rstrip().rfind("</div>")]
    return body


def process(fname, body):
    used, headings = set(), []

    def h_repl(m):
        level, attrs, inner = m.group(1), m.group(2), m.group(3)
        inner = re.sub(r'<a class="heading-anchor".*?</a>', "", inner)
        prev_cls = re.search(r'class="([^"]*)"', attrs)
        attrs = re.sub(r'\s*(id|class)="[^"]*"', "", attrs)
        text = strip_tags(inner)
        classes = [prev_cls.group(1)] if prev_cls else []
        if level == "3" and not classes:
            raw = text
            if raw.startswith("✅"):
                classes.append("verdict verdict-do")
            elif raw.startswith("❌"):
                classes.append("verdict verdict-dont")
            elif raw.startswith("⚠"):
                classes.append("verdict verdict-caution")
            elif fname == "api.html":
                classes.append("sig")
            if classes and classes[0].startswith("verdict"):
                inner = EMOJI.sub("", inner).rstrip().rstrip(":")
                text = strip_tags(inner)
        if level == "1":
            return f"<h1{attrs}>{inner}</h1>"
        slug = slugify(text, used)
        is_verdict = bool(classes and classes[0].startswith("verdict"))
        if not is_verdict:
            headings.append({"level": int(level), "text": text, "id": slug})
        cls = f' class="{classes[0]}"' if classes else ""
        anchor = f'<a class="heading-anchor" href="#{slug}" aria-label="Link to this section">#</a>'
        return f'<h{level} id="{slug}"{cls}{attrs}>{inner}{anchor}</h{level}>'

    body = re.sub(r"<h([123])([^>]*)>(.*?)</h\1>", h_repl, body, flags=re.S)
    body = re.sub(r'<div class="table-wrap">\s*(<table.*?</table>)\s*</div>', r"\1", body, flags=re.S)
    body = re.sub(r"(<table.*?</table>)", r'<div class="table-wrap">\1</div>', body, flags=re.S)
    return body.strip(), headings


def sidebar(current, headings):
    out = ['<aside class="sidebar" id="sidebar" aria-label="Documentation">']
    for g in GROUPS:
        out.append(f'<div class="nav-group"><p class="nav-group-title">{g}</p><ul class="nav-list">')
        for f, label, grp, _ in PAGES:
            if grp != g:
                continue
            cur = ' aria-current="page"' if f == current else ""
            out.append(f'<li><a href="{f}"{cur}>{label}</a>')
            if f == current:
                subs = [h for h in headings if h["level"] == 2]
                if subs:
                    out.append('<ul class="nav-sub">')
                    out += [f'<li><a href="#{h["id"]}">{html.escape(h["text"])}</a></li>' for h in subs]
                    out.append("</ul>")
            out.append("</li>")
        out.append("</ul></div>")
    out.append('<div class="sidebar-meta"><p>Version 2.0.0 · MIT licence</p><p>PHP 8.1+ · Laravel 9+</p></div>')
    out.append("</aside>")
    return "\n".join(out)


def toc(headings):
    if not headings:
        return ""
    items = "".join(
        f'<li class="lvl-{h["level"]}"><a href="#{h["id"]}">{html.escape(h["text"])}</a></li>' for h in headings
    )
    return f'<nav class="toc" aria-label="On this page"><p class="toc-title">On this page</p><ol>{items}</ol></nav>'


def pager(current):
    order = [p[0] for p in PAGES]
    i = order.index(current)
    parts = []
    if i > 0:
        p = PAGES[i - 1]
        parts.append(f'<a class="prev" href="{p[0]}"><small>Previous</small><strong>{p[1]}</strong></a>')
    if i < len(PAGES) - 1:
        n = PAGES[i + 1]
        parts.append(f'<a class="next" href="{n[0]}"><small>Next</small><strong>{n[1]}</strong></a>')
    return f'<nav class="pager" aria-label="Pages">{"".join(parts)}</nav>'


index = []
for f, label, group, blurb in DOC_PAGES:
    path = DOCS / f
    src = path.read_text()
    title = re.search(r"<title>(.*?)</title>", src).group(1)
    desc = re.search(r'<meta name="description" content="(.*?)"', src).group(1)
    body, headings = process(f, extract(src))

    page = f'''{HEAD.format(title=title, description=desc)}
<body>
    {topbar(f)}
    <div class="docs">
        {sidebar(f, headings)}
        <main class="content" id="main">
            <div class="breadcrumb"><a href="index.html">Docs</a> / {group}</div>
            <article class="prose">
{body}
            </article>
            {pager(f)}
            <footer class="page-foot">
                <a href="https://github.com/akindutire/authorization-pkg/edit/main/docs/{f}">Edit this page on GitHub</a>
                <a href="https://github.com/akindutire/authorization-pkg/issues">Report an issue</a>
                <a href="https://packagist.org/packages/akindutire/authorization-pkg">Packagist</a>
            </footer>
        </main>
        {toc(headings)}
    </div>
    {SEARCH_DIALOG}
    {SCRIPTS}
</body>
</html>
'''
    path.write_text(page)
    index.append({"page": label, "url": f, "text": label, "section": blurb})
    for h in headings:
        index.append({"page": label, "url": f"{f}#{h['id']}", "text": h["text"], "level": h["level"]})
    print(f"{f}: {len(headings)} headings")

index.insert(0, {"page": "Overview", "url": "index.html", "text": "Overview", "section": PAGES[0][3]})
(DOCS / "js" / "search-index.js").write_text(
    "/* Generated by the docs build script — headings from every page. */\nwindow.DOCS_INDEX = "
    + json.dumps(index, ensure_ascii=False, indent=1)
    + ";\n"
)

if "--print-shell" in sys.argv:
    print(topbar("index.html"))
