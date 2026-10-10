@props(['locale'])
<svg class="language-flag" viewBox="0 0 30 20" width="21" height="14" aria-hidden="true" focusable="false" data-flag="{{ $locale }}">
    @if($locale === 'fr')
        <path fill="#002654" d="M0 0h10v20H0z"/><path fill="#fff" d="M10 0h10v20H10z"/><path fill="#ed2939" d="M20 0h10v20H20z"/>
    @elseif($locale === 'en')
        <path fill="#012169" d="M0 0h30v20H0z"/>
        <path fill="#fff" d="M0 0h3l27 18v2h-3L0 2zm30 0h-3L0 18v2h3L30 2z"/>
        <path fill="#c8102e" d="M0 0l15 10h-3L0 2zm30 0v2L18 10h-3zM0 20v-2l12-8h3zm30 0L15 10h3l12 8z"/>
        <path fill="#fff" d="M12 0h6v20h-6zM0 6h30v8H0z"/><path fill="#c8102e" d="M13 0h4v20h-4zM0 8h30v4H0z"/>
    @elseif($locale === 'de')
        <path fill="#000" d="M0 0h30v7H0z"/><path fill="#d00" d="M0 7h30v6H0z"/><path fill="#ffce00" d="M0 13h30v7H0z"/>
    @endif
</svg>
