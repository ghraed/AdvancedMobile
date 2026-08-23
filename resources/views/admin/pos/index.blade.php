@extends('admin.layouts.app')

@section('title', 'Point of Sale')
@section('heading', 'Point of Sale')
@section('page_description', 'Search or scan inventory, build a cart, and complete a transaction-secure sale.')
@section('breadcrumbs')<li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li>POS</li>@endsection

@section('actions')
    <a class="admin-link-button" href="{{ route('admin.pos.sales.index') }}"><span class="material-symbols-outlined">receipt_long</span> Sales history</a>
@endsection

@section('content')
    <style>
        .pos-shell{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(380px,.8fr);gap:20px;align-items:start}.pos-sticky{position:sticky;top:20px}.pos-search{font-size:18px;min-height:56px;padding-left:48px}.pos-search-wrap{position:relative}.pos-search-wrap .material-symbols-outlined{position:absolute;left:16px;top:16px;color:var(--admin-muted)}.pos-results{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-top:18px}.pos-product{display:grid;grid-template-rows:128px auto;overflow:hidden;border:1px solid var(--admin-border);border-radius:18px;background:#fff;text-align:left;padding:0;cursor:pointer;transition:.15s}.pos-product:hover{transform:translateY(-2px);border-color:var(--admin-primary);box-shadow:0 12px 30px rgba(15,23,42,.10)}.pos-product__image{display:grid;place-items:center;background:#f8fafc;overflow:hidden}.pos-product__image img{width:100%;height:100%;object-fit:contain}.pos-product__placeholder{font-size:42px;color:#94a3b8}.pos-product__body{padding:14px;display:grid;gap:7px}.pos-product__name{font-weight:750}.pos-meta{color:var(--admin-muted);font-size:13px}.pos-product__bottom{display:flex;justify-content:space-between;gap:8px;align-items:center}.pos-price{font-size:18px;font-weight:800}.pos-stock{font-size:12px;color:var(--admin-success);background:var(--admin-success-soft);padding:5px 8px;border-radius:999px}.pos-cart{display:grid;gap:12px;max-height:37vh;overflow:auto;padding-right:3px}.pos-cart-item{border:1px solid var(--admin-border);border-radius:16px;padding:13px;display:grid;gap:9px}.pos-cart-head,.pos-cart-foot,.pos-total-row{display:flex;justify-content:space-between;gap:12px;align-items:center}.pos-cart-name{font-weight:700}.pos-qty{display:flex;align-items:center;gap:8px}.pos-qty button{width:32px;height:32px;border:1px solid var(--admin-border);border-radius:10px;background:#fff;cursor:pointer}.pos-remove{border:0;background:transparent;color:var(--admin-danger);cursor:pointer}.pos-summary{border-top:1px solid var(--admin-border);padding-top:16px;display:grid;gap:10px}.pos-total-row--grand{font-size:22px;font-weight:800}.pos-payment-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px}.pos-message{display:none;border-radius:14px;padding:12px;margin-bottom:14px}.pos-message.is-error{display:block;background:var(--admin-danger-soft);color:var(--admin-danger)}.pos-empty{padding:30px 12px;text-align:center;color:var(--admin-muted)}@media(max-width:1100px){.pos-shell{grid-template-columns:1fr}.pos-sticky{position:static}}@media(max-width:620px){.pos-results{grid-template-columns:1fr 1fr}.pos-payment-fields{grid-template-columns:1fr}}
    </style>

    <div class="pos-shell" data-pos-root>
        <section class="admin-card">
            <div class="admin-card__header"><div><h3 class="admin-card__title">Products</h3><p class="admin-card__copy">Search name, brand, SKU, or scan a barcode. Only sellable stock is shown.</p></div></div>
            <div class="pos-search-wrap"><span class="material-symbols-outlined">search</span><input class="admin-input pos-search" data-pos-search placeholder="Search or scan barcode…" autofocus autocomplete="off"></div>
            <div class="pos-results" data-pos-results aria-live="polite"></div>
        </section>

        <aside class="admin-card pos-sticky">
            <div class="admin-card__header"><div><h3 class="admin-card__title">Current sale</h3><p class="admin-card__copy"><span data-cart-count>0</span> units</p></div><button class="admin-link" type="button" data-clear-cart>Clear</button></div>
            <div class="pos-message" data-pos-message></div>
            <div class="pos-cart" data-pos-cart><div class="pos-empty">Add a product to begin.</div></div>
            <div class="pos-summary">
                <div class="pos-total-row"><span>Subtotal</span><strong data-subtotal>$0.00</strong></div>
                <div class="pos-payment-fields">
                    <label class="admin-field"><span class="admin-label">Discount</span><select class="admin-select" data-discount-type><option value="">No discount</option><option value="fixed">Fixed amount</option><option value="percentage">Percentage</option></select></label>
                    <label class="admin-field"><span class="admin-label">Value</span><input class="admin-input" type="number" min="0" step="0.01" value="0" data-discount-value disabled></label>
                </div>
                <div class="pos-total-row"><span>Discount</span><strong data-discount>$0.00</strong></div>
                <div class="pos-total-row pos-total-row--grand"><span>Total</span><span data-total>$0.00</span></div>
                <label class="admin-field"><span class="admin-label">Payment method</span><select class="admin-select" data-payment-method><option value="cash">Cash</option><option value="card">Card</option><option value="mixed">Mixed (cash + card)</option></select></label>
                <div data-payment-fields></div>
                <button class="admin-button" style="width:100%;min-height:54px" type="button" data-complete-sale disabled><span class="material-symbols-outlined">point_of_sale</span> Complete sale</button>
            </div>
        </aside>
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-pos-root]');
            if (!root) return;
            let products = @json($initialProducts);
            let token = @json($checkoutToken);
            const cart = new Map();
            const $ = (selector) => root.querySelector(selector);
            const money = (cents) => new Intl.NumberFormat('en-US', {style:'currency',currency:'USD'}).format(cents / 100);
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));

            const renderProducts = () => {
                $('[data-pos-results]').innerHTML = products.length ? products.map(product => `
                    <button class="pos-product" type="button" data-add-variant="${product.variant_id}">
                        <span class="pos-product__image">${product.image_url ? `<img src="${escapeHtml(product.image_url)}" alt="">` : '<span class="material-symbols-outlined pos-product__placeholder">smartphone</span>'}</span>
                        <span class="pos-product__body"><span class="pos-meta">${escapeHtml(product.brand || 'Unbranded')}</span><span class="pos-product__name">${escapeHtml(product.product_name)}</span><span class="pos-meta">${escapeHtml(product.options.join(' / ') || 'Standard')} · ${escapeHtml(product.sku)}${product.barcode ? ` · ${escapeHtml(product.barcode)}` : ''}</span><span class="pos-product__bottom"><span class="pos-price">${money(product.price_cents)}</span><span class="pos-stock">${product.stock_quantity} in stock</span></span></span>
                    </button>`).join('') : '<div class="pos-empty">No sellable variants match this search.</div>';
                root.querySelectorAll('[data-add-variant]').forEach(button => button.addEventListener('click', () => add(Number(button.dataset.addVariant))));
            };

            const totals = () => {
                const subtotal = [...cart.values()].reduce((sum, item) => sum + item.price_cents * item.quantity, 0);
                const type = $('[data-discount-type]').value;
                const raw = Number($('[data-discount-value]').value || 0);
                const discount = type === 'fixed' ? Math.round(raw * 100) : type === 'percentage' ? Math.round(subtotal * Math.min(raw, 100) / 100) : 0;
                return {subtotal, discount: Math.min(discount, subtotal), total: Math.max(0, subtotal - discount)};
            };

            const renderCart = () => {
                $('[data-pos-cart]').innerHTML = cart.size ? [...cart.values()].map(item => `
                    <div class="pos-cart-item"><div class="pos-cart-head"><div><div class="pos-cart-name">${escapeHtml(item.product_name)}</div><div class="pos-meta">${escapeHtml(item.options.join(' / ') || item.sku)}</div></div><button class="pos-remove" type="button" data-remove="${item.variant_id}" aria-label="Remove">✕</button></div><div class="pos-cart-foot"><div class="pos-qty"><button type="button" data-minus="${item.variant_id}">−</button><strong>${item.quantity}</strong><button type="button" data-plus="${item.variant_id}" ${item.quantity >= item.stock_quantity ? 'disabled' : ''}>+</button></div><strong>${money(item.price_cents * item.quantity)}</strong></div></div>`).join('') : '<div class="pos-empty">Add a product to begin.</div>';
                root.querySelectorAll('[data-minus]').forEach(button => button.addEventListener('click', () => change(Number(button.dataset.minus), -1)));
                root.querySelectorAll('[data-plus]').forEach(button => button.addEventListener('click', () => change(Number(button.dataset.plus), 1)));
                root.querySelectorAll('[data-remove]').forEach(button => button.addEventListener('click', () => {cart.delete(Number(button.dataset.remove)); renderCart();}));
                const value = totals();
                $('[data-cart-count]').textContent = [...cart.values()].reduce((sum,item) => sum + item.quantity, 0);
                $('[data-subtotal]').textContent = money(value.subtotal); $('[data-discount]').textContent = `−${money(value.discount)}`; $('[data-total]').textContent = money(value.total);
                $('[data-complete-sale]').disabled = !cart.size;
                renderPaymentFields();
            };

            const add = (id) => { const product = products.find(item => item.variant_id === id); if (!product) return; const current = cart.get(id); if (current && current.quantity >= product.stock_quantity) return showMessage('No more stock is available for this variant.'); cart.set(id, {...product, quantity:(current?.quantity || 0) + 1}); renderCart(); };
            const change = (id, delta) => { const item = cart.get(id); if (!item) return; item.quantity += delta; if (item.quantity < 1) cart.delete(id); else item.quantity = Math.min(item.quantity, item.stock_quantity); renderCart(); };
            const showMessage = (message, error = true) => { const box = $('[data-pos-message]'); box.textContent = message; box.className = `pos-message ${error ? 'is-error' : ''}`; };

            const renderPaymentFields = () => {
                const method = $('[data-payment-method]').value; const total = totals().total; const node = $('[data-payment-fields]');
                if (method === 'cash') node.innerHTML = `<label class="admin-field"><span class="admin-label">Cash received</span><input class="admin-input" type="number" min="${(total/100).toFixed(2)}" step="0.01" value="${(total/100).toFixed(2)}" data-cash-received></label>`;
                else if (method === 'card') node.innerHTML = `<label class="admin-field"><span class="admin-label">Card reference (optional)</span><input class="admin-input" maxlength="255" data-card-reference></label>`;
                else node.innerHTML = `<div class="pos-payment-fields"><label class="admin-field"><span class="admin-label">Cash amount</span><input class="admin-input" type="number" min="0.01" step="0.01" data-cash-amount></label><label class="admin-field"><span class="admin-label">Card amount</span><input class="admin-input" type="number" min="0.01" step="0.01" data-card-amount></label><label class="admin-field"><span class="admin-label">Cash received</span><input class="admin-input" type="number" min="0" step="0.01" data-cash-received></label><label class="admin-field"><span class="admin-label">Card reference</span><input class="admin-input" maxlength="255" data-card-reference></label></div>`;
            };

            let searchTimer;
            $('[data-pos-search]').addEventListener('input', event => { clearTimeout(searchTimer); searchTimer = setTimeout(async () => { const response = await fetch(`{{ route('admin.pos.products.search') }}?q=${encodeURIComponent(event.target.value)}`, {headers:{Accept:'application/json'}}); if (response.ok) { products = (await response.json()).data; renderProducts(); if (products.length === 1 && products[0].barcode === event.target.value.trim()) { add(products[0].variant_id); event.target.select(); } } }, 180); });
            $('[data-clear-cart]').addEventListener('click', () => { cart.clear(); renderCart(); });
            $('[data-discount-type]').addEventListener('change', event => { $('[data-discount-value]').disabled = !event.target.value; if (!event.target.value) $('[data-discount-value]').value = 0; renderCart(); });
            $('[data-discount-value]').addEventListener('input', renderCart);
            $('[data-payment-method]').addEventListener('change', renderPaymentFields);
            $('[data-complete-sale]').addEventListener('click', async event => {
                const button = event.currentTarget; const summary = totals(); const method = $('[data-payment-method]').value; let payments;
                if (method === 'cash') payments = [{method:'cash',amount_cents:summary.total,cash_received_cents:Math.round(Number($('[data-cash-received]').value || 0)*100)}];
                else if (method === 'card') payments = [{method:'card',amount_cents:summary.total,reference:$('[data-card-reference]').value}];
                else payments = [{method:'cash',amount_cents:Math.round(Number($('[data-cash-amount]').value || 0)*100),cash_received_cents:Math.round(Number($('[data-cash-received]').value || $('[data-cash-amount]').value || 0)*100)},{method:'card',amount_cents:Math.round(Number($('[data-card-amount]').value || 0)*100),reference:$('[data-card-reference]').value}];
                const type = $('[data-discount-type]').value; const raw = Number($('[data-discount-value]').value || 0);
                button.disabled = true; button.textContent = 'Completing sale…';
                try { const response = await fetch(@json(route('admin.pos.checkout')), {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},body:JSON.stringify({idempotency_key:token,items:[...cart.values()].map(item=>({variant_id:item.variant_id,quantity:item.quantity,unit_price_cents:0})),discount:type?{type,value:type==='fixed'?Math.round(raw*100):raw}:{},payments})}); const result = await response.json(); if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'Checkout failed.'); window.location.assign(result.sale_url); } catch (error) { showMessage(error.message); button.disabled = false; button.innerHTML = '<span class="material-symbols-outlined">point_of_sale</span> Complete sale'; }
            });
            renderProducts(); renderCart();
        })();
    </script>
@endsection
