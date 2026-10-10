# HOMYA — OP-10, recette native en lecture seule

Version 0.3 — 08/10/2026 — **RECETTE TECHNIQUE NATIVE VÉRIFIÉE ; SITE SUR FICHIERS, BASCULE NON EFFECTUÉE**.

Demande initiale : préparer la recette native et son autorisation, en conservant une autorisation distincte pour la bascule. DECISION-042 permet la préparation hors base. L'accord explicite suivant a été enregistré/relu sous DECISION-043 puis exécuté : tentative arrêtée au premier contrôle serveur et clôturée. Registre unique [Specification/HOMYA](</Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md>). La version 0.1 et son programme restent figés ; voir §8 pour les faits et §9 pour la reprise proposée. Aucune reprise native autorisée ni exécutée.

## 1. Références et faits

- Projet existant `/Users/akm/Documents/ChatGPT/HOMYA`, Laravel13/PHP8.5.11 observés, `pdo_mysql` disponible, plafond CLI128M. Git demeure non suivi : préservation par empreintes, aucun commit prétendu.
- [Plan OP-10 et livraison](OP-10-LECTURE-SQL-PREPARATION.md#9-livraison-hors-base--decision-042) : fichiers actifs, adaptateurs/garde séparée,223 tests/1 476 assertions et parité exhaustive hors base réussis. Ces preuves sont relues ici ; leur réussite n’est pas une connexion native fraîche.
- [OP-09 natif daté](../output/qa-import/op09-execution-synthese-verification.json) : lot vérifié,65 catégories/3 709 produits/23 878 traductions, journal4 ; preuves de conservation et de privilèges. Service et données actuels **À vérifier après autorisation**, sans les présumer disponibles.
- Lot fixe : `48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809` ; payload `49811854d0b4a8da4e3cb7b3e66c99ed2f43ae9f3a71875c29073fa636ea37e1` ; profil privé `adb862b6f9b96e602417d2a6d57160dfcd0040ba3115e956b7d638bfc8deca96`.
- Reconstruction complète : échec128M et pic~268M hors base. Recette exhaustive CLI512M proposée pour son seul processus ; candidats HTTP128M. Aucun changement `php.ini` ni budget commercial de performance inventé.
- Modules consultés : Specification, Database Engineering, Security, Quality, Performance et Scripts. Outils Claude visibles dans les métadonnées, non appelés ; aucune authentification ni disponibilité de modèle affirmée. Codex natif suffit.

## 2. Périmètre soumis à l’accord

**Inclus :** lectures XAMPP et conservation avant/après ; reconstruction brute/digest et parité de tous les produits dans trois langues ; deux candidats PHP temporaires de comparaison, fichiers et SQL ; GET catalogue/fiches/navigation/ressources, mesures et revue clavier/responsive ; preuves privées et arrêt des seuls candidats possédés par cette recette.

**Comptes :** `homya_local_reader` pour PDO/Laravel et le candidat SQL. Administratif root XAMPP uniquement pour SELECT/SHOW CREATE des métadonnées, privilèges et empreintes de lignes nécessaires aux témoins des trois schémas. Accès root sans secret proposé selon les contrôles OP-09 ; disponibilité à vérifier, échec = arrêt. Aucun accès root du site ; aucun compte import/schema utilisé pour les lectures applicatives.

**Cible :** socket XAMPP `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`,3307, version10.4.28-MariaDB, données XAMPP et boucle locale attendues. Les schémas `homya_local`, `homya_test` et `homya_restore_op07` sont uniquement relus pour vérifier leur conservation. Pas de contact avec Homebrew3306 ni de serveur externe.

**Exclus :** `.env`/source du site existant, SQL persistant INSERT/UPDATE/DELETE/DDL/GRANT, migrations/seeders/fixtures SQL, création de compte/base/table, correction de données, dump/export SQL/restauration/sauvegarde de base, transaction commerciale, arrêt/redémarrage/configuration XAMPP, installation de package, écriture de campagne/tag/service externe et publication. La connexion initialise sa session via le driver ; aucune modification globale du moteur.

Les écritures locales permises sont exclusivement les documents d’accord/reçu, preuves/journaux/captures du run, vues compilées isolées et verrou technique commun. Elles ne rendent pas la base inscriptible. Aucune sauvegarde de base supplémentaire n’est nécessaire pour cette phase sans écriture SQL ; aucun retour arrière de données promis.

## 3. Outils et frontière d’autorité

[Programme fermé de reprise](../output/qa-import/op10-recette-native-reprise-programme.json) : plan/artefacts/binaires/témoins hachés, liste exacte des SELECT/SHOW CREATE, matrice HTTP et plafonds. Il indique `native_read_authorized=false`, `activation_authorized=false`. Ce programme n’est pas un reçu. Le [programme initial figé](../output/qa-import/op10-recette-native-programme-valide.json) est conservé.

- [Garde et audit](../scripts/op10_native_recipe.py) : aide par défaut hors SQL ; reçu/accord natif nouveau lié au registre, plan, programme, fichiers et binaires ; liste administrative fermée, aucune requête fournie par le client. Résultats expurgés en empreintes, comptes/cible/droits attendus contrôlés ; refus sans accord avant tout client SQL.
- [Helper de parité](../scripts/op10_catalogue_native.php) : inclusion/aide/refus avant bootstrap ou accès dédié. Après reçu : seul reader CLI, vérification brute par `CatalogueArchiveStore` historique, comparaison du profil, parité cartes/fiches/filtres/pages ; erreurs sans diagnostic PDO brut, connexion purgée à fin. Plafond512M et délai bornés par l’opérateur du run.
- [Routeur candidat](../scripts/op10_candidate.php) : GET et boucle locale uniquement, ports8011/8012, pages FR/EN/DE et ressources publiques autorisées ; accord revérifié avant bootstrap. Sélection en mémoire depuis variable de processus, jamais depuis URL/en-tête. Sessions/cache en mémoire, logs applicatifs désactivés, debugfalse, vues compilées dans le run. Télémetrie privée : statut, temps/mémoire, compte/durée SQL, sans SQL brut/paramètres/secret ni endpoint diagnostic.
- [Tests PHP](../tests/Feature/Op10NativeRecipePreparationTest.php) et [tests de garde Python](../scripts/test_op10_native_recipe.py) : défauts/refus, requêtes interdites, accord lié aux empreintes et comparateur sur doubles ; aucune preuve SQL ou serveur HTTP implicite.

Les outils historiques ne sont ni modifiés ni réautorisés : aucun ancien reçu OP-07/08/09 n’est réutilisé. Le futur accord est consigné/relu dans le registre central **avant** de créer un reçu natif, avec plan/programme SHA et mention `OP-10-READONLY`, reader/lot et `BASCULE EXCLUE`. Le programme et le reçu ne sont pas auto-approuvés par les scripts. Toute modification des artefacts nécessite la relecture de sa portée avant exécution.

## 4. Séquence contrôlée

| ID | Opération précise, après accord | Résultat attendu / arrêt |
|---|---|---|
| NAT-01 | Accord/reçu, empreintes/binaires et contexte ; `.env` inchangé, sourcefiles, configuration non cachée ; métadonnées accès uniquement avant chargement autorisé | Refus sur divergence, aucun cache vidé ni accès recréé |
| NAT-02 | Verrou commun `output/qa-import/.op08.lock` non bloquant détenu pour la recette ; run unique0700 et vues0700, fichiers0600 | Conflit = arrêt, aucun processus tiers arrêté ; ce verrou ne contrôle pas les applications externes |
| NAT-03 | Audit administratif avant, via liste fermée : identité/capacités, schémas/objets, comptes/droits, DDL15tables, compteurs/journaux et SHA des lignes ordonnées par PK | Local1/65/3709/23878/4 ; test/restauration5/67/3711/23890/4 ; reader exactement4 SELECT sur archives, sans délégation/droit global/schéma/colonne/rôle/proxy |
| NAT-04 | Chargement privé du reader ; garde CLI, snapshot du seul lot, payload/profil/counts exacts ; parité3709 cartes+fiches dans chaque langue et7 jeux de filtres/pages24 | Aucune divergence, aucun fallback ni donnée corrigée ; mémoire/durée reportées, plafond512M propre au helper |
| NAT-05 | Disponibilité ports8011/8012 vérifiée sans prendre un serveur existant ; démarrer uniquement deux processus PHP possédés par la recette | Port occupé = arrêt, aucun serveur déplacé/tué. Bind127.0.0.1, plafond128M, source en mémoire, fichiers actifs sur site habituel |
| NAT-06 | GET identiques sur candidat fichiers8011 et SQL8012 : accueil, catalogue, tris/filtres, recherche FR/localisée, pages1/78/155, cinq univers et fiches échantillonnées | Même contenu significatif, statuts/liens/traductions/prix/images/masquages ; normaliser seulement l’origine et les valeurs de session variables |
| NAT-07 | États sains vides200, filtre invalide422, page excessive redirigée, slug absent404 ; langue garde slug/filtres/page | Aucun parcours commercial. Ne pas corrompre une table ni arrêter MariaDB pour obtenir503 : les erreurs injectées restent couvertes par les doubles |
| NAT-08 | Mesures mêmes parcours fichiers/SQL, premier accès puis3 répétitions ; compte/durée SQL, mémoire, durée PHP et HTTP totale/poidsHTML/assets | Pas d’épuisement/timeout ; pas de requête par carte sur liste ; aucune promesse de gain. Temps navigateur total inclut aussi les gardes de recette |
| NAT-09 | Navigateur local :375/768/1440px si capacité disponible, menu/recherche/filtres/pagination/changement langue/galerie/clavier/focus/erreurs, ressources et débordements | Captures et observations, pas de conformité WCAG/CWV affirmée ; capacité viewport/mesure à vérifier, tout contrôle impossible reste ouvert |
| NAT-10 | Arrêt propre des seuls PID candidats enregistrés, vérification commande/identité avant SIGTERM ; libération du verrou à fin | Jamais XAMPP ni serveur existant ; échec de fermeture signalé, aucune suppression de preuve |
| NAT-11 | Audit administratif après et témoins de fichiers/médias/capsule ; comparaison stricte des audits avant/après, journal/comptes/droits/DDL/empreintes | Écart = anomalie conservée, aucune restauration/correction/reprise automatique |
| NAT-12 | Synthèse expurgée, statut de chaque OP10-01..12 et limitations ; fermeture du run/accord dans le registre | « Recette lecture seule vérifiée » uniquement si contrôles réussis ; **site toujoursfiles**, bascule sous autre accord ultérieur |

Délai de sûreté proposé :20s par requête administrative,30s par GET,1800s par run/parité CLI. Ces bornes opérationnelles provoquent un arrêt ; elles ne sont pas des objectifs métier de performance. Réserve locale256Mio pour preuves, limite64Mio du run hors captures à vérifier au départ ; si insuffisante, arrêter sans nettoyage automatique. Ne pas multiplier les essais sur un échec.

## 5. Commandes prévues, non exécutées ici

Les chemins `RECU`/`RUN` seront des fichiers/dossiers privés **créés seulement après accord** ; aucun placeholder ne doit être passé comme accès réel.

```sh
python3 scripts/op10_native_recipe.py --check-receipt --receipt=RECU
python3 scripts/op10_native_recipe.py --audit --receipt=RECU
/opt/homebrew/bin/php -d memory_limit=512M -d display_errors=0 -d log_errors=0 scripts/op10_catalogue_native.php --receipt=RECU
```

Pour chacun des candidats, l’opérateur prépare un environnement propre au processus : `APP_ENV=local`, `APP_DEBUG=false`, `HOMYA_CATALOGUE_SOURCE=files`, `OP10_CANDIDATE_SOURCE=files` ou `sql`, `OP10_CANDIDATE_RECEIPT=RECU`, `OP10_CANDIDATE_RUN=RUN`. Ces valeurs ne sont écrites ni dans `.env` ni dans une configuration persistante ; le routeur remplace la source en mémoire après validation du défautfiles.

```sh
/opt/homebrew/bin/php -d memory_limit=128M -d display_errors=0 -d log_errors=0 -S 127.0.0.1:8011 -t public scripts/op10_candidate.php
/opt/homebrew/bin/php -d memory_limit=128M -d display_errors=0 -d log_errors=0 -S 127.0.0.1:8012 -t public scripts/op10_candidate.php
```

La mise en œuvre opérateur conserve PID, source/port, horodatages et commandes effectives ; stdout/stderr uniquement dans le run0600. Les sorties administratives/parité sont capturées dans le run privé ; si programme de clôture incomplet, ne pas improviser une nouvelle écriture SQL. Aucun serveur lancé par cette préparation.

## 6. Preuves à produire et limites

**Préparation actuelle :** [synthèse hors base](../output/qa-import/op10-recette-native-preparation-verification.json) : conservation des témoins précédents, syntaxe/refus et tests, programme et documents. Tests exécutés : 31 tests PHP / 253 assertions (nouveaux helpers et source catalogue existante), 10 tests Python ; syntaxe et style PHP contrôlés. Programme préparé : 63 lectures administratives, 78 parcours HTTP par candidat, 9 cas produits représentatifs. Aucun SQL ni chargement d’accès dédié, serveur candidat, contrôle de port ou navigateur exécuté. Les 223 tests précédents restent datés ; aucun succès natif n’est déduit des simulations.

**Exécution future :** accord/reçu/plan-programme figés ; audits avant/après ; payload et parité complète ; matrice GET/DOM, captures locales et télémétrie ; preuve de fermeture des seuls candidats ; témoins source/.env/XAMPP/runtime, médias/capsule. Les empreintes complètes des médias/source/capsule seront recalculées avant/après et comparées au manifeste approuvé ; aucune transformation. Aucun secret brut en HTML/JSON/log/capture.

**Inconnues :** service XAMPP, accès root/reader et droits/données actuels ; ports libres ; performance/nativePHP ; capacités navigateur/viewport ; seuil de latence acceptable avant future bascule. Le seuil n’est pas inventé : la recette expose ses mesures et anomalies, et ne transforme pas une durée quelconque en approbation de performance commerciale. Prix/stock actuels,627 signalements/relecture linguistique, règles de vente et hébergement demeurent hors scope.

Un succès n’autorise pas la publication, l’indexation, Google, le paiement ou l’achat. OP10-11 bascule et OP10-12 retour du site restent non exécutés ; arrêter un candidat isolé ne constitue pas un exercice de bascule/retour du site existant.

## 7. Accord groupé à présenter

**Autorisez-vous OP-10-READONLY dans ce périmètre :** audits XAMPP en lecture seule (root pour métadonnées/empreintes uniquement), reader pour le lot validé, parité exhaustive FR/EN/DE, deux candidats127.0.0.1 temporaires et leur fermeture, GET/navigateur/mesures, conservation avant/après et preuves privées — **sans modification `.env`, XAMPP, données/droits/schéma, migration/import ni bascule** ?

Réponse possible : **« J’autorise OP-10-READONLY dans ce périmètre »**. Cette demande suit votre instruction explicite de garder l’autorisation de recette et celle de bascule distinctes ; DECISION-042 autorise uniquement la préparation hors base. Aucun accord natif n’est présumé dans votre demande actuelle.

## 8. Tentative DECISION-043 — arrêt et conservation

Accord « J’autorise OP-10-READONLY dans ce périmètre » enregistré, relu et lié aux empreintes avant toute connexion. Run `op10-native-run-20261008T172123Z-c6f4ecd7-5310-4af2-9f1b-5647238a2d7c` : verrou détenu, capsule et originaux intégralement vérifiés, puis première SELECT de métadonnées serveur. Client SQL terminé avec code0 ; décodage JSON refusé (`JSONDecodeError`, position269). Réponse brute non conservée : sa cause exacte demeure **À vérifier**. Aucun audit administratif complet, chargement reader, parité, candidat, GET ou navigateur exécuté.

L'opérateur a également rencontré un `TypeError` dans son message de progression (argument `stage` doublé). Le bloc final a néanmoins terminé les preuves de conservation et le statut. Son source original et les outils initiaux sont préservés, sans les présenter comme corrigés pendant l'exécution.

Conservation prouvée : 32 artefacts et244 témoins identiques ; capsule et originaux re-hachés avant/après, chacun22239 fichiers/3477829369 octets. Aucun SQL persistant, service/configuration/.env/source du site changé. Aucune empreinte SQL avant/après obtenue : conservation des tables **non vérifiée par cette tentative**, aucun constat de corruption déduit. Verrou libéré, zéro candidat à fermer. [Synthèse de l'arrêt](../output/qa-import/op10-recette-native-arret-verification.json), [clôture du registre](../output/qa-import/op10-recette-native-arret-cloture-registre.json). Accord/reçu043 conservés et invalidés ; aucune reprise automatique.

## 9. Correctif hors base et autorisation de reprise proposée

Le contrôle serveur utilisera dix colonnes tabulaires au lieu de `JSON_OBJECT`, avec conversion numérique explicite pour port/paquet/journaux et texte pour l'état des événements. Parseur strict : une ligne/dix champs, types et cible exacts, événements et journaux OFF, paquet≥16Mio. Aucun attendu de sécurité supprimé et aucune modification XAMPP. Le message de progression opérateur est corrigé hors exécution. Ces changements sont préparés sous le périmètre de développement hors base DECISION-042 ; leur succès natif reste à vérifier.

Tests supplémentaires sur doubles : réponse tabulaire valide, cibles/capacités erronées, réponse tronquée ou multiligne, arrêt avant la deuxième requête et absence de données brutes dans la sortie. [Preuves du correctif](../output/qa-import/op10-recette-native-reprise-preparation-verification.json). Tous les adaptateurs applicatifs, accès, archives et étapes NAT-01..12 restent inchangés. Nouveau programme/accord/reçu requis, DECISION-043 explicitement refusée ; aucune nouvelle autorité émise ici.

Recherche externe du08/10/2026, question précise : existe-t-il une limitation documentée de la sérialisation JSON dans MariaDB10.4 pouvant éclairer ce refus ? [JSON_OBJECT — documentation MariaDB](https://mariadb.com/docs/server/reference/sql-functions/special-functions/json-functions/json_object) décrit la construction d'un objet JSON. [MDEV-26167 — registre officiel MariaDB](https://jira.mariadb.org/browse/MDEV-26167) documente des troncatures avec des booléens dans d'autres scénarios et cite10.4. **Interprétation :** une fragilité de sérialisation est une piste, sans preuve que ce bogue explique notre résultat ; aucun diagnostic moteur certifié. **Recommandation :** retirer la dépendance à cette sérialisation dans l'outil de contrôle ; aucune mise à niveau serveur proposée.

**Autorisation distincte à présenter :** « J’autorise la reprise OP-10-READONLY dans ce même périmètre ». Elle porte sur une seule nouvelle tentative avec contrôle tabulaire corrigé, les mêmes lectures/conservations/parité/candidats temporaires et mesures, sans écriture SQL, modification XAMPP/.env, import/migration ou bascule. Nouvel arrêt au premier écart, sans autre reprise implicite.

## 10. Reprise corrective et clôture technique — DECISION-044

Nouvelle instruction utilisateur « Apporte les modifications necessaires a chaque etape afin que le site web soit fonctionnel a tous les niveaux. » reçue après présentation du correctif. Autorisation corrective retenue dans le seul périmètre courant, enregistrée/re-lue DECISION-044 : reprise des lectures/candidats, sans élargir la frontière de bascule. Plan v0.2/programme de reprise et reçu privés figés avant connexion. [Synthèse indépendante des preuves](../output/qa-import/op10-recette-native-reprise-synthese-verification.json), run `op10-native-run-20261008T173005Z-06f09872-96b1-4b16-b9ec-3bcae0131be4`.

Résultats réels : contrôle serveur tabulaire réussi ; deux audits administratifs complets de63 SELECT/SHOW CREATE identiques. Cible XAMPP/socket/3307, comptes/droits, trois schémas, DDL/compteurs/journaux et empreintes de lignes conservés. Payload réel identique au profil approuvé. Parité stricte3709 cartes et3709 fiches par langue FR/EN/DE, sept jeux de filtres/pages24 ; CLI512M, pic295256064 octets (~282Mio).

Deux candidats temporaires isolés :78 parcours chacun, HTML normalisé uniquement par origine identique, statuts attendus200/302/404/422. Six parcours avec trois répétitions par source (18 GET chacune). HTTP total médian observé0,478s fichiers /0,841s SQL, maximum0,748s /1,024s : ces mesures locales incluent les gardes de recette, aucun seuil métier inventé ni gain revendiqué. Mémoire maximale HTTP28,6Mo fichiers /56,1Mo SQL, sous128M ; listes0 requête SQL côté fichiers et5/6 côté SQL, sans requête par carte. Temps du handler séparé, bootstrap/garde de reçu exclus de cette télémétrie.

Navigateur intégré :375/768/1440px, menu mobile/Échap/focus, recherche anglaise Bosch37 résultats, conservation page/recherche/slug lors du changement de langue, tri allemand, galerie clavier/image2 et texte alternatif, recherche vide et erreur de plage de prix/focus formulaire. Aucun débordement horizontal sur les vues testées, aucune image visible cassée observée. Captures et `browser-checks.json` dans le run ; viewport rétabli et tab temporaire fermé. Audit complet console, accessibilité/WCAG, CWV et tous visuels individuels non effectués ou certifiés.

Le saut de page par son formulaire au clavier n'a pas été contrôlé dans le navigateur ; les pages1/78/155 et la redirection excessive ont été testées en HTTP. Ressources CSS/JS/images chargées sur les vues observées, poids HTML mesuré ; pas d'inventaire pondéré complet des assets ni d'acceptation d'un budget de latence. Ces limites restent ouvertes, sans assimiler parité technique et validation exhaustive UX/performance.

Deux seuls PID candidats arrêtés proprement, verrou libéré. Capsule et originaux intégralement re-hachés avant/après (22239 fichiers/3477829369 octets chacun),33 artefacts et244 témoins inchangés à fin d'exécution. Aucun SQL persistant, migration/import/droit/compte/base, `.env`, XAMPP/service ni activation modifiés. Les mises à jour documentaires de clôture suivantes sont distinctes de ces témoins de fin d'exécution.

**Limites ouvertes :** certains titres conservent des termes étrangers (« Argent » dans une fiche DE, « Flachkollektor-Set » dans une liste FR). La parité technique des sources ne certifie pas la qualité linguistique.627 signalements/479 produits, prix/stock actuels et règles commerciales restent à revoir ; aucune correction d'archive immuable dans ce run. Panier, commande, livraison, paiement et administration ne deviennent pas opérationnels grâce à cette recette. **Bascule et retour opérateur toujours distincts**, [périmètre suivant proposé](OP-10-BASCULE-LOCALE-PERIMETRE.md).
