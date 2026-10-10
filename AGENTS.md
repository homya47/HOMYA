# HOMYA — règles locales

Appliquer les instructions globales `/Users/akm/.codex/AGENTS.md` et les modules pertinents indiqués par `/Users/akm/Codex-Workspace/.codex/workflows/README.md`.

- Projet confirmé : ce dépôt HOMYA ; Laravel et base locale XAMPP.
- Lire `docs/CADRAGE-HOMYA.md` et `docs/ENVIRONNEMENT-LOCAL.md` avant les changements structurants.
- Utiliser PHP 8.3 minimum pour Laravel 13 ; PHP 8.5 est installé localement. Le PHP 8.2 de XAMPP est incompatible avec cette version Laravel.
- La base cible est MariaDB de XAMPP. Ne pas assimiler le service MySQL Homebrew sur le port 3306 à XAMPP.
- Ne pas modifier XAMPP, arrêter un service existant, créer une base ou exécuter les migrations sans plan et autorisation propres à l'action.
- Conserver les secrets dans `.env`, ignoré par Git ; ne jamais les afficher dans les sorties ou les documents.
- Utiliser les composants Blade et les styles partagés. Les prix, stocks, avis et promesses commerciales ne doivent pas être inventés.
- Vérifier chaque phase proportionnellement ; distinguer tests sans base, vérification MariaDB et fonctionnalités commerciales.
- Les arbitrages confirmés sont consignés dans le registre central Specification, filtré sur HOMYA ; aucun registre général parallèle.
