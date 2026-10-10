# HOMYA — accès local dans XAMPP

08/10/2026 — installation locale autorisée et vérifiée, DECISION-045/046.

Le projet de travail reste `/Users/akm/Documents/ChatGPT/HOMYA`. L'utilisateur autorise la conservation de l'ancienne copie de7Go hors de `htdocs`, puis la copie intégrale du projet actuel dans `/Applications/XAMPP/xamppfiles/htdocs/HOMYA`. Aucune suppression, fusion aveugle ou écriture SQL. Les différences d'une prochaine copie devront être examinées ; ce n'est pas une synchronisation automatique bidirectionnelle.

## Plan concret

1. Finir les corrections et tests dans le dossier de travail ; sauvegarde privée de `.env`, seule sélection SQL modifiée, diagnostic reader dédié en lecture seule.
2. Préparer une copie complète hors webroot ; vérifier les fichiers par SHA256, types, permissions et liens, puis vérifier la source inchangée. Conserver l'ancienne copie sous un parent0700 hors de `htdocs`, et installer la copie vérifiée par renommage. Aucun fichier existant supprimé.
3. Protéger la racine complète par `.htaccess` : les secrets, archives, tests et sources ne sont pas un document public. Le seul document root autorisé est `HOMYA/public`.
4. Utiliser le binaire Apache de XAMPP avec une configuration HOMYA séparée, écoute uniquement127.0.0.1:8080, et un pool PHP-FPM8.5 distinct sur127.0.0.1:19085 sous le propriétaire actuel des fichiers. Le PHP8.2 intégré est incompatible avec Laravel13. Aucun changement de `httpd.conf`, `my.cnf`, service existant ou permission globale. Arrêter si un port est déjà occupé ; ne pas tuer son propriétaire.
5. Vérifier syntaxe Apache/FPM avant lancement ; processus locaux dédiés uniquement. Vérifier accueil/catalogue/fiches FR/EN/DE, erreurs, filtres, pagination, langues, images, responsive et accès refusés aux chemins privés. `/up` ne suffit pas à prouver la base.
6. Consigner URL réelle, PID et preuves. Conserver le retour source-only autorisé si la recette SQL échoue sans autre divergence. Une copie de configuration n'est pas une sauvegarde SQL.

L'URL prévue est `http://127.0.0.1:8080/fr` ; elle n'est confirmée disponible qu'après les contrôles. Les processus de développement ne constituent pas un hébergement de production. Après redémarrage de l'ordinateur, utiliser la commande de démarrage documentée ; aucun démarrage automatique système n'est installé.

Le périmètre métier reste un catalogue consultatif. Identité du vendeur, validation actuelle des prix/TVA/stock, livraison/retours, paiement et administration commerciale restent ouverts ; l'utilisateur les fournira ultérieurement. Hostinger choisi, formule et domaine non décidés dans cette phase locale. Aucun compte distant, tag ou transaction réelle créé.

## Résultat réel et utilisation

- Projet servi : `/Applications/XAMPP/xamppfiles/htdocs/HOMYA/public`, configuration réelle SQL sans override de recette. URL principale [HOMYA local](http://127.0.0.1:8080/fr).
- Copie initiale intégrale :74 139 fichiers/9 613 872 228octets, contenus SHA256, types, modes, uid/gid et liens symboliques comparés à la source, source recontrôlée. La copie macOS a hérité du groupeadmin80 pour les fichiers : arrêt avant remplacement, puis groupe source20 rétabli sur la nouvelle copie uniquement et contrôles complets réussis. Racine incluse ; attributs étendus et topologie globale des liens physiques non certifiés. Métadonnées/ACL des accès privés et du profil contrôlées séparément.
- Ancien dossier conservé, même inode/device, sous `/Applications/XAMPP/xamppfiles/homya-local/20261008T180754Z-1afc52ac-d21f-4530-a1ac-83890af9b236/HOMYA-previous`, parent0700. Aucun fichier supprimé. Le dossier de travail original est préservé ; ce n'est pas une synchronisation automatique.
- Apache et FPM dédiés restent démarrés pour l'usage local. Le premier lancement a rencontré une lecture prématurée du PID Apache ; les deux services avaient réussi et ont été contrôlés ensuite sans redémarrage. Le helper final attend la présence du PID et vérifie son processus, sans arrêter un service tiers.
- Après redémarrage de l'ordinateur, lancer `/usr/bin/python3 /Applications/XAMPP/xamppfiles/htdocs/HOMYA/scripts/start_homya_local.py --start`. Sans `--start`, cette commande affiche seulement l'état. Le pool FPM/Apache HOMYA est distinct des boutons de contrôle des autres services XAMPP. MariaDB XAMPP doit être disponible.
- Le dossier complet refuse les fichiers privés ; l'entrée locale habituelle `/HOMYA/` redirige vers le runtime compatible8080. Elle nécessite que l'Apache XAMPP habituel sur80 fonctionne ; l'URL8080 reste l'accès direct à privilégier.

## Corrections et contrôles

Galerie60×60sans débordement, formulaire conservé en erreur422 et champs invalides reliés au message, filtres actifs retirables, contexte recherche/tri/page conservé entre fiche/langue/retour ; en-têtesnosniff/SAMEORIGIN/referrer-policy sur200/302/404/422/503, signal fixe de panne sans exception/secret. Quatre chaînes sources EN/DE corrigées après validation du dictionnaire, sans modifier l'archive ; aucune qualité linguistique globale certifiée.

239testsPHP/1 676assertions hors base passent,13testsPython nouveaux passent, Pint et buildVite réussis. Refus de sockets synthétiques sous sandbox puis suite réussie hors sandbox ; refus du diagnostic reader avec chemin relatif, puis diagnostic absolu réel réussi. PHP CLI sous sandbox ne peut pas écrire `htdocs` ; les permissions réelles ont été vérifiées hors sandbox et par le serveur PHP-FPM.

Recette native de cette phase :78GET/statuts/langues/en-têtes, sept cas complémentaires (cinq HEAD privés, garde503 avec forwarded-header et422avec saisies),13 médias/1 708 730octets identiques aux fichiers installés. HEAD supplémentaires sur80 : `.env`, stockage privé etvendor refusés403, sans corps privé téléchargé. Navigateur375/768/1440 : pas de débordement observé ; menu/Échap/focus, saut clavier verspage78,37résultatsBosch, tri/page2→fiche→DE→retour contextuel,8miniatures, portrait/étiquette/galerieclavier,0résultat et erreurs de filtre. Console interrogée sans erreur/warning dans cette séquence. Photos observées chargées sans rupture ; pas de certification exhaustive des22 239médias ouWCAG/CWV.

Preuves runtime privées sous `/Applications/XAMPP/xamppfiles/homya-local/20261008T180754Z-1afc52ac-d21f-4530-a1ac-83890af9b236` ; synthèse/captures sous `output/qa-local-20261008`. Les changements documentaires/helpers postérieurs à la copie initiale sont synchronisés séparément après contrôle des empreintes des seuls fichiers concernés. Aucun rollback SQL, migration, import, nouvelle permission ou donnée métier créé.

## Sources primaires consultées08/10/2026

| Source et URL | Portée et fait publié | Application locale / limite |
|---|---|---|
| [Laravel13Deployment](https://laravel.com/framework/docs/13.x/deployment) | PHP≥8.3 et document rootpublic ; configuration/permissions de production à vérifier. | PHP8.5 et public/ utilisés localement ; ne prouve pas Hostinger ni readiness publique. |
| [Apachemod_proxy_fcgi](https://httpd.apache.org/docs/2.4/mod/mod_proxy_fcgi.html) | SetHandler permet de transmettre les scriptsPHP à FastCGI ; FPM doit être lancé séparément. | ApacheXAMPP et FPM8.5 dédiés, proxy ouvert désactivé et écoute loopback. |
| [PHPFPMconfiguration](https://www.php.net/manual/en/install.fpm.configuration.php) | Pool, écoute, utilisateurs et restrictions d'extensions configurables. | Propriétaire réel des fichiers, clients loopback, seules extensionsPHP ; preuves sur ce poste. |
| [ApacheFriendsFAQmacOS](https://www.apachefriends.org/faq_osx.html) | XAMPP destiné au développement ; accès externe phpMyAdmin déconseillé. | Aucun phpMyAdmin ouvert à distance ; production à préparer séparément. |
