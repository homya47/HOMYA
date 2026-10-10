<x-admin-layout title="Vue d’ensemble">
<p class="admin-intro">Votre activité et les points à suivre, à partir des données enregistrées.</p>
@if($metrics === null)<div class="admin-notice" role="alert">Les données de commandes et de stock sont momentanément indisponibles. Aucun chiffre n’est estimé.</div>
@else
<div class="admin-metrics">
@foreach([['Commandes enregistrées', $metrics['orders'], 'admin.orders'], ['Paiements à suivre', $metrics['pending'], 'admin.orders'], ['Montants payés TTC', number_format($metrics['paid_cents'] / 100, 2, ',', ' ').' €', 'admin.orders'], ['Produits en inventaire', $metrics['products'], 'admin.inventory']] as [$label, $value, $route])
<a class="admin-card admin-metric" href="{{ route($route) }}"><span>{{ $label }}</span><strong>{{ $value }}</strong><small>Consulter →</small></a>@endforeach
</div><p class="admin-muted">{{ $metrics['paid'] }} commande(s) avec paiement confirmé. Les commandes en attente sont exclues des montants payés. Totaux historiques enregistrés, hors suivi des remboursements.</p>
@endif
<div class="admin-two-columns"><section class="admin-card"><h2>À suivre</h2><dl class="admin-data"><div><dt>Demandes de retour enregistrées</dt><dd><a href="{{ route('admin.returns') }}">{{ $returns['count'] }} →</a></dd></div>@if($metrics !== null)<div><dt>Unités réservées (internes)</dt><dd>{{ number_format($metrics['reserved'], 0, ',', ' ') }}</dd></div>@endif</dl><p class="admin-muted">Les réservations restent gérées par le parcours de commande et sa tâche d’expiration.</p>@if($returns['unreadable'])<p class="admin-notice">{{ $returns['unreadable'] }} dossier(s) de retour illisible(s) à vérifier.</p>@endif</section>
<section class="admin-card"><h2>Configuration de la boutique</h2><dl class="admin-data">
<div><dt>Virement bancaire</dt><dd>{{ $readiness->bankReady() ? 'Disponible' : 'RIB HOMYA à renseigner' }}</dd></div><div><dt>Commandes en attente des coordonnées</dt><dd>{{ $readiness->deferredReady() ? 'Ouvertes' : 'Fermées' }}</dd></div><div><dt>Coordonnées HOMYA</dt><dd>{{ config('contact.email') && config('contact.phone') ? 'Renseignées' : 'À compléter' }}</dd></div></dl><p class="admin-muted">Les coordonnées bancaires et de contact se configurent dans l’environnement privé.</p></section></div>
@if($metrics !== null)<section class="admin-card"><div class="admin-section-heading"><h2>Dernières commandes</h2><a href="{{ route('admin.orders') }}">Toutes les commandes →</a></div><x-admin-orders :orders="$metrics['recent']" /></section>@endif
</x-admin-layout>
