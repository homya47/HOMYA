import './catalogue-variants';

const toggle = document.querySelector('[data-menu-toggle]');
const navigation = document.getElementById('primary-navigation');

if (toggle && navigation) {
    const mobile = window.matchMedia('(max-width: 959px)');
    const label = toggle.querySelector('[data-menu-label]');

    const setExpanded = (expanded) => {
        toggle.setAttribute('aria-expanded', String(expanded));
        label.textContent = expanded ? toggle.dataset.closeLabel : toggle.dataset.openLabel;
        navigation.hidden = mobile.matches && !expanded;
    };

    const adaptNavigation = () => {
        const focusInside = navigation.contains(document.activeElement);
        const focusOnToggle = document.activeElement === toggle;
        toggle.hidden = !mobile.matches;
        setExpanded(false);
        if (mobile.matches && focusInside) toggle.focus();
        if (!mobile.matches && focusOnToggle) navigation.querySelector('a').focus();
    };

    toggle.addEventListener('click', () => {
        setExpanded(toggle.getAttribute('aria-expanded') !== 'true');
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobile.matches && toggle.getAttribute('aria-expanded') === 'true') {
            setExpanded(false);
            toggle.focus();
        }
    });

    navigation.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link || !mobile.matches || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const target = document.getElementById(new URL(link.href).hash.slice(1));
        // Move focus to the destination before hiding the activated navigation link.
        if (target) target.focus({ preventScroll: true });
        else toggle.focus();
        setExpanded(false);
    });

    mobile.addEventListener('change', adaptNavigation);
    adaptNavigation();
}

document.querySelectorAll('[data-language-link]').forEach((link) => {
    link.addEventListener('click', () => {
        // Keep the same section or catalogue results when switching language.
        if (/^#(?:universes|universe-[a-z-]+|results-heading|featured|discoveries|choosing|questions|product-description|product-specifications|product-variants|related-products|department-(?:washing|coffee)|legal-[a-z-]+)$/.test(window.location.hash)
            && document.getElementById(window.location.hash.slice(1))) {
            link.hash = window.location.hash;
        }
    });
});

document.querySelectorAll('[data-product-media]').forEach((media) => {
    const image = media.querySelector('[data-product-image]');
    const fallback = media.querySelector('[data-image-fallback]');
    if (!image || !fallback) return;
    const showFailure = () => {
        if (image.hasAttribute('srcset')) {
            image.removeAttribute('srcset');
            image.src = image.getAttribute('src');
            return;
        }
        image.hidden = true;
        fallback.hidden = false;
    };
    image.addEventListener('error', showFailure);
    image.addEventListener('load', () => { image.hidden = false; fallback.hidden = true; });
    if (image.complete && image.naturalWidth === 0) showFailure();
});

document.querySelectorAll('[data-gallery]').forEach((gallery) => {
    const image = gallery.querySelector('[data-product-image]');
    const caption = gallery.querySelector('[data-gallery-caption]');
    const buttons = gallery.querySelectorAll('[data-gallery-src]');
    if (!image || !caption) return;
    buttons.forEach((button) => button.addEventListener('click', () => {
        buttons.forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
        image.hidden = false;
        gallery.querySelector('[data-image-fallback]').hidden = true;
        if (button.dataset.galleryWidth && button.dataset.galleryHeight) {
            image.setAttribute('width', button.dataset.galleryWidth);
            image.setAttribute('height', button.dataset.galleryHeight);
        } else {
            image.removeAttribute('width');
            image.removeAttribute('height');
        }
        const srcset = button.dataset.gallerySrcset;
        if (srcset) image.setAttribute('srcset', srcset);
        else image.removeAttribute('srcset');
        image.src = button.dataset.gallerySrc;
        caption.textContent = caption.dataset.template.replace(':current', button.dataset.galleryNumber).replace(':total', caption.dataset.total);
    }));
    gallery.querySelectorAll('[data-gallery-step]').forEach((arrow) => {
        arrow.hidden = false;
        arrow.addEventListener('click', () => {
            const list = [...buttons];
            const current = list.findIndex((button) => button.getAttribute('aria-pressed') === 'true');
            list[(current + Number(arrow.dataset.galleryStep) + list.length) % list.length]?.click();
        });
    });
});

document.querySelectorAll('[data-gallery-thumbnail]').forEach((image) => {
    image.addEventListener('error', () => {
        if (image.hasAttribute('srcset')) {
            image.removeAttribute('srcset');
            image.src = image.getAttribute('src');
        } else image.hidden = true;
    });
    if (image.complete && image.naturalWidth === 0) image.hidden = true;
});

const filters = document.querySelector('[data-filter-panel]');
if (filters) {
    const compact = window.matchMedia('(max-width: 959px)');
    const adaptFilters = () => {
        // A validation error keeps the form visible. No-JS users retain the native disclosure.
        const focused = filters.contains(document.activeElement);
        const resetNotice = document.getElementById('category-reset');
        filters.open = !compact.matches || Boolean(document.querySelector('.form-error'))
            || Boolean(resetNotice && !resetNotice.hidden) || focused;
    };
    compact.addEventListener('change', adaptFilters);
    adaptFilters();
}

// Category membership comes from the same real products used by the server.
const universeSelect = document.getElementById('filter-universe');
const categorySelect = document.getElementById('filter-category');
const categoryOptions = document.getElementById('category-options');
const categoryReset = document.getElementById('category-reset');
if (universeSelect && categorySelect && categoryOptions) {
    const allOption = categorySelect.options[0].cloneNode(true);
    const options = Array.from(categoryOptions.content.querySelectorAll('option'));
    const adaptCategories = () => {
        const previous = categorySelect.value;
        const available = options.filter((option) => !universeSelect.value
            || option.dataset.universes.split(',').includes(universeSelect.value));
        categorySelect.replaceChildren(allOption.cloneNode(true), ...available.map((option) => option.cloneNode(true)));
        const preserved = available.some((option) => option.value === previous);
        categorySelect.value = preserved ? previous : '';
        if (previous && !preserved && categoryReset) {
            categoryReset.hidden = false;
            if (filters) filters.open = true;
        }
    };
    universeSelect.addEventListener('change', adaptCategories);
    categorySelect.addEventListener('change', () => { if (categoryReset) categoryReset.hidden = true; });
    // Reconcile values restored by the browser's back/forward cache as well.
    window.addEventListener('pageshow', adaptCategories);
    adaptCategories();
}

// Native fragment navigation scrolls to the results; restore useful keyboard focus too.
if (window.location.hash === '#results-heading') document.getElementById('results-heading')?.focus({ preventScroll: true });
document.querySelector('.form-error')?.focus();

// Measured sticky header offset also protects anchors with long translated labels.
const stickyHeader = document.querySelector('.home-header-wrap');
if (stickyHeader && 'ResizeObserver' in window) {
    const observer = new ResizeObserver(() => {
        document.documentElement.style.setProperty('--anchor-offset', `${Math.ceil(stickyHeader.getBoundingClientRect().height) + 20}px`);
    });
    observer.observe(stickyHeader);
}

document.querySelector('[data-open-category-filter]')?.addEventListener('click', () => {
    if (!filters) return;
    filters.open = true;
    document.getElementById('filter-category')?.focus({ preventScroll: true });
});

document.querySelectorAll('[data-quantity-control]').forEach((control) => {
    const input = control.querySelector('input');
    const buttons = control.querySelectorAll('[data-quantity-step]');
    if (!input) return;
    const minimum = Number(input.min) || 1;
    const maximum = Number(input.max) || 99;
    const update = () => buttons.forEach((button) => {
        button.hidden = false;
        button.disabled = Number(button.dataset.quantityStep) < 0 ? Number(input.value) <= minimum : Number(input.value) >= maximum;
    });
    buttons.forEach((button) => button.addEventListener('click', () => {
        const current = Number(input.value);
        input.value = String(Math.min(maximum, Math.max(minimum, (Number.isFinite(current) ? current : minimum) + Number(button.dataset.quantityStep))));
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }));
    input.addEventListener('input', update);
    update();
});

const submitButtons = (form) => [...form.elements].filter((element) => element.tagName === 'BUTTON' && element.type === 'submit');
document.querySelectorAll('.checkout-form, .product-cart-form, .product-card-purchase form, [data-commerce-action], [data-cart-quantity], [data-cart-remove]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        if (form.hasAttribute('data-cart-quantity')) {
            form.querySelectorAll('[data-quantity-step]').forEach((button) => { button.disabled = true; });
            form.querySelector('input[name="quantity"]').readOnly = true;
        }
        // Keep the chosen button's intent in the submitted data; do not disable it before serialization.
        submitButtons(form).forEach((button) => {
            button.setAttribute('aria-disabled', 'true');
            const label = button.querySelector('[data-submit-label]');
            if (label && button.dataset.busyLabel) {
                label.dataset.originalLabel = label.textContent;
                label.textContent = button.dataset.busyLabel;
            }
        });
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        if (form.hasAttribute('data-cart-quantity')) {
            const input = form.querySelector('input[name="quantity"]');
            input.readOnly = false;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        submitButtons(form).forEach((button) => {
            button.removeAttribute('aria-disabled');
            const label = button.querySelector('[data-submit-label]');
            if (label?.dataset.originalLabel) {
                label.textContent = label.dataset.originalLabel;
                delete label.dataset.originalLabel;
            }
        });
    });
});
document.querySelector('.commerce-success, .commerce-errors')?.focus({ preventScroll: true });

document.querySelectorAll('[data-cart-quantity]').forEach((form) => {
    form.addEventListener('click', (event) => {
        if (!event.target.closest('[data-quantity-step]') || form.dataset.submitting === 'true') return;
        // Quantity buttons have already updated the native input; the server recalculates all amounts.
        form.requestSubmit();
    });
});

document.querySelectorAll('[data-copy-target]').forEach((button) => {
    const value = document.getElementById(button.dataset.copyTarget);
    const feedback = document.getElementById('bank-copy-feedback');
    if (!value || !feedback) return;
    button.hidden = false;
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(value.textContent.trim());
            feedback.textContent = button.dataset.copySuccess;
        } catch {
            feedback.textContent = button.dataset.copyFailure;
        }
    });
});

document.querySelectorAll('[data-address-lookup]').forEach((lookup) => {
    const form = lookup.closest('form');
    const address = form?.querySelector('[name="address"]');
    const country = form?.querySelector('[name="country"]');
    const search = lookup.querySelector('[data-address-search]');
    const automatic = lookup.querySelector('[data-address-auto]');
    const results = lookup.querySelector('[data-address-results]');
    const status = lookup.querySelector('[data-address-status]');
    if (!address || !country || !search || !automatic || !results || !status) return;
    let request, timer, entries = [], activeIndex = -1, lastStarted = 0, composing = false;
    address.setAttribute('role', 'combobox');
    address.setAttribute('aria-autocomplete', 'list');
    address.setAttribute('aria-controls', results.id);
    address.setAttribute('aria-expanded', 'false');
    address.setAttribute('aria-describedby', [address.getAttribute('aria-describedby'), 'checkout-address-help'].filter(Boolean).join(' '));
    const dismiss = () => {
        results.hidden = true;
        results.replaceChildren();
        entries = [];
        activeIndex = -1;
        address.setAttribute('aria-expanded', 'false');
        address.removeAttribute('aria-activedescendant');
    };
    const clear = () => {
        window.clearTimeout(timer);
        timer = undefined;
        request?.abort();
        request = undefined;
        dismiss();
        status.textContent = '';
        search.disabled = false;
        lookup.hidden = country.value !== 'FR';
    };
    const choose = (entry) => {
        clear();
        address.value = entry.address;
        form.querySelector('[name="postal_code"]').value = entry.postal_code;
        form.querySelector('[name="city"]').value = entry.city;
        status.textContent = lookup.dataset.selected;
        address.focus();
    };
    const activate = (index) => {
        activeIndex = index;
        const options = results.querySelectorAll('[role="option"]');
        options.forEach((option, i) => option.setAttribute('aria-selected', String(i === index)));
        address.setAttribute('aria-activedescendant', options[index].id);
        options[index].scrollIntoView({ block: 'nearest' });
    };
    const lookupAddress = async () => {
        clear();
        const query = address.value.trim();
        if (country.value !== 'FR' || query.length < 4 || query.length > 200) return;
        request = new AbortController();
        const activeRequest = request;
        lastStarted = Date.now();
        search.disabled = true;
        status.textContent = lookup.dataset.loading;
        try {
            const response = await fetch(lookup.dataset.url, {
                method: 'POST', credentials: 'same-origin', signal: activeRequest.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                body: JSON.stringify({ address: query, country: 'FR' }),
            });
            if (!response.ok) throw new Error('Address lookup unavailable');
            const data = await response.json();
            if (activeRequest !== request || country.value !== 'FR' || address.value.trim() !== query) return;
            entries = (Array.isArray(data.suggestions) ? data.suggestions : []).slice(0, 6).filter(entry =>
                typeof entry.address === 'string' && entry.address.length <= 200 &&
                typeof entry.city === 'string' && entry.city.length <= 100 && /^\d{5}$/.test(entry.postal_code));
            entries.forEach((entry, index) => {
                const item = document.createElement('li');
                item.setAttribute('role', 'presentation');
                const button = document.createElement('button');
                button.type = 'button';
                button.id = `${results.id}-${index}`;
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');
                button.tabIndex = -1;
                button.textContent = `${entry.address} · ${entry.postal_code} ${entry.city}`;
                button.addEventListener('pointerdown', event => event.preventDefault());
                button.addEventListener('click', () => choose(entry));
                item.append(button);
                results.append(item);
            });
            results.hidden = entries.length === 0;
            address.setAttribute('aria-expanded', String(entries.length > 0));
            status.textContent = entries.length ? lookup.dataset.count.replace(':count', String(entries.length)) : lookup.dataset.empty;
        } catch (error) {
            if (activeRequest === request && error.name !== 'AbortError') status.textContent = lookup.dataset.failure;
        } finally {
            if (activeRequest === request) search.disabled = false;
        }
    };
    const schedule = () => {
        clear();
        if (!automatic.checked || composing || country.value !== 'FR' || address.value.trim().length < 4) return;
        // Debounce typing and stay below the existing twenty requests/minute limit.
        timer = window.setTimeout(lookupAddress, Math.max(650, 3200 - (Date.now() - lastStarted)));
    };
    address.addEventListener('input', schedule);
    address.addEventListener('compositionstart', () => { composing = true; clear(); });
    address.addEventListener('compositionend', () => { composing = false; schedule(); });
    address.addEventListener('keydown', event => {
        if (event.isComposing) return;
        if (event.key === 'Escape') { clear(); return; }
        if (event.key === 'Tab') { clear(); return; }
        if (event.key === 'Enter' && (timer !== undefined || search.disabled)) {
            event.preventDefault();
            if (!search.disabled) lookupAddress();
            return;
        }
        if (!entries.length || results.hidden) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activate(event.key === 'ArrowDown' ? (activeIndex + 1) % entries.length : (activeIndex <= 0 ? entries.length - 1 : activeIndex - 1));
        } else if (event.key === 'Enter') {
            // An open suggestion list must never submit the order form.
            event.preventDefault();
            choose(entries[activeIndex < 0 ? 0 : activeIndex]);
        }
    });
    address.addEventListener('blur', clear);
    country.addEventListener('change', clear);
    automatic.addEventListener('change', () => { if (automatic.checked) schedule(); else clear(); });
    search.addEventListener('click', () => { address.focus(); lookupAddress(); });
    clear();
});
