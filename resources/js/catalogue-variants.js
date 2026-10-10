// Every finish has a bookmarkable landing URL; server rendering selects it without JavaScript.
document.querySelector('#product-variant')?.addEventListener('change', event => {
    if (! event.target.value) return;
    const url = new URL(window.location.href);
    url.searchParams.set('variant', event.target.value);
    url.hash = 'product-purchase';
    window.location.assign(url);
});
