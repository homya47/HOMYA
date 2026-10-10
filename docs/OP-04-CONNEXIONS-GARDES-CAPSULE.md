# HOMYA — OP-04 : préparation du premier incrément de développement

Version de référence 0.1 — 07/10/2026. **PREMIER INCRÉMENT AUTORISÉ DANS DECISION-027 ; RÉSULTATS AU §9.** Les sections 1–8 conservent le plan et les observations de préparation ; leurs mentions d'accord en attente décrivent cette étape historique. Le plan exact soumis est conservé dans [op04-plan-autorise.md](../output/qa-import/op04-plan-autorise.md).

Demande initiale : préparer les connexions dédiées, gardes et capsule catalogue, puis présenter l'autorisation de développement. Elle autorisait ce document et l'inspection, pas l'implémentation. Le [registre central](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md) a été consulté : DECISION-019 valide la conception ; DECISION-025/026 clôturent comptes et droits. Aucun accord OP-04 n'était enregistré à la préparation ; aucun registre parallèle créé.

Références : [plan technique §6/§10](PLAN-TECHNIQUE-ARCHIVE-XAMPP.md), [MLD v0.2 et contrat de capsule](MODELE-LOGIQUE-ARCHIVE-HOMYA.md), [clôture OP-03](OP-03-COMPTES-DROITS-CONNEXION.md). Traçabilité : TECH-03/05/07, IMP-04, CON-001/006 et critères OP04-AC ci-dessous.

## 1. Observations et périmètre proposé

| Élément | Fait observé / source | Conséquence |
|---|---|---|
| Socle | Laravel 13.35.0, PHP CLI 8.5.11/pdo_mysql ; conventions Blade et services existants | Réutiliser le socle ; pas de dépendance Composer/npm supplémentaire proposée |
| Catalogue | config/catalogue.php et app/Services/Catalogue.php relus : index/fiches/dictionnaires/overlay sur fichiers ; filtres/tri et masquages dans la projection | Conserver cette lecture et tous les parcours FR/EN/DE |
| Connexions | config/database.php : seule connexion mariadb générique utilisée ; pas de connexions dédiées ni de chargeur d'accès d'archive | Six connexions nommées à préparer, sans utilisation automatique |
| Diagnostic | CheckLocalDatabase.php exige local/mariadb/DB_URL absent/socket exact/identifiant présent ; compare version MariaDB et socket | Étendre les gardes et distinguer authentification sans base / accès au schéma |
| Configuration privée | Dernier profil filtré OP-03 : identifiants runtime absents, port déclaré différent de 3307, collation utf8mb4_unicode_ci | Ne pas modifier .env dans cet incrément ; tout refus futur doit rester explicite |
| MariaDB | Dernier contrôle OP-03b ID116 : six comptes, 20 seuls droits CREATE/ALTER de table aux deux comptes schema ; deux bases vides | Lecture runtime et DML indisponibles ; pas de nouveau SQL dans cette préparation |
| Tests | phpunit.xml cible homya_test_not_created ; tests existants interdisent la connexion sur les parcours sans base | Conserver cette protection et tester les nouvelles gardes avec doubles/fixtures |
| Migrations | database/migrations contient users/cache/jobs du squelette Laravel ; aucun répertoire catalogue observé | Ne pas exécuter une migration générale, même pretend ; ne pas toucher ces fichiers |

**Décision nécessaire — périmètre du premier incrément OP-04 :** développer uniquement connexions, gardes, préparation/vérification de capsule et tests sans base ; produire une copie privée réelle après recontrôle. Le §6 du plan technique prévoit aussi migrations catalogue, importeur et lecteur SQL : ils restent prévus mais sont différés à une préparation de code ultérieure, avant toute demande OP-05. Cet incrément ne terminera donc pas à lui seul l'ensemble des préparatifs de migration.

**Exclus :** code/exécution de migration, importeur SQL et CatalogueArchiveStore dans ce premier incrément ; tout SQL réel, y compris diagnostic ; droits, nouveaux comptes, réglage/service XAMPP, édition .env applicative ou des accès existants, bascule, données commerciales corrigées, réseau, paiement, tracking ou déploiement. Aucun prix/stock/caractéristique/traduction/photo n'est modifié.

## 2. Capsule : périmètre mesuré, sans copie créée

L'[inspection du 07/10 à 22:52 UTC](../output/qa-import/op04-inspection-sources-medias.json) a lu et haché tous les fichiers concernés ; les 3 714 empreintes de sources concordent avec la revue, les clés JSON sont uniques et les SHA-256 déclarés des médias concordent avec leurs octets. Les sources ont été recontrôlées après lecture des médias. Ce contrôle n'est pas un instantané figé ni une nouvelle validation métier.

| Ensemble fermé | Nombre | Taille observée |
|---|---:|---:|
| Index, inventaire, overlay, 3 709 fiches, dictionnaires EN/DE | 3 714 | 17 397 152 octets |
| Médias uniques référencés : 18 488 publics + 34 originaux | 18 522 | 3 459 408 236 octets |
| Rapports de revue synthese.json, anomalies-detaillees.json, empreintes-sources.json | 3 | 1 023 981 octets |
| Total des fichiers de contenu candidat | **22 239** | **3 477 829 369 octets (~3,48 Go)** |

Manifeste déterministe candidat calculé **en mémoire seulement** : 3 550 149 octets ; SHA-256 `48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809`. Aucun fichier manifeste ni répertoire capsule créé. Cette clé devra être recalculée et confirmée lors de la préparation autorisée, pas reprise aveuglément.

Espace libre observé : 65 536 000 000 octets ; parent catalogue-archives absent et future destination ignorée par Git. Recontrôler l'espace avant copie, y compris manifeste, preuves et fichiers temporaires ; cela ne garantit ni quota disponible ni succès d'E/S. Aucun délai de copie annoncé.

**Résolution vérifiée :** image.local_url `/media/catalogue/…` correspond à `public/media/catalogue/…`. Les valeurs overlay.original_file `media-originals/…` sont relatives à `storage/app/catalogue/`, conformément à scripts/prepare_catalogue_photos.py. Elles ne se résolvent pas depuis la racine du projet. Les URLs de provenance restent des valeurs privées archivées ; aucune récupération réseau implicite.

**Conservation validée :** 3 709 produits, 65 catégories, 41 977 caractéristiques, 92 variantes, 18 454 associations de galerie et 627 signalements. Les photos masquées/trop petites et données incomplètes restent dans la capsule ; la projection publique actuelle reste inchangée. Une empreinte valide ne confirme ni justesse métier, ni traduction humaine, ni admissibilité Google.

## 3. Connexions et gardes à implémenter après accord

| Connexion nommée proposée | Identité attendue @localhost | Schéma associé |
|---|---|---|
| homya_test_reader | homya_test_reader | homya_test |
| homya_test_import | homya_test_import | homya_test |
| homya_test_schema | homya_test_schema | homya_test |
| homya_local_reader | homya_local_reader | homya_local |
| homya_local_import | homya_local_import | homya_local |
| homya_local_schema | homya_local_schema | homya_local |

Configuration commune fixe : mariadb, socket XAMPP exact, port déclaré 3307, utf8mb4/utf8mb4_bin, strict actif, DB_URL absent, aucune connexion persistante ni transport TCP de repli. Pas de compte root/admin ni mot de passe réel dans config/*.php/.env.example. Ces connexions restent inutilisées par les pages.

**Chargement d'accès proposé :** seulement pour une commande CLI explicite, un fichier .env dédié sélectionné par chemin, dans storage/app/private/database-access/ ; nom correspondant au rôle attendu, propriétaire utilisateur, dossier0700/fichier0600, absence de lien symbolique/ACL élargie. Charger le seul accès nécessaire en mémoire, sans exporter les secrets ni modifier .env ou le cache de configuration. Aucun chargement automatique des six accès au démarrage web. Les tests de ce lot utilisent uniquement des fichiers synthétiques ; aucun accès réel nécessaire pour la copie capsule.

**Gardes avant connexion :** environnement local pour les opérations réelles, commande/connexion dans l'allowlist, nom de base et utilisateur liés au rôle, driver/url/socket/port/charset/collation conformes, socket présent de type socket, secret non vide et chemin privé valide. Refus avant PDO sur un écart ; pas de repli root/Homebrew ni d'élargissement de droits.

**Gardes après connexion, à tester avec doubles dans cet incrément :** identité CURRENT_USER, MariaDB/version locale attendue, socket/datadir/port XAMPP, charset/collation/modes stricts, capacité global/session pour les documents. Elles ne certifient pas seules les privilèges effectifs : l'audit administratif d'OP-03 reste une preuve distincte, à renouveler lors des opérations SQL suivantes.

**Diagnostic proposé :** conserver homya:db-check et ses refus actuels ; ajouter sélection explicite d'une connexion et d'un fichier d'accès, plus mode d'authentification sans base. Dans ce dernier mode seulement, database est vide en mémoire, après validation de l'association rôle/schéma. Distinguer accès authentifié, schéma accessible et données disponibles. Un reader à USAGE ne doit pas être déclaré prêt à lire le catalogue. Aucun diagnostic réel exécuté pendant OP-04 ; tests avec doubles uniquement.

Les paramètres conseillés DB_PORT/DB_CHARSET/DB_COLLATION seront documentés dans .env.example ; la configuration privée actuelle reste inchangée. L'activation runtime lecteur dépend des tables et droits SELECT autorisés en OP-08, puis de la bascule OP-10.

## 4. Préparation et vérification de capsule

Contrat conservé du MLD : format_version=1 puis files ; entrées path/bytes/sha256 dans cet ordre ; chemins relatifs `/`, tri par octets UTF-8, aucune entrée dupliquée. JSON compact UTF-8 sans BOM ni saut final ; chaînes/entiers du manifeste sans conversion implicite. lot_key = SHA-256 des octets exacts du manifeste, sans sa propre empreinte, date de préparation ou état SQL.

Périmètre fermé : fichiers de la revue d'empreintes, médias uniques référencés et trois rapports d'origine uniquement. Ne pas incorporer les nouveaux rapports OP-03/OP-04, secrets, code, caches, HTML de provenance non prévu ou médias non référencés. Les octets de chaque source sont copiés, pas réencodés. Valider JSON/UTF-8/clés uniques avant admission des documents ; conserver tableaux, types, chaînes vides et absences, sans corriger les anomalies métier.

Flux proposé :

```mermaid
flowchart LR
  A[Sources fermées et médias locaux] --> B[Validation et empreintes]
  B --> C[Manifeste déterministe en mémoire]
  C --> D[Copie dans une zone privée neuve]
  D --> E[Comparaison sources copies sources]
  E --> F[Publication locale de la capsule privée complète]
```

1. Simulation par défaut : valider liste, chemins, JSON, hashes et espace ; aucun SQL, téléchargement ou copie de capsule. Séparer clairement simulation et option explicite de copie.
2. Pour la copie autorisée, créer un staging neuf et privé sous storage/app/private/catalogue-archives/, sur le même volume que la destination. Refuser liens symboliques, traversées, chemins absolus, doublons/conflits fichier-répertoire et fichier non attendu. Aucune destination existante réutilisée ou supprimée.
3. Copie en flux des fichiers vers `files/<chemin relatif au projet>` ; dossier0700, fichiers privés. Vérifier chaque source avant/après lecture et chaque copie avec taille/SHA-256, puis recontrôler l'ensemble des sources et copies. Une modification/destination trouvée interrompt la préparation.
4. Écrire manifest.json exact ; contrôler de nouveau son hash/format. Recommandation : copies et manifeste0400 après vérification, dossiers0700, sans modifier les permissions des originaux. Ces modes protègent contre les écritures accidentelles, pas contre leur propriétaire.
5. Promouvoir le staging vers `storage/app/private/catalogue-archives/<lot_key>/` seulement après succès complet, sans remplacement. Prévoir une exclusion mutuelle locale entre préparations pour empêcher une collision de promotion ; ne pas utiliser un verrou MariaDB dans ce lot. Les rapports de préparation restent hors identité du manifeste, sans déclarer un état verified SQL.
6. Au premier échec : conserver staging incomplet privé et rapport expurgé ; aucun nettoyage d'une capsule existante, aucun renommage en capsule complète, aucune activation. Une commande de vérification doit pouvoir relire une capsule existante sans la modifier.

Les quelque 3,48 Go sont des copies sur disque, pas un paquet SQL ni des images BLOB. Le manifeste candidat fait 3,55 Mo, mais la taille du futur archive_document et des messages combinés doit encore être contrôlée avant transport PDO ; aucun transport de ces documents validé ici. Les preuves SQL ultérieures ne changent pas lot_key.

## 5. Fichiers concernés et limites

| Fichier prévu | Travail du premier incrément |
|---|---|
| config/database.php | Six connexions nommées sans secret réel ; conserver connexion/default actuels et les autres configurations |
| config/catalogue.php, config/homya.php | Chemins privés bornés, rapports fermés et constantes de cible locale ; ne pas modifier les chemins du lecteur fichiers |
| .env.example | Documenter paramètres non sensibles et rôle runtime futur ; aucun accès rempli, aucun .env privé édité |
| app/Services/ArchiveDatabaseGuard.php — nouveau | Validation rôle/cible/transport/accès privé et diagnostic expurgé ; aucun GRANT/DDL/DML |
| app/Services/CatalogueCapsule.php — nouveau | Résolution des références, validation stricte, manifeste, copie et vérification ; aucune dépendance SQL/réseau |
| app/Console/Commands/CheckLocalDatabase.php | Modes explicites et gardes ; préserver signature de base/refus existants |
| app/Console/Commands/PrepareCatalogueCapsule.php — nouveau | Simulation par défaut ; copie explicite après contrôles ; sorties privées/expurgées |
| Tests Unit/Feature dédiés, tests existants | Fixtures temporaires synthétiques, doubles de connexion ; régression du socle et catalogue sans base |
| docs/ENVIRONNEMENT-LOCAL.md, présente fiche, output/qa-import/ | Utilisation, mesures et résultats réellement exécutés ; preuves sans secret |

La validation JSON à clés uniques est indispensable : json_decode avec JSON_THROW_ON_ERROR seul conserve la dernière valeur d'une clé dupliquée dans l'essai PHP8.5.11. Prévoir un contrôle lexical des clés décodées, testé avec échappements équivalents et imbrications, intégré au service ou à un petit lecteur dédié si sa réutilisation le justifie. Aucun parseur/runtime supplémentaire imposé.

Ne pas modifier Catalogue.php, routes, contrôleurs, Blade, styles, prix/photos/dictionnaires, migrations du squelette ou dépendances. Le futur importeur, les migrations catalogue, la lecture SQL, le verrou transactionnel de lot et les tests MariaDB restent à développer/revoir dans un périmètre ultérieur, sans les annoncer validés par ces tests sans base.

## 6. Critères d'acceptation de développement

Tous les critères ci-dessous sont **À vérifier après autorisation** ; ils ne sont pas présentés comme tests exécutés de code inexistant.

| ID | Critère observable | Dépendance |
|---|---|---|
| OP04-AC01 | Six associations connexion/utilisateur/base exactes ; pas de secret/default privilégié, ni connexion des pages | Configurations et OP-03 |
| OP04-AC02 | Avant PDO, tests refusent root/autre rôle/base/socket/URL/port, chemin d'accès extérieur, lien, ACL/mode inadéquat et secret absent | Guard et fixtures ; aucun SQL réel |
| OP04-AC03 | Doubles refusent identité/cible/session/capacité inattendues ; distinction authentification sans base / schéma inaccessible explicite | Diagnostic ; recette live différée |
| OP04-AC04 | Liste fermée : sources, index/galeries/overlays et originaux correctement résolus ; aucune source réseau/secret ajouté | Contrat MLD et revue d'empreintes |
| OP04-AC05 | Manifestes identiques pour les mêmes octets, même après changement de date/ordre de découverte ; tri UTF-8 et ordre de clés exacts, sans BOM/saut final | Tests canoniques indépendants, Unicode/U+2028 et chaînes numériques |
| OP04-AC06 | JSON malformé, UTF-8 invalide, doublons de clés décodées, chemin sortant, fichier absent/muté, collision ou espace/E/S insuffisants interrompent sans écraser | Fixtures et contrôle de copie |
| OP04-AC07 | Sur sources réelles recontrôlées, capsule neuve complète ; tailles/SHA-256 source/copie/source identiques pour tous les fichiers ; aucun original modifié | Autorisation incluant copie privée ~3,48 Go |
| OP04-AC08 | Vérification détecte capsule altérée/incomplète ; deux préparations ne remplacent pas la même destination ; échec reste privé et non consommable | Fixtures et contrôle de promotion |
| OP04-AC09 | CLI/erreurs/preuves n'exposent pas de secret de fixture ; accès non chargés dans le web/cache ; sources privées non exposées | Tests et inspection des sorties |
| OP04-AC10 | Suite PHP sans base et contrôles pertinents existants passent ; FR/EN/DE/catalogue/masquages inchangés ; aucun SQL, migration/import/bascule | Régression, témoins et compte rendu |

Contrôles futurs : syntaxe PHP8.5, style ciblé, PHPUnit direct sans modification du cache de configuration par un script Composer ; tests de fichiers petits et panne/collision/concurrence sur fixtures ; simulation puis copie/vérification réelle si incluse dans l'accord. Aucun build frontend demandé puisque aucun asset modifié. Pas de test avec RefreshDatabase ni migration générale/pretend ; configuration de recette réelle à préparer ultérieurement.

## 7. Sources, inconnues et risque résiduel

Question externe ciblée examinée avant rédaction : quelles options PHP permettent les octets JSON du contrat, notamment Unicode et séparateurs de ligne, sans substitution silencieuse ?

| Source / consultation | Portée / fait étayé | Limite |
|---|---|---|
| [PHP — Predefined JSON Constants](https://www.php.net/manual/en/json.constants.php), 07/10/2026 | Options Unicode/slashes/line terminators et exceptions ; options de sortie partielle/substitution à éviter pour ce contrat | N'établit pas la détection des doublons ; essai local distinct décrit au §5 |
| [PHP — json_encode](https://www.php.net/manual/en/function.json-encode.php), 07/10/2026 | Encodage et options de sérialisation UTF-8 | Le contrat déterministe doit être testé, pas déduit des seules options |
| Vendor installé : DatabaseManager.php, ConfigurationUrlParser.php, MySqlConnector.php, MariaDbConnector.php | Lecture locale : URL peut influencer la configuration, socket prioritaire, sélection de base et paramètres de session | Aucun nouveau SELECT applicatif ni privilège testé dans cette préparation |
| Scripts de revue/photos et MLD/plan technique | Base de résolution des originaux, clés JSON uniques et périmètre fermé/identité capsule | Données métier et traductions signalées non résolues par copie/hachage |

**Décisions validées :** conception MLD/physique, conservation, six comptes et droits ciblés. **Recommandations :** périmètre du premier incrément, connexions nommées, modes privés/collision et stratégie de tests ci-dessus. **Décision nécessaire :** autoriser ce développement et la copie privée réelle, sans autoriser le reste d'OP-04 ni OP-05 à OP-10.

**Inconnues non bloquantes pour cette préparation :** temps/coût mémoire de préparation à mesurer, taille finale des messages PDO et efficacité des futurs gardes sur connexion réelle. **Bloquant avant exécution :** accord de développement/copie non reçu. Toute divergence d'empreinte, capacité ou destination au recontrôle bloque la copie, sans correction silencieuse des sources.

## 8. Accord précis à présenter

Autoriser le **premier incrément OP-04** décrit ici : code des connexions/gardes/diagnostic/capsule et tests sans base ; simulation puis création/vérification d'une capsule privée neuve de tous les fichiers référencés (~3,48 Go au contrôle), originaux inchangés. Pas de .env applicatif édité, accès privé existant modifié, SQL réel, GRANT, migration/import/bascule, code de migration/importeur/lecteur SQL, dépendance externe, réseau ou déploiement. Arrêt au premier écart ; staging incomplet conservé privé et signalé.

L'accord sera enregistré et relu dans le registre central avant développement. Cette séparation vient d'AGENTS.md et du plan validé ; aucun accord de conception, de comptes ou de droits ne vaut autorisation de ce code/cette copie.

La préparation documentaire est contrôlée dans [la synthèse de vérification](../output/qa-import/op04-preparation-synthese-verification.json). Elle distingue conservation des témoins, liens et inspection des fichiers des critères de développement encore non exécutés.

## 9. Premier incrément réalisé — état après accord

**Accord reçu :** « j autorise le p-04 », en réponse au premier incrément décrit ci-dessus. DECISION-027 enregistré avec sauvegarde et relu **avant édition du code**. [Reçu d'autorisation](../output/qa-import/op04-autorisation.json). Aucun accord de migration/import/bascule ni diagnostic SQL ajouté.

**Implémentation :** six configurations nommées, ArchiveDatabaseGuard, PrivateLocalFiles, StrictJson, CatalogueCapsule et commande homya:catalogue-capsule ; extension de homya:db-check. Configurations homya/catalogue et .env.example documentées ; .env applicatif et six fichiers d'accès existants conservés, ces derniers non lus. Les secrets restent chargés seulement par un appel CLI explicite ; aucune nouvelle configuration de secret dans le web/cache. Catalogue.php, routes, vues, styles, données, migrations et dépendances inchangés.

**Capsule réelle :** copie terminée le 07/10/2026 à 23:27:34 UTC, code retour 0. Durée locale observée du processus : environ 238 secondes, incluant inspection/copie/relectures ; ce n'est pas un engagement de durée future. 22 239 fichiers de contenu / 3 477 829 369 octets, plus manifeste de 3 550 149 octets. Clé `48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809`, identique à la simulation et au candidat initial. Chemin privé `storage/app/private/catalogue-archives/<clé>/`, ignoré par Git ; copies/manifeste0400 et dossiers0700. Aucune copie incomplète laissée dans le projet par cette opération réussie.

**Preuves distinctes :** [copie par le service](../output/qa-import/op04-copie.json), [relecture CLI sans mutation](../output/qa-import/op04-verification-cli.json), [contrôle indépendant Python](../output/qa-import/op04-verification-independante.json). Ce dernier reconstruit la liste fermée depuis sources/références, contrôle le JSON canonique exact, relit toutes les tailles et empreintes source/copie/source et vérifie propriétaire, modes privés et ACL des dossiers. Les 627 signalements restent dans la capsule ; aucune correction métier ou admissibilité Google certifiée. Aucun état verified SQL ni activation de lot.

**Renforcement final des gardes :** la copie exige maintenant --expected-lot égal à la clé simulée ; une autre clé ou un rapport changé est refusé avant création. Après vérification des copies, les sources sont relues de nouveau immédiatement avant promotion. Ces gardes sont testées sur fixtures. La copie réelle précédente avait déjà exactement la clé simulée et ses octets sont vérifiés indépendamment ; elle n'a pas été recopiée ni écrasée après ce renforcement.

| Critère | Résultat et portée de vérification |
|---|---|
| OP04-AC01/02 | Associations fixes et refus avant connexion testés ; fichiers d'accès synthétiques uniquement, y compris ACL macOS. Aucun compte réel contacté |
| OP04-AC03 | Identité/cible/session/capacité et erreur de schéma testées avec doubles ; recette XAMPP réelle différée |
| OP04-AC04/05 | Ensemble fermé et résolution des originaux constatés ; manifeste réel confirmé par un encodeur indépendant ; tests Unicode/U+2028/ordre/dates |
| OP04-AC06 | JSON/doublons/UTF-8, traversée, source/média changé, absence/altération de copie, conflits E/S et espace refusés sur fixtures ; clé simulée désormais requise |
| OP04-AC07 | Tous les octets réels source/copie/source rapprochés ; originaux conservés |
| OP04-AC08 | Altération/incomplétude, destination déjà présente, verrou local et échec sans promotion testés ; staging synthétique incomplet conservé jusqu'au nettoyage de sa fixture seulement |
| OP04-AC09 | Sorties d'erreurs expurgées testées ; accès de fixture non placés dans la configuration, pas de chargement web ; chemins/modes/Git vérifiés sur capsule réelle |
| OP04-AC10 | Régression finale : **101 tests / 717 assertions, zéro erreur/échec/skipped** ; parcours FR/EN/DE et catalogue sans base conservés |

Premier essai ciblé : socket synthétique trop long pour macOS ; chemin de fixture raccourci. Deuxième essai : création de socket bloquée par le sandbox ; reprise hors sandbox réussie sans connexion de base. Les comptes rendus initiaux sont conservés. Puis 71 tests ciblés et 97 tests de régression réussis ; après le renforcement final, [101 tests réussis](../output/qa-import/op04-tests-finaux.xml). Syntaxe PHP et Pint contrôlés ; pas de build frontend puisque aucun asset modifié. Aucun test ne lance RefreshDatabase, DDL/DML ou migration générale/pretend.

### Utilisation locale et limites

Depuis le dépôt confirmé, commandes sans base :

```sh
/opt/homebrew/bin/php -d memory_limit=512M artisan homya:catalogue-capsule
/opt/homebrew/bin/php -d memory_limit=512M artisan homya:catalogue-capsule --verify=48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809
```

La simulation ne crée pas de capsule. Pour une future copie autorisée, --copy exige --expected-lot= suivi de la clé obtenue ; une destination existante reste refusée. Aucun nettoyage ni reprise automatique. Le verrou local .prepare.lock reste privé et persistant ; ne pas le supprimer pendant une préparation. Les échecs après création restent en .staging-… avec rapport .failure-… privé, jamais acceptés comme un répertoire de lot.

homya:db-check propose --connection, --access et --auth-only. **Aucun diagnostic réel exécuté sous OP-04.** Ces commandes peuvent ouvrir une connexion et demandent leur propre autorisation d'opération SQL. Le mode sans base ne prouve pas les droits SELECT ni la disponibilité du catalogue ; le mode sans options refuse désormais un compte runtime autre que homya_local_reader et une configuration non conforme.

**Limites conservées :** PHP CLI8.5, pas le PHP XAMPP8.2 ; configuration runtime .env toujours non alignée/lecteur non activé ; contrôles live et transport PDO différés ; consommation mémoire maximale non mesurée (limite CLI utilisée512 Mio). Capsule sur le même disque, modes réversibles par le propriétaire, pas une sauvegarde hors machine ou une restauration SQL testée. Les contrôles de concurrence couvrent les préparations coopérant avec le verrou ; ils ne figent pas atomiquement des sources modifiées par un processus extérieur. Toute opération future doit recontrôler la capsule et la cible.

**Prochaine étape :** préparer le complément OP-04 prévu par le plan — migrations catalogue, importeur et lecteur SQL — puis présenter son autorisation de développement distincte. Les prérequis de code pour OP-05 ne sont pas tous achevés ; aucune migration ni import autorisé par ce premier incrément.
