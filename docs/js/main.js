/**
 * Akindutire Authorization — documentation behaviour.
 * Theme, mobile nav, code blocks, scrollspy, search palette, landing tabs and playground.
 */
(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const store = {
        get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* storage blocked */ } },
    };

    /* ---------- Theme ---------- */
    const themeBtn = $('.theme-btn');
    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const root = document.documentElement;
            const current = root.dataset.theme
                || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            const next = current === 'dark' ? 'light' : 'dark';
            root.dataset.theme = next;
            store.set('docs-theme', next);
        });
    }

    /* ---------- Mobile navigation ---------- */
    const menuBtn = $('.menu-btn');
    const sidebar = $('#sidebar');
    function setNav(open) {
        document.body.classList.toggle('nav-open', open);
        if (menuBtn) {
            menuBtn.setAttribute('aria-expanded', String(open));
            menuBtn.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        }
    }
    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', () => setNav(!document.body.classList.contains('nav-open')));
        sidebar.addEventListener('click', (e) => { if (e.target.closest('a')) setNav(false); });
        document.addEventListener('click', (e) => {
            if (document.body.classList.contains('nav-open') && !sidebar.contains(e.target) && !menuBtn.contains(e.target)) setNav(false);
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setNav(false); });
    }

    /* ---------- Code blocks: label, highlight, copy ---------- */
    const COPY_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>';

    function guessLanguage(text) {
        const t = text.trim();
        if (/^(composer|php artisan|redis-cli|rm |npm |cd |git |#\s)/m.test(t) && !/[;{]\s*$/m.test(t.split('\n')[0])) {
            if (/^php artisan tinker/.test(t)) return { lang: 'php', label: 'Tinker' };
            return { lang: 'bash', label: 'Terminal' };
        }
        if (/^#\s*\.env|^[A-Z_]+=/m.test(t) && !/[;{}]/.test(t)) return { lang: 'bash', label: '.env' };
        if (/^return \[|'[a-z_]+' =>/m.test(t) && /config|return \[/.test(t)) return { lang: 'php', label: 'PHP · config' };
        return { lang: 'php', label: 'PHP' };
    }

    function enhanceCode(root) {
        $$('pre > code', root).forEach((code) => {
            const pre = code.parentElement;
            if (pre.closest('.code-block') || pre.classList.contains('pg-code')) return;
            const explicit = (code.className.match(/language-(\w+)/) || [])[1];
            const guess = guessLanguage(code.textContent);
            const lang = explicit || guess.lang;
            const label = pre.dataset.label || (explicit ? guess.label : guess.label);
            code.classList.add('language-' + lang);
            if (window.hljs) window.hljs.highlightElement(code);

            const wrap = document.createElement('div');
            wrap.className = 'code-block';
            const head = document.createElement('div');
            head.className = 'code-head';
            head.innerHTML = '<span></span><button class="copy-btn" type="button">' + COPY_ICON + '<span>Copy</span></button>';
            head.firstChild.textContent = label;
            pre.replaceWith(wrap);
            wrap.append(head, pre);

            const btn = head.querySelector('.copy-btn');
            const btnLabel = btn.querySelector('span');
            btn.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(code.textContent);
                    btnLabel.textContent = 'Copied';
                    btn.classList.add('is-copied');
                } catch (e) {
                    btnLabel.textContent = 'Press ⌘C to copy';
                    const range = document.createRange();
                    range.selectNodeContents(code);
                    const sel = getSelection(); sel.removeAllRanges(); sel.addRange(range);
                }
                setTimeout(() => { btnLabel.textContent = 'Copy'; btn.classList.remove('is-copied'); }, 1800);
            });
        });
    }
    if (window.hljs) window.hljs.configure({ ignoreUnescapedHTML: true });
    enhanceCode(document);

    /* install line copy (landing) */
    $$('[data-copy]').forEach((btn) => {
        const label = btn.querySelector('span');
        btn.addEventListener('click', async () => {
            try { await navigator.clipboard.writeText(btn.dataset.copy); label.textContent = 'Copied'; btn.classList.add('is-copied'); }
            catch (e) { label.textContent = 'Select to copy'; }
            setTimeout(() => { label.textContent = 'Copy'; btn.classList.remove('is-copied'); }, 1800);
        });
    });

    /* ---------- Scrollspy for TOC + sidebar sub-nav ---------- */
    const spyLinks = $$('.toc a, .nav-sub a');
    if (spyLinks.length && 'IntersectionObserver' in window) {
        const targets = $$('.prose h2[id], .prose h3[id]');
        let activeId = null;
        const visible = new Set();
        const mark = (id) => {
            if (id === activeId) return;
            activeId = id;
            const h = document.getElementById(id);
            const h2 = h && h.tagName === 'H3'
                ? targets.filter((t) => t.tagName === 'H2' && (t.compareDocumentPosition(h) & Node.DOCUMENT_POSITION_FOLLOWING)).pop()
                : h;
            spyLinks.forEach((a) => {
                const ref = a.getAttribute('href').slice(1);
                const on = a.closest('.toc') ? ref === id : (h2 && ref === h2.id);
                a.classList.toggle('is-active', !!on);
            });
        };
        const io = new IntersectionObserver((entries) => {
            entries.forEach((en) => { en.isIntersecting ? visible.add(en.target) : visible.delete(en.target); });
            const first = targets.find((t) => visible.has(t));
            if (first) mark(first.id);
            else {
                const above = targets.filter((t) => t.getBoundingClientRect().top < 120).pop();
                if (above) mark(above.id);
            }
        }, { rootMargin: '-70px 0px -65% 0px' });
        targets.forEach((t) => io.observe(t));
    }

    /* ---------- Search palette ---------- */
    const dialog = $('.search-dialog');
    if (dialog && window.DOCS_INDEX) {
        const input = $('input', dialog);
        const list = $('.search-results', dialog);
        let results = [];
        let cursor = 0;

        const esc = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        const highlight = (text, terms) => {
            let out = esc(text);
            terms.forEach((t) => {
                if (!t) return;
                out = out.replace(new RegExp('(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'), '<mark>$1</mark>');
            });
            return out;
        };

        function search(q) {
            const terms = q.toLowerCase().split(/\s+/).filter(Boolean);
            if (!terms.length) {
                return window.DOCS_INDEX.filter((e) => !e.level).map((e) => ({ e, score: 0 }));
            }
            return window.DOCS_INDEX
                .map((e) => {
                    const hay = (e.text + ' ' + e.page).toLowerCase();
                    if (!terms.every((t) => hay.includes(t))) return null;
                    const title = e.text.toLowerCase();
                    let score = 0;
                    terms.forEach((t) => {
                        if (title.startsWith(t)) score += 6;
                        else if (title.includes(t)) score += 3;
                        else score += 1;
                    });
                    if (!e.level) score += 1;
                    if (e.level === 3) score += 0.5;
                    return { e, score };
                })
                .filter(Boolean)
                .sort((a, b) => b.score - a.score)
                .slice(0, 30);
        }

        function render() {
            const q = input.value.trim();
            results = search(q);
            cursor = 0;
            if (!results.length) {
                list.innerHTML = '<li class="search-empty">No matches for “' + esc(q) + '”. Try a method name like <code>grantPermission</code> or a command like <code>permission:cache</code>.</li>';
                return;
            }
            const terms = q.split(/\s+/).filter(Boolean);
            list.innerHTML = results.map(({ e }, i) =>
                '<li role="option" id="sr-' + i + '" aria-selected="' + (i === 0) + '"><a href="' + e.url + '">' +
                highlight(e.text, terms) + '<small>' + esc(e.level ? e.page : (e.section || '')) + '</small></a></li>'
            ).join('');
            input.setAttribute('aria-activedescendant', 'sr-0');
        }

        function move(d) {
            if (!results.length) return;
            const items = $$('li[role="option"]', list);
            items[cursor].setAttribute('aria-selected', 'false');
            cursor = (cursor + d + items.length) % items.length;
            items[cursor].setAttribute('aria-selected', 'true');
            items[cursor].scrollIntoView({ block: 'nearest' });
            input.setAttribute('aria-activedescendant', items[cursor].id);
        }

        function open() {
            if (dialog.open) return;
            setNav(false);
            dialog.showModal();
            input.value = '';
            render();
            input.focus();
        }

        input.addEventListener('input', render);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
            else if (e.key === 'Enter' && results[cursor]) {
                e.preventDefault();
                dialog.close();
                location.href = results[cursor].e.url;
            }
        });
        list.addEventListener('click', (e) => { if (e.target.closest('a')) dialog.close(); });
        dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
        $$('[data-search-open]').forEach((b) => b.addEventListener('click', open));
        document.addEventListener('keydown', (e) => {
            const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);
            if ((e.key === 'k' && (e.metaKey || e.ctrlKey)) || (e.key === '/' && !typing)) {
                e.preventDefault();
                open();
            }
        });
        if (!/Mac|iPhone|iPad/.test(navigator.platform)) $$('.search-trigger kbd').forEach((k) => { k.textContent = 'Ctrl K'; });
    }

    /* ---------- Landing: example tabs ---------- */
    const tabs = $$('.example-tabs [role="tab"]');
    tabs.forEach((tab, i) => {
        tab.addEventListener('click', () => select(i));
        tab.addEventListener('keydown', (e) => {
            const d = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[e.key];
            if (d) { e.preventDefault(); select((i + d + tabs.length) % tabs.length, true); }
        });
    });
    function select(i, focus) {
        tabs.forEach((t, j) => {
            const on = i === j;
            t.setAttribute('aria-selected', String(on));
            t.tabIndex = on ? 0 : -1;
            document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
        });
        if (focus) tabs[i].focus();
    }

    /* ---------- Landing: access-check playground ---------- */
    const pg = $('#playground');
    if (pg) {
        const REQUIRED = ['can_be_edited', 'can_be_published'];
        const state = { mode: 'HasAny', abilities: { can_be_edited: 'granted', can_be_published: 'none', can_be_deleted: 'granted' } };
        const attrEl = $('[data-pg-attr]', pg);
        const callEl = $('[data-pg-call]', pg);
        const verdict = $('[data-pg-verdict]', pg);
        const title = $('[data-pg-title]', pg);
        const detail = $('[data-pg-detail]', pg);
        const effectiveEl = $('[data-pg-effective]', pg);

        const q = (s) => '<span class="t-str">\'' + s + '\'</span>';

        function effective() {
            return Object.keys(state.abilities).filter((k) => state.abilities[k] === 'granted');
        }

        function update(changed) {
            const eff = effective();
            const hits = REQUIRED.filter((r) => eff.includes(r));
            const pass = state.mode === 'HasAny' ? hits.length > 0 : hits.length === REQUIRED.length;

            attrEl.innerHTML = '<span class="t-attr">#[' + state.mode + '(</span>\n    [' + REQUIRED.map(q).join(', ') + '],\n    <span class="t-cls">Article</span>::class, ' + q('id') + '\n<span class="t-attr">)]</span>';
            effectiveEl.innerHTML = eff.length ? eff.map((e) => '<code>' + e + '</code>').join(' ') : '<em>none</em>';

            const prev = verdict.dataset.result;
            verdict.dataset.result = pass ? 'pass' : 'fail';
            if (pass) {
                title.textContent = 'Allowed — update() runs';
                detail.innerHTML = state.mode === 'HasAny'
                    ? 'Article #42 has <code>' + hits[0] + '</code>, and <code>HasAny</code> needs just one.'
                    : 'Article #42 has every listed ability.';
            } else {
                title.textContent = 'Blocked — ValidateSubjectActionException';
                const missing = REQUIRED.filter((r) => !eff.includes(r));
                const revoked = missing.filter((m) => state.abilities[m] === 'revoked');
                detail.innerHTML = (state.mode === 'HasAny' ? 'None of the listed abilities are effective.' : 'Missing <code>' + missing.join('</code>, <code>') + '</code>.')
                    + (revoked.length ? ' Revoked abilities never count, even if they were granted before.' : '');
            }
            if (changed && prev !== verdict.dataset.result) {
                verdict.classList.remove('is-changed');
                void verdict.offsetWidth;
                verdict.classList.add('is-changed');
            }
        }

        $$('[data-mode]', pg).forEach((b) => b.addEventListener('click', () => {
            state.mode = b.dataset.mode;
            $$('[data-mode]', pg).forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
            callEl.innerHTML = '<span class="t-com">// switched the attribute to #[' + state.mode + ']</span>';
            update(true);
        }));

        $$('.ability', pg).forEach((row) => {
            const name = row.dataset.ability;
            $$('button', row).forEach((b) => b.addEventListener('click', () => {
                const s = b.dataset.state;
                if (state.abilities[name] === s) return;
                state.abilities[name] = s;
                $$('button', row).forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
                const method = s === 'granted' ? 'grantPermission' : s === 'revoked' ? 'revokePermission' : null;
                callEl.innerHTML = method
                    ? '<span class="t-var">$article</span>-&gt;' + method + '(' + q(name) + ');'
                    : '<span class="t-com">// ' + name + ' is in neither list</span>';
                update(true);
            }));
        });

        update(false);
    }
})();
