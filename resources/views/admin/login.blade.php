<x-admin-layout title="Connexion administrateur" :guest="true">
<section class="admin-card admin-login">
<h2>Bienvenue sur votre espace HOMYA</h2><p>Consultez les commandes, les stocks et les demandes de retour.</p>
@if($configured)
<form method="post" action="{{ route('admin.authenticate') }}">@csrf
<label for="username">Identifiant</label><input id="username" name="username" autocomplete="username" required maxlength="80" value="{{ old('username') }}">
<label for="password">Mot de passe</label><input id="password" name="password" type="password" autocomplete="current-password" required maxlength="256">
<button type="submit" class="admin-primary">Se connecter au dashboard →</button>
</form><p class="admin-muted">Déconnexion après 30 minutes d’inactivité. Accès réservé à cet ordinateur.</p>
@else
<div class="admin-notice">Le compte administrateur doit être créé depuis le terminal local avec <code>php artisan homya:admin-create</code>. Aucune inscription publique n’est disponible.</div>
@endif
</section></x-admin-layout>
