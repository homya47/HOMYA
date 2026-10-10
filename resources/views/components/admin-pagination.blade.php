@props(['items'])
<nav class="admin-pagination" aria-label="Pagination"><span>{{ $items->total() }} résultat(s) · Page {{ $items->currentPage() }} / {{ $items->lastPage() }}</span><div>@if($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}" rel="prev">← Précédent</a>@endif @if($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}" rel="next">Suivant →</a>@endif</div></nav>
