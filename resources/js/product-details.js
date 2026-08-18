const root = document.querySelector('[data-product-detail]');

if (root) {
    const config = document.getElementById('product-detail-data');
    const $ = (selector) => root.querySelector(selector);

    try {
        const data = JSON.parse(config?.textContent || '');
        const storage = $('[data-storage-options]');
        const colors = $('[data-color-options]');
        const mainImage = $('[data-main-image]');
        const mainMock = $('[data-main-mock]');
        const thumbnails = $('[data-thumbnails]');
        const purchase = $('[data-purchase]');
        const modal = document.querySelector('[data-purchase-modal]');
        const modalProduct = modal?.querySelector('[data-modal-product]');
        const modalSummary = modal?.querySelector('[data-modal-summary]');
        const modalSchedule = modal?.querySelector('[data-modal-schedule]');
        const closeModalButton = modal?.querySelector('[data-close-modal]');
        const confirmPurchaseButton = modal?.querySelector('[data-confirm-purchase]');
        const selected = { storage: null, color: null };
        let payload = null;
        let selectedPlan = null;
        let preview = null;
        const money = (value) => `$${Number(value).toFixed(2)}`;
        const request = async (url, body) => {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(body),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'The selection changed. Please try again.');
            return result;
        };
        const paymentState = () => { purchase.disabled = !(payload?.in_stock && selectedPlan); };
        const renderOptions = () => {
            if (storage) storage.innerHTML = data.storageValues.map((value) => {
                const active = selected.storage === value.id;
                const disabled = !data.variants.some((variant) => variant.inStock && variant.optionValueIds.includes(value.id));
                return `<button type="button" data-option="${value.id}" ${disabled ? 'disabled' : ''} aria-pressed="${active}" class="rounded-xl border px-2 py-3 text-sm font-bold ${active ? 'border-[var(--pm-primary)] bg-[var(--pm-primary)] text-white' : 'border-[var(--pm-border)] bg-white text-[var(--pm-text)]'} ${disabled ? 'cursor-not-allowed opacity-40' : ''}">${value.name}</button>`;
            }).join('');
            if (colors) colors.innerHTML = data.colorValues.map((value) => {
                const matches = selected.storage ? data.variants.filter((variant) => variant.optionValueIds.includes(selected.storage) && variant.optionValueIds.includes(value.id)) : data.variants.filter((variant) => variant.optionValueIds.includes(value.id));
                const active = selected.color === value.id;
                const disabled = !matches.some((variant) => variant.inStock);
                const background = value.image ? `background-image:url('${value.image}');background-size:cover` : `background:${value.hex || '#cbd5e1'}`;
                return `<button type="button" data-option="${value.id}" ${disabled ? 'disabled' : ''} aria-label="${value.name} color" title="${value.name}" aria-pressed="${active}" class="h-9 w-9 rounded-full border-4 border-white ${active ? 'shadow-[0_0_0_2px_var(--pm-primary)]' : 'shadow-[0_0_0_1px_#cbd5e1]'} ${disabled ? 'cursor-not-allowed opacity-30' : ''}" style="${background}"><span class="sr-only">${value.name}</span></button>`;
            }).join('');
            $('[data-selected-storage]') && ($('[data-selected-storage]').textContent = data.storageValues.find((value) => value.id === selected.storage)?.name || '');
            $('[data-selected-color]') && ($('[data-selected-color]').textContent = data.colorValues.find((value) => value.id === selected.color)?.name || '');
            if (mainMock) mainMock.style.setProperty('--mock-phone-color', data.colorValues.find((value) => value.id === selected.color)?.hex || data.primaryColor);
            storage?.querySelectorAll('[data-option]').forEach((button) => { button.onclick = () => choose('storage', Number(button.dataset.option)); });
            colors?.querySelectorAll('[data-option]').forEach((button) => { button.onclick = () => choose('color', Number(button.dataset.option)); });
        };
        const renderPlans = () => {
            const section = $('[data-plan-section]');
            const list = $('[data-plan-options]');
            if (!payload?.plans?.length) { section.hidden = true; selectedPlan = null; paymentState(); return; }
            section.hidden = false;
            selectedPlan = payload.plans.find((plan) => plan.id === selectedPlan?.id) || [...payload.plans].sort((a, b) => a.payments - b.payments)[0];
            list.innerHTML = payload.plans.map((plan) => `<button type="button" data-plan="${plan.id}" aria-pressed="${plan.id === selectedPlan?.id}" class="rounded-[15px] border px-2 py-3 text-center ${plan.id === selectedPlan?.id ? 'border-[var(--pm-primary)] bg-blue-50 text-blue-700' : 'border-[var(--pm-border)] bg-white text-[var(--pm-text)]'}"><strong class="block text-lg">${plan.payments}×</strong><span class="block text-xs">${money(plan.installment_amount)}/payment</span></button>`).join('');
            list.querySelectorAll('[data-plan]').forEach((button) => { button.onclick = () => { selectedPlan = payload.plans.find((plan) => plan.id === Number(button.dataset.plan)); renderPlans(); }; });
            const calendar = $('[data-calendar]');
            calendar.innerHTML = `<div class="flex justify-between gap-4 text-[var(--pm-text-muted)]"><span>Pay today</span><strong class="text-[var(--pm-text)]">${money(selectedPlan.amount_due_now)}</strong></div><div class="mt-2 flex justify-between gap-4 text-[var(--pm-text-muted)]"><span>Remaining payments</span><strong class="text-[var(--pm-text)]">${selectedPlan.future_payment_count} × ${money(selectedPlan.installment_amount)}</strong></div><div class="mt-3 flex justify-between gap-4 border-t border-[var(--pm-border)] pt-3 font-black"><span>Total</span><strong>${money(selectedPlan.total)}</strong></div>`;
            paymentState();
        };
        const applyPayload = (result) => {
            payload = result;
            $('[data-price]').textContent = money(result.price);
            $('[data-compare-price]').textContent = result.compare_at_price ? `Was ${money(result.compare_at_price)}` : '';
            $('[data-stock]').textContent = result.stock_message;
            $('[data-status]').textContent = '';
            const images = result.images?.length ? result.images : data.fallbackImages;
            if (images.length) {
                mainImage.hidden = false;
                mainMock.hidden = true;
                mainImage.src = images[0].url;
                mainImage.alt = images[0].alt;
                thumbnails.innerHTML = images.map((image, index) => `<button type="button" data-thumbnail="${index}" class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border ${index === 0 ? 'border-[var(--pm-primary)]' : 'border-[var(--pm-border)]'}"><img src="${image.url}" alt="${image.alt}" class="h-full w-full object-contain"></button>`).join('');
                thumbnails.querySelectorAll('[data-thumbnail]').forEach((button) => { button.onclick = () => { const image = images[Number(button.dataset.thumbnail)]; mainImage.src = image.url; mainImage.alt = image.alt; }; });
            } else {
                mainImage.hidden = true;
                mainMock.hidden = false;
                thumbnails.innerHTML = '';
            }
            renderPlans();
        };
        const resolve = async () => {
            const ids = [selected.storage, selected.color].filter(Boolean);
            if ((data.storageValues.length && !selected.storage) || (data.colorValues.length && !selected.color)) return;
            try { const result = await request(data.resolveUrl, { option_value_ids: ids }); if (!result.resolved) throw new Error(result.message); applyPayload(result); }
            catch (error) { $('[data-status]').textContent = error.message; paymentState(); }
        };
        const choose = (type, id) => {
            selected[type] = id;
            if (type === 'storage' && selected.color && !data.variants.some((variant) => variant.inStock && variant.optionValueIds.includes(id) && variant.optionValueIds.includes(selected.color))) {
                selected.color = data.colorValues.find((color) => data.variants.some((variant) => variant.inStock && variant.optionValueIds.includes(id) && variant.optionValueIds.includes(color.id)))?.id || null;
            }
            renderOptions();
            resolve();
        };
        selected.storage = data.initialIds.find((id) => data.storageValues.some((value) => value.id === id)) || null;
        selected.color = data.initialIds.find((id) => data.colorValues.some((value) => value.id === id)) || null;
        renderOptions();
        if (data.initialPayload) applyPayload(data.initialPayload);
        resolve();
        purchase.onclick = async () => {
            if (!payload?.in_stock || !selectedPlan) return;
            try {
                preview = await request(data.previewUrl, { variant_id: payload.variant_id, plan_id: selectedPlan.id });
                if (!modal || !modalProduct || !modalSummary || !modalSchedule) {
                    throw new Error('Unable to open the purchase preview. Please refresh the page.');
                }
                modalProduct.textContent = preview.product;
                modalSummary.textContent = `Due today: ${money(preview.amount_due_now)} · Total: ${money(preview.total_amount)}`;
                modalSchedule.innerHTML = preview.future_installments.map((payment) => `<li>Payment ${payment.sequence}: ${payment.due_date} · ${money(payment.amount)}</li>`).join('');
                modal.hidden = false;
            } catch (error) { $('[data-status]').textContent = error.message; }
        };
        closeModalButton?.addEventListener('click', () => { if (modal) modal.hidden = true; });
        confirmPurchaseButton?.addEventListener('click', async () => {
            if (!preview) return;
            confirmPurchaseButton.disabled = true;
            try {
                const result = await request(data.confirmUrl, { variant_id: preview.variant_id, plan_id: preview.plan_id });
                if (!result.application_url) throw new Error('Unable to continue to the installment application. Please try again.');
                window.location.assign(result.application_url);
            } catch (error) {
                const modalStatus = modal?.querySelector('[data-modal-status]');
                if (modalStatus) modalStatus.textContent = error.message;
                confirmPurchaseButton.disabled = false;
            }
        });
    } catch (error) {
        $('[data-status]').textContent = 'Unable to load product options. Please refresh the page.';
    }
}
