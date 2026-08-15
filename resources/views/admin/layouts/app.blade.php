<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Admin')</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL@20..48,100..700,0..1&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                --admin-bg: #f6f8fc;
                --admin-surface: #ffffff;
                --admin-surface-muted: #f8fafc;
                --admin-border: #d7dde5;
                --admin-border-strong: #b8c2cc;
                --admin-text: #111827;
                --admin-muted: #5b6472;
                --admin-primary: #2563eb;
                --admin-primary-soft: #4f46e5;
                --admin-danger: #b42318;
                --admin-danger-soft: #fef3f2;
                --admin-success: #027a48;
                --admin-success-soft: #ecfdf3;
                --admin-warning: #b54708;
                --admin-warning-soft: #fffaeb;
                --admin-shadow: 0 18px 55px rgba(15, 23, 42, 0.10);
            }

            * { box-sizing: border-box; }
            body { margin: 0; background: radial-gradient(circle at 8% 0%, rgba(37,99,235,.12), transparent 28rem), radial-gradient(circle at 100% 18%, rgba(79,70,229,.10), transparent 30rem), var(--admin-bg); color: var(--admin-text); font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
            a { color: inherit; }
            button, input, select, textarea { font: inherit; }
            .admin-app { min-height: 100vh; display: grid; grid-template-columns: 280px minmax(0, 1fr); transition: grid-template-columns .2s ease; }
            .admin-app.is-sidebar-collapsed { grid-template-columns: 76px minmax(0, 1fr); }
            .admin-sidebar { position: sticky; top: 0; height: 100vh; overflow: hidden; background: linear-gradient(165deg, #0f172a 0%, #1e3a8a 68%, #3730a3 100%); color: #f9fafb; padding: 28px 20px; border-right: 1px solid rgba(255, 255, 255, 0.08); transition: padding .2s ease; }
            .admin-sidebar__brand { display: flex; flex-direction: column; gap: 8px; margin-bottom: 28px; }
            .admin-sidebar__eyebrow { font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255, 255, 255, 0.64); }
            .admin-sidebar__title { margin: 0; font-size: 24px; font-weight: 700; }
            .admin-sidebar__text { margin: 0; color: rgba(255, 255, 255, 0.72); line-height: 1.5; }
            .admin-sidebar__section { margin-top: 26px; }
            .admin-sidebar__label { display: block; margin-bottom: 10px; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255, 255, 255, 0.56); }
            .admin-sidebar__nav { display: grid; gap: 8px; }
            .admin-sidebar__nav a,
            .admin-sidebar__nav button { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 12px; border: 0; border-radius: 14px; padding: 12px 14px; text-decoration: none; color: rgba(255, 255, 255, 0.82); background: transparent; cursor: pointer; }
            .admin-sidebar__nav-icon, .admin-sidebar-collapse-toggle .material-symbols-outlined { font-size: 21px; line-height: 1; }
            .admin-sidebar__nav-label { flex: 1; text-align: left; }
            .admin-sidebar__nav-meta { color: rgba(255, 255, 255, .55); font-size: 12px; }
            .admin-sidebar__nav a:hover,
            .admin-sidebar__nav button:hover,
            .admin-sidebar__nav a.is-active { background: rgba(255, 255, 255, 0.12); color: #fff; }
            .admin-main { min-width: 0; padding: 24px; }
            .admin-mobile-toggle { display: none; align-items: center; justify-content: center; width: 46px; height: 46px; border-radius: 14px; border: 1px solid var(--admin-border); background: var(--admin-surface); box-shadow: var(--admin-shadow); }
            .admin-sidebar-collapse-toggle { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border: 1px solid rgba(255,255,255,.2); border-radius: 13px; padding: 0; background: rgba(255,255,255,.1); color: #fff; cursor: pointer; transition: .15s ease; }
            .admin-sidebar-collapse-toggle:hover { background: rgba(255,255,255,.2); transform: translateY(-1px); }
            .admin-app.is-sidebar-collapsed .admin-sidebar { padding: 18px 12px; }
            .admin-app.is-sidebar-collapsed .admin-sidebar__brand { align-items: center; margin-bottom: 18px; }
            .admin-app.is-sidebar-collapsed .admin-sidebar__eyebrow,
            .admin-app.is-sidebar-collapsed .admin-sidebar__title,
            .admin-app.is-sidebar-collapsed .admin-sidebar__text,
            .admin-app.is-sidebar-collapsed .admin-sidebar__label,
            .admin-app.is-sidebar-collapsed .admin-sidebar__nav-label,
            .admin-app.is-sidebar-collapsed .admin-sidebar__nav-meta { display: none; }
            .admin-app.is-sidebar-collapsed .admin-sidebar__section { margin-top: 18px; }
            .admin-app.is-sidebar-collapsed .admin-sidebar__nav { justify-items: center; }
            .admin-app.is-sidebar-collapsed .admin-sidebar__nav a,
            .admin-app.is-sidebar-collapsed .admin-sidebar__nav button { width: 52px; height: 50px; justify-content: center; padding: 0; }
            .admin-app.is-sidebar-collapsed .admin-sidebar__nav-icon { font-size: 23px; }
            .admin-topbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 20px; }
            .admin-page-meta { min-width: 0; }
            .admin-page-title { margin: 4px 0 0; font-size: 32px; line-height: 1.1; }
            .admin-page-description { margin: 8px 0 0; color: var(--admin-muted); max-width: 70ch; }
            .admin-breadcrumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0 0 10px; padding: 0; list-style: none; color: var(--admin-muted); font-size: 14px; }
            .admin-breadcrumbs li:not(:last-child)::after { content: "/"; margin-left: 8px; color: var(--admin-border-strong); }
            .admin-grid { display: grid; gap: 20px; }
            .admin-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .admin-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .admin-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .admin-card { background: rgba(255, 255, 255, 0.94); border: 1px solid var(--admin-border); border-radius: 24px; box-shadow: var(--admin-shadow); padding: 24px; }
            .admin-card--tight { padding: 18px; }
            .admin-card__header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
            .admin-card__title { margin: 0; font-size: 22px; }
            .admin-card__copy { margin: 6px 0 0; color: var(--admin-muted); }
            .admin-kpi { display: grid; gap: 10px; }
            .admin-kpi__label { color: var(--admin-muted); font-size: 14px; }
            .admin-kpi__value { font-size: 34px; font-weight: 700; line-height: 1; }
            .admin-kpi__meta { color: var(--admin-muted); font-size: 13px; }
            .admin-actions { display: flex; flex-wrap: wrap; gap: 12px; }
            .admin-button,
            .admin-link-button,
            .admin-link { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; border-radius: 14px; padding: 0 16px; border: 1px solid transparent; text-decoration: none; font-weight: 600; cursor: pointer; transition: 0.15s ease; }
            .admin-button { background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-soft)); color: #fff; box-shadow: 0 10px 22px rgba(37, 99, 235, .18); }
            .admin-button:hover,
            .admin-link-button:hover,
            .admin-link:hover { transform: translateY(-1px); }
            .admin-button--secondary,
            .admin-link-button,
            .admin-link { background: var(--admin-surface); color: var(--admin-text); border-color: var(--admin-border); }
            .admin-button--danger { background: var(--admin-danger); color: #fff; }
            .admin-button--ghost { background: transparent; color: var(--admin-text); border-color: var(--admin-border); }
            .admin-button[disabled],
            .admin-link-button[disabled] { opacity: 0.65; cursor: wait; transform: none; }
            .admin-form-grid { display: grid; gap: 18px; }
            .admin-form-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .admin-field { display: grid; gap: 8px; }
            .admin-label { font-weight: 600; font-size: 14px; }
            .admin-help { color: var(--admin-muted); font-size: 13px; line-height: 1.5; }
            .admin-error-text { color: var(--admin-danger); font-size: 13px; }
            .admin-input,
            .admin-select,
            .admin-textarea,
            .admin-file-input { width: 100%; min-height: 46px; border-radius: 14px; border: 1px solid var(--admin-border); background: var(--admin-surface); padding: 12px 14px; color: var(--admin-text); }
            .admin-input:focus,
            .admin-select:focus,
            .admin-textarea:focus,
            .admin-file-input:focus { outline: none; border-color: var(--admin-primary-soft); box-shadow: 0 0 0 4px rgba(55, 65, 81, 0.12); }
            .admin-textarea { min-height: 120px; resize: vertical; }
            .admin-field.has-error .admin-input,
            .admin-field.has-error .admin-select,
            .admin-field.has-error .admin-textarea,
            .admin-field.has-error .admin-file-input { border-color: rgba(180, 35, 24, 0.52); background: #fff8f7; }
            .admin-table-wrap { width: 100%; overflow-x: auto; }
            .admin-table { width: 100%; border-collapse: collapse; min-width: 680px; }
            .admin-table th,
            .admin-table td { padding: 14px 12px; border-bottom: 1px solid var(--admin-border); text-align: left; vertical-align: top; }
            .admin-table th { color: var(--admin-muted); font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; }
            .admin-status-badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 700; }
            .admin-status-badge--success { background: var(--admin-success-soft); color: var(--admin-success); }
            .admin-status-badge--danger { background: var(--admin-danger-soft); color: var(--admin-danger); }
            .admin-status-badge--warning { background: var(--admin-warning-soft); color: var(--admin-warning); }
            .admin-status-badge--neutral { background: #eef2f6; color: #475467; }
            .admin-empty-state { display: grid; gap: 10px; place-items: start; padding: 26px; border: 1px dashed var(--admin-border-strong); border-radius: 18px; background: var(--admin-surface-muted); }
            .admin-empty-state h3 { margin: 0; font-size: 20px; }
            .admin-empty-state p { margin: 0; color: var(--admin-muted); max-width: 56ch; }
            .admin-alert-stack { display: grid; gap: 12px; margin-bottom: 16px; }
            .admin-alert { border-radius: 18px; padding: 14px 16px; border: 1px solid transparent; }
            .admin-alert--success { background: var(--admin-success-soft); border-color: rgba(2, 122, 72, 0.18); color: var(--admin-success); }
            .admin-alert--error { background: var(--admin-danger-soft); border-color: rgba(180, 35, 24, 0.18); color: var(--admin-danger); }
            .admin-filter-bar { display: flex; flex-wrap: wrap; align-items: end; gap: 14px; }
            .admin-filter-bar > * { flex: 1 1 220px; }
            .admin-inline { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
            .admin-row-card { border: 1px dashed var(--admin-border-strong); border-radius: 18px; padding: 16px; background: var(--admin-surface-muted); }
            .admin-pagination { margin-top: 20px; }
            .admin-modal-backdrop { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 23, 42, 0.56); z-index: 60; }
            .admin-modal-backdrop.is-open { display: flex; }
            .admin-modal { width: min(100%, 480px); background: var(--admin-surface); border: 1px solid var(--admin-border); border-radius: 24px; box-shadow: var(--admin-shadow); padding: 24px; }
            .admin-modal h3 { margin-top: 0; margin-bottom: 10px; }
            .admin-modal p { margin: 0 0 18px; color: var(--admin-muted); }
            .admin-auth-shell { max-width: 520px; margin: 8vh auto 0; }

            @media (max-width: 1100px) {
                .admin-grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }

            @media (max-width: 960px) {
                .admin-app { grid-template-columns: 1fr; }
                .admin-app.is-sidebar-collapsed { grid-template-columns: 1fr; }
                .admin-sidebar { position: fixed; inset: 0 auto 0 0; width: min(86vw, 300px); transform: translateX(-100%); transition: transform 0.2s ease; z-index: 50; }
                .admin-sidebar.is-open { transform: translateX(0); }
                .admin-app.is-sidebar-collapsed .admin-sidebar { padding: 28px 20px; }
                .admin-app.is-sidebar-collapsed .admin-sidebar__brand { align-items: stretch; margin-bottom: 28px; }
                .admin-app.is-sidebar-collapsed .admin-sidebar__eyebrow,
                .admin-app.is-sidebar-collapsed .admin-sidebar__title,
                .admin-app.is-sidebar-collapsed .admin-sidebar__text,
                .admin-app.is-sidebar-collapsed .admin-sidebar__label,
                .admin-app.is-sidebar-collapsed .admin-sidebar__nav-label,
                .admin-app.is-sidebar-collapsed .admin-sidebar__nav-meta { display: initial; }
                .admin-app.is-sidebar-collapsed .admin-sidebar__nav { justify-items: stretch; }
                .admin-app.is-sidebar-collapsed .admin-sidebar__nav a,
                .admin-app.is-sidebar-collapsed .admin-sidebar__nav button { width: 100%; height: auto; justify-content: space-between; padding: 12px 14px; }
                .admin-mobile-toggle { display: inline-flex; }
                .admin-sidebar-collapse-toggle { display: none; }
                .admin-main { padding: 18px; }
                .admin-topbar { align-items: center; }
                .admin-grid-2,
                .admin-grid-3,
                .admin-grid-4,
                .admin-form-grid-2 { grid-template-columns: 1fr; }
            }
        </style>
        @stack('styles')
    </head>
    <body>
        @php
            $breadcrumbs = trim($__env->yieldContent('breadcrumbs'));
            $pageTitle = trim($__env->yieldContent('heading', 'Admin'));
            $pageDescription = trim($__env->yieldContent('page_description', ''));
            $showSidebar = auth()->check() && request()->routeIs('admin.*') && ! request()->routeIs('admin.login*');
        @endphp

        @if ($showSidebar)
            <div class="admin-app" data-admin-app>
                <aside id="admin-sidebar" class="admin-sidebar" data-admin-sidebar>
                    <div class="admin-sidebar__brand">
                        <button type="button" class="admin-sidebar-collapse-toggle" data-admin-sidebar-collapse aria-controls="admin-sidebar" aria-expanded="true" aria-label="Minimize navigation" title="Minimize navigation"><span class="material-symbols-outlined" data-admin-sidebar-collapse-icon>menu_open</span></button>
                        <span class="admin-sidebar__eyebrow">Administration</span>
                        <h1 class="admin-sidebar__title">Control Panel</h1>
                        {{-- <p class="admin-sidebar__text">Secure catalog management for products, categories, installment plans, and account access.</p> --}}
                    </div>

                    <div class="admin-sidebar__section">
                        <span class="admin-sidebar__label">Navigation</span>
                        <nav class="admin-sidebar__nav">
                            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" aria-label="Dashboard" title="Dashboard"><span class="material-symbols-outlined admin-sidebar__nav-icon">dashboard</span><span class="admin-sidebar__nav-label">Dashboard</span><span class="admin-sidebar__nav-meta">01</span></a>
                            <a href="{{ route('admin.categories.index') }}" class="{{ request()->routeIs('admin.categories.*') ? 'is-active' : '' }}" aria-label="Categories" title="Categories"><span class="material-symbols-outlined admin-sidebar__nav-icon">category</span><span class="admin-sidebar__nav-label">Categories</span><span class="admin-sidebar__nav-meta">02</span></a>
                            <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products.*') ? 'is-active' : '' }}" aria-label="Products" title="Products"><span class="material-symbols-outlined admin-sidebar__nav-icon">inventory_2</span><span class="admin-sidebar__nav-label">Products</span><span class="admin-sidebar__nav-meta">03</span></a>
                            <a href="{{ route('admin.installment-plans.index') }}" class="{{ request()->routeIs('admin.installment-plans.*') ? 'is-active' : '' }}" aria-label="Installment Plans" title="Installment Plans"><span class="material-symbols-outlined admin-sidebar__nav-icon">payments</span><span class="admin-sidebar__nav-label">Installment Plans</span><span class="admin-sidebar__nav-meta">04</span></a>
                            <a href="{{ route('admin.installment-applications.index') }}" class="{{ request()->routeIs('admin.installment-applications.*') ? 'is-active' : '' }}" aria-label="Applications" title="Applications"><span class="material-symbols-outlined admin-sidebar__nav-icon">assignment</span><span class="admin-sidebar__nav-label">Applications</span><span class="admin-sidebar__nav-meta">05</span></a>
                        </nav>
                    </div>

                    <div class="admin-sidebar__section">
                        <span class="admin-sidebar__label">Account</span>
                        <nav class="admin-sidebar__nav">
                            <a href="{{ route('admin.account.edit') }}" class="{{ request()->routeIs('admin.account.*') ? 'is-active' : '' }}" aria-label="Profile" title="Profile"><span class="material-symbols-outlined admin-sidebar__nav-icon">person</span><span class="admin-sidebar__nav-label">Profile</span><span class="admin-sidebar__nav-meta">Me</span></a>
                            <form method="POST" action="{{ route('admin.logout') }}" data-loading-form>
                                @csrf
                                <button type="submit" aria-label="Logout" title="Logout"><span class="material-symbols-outlined admin-sidebar__nav-icon">logout</span><span class="admin-sidebar__nav-label">Logout</span><span class="admin-sidebar__nav-meta">Exit</span></button>
                            </form>
                        </nav>
                    </div>
                </aside>

                <main class="admin-main">
                    <div class="admin-topbar">
                        <div class="admin-inline">
                            <button type="button" class="admin-mobile-toggle" data-admin-sidebar-toggle aria-label="Toggle sidebar">☰</button>
                            <div class="admin-page-meta">
                                @if ($breadcrumbs !== '')
                                    <ol class="admin-breadcrumbs">{!! $breadcrumbs !!}</ol>
                                @endif
                                <h2 class="admin-page-title">{{ $pageTitle }}</h2>
                                @if ($pageDescription !== '')
                                    <p class="admin-page-description">{{ $pageDescription }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div aria-live="polite" aria-atomic="true">
                        @include('admin.partials.flash')
                    </div>
                    <div aria-live="assertive" aria-atomic="true">
                        <x-admin.validation-errors />
                    </div>
                    @yield('content')
                </main>
            </div>
        @else
            <main class="admin-main">
                <div aria-live="polite" aria-atomic="true">
                    @include('admin.partials.flash')
                </div>
                <div aria-live="assertive" aria-atomic="true">
                    <x-admin.validation-errors />
                </div>
                @yield('content')
            </main>
        @endif

        <script>
            const adminApp = document.querySelector('[data-admin-app]');
            const sidebarCollapseToggle = document.querySelector('[data-admin-sidebar-collapse]');
            const sidebarCollapseIcon = document.querySelector('[data-admin-sidebar-collapse-icon]');
            const sidebarPreferenceKey = 'admin-sidebar-collapsed';

            const setSidebarCollapsed = (collapsed) => {
                if (!adminApp || !sidebarCollapseToggle) return;

                adminApp.classList.toggle('is-sidebar-collapsed', collapsed);
                sidebarCollapseToggle.setAttribute('aria-expanded', String(!collapsed));
                sidebarCollapseToggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Minimize navigation');
                sidebarCollapseToggle.setAttribute('title', collapsed ? 'Expand navigation' : 'Minimize navigation');
                if (sidebarCollapseIcon) sidebarCollapseIcon.textContent = collapsed ? 'menu' : 'menu_open';
                localStorage.setItem(sidebarPreferenceKey, String(collapsed));
            };

            if (adminApp && sidebarCollapseToggle && localStorage.getItem(sidebarPreferenceKey) === 'true') {
                setSidebarCollapsed(true);
            }

            sidebarCollapseToggle?.addEventListener('click', () => {
                setSidebarCollapsed(!adminApp?.classList.contains('is-sidebar-collapsed'));
            });

            document.querySelectorAll('[data-admin-sidebar-toggle]').forEach((toggle) => {
                toggle.addEventListener('click', () => {
                    document.querySelector('[data-admin-sidebar]')?.classList.toggle('is-open');
                });
            });

            document.querySelectorAll('[data-loading-form]').forEach((form) => {
                form.addEventListener('submit', () => {
                    const submitters = form.querySelectorAll('button[type="submit"], input[type="submit"]');

                    submitters.forEach((button) => {
                        if (button.dataset.originalLabel === undefined) {
                            button.dataset.originalLabel = button.innerHTML;
                        }

                        button.disabled = true;
                        button.setAttribute('aria-busy', 'true');
                        button.innerHTML = button.dataset.loadingLabel || 'Working...';
                    });
                });
            });

            document.querySelectorAll('[data-confirm-trigger]').forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    const target = document.getElementById(trigger.dataset.confirmTrigger);

                    if (target) {
                        target.classList.add('is-open');
                    }
                });
            });

            document.querySelectorAll('[data-modal-close]').forEach((button) => {
                button.addEventListener('click', () => {
                    button.closest('.admin-modal-backdrop')?.classList.remove('is-open');
                });
            });
        </script>
        @stack('scripts')
    </body>
</html>
