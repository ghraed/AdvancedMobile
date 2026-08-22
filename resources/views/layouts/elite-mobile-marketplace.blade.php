<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#687f68">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" href="{{ asset('icons/taqqsit-icon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('icons/taqqsit-icon.svg') }}">
        <title>@yield('title', 'Catalog')</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Manrope:wght@400;500;600;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <script src="https://cdn.tailwindcss.com"></script>
            <style>
                :root {
                    --pm-bg: #f4efe4;
                    --pm-surface: #fffaf0;
                    --pm-surface-soft: #ebe4d5;
                    --pm-surface-muted: #ddd4c2;
                    --pm-border: #d8cebc;
                    --pm-text: #263128;
                    --pm-text-muted: #746f64;
                    --pm-primary: #687f68;
                    --pm-primary-strong: #435744;
                    --pm-secondary: #86977c;
                    --pm-accent: #728970;
                    --pm-danger: #dc2626;
                }

                * { box-sizing: border-box; }
                body {
                    margin: 0;
                    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
                }
                .pm-page {
                    min-height: 100vh;
                    background:
                        radial-gradient(circle at top left, rgba(134, 151, 124, 0.18), transparent 28%),
                        radial-gradient(circle at top right, rgba(104, 127, 104, 0.12), transparent 22%),
                        linear-gradient(180deg, #f4efe4 0%, #faf6ed 42%, #f2ecdf 100%);
                    color: var(--pm-text);
                }
                .pm-shell {
                    position: relative;
                    min-height: 100vh;
                    overflow: hidden;
                    background: var(--pm-bg);
                }
                @media (min-width: 1024px) {
                    .pm-shell {
                        min-height: calc(100vh - 3rem);
                        border: 1px solid rgba(26, 54, 93, 0.08);
                        border-radius: 32px;
                        box-shadow: 0 28px 72px rgba(12, 33, 58, 0.14);
                    }
                }
                .pm-topbar {
                    position: sticky;
                    top: 0;
                    z-index: 20;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 1rem;
                    background: rgba(255, 255, 255, 0.92);
                    backdrop-filter: blur(14px);
                    border-bottom: 1px solid rgba(214, 221, 229, 0.72);
                }
                .pm-card {
                    border: 1px solid var(--pm-border);
                    border-radius: 24px;
                    background: var(--pm-surface);
                    padding: 1rem;
                    box-shadow: 0 10px 28px rgba(12, 33, 58, 0.05);
                }
                .pm-hero-card {
                    position: relative;
                    overflow: hidden;
                    border-radius: 28px;
                    padding: 1.25rem;
                    min-height: 16rem;
                    background:
                        linear-gradient(135deg, rgba(255, 255, 255, 0.08), transparent 45%),
                        linear-gradient(160deg, #17345b 0%, #1d4677 65%, #102643 100%);
                    box-shadow: 0 22px 54px rgba(12, 33, 58, 0.18);
                }
                .pm-button {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    border-radius: 16px;
                    padding: 0.875rem 1.125rem;
                    font-size: 0.9375rem;
                    font-weight: 700;
                    transition: transform 0.15s ease, background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
                    text-decoration: none;
                    border: 0;
                    cursor: pointer;
                }
                .pm-button:hover { transform: translateY(-1px); }
                .pm-button--secondary { background: #ffffff; color: var(--pm-primary); justify-content: center; }
                .pm-button--outline { border: 1px solid rgba(255, 255, 255, 0.22); background: rgba(7, 20, 38, 0.28); color: #ffffff; justify-content: center; }
                .pm-button--accent { background: var(--pm-accent); color: #ffffff; justify-content: center; }
                .pm-button--ghost { border: 2px solid var(--pm-secondary); color: var(--pm-secondary); background: transparent; justify-content: center; }
                .pm-pill {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    border: 1px solid var(--pm-border);
                    border-radius: 999px;
                    background: rgba(255, 255, 255, 0.9);
                    padding: 0.625rem 0.875rem;
                    font-size: 0.75rem;
                    font-weight: 600;
                    color: var(--pm-text-muted);
                }
                .pm-filter-button {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.375rem;
                    border: 1px solid var(--pm-border);
                    border-radius: 16px;
                    background: rgba(255, 255, 255, 0.9);
                    padding: 0.625rem 0.875rem;
                    font-size: 0.8125rem;
                    font-weight: 600;
                    color: var(--pm-text-muted);
                }
                .pm-bottom-nav {
                    position: fixed;
                    right: 0;
                    bottom: 0;
                    left: 0;
                    display: grid;
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    gap: 0.25rem;
                    padding: 0.625rem 0.5rem 1rem;
                    background: rgba(255, 255, 255, 0.96);
                    border-top: 1px solid rgba(214, 221, 229, 0.9);
                    backdrop-filter: blur(16px);
                    z-index: 40;
                }
                .pm-bottom-nav__item {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 0.1875rem;
                    border-radius: 999px;
                    padding: 0.4375rem 0.25rem;
                    font-size: 0.6875rem;
                    font-weight: 600;
                    color: var(--pm-text-muted);
                    transition: background-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
                    text-decoration: none;
                }
                .pm-bottom-nav__item:hover { transform: translateY(-1px); }
                .pm-bottom-nav__item--active { background: rgba(43, 108, 176, 0.16); color: var(--pm-primary); }
                .pm-badge {
                    position: absolute;
                    top: 0.5rem;
                    left: 0.5rem;
                    border-radius: 999px;
                    padding: 0.25rem 0.5rem;
                    font-size: 0.625rem;
                    font-weight: 700;
                    line-height: 1;
                }
                .pm-badge--danger { background: var(--pm-danger); color: #ffffff; }
                .pm-badge--secondary { background: var(--pm-secondary); color: #ffffff; }
                .pm-clamp-2, .pm-clamp-4 {
                    display: -webkit-box;
                    overflow: hidden;
                    -webkit-box-orient: vertical;
                }
                .pm-clamp-2 { -webkit-line-clamp: 2; }
                .pm-clamp-4 { -webkit-line-clamp: 4; }
                .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
                .hide-scrollbar::-webkit-scrollbar { display: none; }
                @media (min-width: 1024px) {
                    .pm-bottom-nav {
                        display: none;
                    }
                }
            </style>
        @endif
        @stack('styles')
    </head>
    <body class="pm-page">
        <div class="pm-shell">
            @if(session('error'))<p class="mx-auto mt-4 max-w-7xl rounded-xl bg-red-50 p-3 text-sm text-red-800" role="alert">{{ session('error') }}</p>@endif
            @if(session('status'))<p class="mx-auto mt-4 max-w-7xl rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p>@endif
            @yield('content')
        </div>

        <div data-page-loader class="pointer-events-none fixed inset-x-0 top-0 z-[60] hidden h-1 bg-slate-200" aria-live="polite"><div class="h-full w-1/3 animate-pulse bg-slate-900"></div></div>

        <script>
            (() => {
                const drawer = document.querySelector('[data-category-drawer]');
                const search = document.querySelector('[data-search-overlay]');
                const loader = document.querySelector('[data-page-loader]');
                let drawerHistoryOpen = false;

                const setOpen = (element, open) => {
                    if (!element) return;
                    element.classList.toggle('hidden', !open);
                    element.setAttribute('aria-hidden', String(!open));
                    document.documentElement.classList.toggle('overflow-hidden', open || !(search?.classList.contains('hidden') ?? true));
                };
                const resetDrawer = () => {
                    drawer?.querySelector('[data-category-root]')?.classList.remove('hidden');
                    drawer?.querySelectorAll('[data-category-panel]').forEach(panel => { panel.classList.add('hidden'); panel.setAttribute('aria-hidden', 'true'); });
                };
                const closeDrawer = (fromHistory = false) => {
                    if (!drawer || drawer.classList.contains('hidden')) return;
                    setOpen(drawer, false); resetDrawer();
                    if (drawerHistoryOpen && !fromHistory) history.back();
                    drawerHistoryOpen = false;
                };
                document.querySelector('[data-category-open]')?.addEventListener('click', event => {
                    if (!drawer) { window.location.assign(event.currentTarget.dataset.catalogUrl); return; }
                    setOpen(drawer, true); drawer?.querySelector('[data-category-dialog]')?.focus();
                    if (!drawerHistoryOpen) { history.pushState({ categoryDrawer: true }, ''); drawerHistoryOpen = true; }
                });
                drawer?.querySelectorAll('[data-category-close], [data-category-overlay]').forEach(button => button.addEventListener('click', () => closeDrawer()));
                drawer?.querySelectorAll('[data-category-trigger]').forEach(trigger => trigger.addEventListener('click', event => {
                    const id = trigger.dataset.categoryTrigger;
                    if (!id) return;
                    event.preventDefault();
                    drawer.querySelector('[data-category-root]')?.classList.add('hidden');
                    drawer.querySelectorAll('[data-category-panel]').forEach(panel => { panel.classList.add('hidden'); panel.setAttribute('aria-hidden', 'true'); });
                    const panel = drawer.querySelector(`[data-category-panel="${id}"]`);
                    panel?.classList.remove('hidden'); panel?.setAttribute('aria-hidden', 'false');
                }));
                drawer?.querySelectorAll('[data-category-back]').forEach(button => button.addEventListener('click', () => resetDrawer()));
                document.querySelector('[data-search-open]')?.addEventListener('click', () => { setOpen(search, true); search?.querySelector('[data-search-input]')?.focus(); });
                search?.querySelectorAll('[data-search-close]').forEach(button => button.addEventListener('click', () => setOpen(search, false)));
                window.addEventListener('popstate', () => { if (drawerHistoryOpen) closeDrawer(true); });
                document.addEventListener('keydown', event => { if (event.key === 'Escape') { closeDrawer(); setOpen(search, false); } });
                document.addEventListener('click', event => {
                    const link = event.target.closest('a[href]');
                    if (link && !link.target && !link.hasAttribute('download') && link.origin === location.origin) loader?.classList.remove('hidden');
                });
            })();
        </script>

        <script>
            (() => {
                const selector = 'input:not([type="hidden"]):not([type="file"]):not([type="checkbox"]):not([type="radio"]), select:not([multiple]), textarea';
                const isEligible = control => control?.matches(selector) && !control.closest('[data-search-overlay], .pm-header, .pm-product-page');

                const sync = (field, control) => {
                    const hasValue = String(control.value ?? '').trim() !== '';
                    const isSelect = control.tagName === 'SELECT';
                    field.classList.toggle('is-filled', hasValue);
                    field.classList.toggle('is-select', isSelect);
                    field.classList.toggle('is-empty', isSelect && !hasValue);
                };

                const activate = (field, control) => {
                    if (!field || !control || control.dataset.floatingEnhanced) return;
                    control.dataset.floatingEnhanced = 'true';
                    control.classList.add('pm-floating-control');
                    sync(field, control);
                    control.addEventListener('input', () => sync(field, control));
                    control.addEventListener('change', () => sync(field, control));
                    control.addEventListener('animationstart', () => sync(field, control));
                };

                document.querySelectorAll('main form label').forEach(label => {
                    if (label.classList.contains('sr-only') || label.matches('[data-upload-card] label') || label.querySelector('input[type="file"]')) return;

                    let control = [...label.children].find(child => isEligible(child));
                    if (control) {
                        const textNodes = [...label.childNodes].filter(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
                        const caption = textNodes.map(node => node.textContent.trim()).join(' ');
                        if (!caption) return;
                        textNodes.forEach(node => node.remove());
                        const floatingLabel = document.createElement('span');
                        floatingLabel.className = 'pm-floating-label';
                        floatingLabel.textContent = caption;
                        label.insertBefore(floatingLabel, control);
                        label.classList.add('pm-floating-field');
                        activate(label, control);
                        return;
                    }

                    control = label.nextElementSibling;
                    if (!isEligible(control)) return;
                    const field = document.createElement('div');
                    field.className = 'pm-floating-field';
                    if (!control.id) control.id = `floating-field-${Math.random().toString(36).slice(2, 9)}`;
                    label.htmlFor = control.id;
                    label.parentNode.insertBefore(field, label);
                    field.append(label, control);
                    label.classList.add('pm-floating-label');
                    activate(field, control);
                });

                document.querySelectorAll('main form input[placeholder], main form textarea[placeholder]').forEach(control => {
                    if (!isEligible(control) || control.dataset.floatingEnhanced || !control.placeholder.trim()) return;
                    const field = document.createElement('div');
                    const label = document.createElement('span');
                    field.className = 'pm-floating-field';
                    label.className = 'pm-floating-label';
                    label.textContent = control.placeholder;
                    if (!control.hasAttribute('aria-label')) control.setAttribute('aria-label', control.placeholder);
                    control.parentNode.insertBefore(field, control);
                    field.append(label, control);
                    activate(field, control);
                });

                window.addEventListener('pageshow', () => document.querySelectorAll('[data-floating-enhanced]').forEach(control => sync(control.closest('.pm-floating-field'), control)));
            })();
        </script>

        @stack('scripts')
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('service-worker.js') }}'));
            }
        </script>
    </body>
</html>
