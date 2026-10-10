# HOMYA — OP-09, préparation de l'import contrôlé du catalogue local

Version 0.3 — 08/10/2026 — **IMPORT LOCAL VÉRIFIÉ ; SITE TOUJOURS SUR FICHIERS**.

État actuel au §9. Les sections antérieures conservent leurs limites et états historiques. Les sections1–7 conservent la préparation historique ; [plan v0.1 validé](../output/qa-import/op09-plan-valide-v01.md) figé et autorisation limitée DECISION-040. Aucune autorisation d'exécution n'est contenue dans cette livraison.

La demande « préparer OP-09, l’import contrôlé du catalogue, avant son autorisation distincte » couvre cette inspection, la simulation existante sans SQL et le présent plan. Elle ne couvre pas l'exécution de chargement. Les choix proposés et les contrôles futurs ci-dessous ne sont pas présentés comme validés ou exécutés.

## 1. Sources, état de départ et qualification

Registre unique : [Specification, décisions HOMYA](</Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md>). Aucun second registre général ni décision d'import validée créés.

| Qualification | Élément | Source / limite |
|---|---|---|
| Décision validée | Archive consultative non vendable ; conserver tous les produits, documents, dictionnaires, médias référencés et signalements | [Plan d'import v0.2](PLAN-IMPORT-XAMPP.md), DECISION-017 ; [MLD](MODELE-LOGIQUE-ARCHIVE-HOMYA.md), DECISION-018 ; modèle physique validé, DECISION-019 |
| Fait vérifié à OP-08, pas renouvelé par SQL dans cette préparation | `homya_local` contient exactement cinq tables ; quatre archives vides ; journal quatre lignes id1–4/batch1 | [Synthèse OP-08](../output/qa-import/op08-reprise-synthese-verification.json), [contrôle indépendant](../output/qa-import/op08-reprise-controle-independant.json), DECISION-039 |
| Fait vérifié à OP-08 | 34 colonnes, 27 CHECK, quatre FK RESTRICT, cinq PK, deux UNIQUE ; DDL complet conforme à la référence restaurée OP-07 | Même preuve ; ne pas confondre ce constat daté et l'état au moment du futur import |
| Fait vérifié à OP-08 | Comptes locaux existants : 26 droits de table et trois droits UPDATE de colonne au total ; connexions Laravel/PDO schema, reader et import contrôlées | Aucun GRANT, création de compte ou droit supplémentaire requis pour OP-09 |
| Fait vérifié antérieurement | Import réel, intégrité, transport et concurrence éprouvés dans `homya_test` ; export et restauration isolée de ses cinq tables vérifiés | [OP-06 §13](OP-06-RECETTE-XAMPP-PREPARATION.md), [OP-07](OP-07-SAUVEGARDE-RESTAURATION-PREPARATION.md), DECISION-033/036 ; fixtures test non destinées au local |
| Fait observé dans les fichiers actuels | Importeur, lecteur privé et commande de simulation existent ; aucune liaison SQL du catalogue web | [Importeur](../app/Services/CatalogueArchiveImporter.php), [commande](../app/Console/Commands/ImportCatalogueArchive.php), [lecteur](../app/Services/CatalogueArchiveStore.php), [configuration](../config/catalogue.php) |
| À vérifier avant toute écriture | Identité XAMPP, capacités/session, droits, état local encore vide, absence d'écrivain concurrent, source inchangée, capacité disque et sauvegarde pré-import | Tous les écarts bloquent l'import ; aucune correction automatique |
| Information manquante, non bloquante pour l'archive | Prix/stock commercial actuels, taxes, livraison, paiement et relecture éditoriale des traductions | Pas de règle ajoutée ; cet import ne valide pas la vente ni la publication |

Le dépôt Git est toujours sans fichiers suivis dans l'état inspecté. Préservation par empreintes, pas par supposition d'un commit de sauvegarde. Les anciens documents gardent leurs états historiques ; les clôtures OP-06/07/08 constituent les sources de l'état récent.

## 2. Lot exact et effets proposés

**Seule capsule proposée :** `48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809`, sous `storage/app/private/catalogue-archives/` dans le projet confirmé. Identité par SHA-256 des octets du manifeste, pas par le seul nom du dossier. Aucun nouveau téléchargement, collecte fournisseur, correction ou traduction pendant l'import.

| Données conservées | Attendu du lot réel | Stockage / règle |
|---|---:|---|
| Lot | 1 | Manifestes, enveloppe d'archive, preuve et état technique `verified` après rapprochement |
| Catégories | 65 | Slugs et noms source ; aucune catégorie de fixture |
| Produits | 3 709 | Index et fiches complets ; montants EUR entiers observés, ancien prix null conservé |
| Traductions | 23 878 | 11 939 EN + 11 939 DE, entrées inutilisées comprises ; FR dans la source |
| Caractéristiques | 41 977 | Listes ordonnées, répétitions conservées ; pas de conversion d'unité |
| Variantes | 92 | Documents existants ; aucune offre vendable ni prix hérité inventés |
| Galeries source / overlays | 18 454 / 35 | Ordre, chemins et règles de projection conservés ; pas de BLOB SQL |
| Signalements / fiches à relire | 627 / 479 | Rapport privé conservé dans le lot, aucun signalement considéré résolu |
| Marques vides | 6 | Valeur vide maintenue |
| Capsule complète | 22 239 fichiers ; 3 477 829 369 octets | Médias et documents sur disque vérifiés contre manifeste ; ces volumes ne sont pas la taille d'un dump SQL |

**État nominal local futur :** `archive_lots=1`, `archive_categories=65`, `archive_products=3709`, `archive_translations=23878`, `migrations=4`. Soit 27 653 lignes d'archive, plus quatre lignes techniques du journal, sans modification de ce journal. Les chiffres sont à confronter à la simulation avant émission d'un reçu et aux données réelles après chargement.

**Exclus :** fixtures et scénarios d'écriture négatifs dans le local, synchronisation source, modification commerciale, DDL/migration/GRANT, base ou compte neuf, changement de services/configuration XAMPP/.env, suppression/restauration/purge, sélection du lot par le site, routes/UI, panier/commandes/paiement/clients, publication, communications ou dépenses. OP-10 reste la bascule distincte.

## 3. Existant réutilisable et complément nécessaire

La simulation de `homya:catalogue-import` vérifie la capsule et assemble les lignes sans SQL ni chargement d'accès dédiés. En exécution, la commande exige CLI locale, rôle import, lot attendu et opération OP-09, puis une confirmation interactive. **Cette confirmation seule n'est pas une preuve d'autorisation liée aux empreintes** : l'enveloppe doit vérifier l'accord réel avant de fournir une confirmation au helper.

L'importeur actuel désactive la reconnexion/relecture automatique avant SQL. Il enregistre le parent `prepared` hors transaction, puis verrouille ce parent (`FOR UPDATE`) dans une transaction unique, charge les enfants par messages d'une ligne, rapproche les documents et re-vérifie les fichiers avant passage à `verified` et commit. Il n'utilise ni `INSERT IGNORE`, ni remplacement de données. L'état commercial du produit ne découle pas de cet état technique.

Le lecteur privé `CatalogueArchiveStore` reconstruit le payload depuis un lot vérifié sous rôle reader. Il peut servir à une recette CLI dédiée ; le [test de lecture actuel](../tests/MariaDB/CatalogueArchiveReadRecipeTest.php) est lié exclusivement à OP-06/`homya_test` et ne doit pas être retargeté ou lancé pour OP-09.

**Complément proposé à préparer sans base après accord sur ce plan :**

- `scripts/op09_xampp_import.py` : préparation hors ligne par défaut, reçu propre, verrou exclusif, audits avant/après, sauvegarde locale avant DML, orchestration d'un seul chargement et preuves privées ; aucune émission autonome d'autorisation.
- `scripts/op09_catalogue.php` : helper CLI import/lecture locale soumis au reçu, utilisant les services existants ; aucune connexion, bootstrap ou lecture d'accès à l'inclusion seule. Aucun assouplissement des gardes HTTP.
- `scripts/test_op09_xampp_import.py` et tests PHP dédiés : doubles sans PDO réel pour périmètre, empreintes, refus, conservation, étapes d'erreur et verrou. Pas de nouveaux scénarios destructifs ou fixtures persistées.

Réutiliser les définitions pures d'audit, de DDL/dump et de hachage existantes ; conserver les scripts, reçus et témoins OP-06/07/08. Ne pas réutiliser leur autorité ni substituer globalement leurs cibles. Un changement des services d'archive existants, s'il devenait nécessaire, devra être motivé et testé ; il n'est pas présumé dans ce plan. **Outils OP-09 encore absents à cette préparation ; aucune commande d'exécution prête à lancer n'est annoncée.**

## 4. Séquence proposée sous une future autorisation groupée

| Étape | Opérations bornées | Sortie / arrêt |
|---|---|---|
| 09-A — Autorité | Accord utilisateur distinct enregistré et relu dans le registre central ; plan, programme, lot, artefacts, versions/binaires, témoins et accès par chemins liés au reçu neuf | Aucun contenu secret dans reçu ou arguments ; anciens reçus refusés ; une seule tentative par accord |
| 09-B — Isolation | Verrou d'orchestration commun avec OP-08 sur cette cible, non bloquant ; fenêtre sans autre écrivain autorisé | Refus si verrou occupé ; le verrou local n'empêche pas un client SQL externe, donc dérive contrôlée séparément |
| 09-C — Précontrôles | Audit administratif en lecture seule du seul périmètre HOMYA ; XAMPP 10.4.28/socket/3307/datadir exacts, DDL/journal/droits conformes ; quatre archives encore vides | Si un lot existe, même vérifié : arrêt et examen, sans charger/réutiliser/nettoyer automatiquement cet état trouvé |
| 09-D — Sessions / sources | Paquets global et sessions à 16 777 216, InnoDB, FK/CHECK actifs, collations et modes stricts ; accès import/reader dédiés protégés ; capsule et sources intégralement re-hachées | Refus Homebrew/3306/default/URL/config en cache et mauvais rôle ; aucun réglage global pour contourner une erreur |
| 09-E — Sauvegarde préalable | Nouveau dossier privé unique `storage/app/private/database-backups/op09/<tentative>/` ; export cohérent des cinq seules tables locales encore vides, journal compris, par client natif et métadonnées autorisées | Aucun DML/DDL root, GRANT ou nouvelle base. Dump validé avec dialecte fermé existant, DDL/lignes/empreintes concordants ; instruction sous 16 Mio ; premier DML interdit si échec |
| 09-F — Chargement | Un seul appel à l'importeur existant sous `homya_local_import`, seul lot attendu ; préparation native et transport éprouvés réutilisés | Parent et transition technique autorisés ; 65/3709/23878 INSERT enfants, trois seules colonnes de lot modifiables ; aucun accès DML schema/root |
| 09-G — Rapprochement | Nouveau lecteur CLI `homya_local_reader` ; égalité du digest payload, champs/types/ordre/null/vide/absence, dictionnaires et rapports avec la capsule ; audit administratif complet | Un simple compteur, un statut `verified` ou un écran phpMyAdmin ne suffit pas |
| 09-H — Conservation et clôture | DDL/droits/journal locaux intacts ; lignes/DDL/droits test et restauration intacts ; capsule/source/médias/runtime et anciennes preuves intacts ; rapports et registre de clôture | Statut vérifié sans activation uniquement si tous les critères réussissent ; aucun retry automatique |

**Sauvegarde et limites :** OP-07 a éprouvé une restauration du schéma et des données test, pas une restauration du futur export local OP-09. Le nouvel export pré-import et la capsule rendent l'état antérieur traçable ; ne pas annoncer ce paquet comme restauré tant qu'il ne l'a pas été. Sauvegardes sur le même serveur/disque, perte du disque non éprouvée. Aucune restauration ou suppression incluse pour revenir au local vide. Le site reste sur les fichiers même si l'import échoue.

Le futur audit indépendant reprend une connexion de lecture après chargement, compare les lignes et preuves contre capsule et snapshots protégés, sans réexécuter l'import. Les horaires/durées et espace libre seront mesurés ; aucun engagement de délai ou performance n'est déduit de la recette test.

**Ressources CLI proposées, déjà éprouvées OP-06 :** PHP `-d memory_limit=512M` uniquement pour le processus, sans modification de `php.ini`, plafond de supervision 1 800 s, délai InnoDB 5 s en session. Le délai global doit arrêter uniquement les processus lancés par l'enveloppe, jamais MariaDB ; après interruption, état à inspecter sans rejeu. La simulation de préparation confirme que le PHP CLI par défaut à 128 Mio est insuffisant ; ne pas confondre mémoire PHP et paquet MariaDB de 16 Mio.

## 5. Erreurs, concurrence et non-rejeu

| Situation | Comportement existant / attendu de l'enveloppe |
|---|---|
| Refus de cible, source, reçu, droit, sauvegarde ou verrou | Arrêt avant import ; aucune correction/réutilisation automatique |
| INSERT initial ou démarrage de transaction incertains | Parent éventuellement présent ; inspecter en lecture seule avec preuve distincte, sans supposer un rollback global |
| Échec avant tentative de commit | Rollback des enfants tenté ; transition vers `failed` uniquement si parent propre et concordant ; sinon état à inspecter. Parent initial hors transaction peut rester |
| Commit incertain / connexion perdue | Aucun retry ni rétrogradation d'un succès possible ; relecture indépendante pour déterminer l'état réellement commité |
| Lot `prepared`, `loading`, `failed` ou divergent | Refus d'import/reprise par le service ; aucune purge/UPDATE correctif implicite |
| Lot déjà vérifié | Le service sait rapprocher sans réécrire ; enveloppe OP-09 proposée refuse une cible initialement non vide, même si le service pourrait relire. Une relecture diagnostique ultérieure ne constitue pas une seconde autorisation de chargement |
| Deux écrivains | Verrou d'enveloppe puis verrou parent, cohérence revérifiée ; pas de scénario à deux importeurs injecté dans le local, déjà éprouvé OP-06 |
| Audit final en erreur | État partiel ou résultat à inspecter conservé ; aucune activation, suppression, rollback destructif ni restauration |

Le gel de lot vérifié repose sur les écrivains coopérants et les gardes, pas uniquement sur les droits SQL. La sauvegarde pré-import ne justifie pas une promesse de retour arrière automatique.

## 6. Critères d'acceptation et preuves à produire

Priorité : préalables pour 01–03 ; tous les critères sont requis pour clôturer le futur import. Traçabilité aux [IMP-04/06/07/08/09/10](PLAN-IMPORT-XAMPP.md), TECH-03/07/09 et CON-001 à CON-008 du [plan physique](PLAN-TECHNIQUE-ARCHIVE-XAMPP.md) et du [modèle logique](MODELE-LOGIQUE-ARCHIVE-HOMYA.md).

| ID stable | Critère observable | Preuve future |
|---|---|---|
| OP09-01 | Accord propre à OP-09/local/lot exact, empreintes et une tentative ; refus hors CLI locale ou reçu absent/invalide | Accord/reçu privés, refus testés sans base, lecture du registre |
| OP09-02 | Cible XAMPP et cinq tables conformes ; archives initialement vides, journal exact et droits inchangés | Audit natif complet avant SQL d'import et connexions dédiées |
| OP09-03 | Capsule entière concordante ; nouveau dump pré-import validé et capacité/messages compatibles | Manifeste/médias, simulation, dump/DDL/lignes, SHA-256, octets et relevé d'espace |
| OP09-04 | Seul lot réel vérifié, 1/65/3709/23878 lignes d'archive, journal quatre lignes inchangées | Import sous rôle import, relecture reader et audit indépendant |
| OP09-05 | Identité de tous documents et listes, montants EUR entiers, 627 signalements/479 fiches, marques vides et dictionnaires conservés | Payload/digest, comparaisons exhaustives ; aucune résolution commerciale implicite |
| OP09-06 | Aucune mutation test/restauration, comptes/droits/DDL/journal/médias/runtime/provenance privée | Snapshots avant/après, anciennes preuves et empreintes inchangées |
| OP09-07 | Incertitude ou dérive = arrêt avec preuves, jamais reconnexion/rejeu/purge/reprise implicite | Tests sans base des gardes et frontières ; aucun échec artificiel injecté dans le local |
| OP09-08 | Site toujours sur fichiers, aucune vente/publication ni exposition des payloads privés | Empreintes config/provider/routes/catalogue/.env, inspection des liaisons et statut final non activé |

Pas de recette navigateur ou build annoncée pour une préparation documentaire sans changement du site. La parité du parcours public, SEO et états d'erreur web sous lecture SQL appartiendra à OP-10 ; le rapprochement privé OP-09 ne remplace pas cette recette.

## 7. Préparation réellement effectuée et décisions à grouper

Inspection du code import/lecture/gardes/configuration et consultation du registre central ; relecture par empreintes de la clôture OP-08 et des témoins historiques. Aucune requête SQL, chargement d'accès dédié, modification de script applicatif/migration, service ou configuration pendant cette phase. La simulation existante sans SQL et les contrôles documentaires ont leurs résultats dans les preuves ci-dessous ; un résultat natif futur reste à vérifier.

- [Inspection et témoins avant préparation](../output/qa-import/op09-preparation-inspection.json).
- [Sortie de simulation hors SQL](../output/qa-import/op09-simulation-sans-sql.txt).
- [Relance de simulation avec plafond CLI 512 Mio](../output/qa-import/op09-simulation-512m-sans-sql.txt) : mêmes données et commande existante, aucun changement de configuration persistante.

**Incident de préparation conservé :** premier lancement avec plafond PHP par défaut 128 Mio arrêté (code 255, mémoire épuisée), sans SQL. Le lancement corrigé réutilise le plafond processus 512 Mio déjà documenté OP-04/06, sans changement de code. Une réussite de la simulation corrigée doit être constatée dans sa preuve, pas déduite de cette correction.

**Résultat réel de la relance :** code 0, `IMPORT_SIMULATION_NO_SQL`, durée observée 74,243 s. Lot et tous les compteurs du §2 concordent ; digest payload `49811854d0b4a8da4e3cb7b3e66c99ed2f43ae9f3a71875c29073fa636ea37e1`, identique au résultat historique OP-04. Manifeste 3 550 149 octets, document d'archive 4 730 205 octets ; budget conservateur INSERT lot 16 626 698 octets, sous 16 777 216 avec seulement 150 518 octets de marge. Le message réel/transport a été éprouvé OP-06 ; la présente simulation ne refait aucun test PDO. Tout changement de capsule ou de budget impose un nouvel examen, jamais une augmentation implicite de la limite serveur. Aucun pic mémoire nouveau n'a été mesuré.

[Lancement et incident](../output/qa-import/op09-simulation-lancement.json), [synthèse de préparation et conservation](../output/qa-import/op09-preparation-verification.json). Les critères OP09-01 à OP09-08 sont des critères d'exécution future, **non satisfaits par cette simulation seule**. Seuls le plan, la simulation hors SQL et les témoins documentaires sont préparés à cette étape ; aucune nouvelle connexion ni sauvegarde locale créée.

**Décisions nécessaires regroupées :** confirmer le seul lot réel proposé, la cible local vide connue, la sauvegarde préalable des cinq tables, l'absence de DDL/GRANT/import test/bascule et le stop sans reprise implicite ; autoriser la préparation de l'enveloppe/helper et de leurs tests sans base. Ces outils et preuves hors base devront ensuite être présentés avec leurs empreintes pour **une autorisation distincte groupée d'exécution OP-09** couvrant sauvegarde, chargement et audits. Aucun accord d'exécution ne peut être déduit de la validation du présent document.

Pas de nouvelle question sur les langues, données métier, identité ou design déjà confirmés ; les inconnues commerciales restent ouvertes pour la vente. Aucune recherche externe nouvelle nécessaire pour ce plan : il réutilise le runtime, le transport et le dialecte natif déjà vérifiés et leurs références OP-04/06/07/08 ; toute incompatibilité nouvelle devra être isolée avant recherche/correction.

## 8. Outils livrés après accord — 08/10/2026

Accord « j autorise et je valide » reçu en réponse à la validation du plan et aux outils/tests sans base, consigné puis relu **DECISION-040**. Il exclut tout SQL réel, même diagnostic/export, ainsi que chargement d'accès privés réels, création/migration/import et bascule. [Preuve de persistance](../output/qa-import/op09-outils-enregistrement-accord.json). Les formulations de proposition des sections précédentes restent historiques ; leur périmètre est maintenant validé, leur exécution ne l'est pas.

### Fichiers et mécanismes effectifs

| Fichier nouveau | Livré / contrôlé hors base |
|---|---|
| [op09_xampp_import.py](../scripts/op09_xampp_import.py) | Préparation hors ligne par défaut ; reçu d'exécution propre exigeant un futur accord au-delà de DECISION-040, lot/cible exacts, registre relu et empreintes. Audits réutilisant des définitions pures, client root limité SELECT/SHOW CREATE sans multi-instructions ou export SQL par SELECT. Verrou commun `.op08.lock`, marqueur exclusif consommant une tentative par décision avant SQL, tickets de helper à usage unique liés au run/reçu/PID. |
| [op09_catalogue.php](../scripts/op09_catalogue.php) | Aucun bootstrap/PDO/accès à l'inclusion ou par défaut ; reçu et ticket vérifiés avant autoload, CLI locale sans cache, deux seuls rôles locaux. Importeur actuel conservé, cible vide obligatoire ; relecture indépendante par `CatalogueArchiveStore`, digest/compteurs attendus et aucune activation. Pas de confirmation interactive assimilée à l'autorité. |
| [test_op09_xampp_import.py](../scripts/test_op09_xampp_import.py) | 23 tests dédiés : refus de portée/reçu/liens/dérive, SQL root en lecture seule, audit avant/après, dump et journal exacts, espace insuffisant, tickets, verrou, non-rejeu, erreurs de chaque frontière et timeout de ses seuls processus. SQL/processus d'export sont des doubles. |
| [Op09CataloguePreparationTest.php](../tests/Feature/Op09CataloguePreparationTest.php) | Sept tests sur vrai Query Builder avec PDO interdit : import local synthétique, rapprochement lecteur, refus du local déjà rempli/rôle/cible, erreur enfant, commit incertain, données corrompues à compteurs égaux, CLI invalide/défaut. |

Aucun changement des services d'archive, commandes historiques, tests historiques, migrations, source active ou site. Le wrapper ne retargete aucun workflow historique ; la substitution en mémoire des fonctions de requêtes d'audit est bornée à un contexte puis restaurée, testée même sur erreur. Les programmes/reçus historiques restent intacts.

L'accord humain d'exécution futur devra consigner aussi le SHA-256 du programme final ; le reçu est comparé à ce programme approuvé (artefacts, binaires, plan, cible et lot). Une mise à jour des seules empreintes du reçu ne peut donc pas autoriser un programme modifié : refus testé séparément. Le programme est un descriptif hors base, jamais un accord autonome.

**Sauvegarde future précisément bornée :** client natif XAMPP, cinq seules tables `homya_local` encore vides et quatre lignes journal ; flags cohérents du dialecte OP-07, fichier ≤1 Mio et réserve disque de 256 Mio en plus du plafond. Nouveau dossier privé 0700 et dump figé 0400 après validation. Comparaison des DDL, compteurs et des quatre valeurs exactes du journal, puis nouveau précontrôle avant DML. Ces bornes techniques n'autorisent pas l'export ; aucun nouveau dump réel produit. Restauration de ce futur paquet non éprouvée, aucune restauration/purge incluse.

**Erreurs/supervision :** PHP512M par processus, délai InnoDB5s en session, plafond global1800s appliqué aux appels natifs et vérifications ; timeout du seul groupe PHP/export lancé par l'outil, jamais du service MariaDB. Tout écart laisse un rapport privé, l'état trouvé et le marqueur consommé. Aucun retry, second appel d'import ou nettoyage automatique. Nouvelle tentative après arrêt = nouvelle autorisation. Une perte de connexion peut laisser un parent ou un commit à inspecter ; aucun succès n'est déduit du code retour seul.

### Vérifications réellement exécutées

- **111 tests Python réussis**, dont 23 nouveaux OP-09 et les tests historiques des outils OP-05/06/07/08. [Journal final](../output/qa-import/op09-outils-python-tests-final.txt).
- **69 tests PHP / 338 assertions réussis**, dont sept nouveaux OP-09 (58 assertions). Doubles et sockets Unix synthétiques temporaires uniquement ; la création de ces sockets a nécessité une exécution hors sandbox. [JUnit de régression](../output/qa-import/op09-outils-regression-php.xml), [JUnit dédié](../output/qa-import/op09-outils-php-tests-final.xml).
- Syntaxe Python via AST, syntaxe PHP et Pint sur les deux fichiers PHP nouveaux valides ; refus CLI sans reçu constaté avant bootstrap/accès. Programme hors connexion préparé, sans SQL ni émission d'autorisation.
- Les **183 témoins existants** restent identiques. Aucun code applicatif, runtime, configuration XAMPP, accès existant ou témoin historique modifié ; seuls les quatre fichiers nouveaux, ce document et la note d'environnement constituent les changements fonctionnels/documentaires de cette étape, hors rapports et registre.

Les premiers essais de tests, conservés dans les sorties, ont signalé des fixtures incomplètes (référence d'audit, DDL multiligne, alias SQL `aggregate` avec accents graves) et l'interdiction sandbox des sockets temporaires ; fixtures corrigées puis contrôles ci-dessus réussis. Aucun SQL réel utilisé pour corriger ces écarts. La simulation réelle de 74,243s du §7 demeure la preuve précédente : non rejouée, importeur/payload/capsule inchangés. Aucun build ou navigateur relancé pour ces seuls outils CLI ; aucune épreuve native de l'export local ou du helper annoncée.

[Programme final hors base](../output/qa-import/op09-outils-programme.json), [synthèse de conservation et tests](../output/qa-import/op09-outils-synthese-verification.json). Les critères d'import OP09-01..08 restent à éprouver dans l'exécution autorisée ; ces tests ne constituent ni une connexion MariaDB ni une validation commerciale.

### Autorisation d'exécution groupée à présenter

Périmètre prêt à soumettre après clôture des preuves : précontrôles natifs XAMPP et habilitations existantes, sauvegarde locale des cinq tables sans restauration, re-hachage capsule et originaux, **un seul import du lot réel** dans `homya_local`, lecture indépendante et audits/conservation finaux. Aucun GRANT, DDL, nouveau compte/base, import de fixture, bascule, production ou dépense.

La future commande unique, **inutilisable avant émission séparée d'un reçu sous accord d'exécution**, sera :

```sh
/usr/bin/python3 scripts/op09_xampp_import.py --execute --receipt=/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/op09-execution-recu.json
```

**Aucun accord ni reçu d'exécution émis actuellement.** Le mode par défaut du script ne connecte pas de base. Le schéma et les sessions devront être revalidés sous l'accord d'exécution, pas présumés à partir d'OP-08. Le site continue de lire les fichiers ; OP-10 reste la sélection/parité web et bascule à préparer/autoriser séparément.


## 9. Exécution autorisée et clôture native — 08/10/2026

Accord utilisateur « J'autorise » reçu pour les précontrôles XAMPP, sauvegarde des cinq tables, import unique dans `homya_local`, rapprochement et conservation ; consigné/relu **DECISION-041** avant SQL. [Autorisation enregistrée](../output/qa-import/op09-execution-enregistrement-accord.json), [reçu lié aux empreintes](../output/qa-import/op09-execution-recu.json), [plan v0.2 figé](../output/qa-import/op09-plan-execution-valide-v02.md). Les états des §1–8 sont historiques ; ils ne décrivent plus le résultat actuel.

**OP-09 TERMINÉE dans son périmètre**, statut `LOCAL_ARCHIVE_VERIFIED_NOT_ACTIVATED`. Une seule invocation d'import, un seul lot réel vérifié : **1 lot, 65 catégories, 3 709 produits, 23 878 traductions**. Les quatre lignes du journal restent identiques. Relecture PDO sous le compte reader distinct et audit natif indépendant concordants ; digest exhaustif du payload `49811854d0b4a8da4e3cb7b3e66c99ed2f43ae9f3a71875c29073fa636ea37e1` inchangé. 41 977 caractéristiques, 92 variantes, 18 454 associations de galerie, 35 overlays, 627 signalements/479 fiches et six marques vides conservés. Cet import ne résout aucune anomalie métier et n'établit ni prix/stock commercial actuels ni qualité éditoriale définitive.

### Contrôles et critères constatés

| Critères | Résultat natif observé / preuve |
|---|---|
| OP09-01/02 | Autorité propre relue, reçu/programme/artefacts/binaires conformes ; verrou et tickets uniques ; cible XAMPP MariaDB10.4.28/socket/3307, paquet16Mio, capacités et droits conformes. Quatre archives vides et journal exact avant DML. |
| OP09-03 | Capsule et originaux entièrement re-hachés avant et après par l'enveloppe : chacun22 239 fichiers/3 477 829 369 octets, manifeste exact. Export fermé des cinq tables avant import ; comparaison DDL/compteurs/valeurs du journal ; espace/plafonds contrôlés. |
| OP09-04/05 | Import dédié et relecture indépendante du payload complets ; compteurs finaux et lot vérifié confirmés par nouveau contrôle natif SELECT/SHOW CREATE, hashes typés des cinq tables locales enregistrés. Identité documents/listes/montants/dictionnaires/signalements conservée. |
| OP09-06/08 | DDL/comptes/droits/journal inchangés ; hashes complets test/restauration identiques avant/après et au témoin OP-08. 183 témoins code/runtime/.env/my.cnf et historiques inchangés ; site toujours sur fichiers. |
| OP09-07 | Une tentative consommée et aucune reprise ; aucun échec injecté dans le local. Gardes/erreurs éprouvées précédemment par tests hors base, pas présentées comme scénarios d'échec natifs rejoués. |

[Run d'exécution](../output/qa-import/op09-run-20261008T155920Z-3cca85e0-e2d0-41c4-bef7-13cd359934f9/status.json), [audit indépendant](../output/qa-import/op09-execution-controle-independant.json), [synthèse vérifiée](../output/qa-import/op09-execution-synthese-verification.json). Les preuves natives privées sont0600, le dossier run0700. Le contrôle indépendant a renouvelé SQL/droits/DDL/journal/hashes test/copie/local et validation de l'export ; il **n'a pas refait** le hachage complet des médias, déjà réalisé avant/après par l'enveloppe achevée. Aucun code modifié pendant cette exécution ; les111 tests Python et69 tests PHP/338 assertions antérieurs ne sont pas annoncés comme rejoués. Aucun build/contrôle navigateur requis pour cet import privé ; parité web à traiter OP-10.

### Sauvegarde et limites

[Paquet pré-import](../storage/app/private/database-backups/op09/20261008T160019Z-15576b00-f2e6-49d1-b342-6941584cd71b/verification.json) : dump `local-before.sql` **8 156 octets**, SHA-256 `dad5e55db38aea4d313224af68007e296246b0584f5b266725171a00839b162c`. Cinq tables, archives vides et quatre lignes exactes de journal ; DDL/compteurs/parser/hachage relus indépendamment. Dossier0700, dump0400 après validation. **Restauration de ce paquet non testée**, sauvegarde sur le même disque ; aucune restauration exécutée ni promesse de retour arrière. Le SQL n'inclut pas les médias.

Aucun nouveau GRANT/compte/base/table, migration, import de fixture, modification service/configuration XAMPP/.env/code du site, activation ou action externe. Les anomalies et inconnues commerciales restent ouvertes pour une future vente. **Prochaine étape : préparer OP-10**, sélection du lecteur SQL et recette de parité publique/états d'erreur ; la bascule conservera son autorisation distincte. Le présent accord ne l'autorise pas.
