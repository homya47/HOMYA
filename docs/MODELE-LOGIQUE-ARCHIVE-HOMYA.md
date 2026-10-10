# HOMYA — modèle logique de l'archive catalogue

Version 0.2 — 7 octobre 2026 — **VALIDÉ sur le plan documentaire, non implémenté**. Statut actualisé après confirmation, sans modification du MCD ou des règles logiques approuvées.

Origine : plan v0.2 et MCD approuvés par l'utilisateur dans **DECISION-017**, registre [Specification](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md). [Plan et portée approuvée](/Users/akm/Documents/ChatGPT/HOMYA/docs/PLAN-IMPORT-XAMPP.md). La réponse autorise cette préparation documentaire, aucune création de base/compte, migration, import ou bascule. Aucun DDL ou fichier de migration n'est produit ici.

## 1. Périmètre et règles de lecture

**Validé :** archive consultative non vendable, quatre concepts lot/catégorie/produit/traduction ; source privée conservée ; fichiers médias sur disque ; tous les produits et signalements gardés. MariaDB XAMPP cible ; `homya_local` et `homya_test` retenus sous réserve de collision. **Observé :** 3 709 produits, 65 catégories, 41 977 lignes de caractéristiques, 92 variantes, 18 454 associations images originales, 23 878 traductions EN/DE, 35 overlays et 627 signalements. Ces comptes sont des contrôles de cet instantané, pas des plafonds métier.

**Propriétaires :** utilisateur HOMYA pour le sens et la conservation ; Database Engineering pour la conception ; Development pour la future traduction technique ; Security pour les accès/exposition ; Quality pour la recette. Aucun contact externe ni revue indépendante présumés.

Les relations, attributs et règles logiques ci-dessous sont **validés dans DECISION-018**. Les types physiques restent des recommandations non approuvées. « Document » signifie document JSON valide avec clés uniques ; tableaux conservés dans leur ordre, objets comparés sans perte de valeurs. Les octets originaux restent dans les fichiers et leurs empreintes : un aller-retour JSON SQL n'est pas une garantie d'identité binaire du fichier. Aucun défaut zéro, liste vide ou texte vide ne remplace une absence source. Les mentions « proposé » conservées dans le détail retracent le contenu soumis puis approuvé ; elles ne valent pas autorisation d'exécution.

Les relations `archive_lots`, `archive_categories`, `archive_products`, `archive_translations` réalisent les quatre concepts. Aucune relation client, utilisateur, achat, stock, variante vendable ou image BLOB n'est ajoutée.

## 2. Relations et attributs logiques proposés

### archive_lots — lot d'archive

Clé primaire logique : `lot_key`, empreinte SHA-256 du manifeste déterministe décrit ci-dessous. Il n'inclut pas sa propre empreinte. Les preuves de chargement générées après identification ne changent pas la clé. Une égalité d'empreinte exige aussi une comparaison des octets du manifeste exact ; sinon refuser le lot.

**Contrat de capsule proposé, version de format 1 :** document avec `format_version` puis `files` ; chaque entrée contient dans cet ordre `path`, `bytes`, `sha256`. Chemins relatifs au projet, séparateur `/`, tri croissant des octets UTF-8 des chemins, aucune entrée dupliquée. Encodage JSON UTF-8 sans BOM, sans échappement des caractères Unicode, sans espaces de séparation et sans saut de ligne final ; valeurs limitées à chaînes et entiers. Le SHA-256 porte sur ces octets, sans timestamp de préparation ni état SQL ajouté. La longueur et le hash sont calculés sur les fichiers tels quels, pas sur leur réencodage JSON.

Le périmètre du manifeste est fermé : les 3 714 fichiers source recensés dans `empreintes-sources.json` (index, inventaire, overlay, fiches et dictionnaires), les binaires uniques référencés par index/galeries/overlays, et les trois rapports de revue figés `synthese.json`, `anomalies-detaillees.json`, `empreintes-sources.json`. Les preuves de cette présente revue du MLD et les futurs comptes rendus SQL ne sont pas des entrées source du manifeste. Aucune URL de provenance n'est téléchargée implicitement. Tous les chemins restent dans les répertoires attendus ; refuser chemin absolu, traversée, lien symbolique sortant ou fichier changé pendant la préparation. Les binaires trop petits déjà masqués restent inclus s'ils sont référencés : conservation n'est pas publication.

Une modification d'un rapport de revue inclus produit une autre capsule ; elle ne compte pas comme reprise de la même capsule. Le rapprochement doit contrôler les fichiers avant et après chargement : aucun hash binaire média n'est déclaré fraîchement vérifié dans cette revue documentaire.

| Attribut | Domaine logique / nullabilité | Source / sensibilité |
|---|---|---|
| lot_key | Empreinte, obligatoire et unique | Identifiant de la capsule, privé |
| format_version | Entier positif, obligatoire ; 1 proposé pour cette capsule | Version du contrat décrit ci-dessus |
| manifest_document | Document obligatoire | Chemins relatifs bornés + empreintes des 3 714 sources et binaires médias référencés ; privé |
| archive_document | Document obligatoire | Index complet avec son ordre, inventaire, overlay médias, métadonnées EN/DE hors `strings` et rapports de revue ; privé |
| load_state | État technique obligatoire | Cycle proposé en section 5 ; privé, distinct de disponibilité commerciale |
| verification_document | Document nullable | Preuves de rapprochement ; obligatoire pour passage vérifié, aucun secret/journal SQL brut |
| registered_at | Instant UTC obligatoire | Enregistrement futur du lot, aucune date réelle inventée |
| verified_at | Instant UTC nullable | Renseigné uniquement après recette réussie |

Dates de collecte, compteurs et provenance restent dans les documents source ; ne pas les confondre avec dates de chargement. La capsule conserve les fichiers de revue et leurs empreintes, sans déclaration de résolution des anomalies.

### archive_categories — catégorie observée

Clé primaire logique : `(lot_key, slug)`. Nom conservé tel que capturé, traduction par dictionnaire existant. Pas de taxonomie HOMYA supplémentaire inventée.

| Attribut | Domaine logique / nullabilité | Source / sensibilité |
|---|---|---|
| lot_key | Empreinte obligatoire | FK lot, privé |
| slug | Chaîne ASCII sûre obligatoire | `product.category.slug`, publique par projection |
| source_name | Texte Unicode obligatoire | `product.category.name`, soumis à projection HOMYA/localisation |

Catégories dérivées des produits : une différence de nom pour un même slug dans un lot doit être signalée et empêcher la vérification du lot, sans choisir automatiquement un nom. Une catégorie sans produit n'est pas admise dans un lot vérifié de ce modèle dérivé.

### archive_products — produit observé

Clé primaire logique : `(lot_key, slug)`. La référence est conservée sans unicité globale supposée. L'index compact et la fiche complète ont des contenus distincts : **les deux doivent être conservés**, notamment l'image primaire de carte présente uniquement dans l'index.

| Attribut | Domaine logique / nullabilité | Source / sensibilité |
|---|---|---|
| lot_key | Empreinte obligatoire | FK lot, privé |
| slug | Chaîne ASCII sûre obligatoire | `slug`, identité publique actuelle |
| category_slug | Chaîne ASCII sûre obligatoire | FK catégorie du même lot |
| index_position | Entier positif obligatoire, unique dans le lot | Position dans `index.products`, ordre source |
| reference | Texte Unicode obligatoire | Référence exacte, sans normalisation de casse |
| source_name | Texte Unicode obligatoire | Nom exact, projection localisée avant publication |
| source_brand | Texte Unicode non null ; chaîne vide autorisée et conservée | Marque observée, six valeurs vides vérifiées ; projection HOMYA actuelle conservée, aucun fabricant inventé |
| universe | Code observé obligatoire | Code existant, aucune famille supplémentaire |
| source_condition | Code observé obligatoire | État produit observé, pas stock/disponibilité |
| price_cents | Entier positif obligatoire dans cet instantané | Prix capturé en centimes, pas prix de vente activé |
| previous_price_cents | Entier ou null conforme au document | 972 valeurs non nulles, de 10 900 à 928 200 centimes ; absence conservée dans le document, aucune promotion activée ni relation ancien/nouveau prix imposée |
| currency | Code obligatoire, EUR pour ce lot | Devise source |
| index_document | Document obligatoire | Ligne source compacte complète, dont image primaire/provenance ; privé |
| detail_document | Document obligatoire | Fiche entière : descriptions, caractéristiques, variantes, images, flags, dates et provenance ; privé |

Colonnes de lecture = projections redondantes du document, pas une seconde vérité éditable. Contrôler leur égalité avec les deux documents pour les champs partagés avant vérification. Une source future incompatible est refusée pour revue du contrat, pas corrigée silencieusement. Les champs supplémentaires restent dans le document.

### archive_translations — entrée de dictionnaire

Clé primaire logique proposée : `(lot_key, locale, source_key)`. `source_key` est SHA-256 des octets UTF-8 du texte source exact, sans nettoyage, trim, conversion de casse ou normalisation Unicode. Le texte exact reste conservé et comparé pour refuser une collision, même théoriquement rare.

| Attribut | Domaine logique / nullabilité | Source / sensibilité |
|---|---|---|
| lot_key | Empreinte obligatoire | FK lot, privé |
| locale | Code obligatoire EN ou DE (`en`/`de`) | Dictionnaire correspondant |
| source_key | Empreinte obligatoire | Clé technique dérivée, proposition physique à valider |
| source_text | Texte Unicode exact obligatoire | Clé de `strings`, soumis aux règles de projection |
| translated_text | Texte Unicode exact obligatoire | Valeur de `strings` ; validation des entrées employées à conserver |
| source_position | Entier positif obligatoire, unique par lot/langue | Ordre des entrées du fichier, pour rapprochement |

Les 11 939 entrées de chaque langue restent conservées, y compris les 12 non employées par langue. Métadonnées globales, dont `segmented_review` et indications de relecture, restent au niveau du lot ; elles ne deviennent pas des badges de traduction humaine validée. FR utilise la source et la projection, aucune ligne FR artificielle. Les traductions douteuses restent signalées, sans correction automatique.

## 3. Contraintes et relations

Les contraintes ci-dessous sont proposées à partir du MCD validé. Les règles liées au contenu JSON et à l'état d'un lot exigent une validation applicative en plus des contraintes SQL ; ne pas prétendre les garantir par une seule FK.

| ID | Type / attributs | Règle et comportement | Critère futur observable |
|---|---|---|---|
| CON-001 | PK des quatre relations | Identités décrites ci-dessus ; aucun doublon silencieux | Même lot reconnu sans duplication ; collision signalée |
| CON-002 | FK catégories/produits/traductions → lot | Chaque ligne appartient à un lot existant ; suppression/mise à jour restrictive proposée | Enfant sans lot et suppression de parent référencé refusés |
| CON-003 | FK composite produit `(lot_key, category_slug)` → catégorie `(lot_key, slug)` | Catégorie obligatoirement du même lot ; restrictive | Catégorie d'un autre lot refusée |
| CON-004 | UNIQUE positions produit et traduction | `(lot_key, index_position)` et `(lot_key, locale, source_position)` | Ordre reconstruit sans doublon ; positions 1..N rapprochées avant vérification |
| CON-005 | Domaines et nullabilité | Identités/champs requis présents ; EUR, entiers de prix, EN/DE, slugs sûrs ; valeurs inconnues non converties | Sources malformées rejetées avant écriture ; tests de contraintes réelles |
| CON-006 | Documents et projections | JSON valide à clés uniques ; fidélité des valeurs, tableaux, clés absentes et colonnes dérivées | Comparaison exhaustive source/SQL, pas seulement compte global |
| CON-007 | Lot vérifié et immuable | Tous les minimums/cardinalités et contrôles satisfaits ; source/colonnes gelées une fois vérifiées | Lot partiel jamais sélectionné ; aucun remplacement d'un lot vérifié |
| CON-008 | Preuves et médias | Fichiers présents, chemins bornés, empreintes et référence produit des overlays exactes | Erreur de chemin, fichier, référence ou hash bloque vérification |

**Répartition des garanties :** PK/UNIQUE/FK, nullabilité et domaines scalaires relèvent du schéma physique futur ; clés JSON dupliquées, alignement index/fiche, positions sans trou, catégorie ayant au moins un produit et exhaustivité de la capsule relèvent aussi de l'importeur et du rapprochement. Les JSON valides contenant `$7e`, valeurs divergentes, tableaux vides ou variantes sans prix restent archivés et signalés ; ces anomalies métier ne deviennent pas des motifs de suppression. Les règles de masquage/publication demeurent dans la projection actuelle.

Une FK nullable ne garantit pas le parent obligatoire : toutes les composantes des FK ci-dessus seront non nulles. Les noms des contraintes physiques devront être uniques dans le schéma MariaDB 10.4. Les paramètres de session `foreign_key_checks` et `check_constraint_checks` devront être actifs ; ne jamais les désactiver pour faire passer l'import. Contrôles physiques et état de session sont **À vérifier**, pas attestés par cette conception.

REL-001 lot→catégorie, REL-002 lot→produit, REL-003 catégorie→produit, REL-004 lot→traduction : chaque enfant a un parent ; chaque parent de ces associations possède au moins un enfant dans un **lot complet vérifié** conformément au MCD. Aucune table d'association nécessaire pour cette capture à une catégorie par produit. Ce choix ne limite pas le futur catalogue de vente.

## 4. Requêtes, index et propositions physiques MariaDB

**Observations de code :** `Catalogue::archive/products/product/filtered/text` lit l'index, la fiche par slug et le dictionnaire exact ; recherche avec `Str::ascii`, tri naturel et départage par slug ; filtres univers/marque/état/catégorie/prix. L'image de carte et le repli galerie dépendent des deux documents. L'application actuelle continue de fonctionner sur fichiers.

**Recommandation :** future lecture SQL limitée au lot explicitement choisi, reconstruction des mêmes données puis projection/filtrage PHP existants. Ne pas remplacer simultanément stockage, tri et recherche. La première comparaison n'ajoute aucun moteur de recherche.

| Index logique proposé | Usage / justification | Coût et limite |
|---|---|---|
| PK lot et PK `(lot, slug)` | Lot et fiche/category par identité | Unicité et accès direct, fournis par clés |
| PK `(lot, locale, source_key)` | Chargement EN/DE et recherche exacte contrôlée | Empreinte confrontée au texte complet |
| `(lot, category_slug)` produits | FK composite et intégrité de jointure | Index non unique nécessaire au contrôle relationnel |
| Uniques positions CON-004 | Reconstitution ordonnée par lot/langue | Coût d'écriture/espace accepté sous réserve de revue MLD |

Pas d'index marque/prix/nom/full-text spéculatif : le filtrage initial reste PHP. Ajouter ces index seulement après requêtes SQL approuvées et mesures du moteur réel. Aucune performance chiffrée ou latence garantie.

**Types physiques recommandés, non approuvés :** InnoDB ; documents JSON MariaDB (alias LONGTEXT, validation à éprouver sur 10.4.28), textes Unicode utf8mb4 ; empreintes ASCII de 64 caractères ; slugs ASCII limités au contrat proposé de 221 caractères, dont maximums observés produit 90 et catégorie 44 ; prix en entier SQL suffisamment dimensionné (INT signé satisfait les maximums observés 790 758 pour prix courant et 928 200 pour ancien prix ; borne de contrat future à valider) ; instants en UTC. Références/noms/textes doivent être conservés sans troncature ; pas de longueur métier déduite du maximum capturé. `source_brand` doit accepter explicitement `''` ; aucun défaut artificiel. JSON et longs textes n'ont pas besoin d'index sur un préfixe de leur contenu.

**Capacité à contrôler :** fichier index 3 414 780 octets, inventaire 315 142, overlay 55 079 ; taille maximale d'une fiche 8 857. Le document de lot contient plusieurs de ces objets et les rapports : mesurer la ligne complète sérialisée et le paquet SQL, pas seulement une fiche. Comparer aux limites effectives serveur/client (`max_allowed_packet`) et à la mémoire du processus PHP avant chargement ; si insuffisantes, arrêter avec diagnostic et proposer séparément un ajustement ou une révision du stockage. Aucune augmentation de configuration, estimation de performance ou compatibilité de paquet n'est présumée ici.

Collations, charset et comparaison exacte des clés/hash sont à éprouver : ne pas laisser une collation linguistique fusionner des identités différentes. Le hash évite d'indexer les longs textes, pas le contrôle des collisions. Pas de BLOB média, ENUM imposé, colonne JSON générée ou index propriétaire supplémentaire proposé.

**Portabilité :** MySQL JSON n'a pas le même stockage que MariaDB ; SQLite en tests ne démontre pas les FK/JSON/collations du moteur cible. Pas de migration PostgreSQL demandée. Les sources officielles et leur consultation sont détaillées dans le plan §8. MariaDB 10.4 n'est plus maintenu : évolution avant données réelles/production à planifier séparément. Ni cette remarque ni ce modèle n'autorisent une modification de XAMPP.

## 5. Cycle de vie, accès et évolution

**États techniques proposés, à approuver avec le MLD :** `prepared` → `loading` → `verified` ; échec de chargement/rapprochement → `failed`. `failed` → `loading` uniquement après inspection des écritures, nouvelle simulation et reprise explicitement autorisée. Aucun retour depuis `verified`. En état `verified`, `verification_document` et `verified_at` doivent être non nulls ; ces champs restent nulls avant succès. Un même manifeste vérifié est reconnu sans réimport. Un autre instantané crée un autre lot. Seul `verified` permet de proposer une sélection ; aucun lot n'est activé automatiquement. Aucun état de disponibilité commerciale n'est dérivé de ces états.

**Concurrence et gel proposés :** toutes les écritures d'un même lot ainsi que sa transition finale doivent prendre le même verrou de ligne parent dans une transaction InnoDB, vérifier à nouveau l'état après verrouillage, puis refuser toute mutation d'un lot vérifié. Le rapprochement final se déroule sous ce même verrou ; la transition `verified` est conditionnée aux champs et preuves complets, pas au seul compte de lignes. Une deuxième tentative attend ou échoue avec diagnostic borné, sans écrasement. Les clés uniques traitent le conflit de création initiale ; l'empreinte de manifeste/texte exact départage ensuite une vraie reprise d'une collision. Aucun verrou long de base entière, nettoyage automatique ni déclencheur imposé. L'absence d'effet du verrou hors transaction devra être couverte par la recette réelle.

Le mécanisme de sélection du lot reste à concevoir dans un incrément autorisé (configuration locale explicite recommandée, sans cinquième relation nécessaire). Les FK ne prouvent pas à elles seules que des écritures enfant ciblent un lot modifiable : importeur et droits bornés doivent appliquer ce contrôle.

**Accès proposés :** compte de lecture applicative sur ces seules relations ; compte import limité à l'écriture nécessaire du schéma confirmé ; compte de migration distinct avec DDL limité au schéma, usages à autoriser séparément. Aucun GRANT, compte ni secret créé ici. Provenance et payloads complets restent privés ; seules les projections HOMYA déjà filtrées sont exposables. Aucun client, donnée de paiement ou traitement marketing ajouté.

**Conservation :** fichiers et lots vérifiés conservés, aucune purge automatique. Durée de rétention et procédure de suppression : **Inconnues**, à définir avant besoin réel ; ne pas supposer droit de supprimer. Corrections métier/linguistiques : nouvel instantané tracé après revue, sans modification d'un lot vérifié. Évolution vers commerce/variantes vendables ou filtres structurés : nouveau cadrage et modèle, pas extension implicite de ce MLD.

**Échecs :** chargement partiel non visible ; transactions de données séparées du DDL ; absence de photo, traduction ou donnée invalide traitée selon erreurs/masquages existants et bloque la recette en cas d'écart. Revenir à la source fichiers après une future bascule ne supprime pas le schéma ni les données SQL. Aucune restauration ni annulation globale n'est déclarée testée.

## 6. Traçabilité et contrôles prévus

| Critères approuvés / exigences | Objets MLD | Contrôle prévu, non exécuté en SQL |
|---|---|---|
| MOD-01 / REQ-001/005/006 | Lot, produits, CON-006/008 | Tous les volumes, 627 signalements et champs exacts ; caractéristiques/variantes/images dans l'ordre |
| MOD-02 / REQ-007/009/010 | Prix observés et document des variantes | Prix entiers EUR ; pas d'héritage, stock inventé ni achat |
| MOD-03 | Quatre relations, CON-001/004/007 | Orphelin, collision, mauvaise catégorie de lot, position doublée, lot incomplet refusés |
| MOD-04 / REQ-003/004 | Traductions, index et fiche, overlay du lot | FR/EN/DE, refus de langue absente, carte/repli galerie, masquages et provenance privée ; comparaison projection actuelle |
| MOD-05 / REQ-025/029/030/034 | Isolation et lot choisi | Cible XAMPP/collisions/droits, sauvegarde-restauration et tests MariaDB séparés |
| MOD-06 | Portée documentaire | Accord MLD distinct, puis plans et autorisations propres aux actions techniques |

Cas futurs de recette : doublon JSON, chemin sortant, catégorie inexistante/autre lot, prix invalide ou montant hors type choisi, doublon/collision de traduction simulée, échec au milieu d'un chargement, reprise sans doublons, deux importeurs concurrents, paquet trop grand, marque vide conservée, état `verified` sans preuves/date et tentative de modification d'un lot vérifié. Les comptes/collations/transactions doivent être vérifiés sur la vraie cible isolée, pas présumés par inspection. Les originaux sous le seuil photo doivent rester archivés tout en conservant les masquages et photos de repli déjà approuvés.

## 7. État, décisions et prochaine étape

**Vérifié dans cette phase documentaire :** champs réels d'index/fiche/dictionnaires/overlay et usages du service Catalogue relus ; liens et cohérence documentaire contrôlés ; empreintes source inchangées. Pas de changement applicatif, DDL, requête SQL, test de sauvegarde/restauration ou recette du schéma.

**Approbation reçue le 7 octobre 2026 :** « Valider le modèle logique v0.2 », réponse utilisateur à la question regroupant relations, clés/positions, conservation des documents et marques vides, capsule déterministe, verrou commun et lots vérifiés immuables. **DECISION-018 VALIDÉ**, enregistré puis relu dans le registre central. La préparation du plan technique est permise ; création de base/compte, code applicatif, migration, import, bascule et déploiement restent exclus de cette approbation.

**Décisions physiques restantes :** types, capacités et droits à détailler puis éprouver dans [le plan technique proposé](/Users/akm/Documents/ChatGPT/HOMYA/docs/PLAN-TECHNIQUE-ARCHIVE-XAMPP.md). L'approbation du MLD ne certifie aucune de ces capacités.

**Bloquants pour exécution :** types physiques non validés, accès Laravel absents, collisions des bases et droits non contrôlés, sauvegarde/restauration non prouvées, autorisations de création/migration/import distinctes non reçues. Ces limites n'empêchent pas la préparation du plan technique.

**Prochaine étape :** examiner le plan technique et compléter les contrôles de cible en lecture seule avant autorisations propres aux écritures. Ce fichier est un livrable de conception ; le registre central reste l'unique registre de décisions.

**Version exacte approuvée :** SHA-256 `03b2245f3eadc9f12915f5a4a07f1136fe950a1132980e620d7a437f78a4f3f2`, avant actualisation de statut. Copie sauvegardée dans `/private/tmp/homya-avant-validation-mld-018-hf_awnk4/MODELE-LOGIQUE-ARCHIVE-HOMYA.md`. Les changements présents consignent l'accord et la prochaine phase ; la proposition physique reste distincte.

## 8. Revue de la version 0.2 et sources

**Méthode :** lecture MCD/MLD, règles Database Engineering/Specification et revue Security/Quality proportionnée, analyse de toutes les fiches/index/dictionnaires, recontrôle des 3 714 empreintes source. Aucun agent indépendant ou test offensif lancé. Les faits sont consignés dans [le rapport de revue](/Users/akm/Documents/ChatGPT/HOMYA/output/qa-import/revue-modele-logique-v0.2.json).

| Point de revue | Fait / interprétation | Correction documentaire v0.2 / limite |
|---|---|---|
| REV-MLD-01 — Marque | Six marques exactement vides ; une règle « non vide » perdrait des produits | Champ non null acceptant la chaîne vide explicitement, sans valeur de remplacement |
| REV-MLD-02 — Fidélité | Zéro divergence sur tous les champs partagés index/fiche ; carte et fiche ont des données distinctes | Deux documents conservés, ordre et colonnes dérivées rapprochés |
| REV-MLD-03 — Taille | L'index de 3,4 Mo dépasse largement une fiche de 8,9 Ko | Contrôle de la ligne et des paquets complets avant écriture, limites effectives inconnues |
| REV-MLD-04 — Identité | Texte de traduction maximal 976 octets source ; pas de collision SHA-256 dans les 23 878 entrées analysées par langue | Manifeste déterministe et texte exact conservé ; collision future toujours testée et refusée |
| REV-MLD-05 — État / concurrence | Les FK ne gèlent pas un lot vérifié, et un verrou hors transaction n'apporte pas la garantie recherchée | Protocole de verrou commun, transitions, preuves/date obligatoires ; recette MariaDB non exécutée |
| REV-MLD-06 — Conservation | Les anomalies et photos masquées sont des données à garder ; validité JSON ne signifie pas contenu métier validé | Refus des structures invalides séparé des signalements métier ; aucun arbitrage automatique |

**Avis technique : favorable pour validation documentaire du MLD v0.2**, limité à l'archive consultative approuvée. Les contrôles physiques restent les prérequis de l'exécution future. Cet avis ne constitue pas une approbation utilisateur ni une preuve SQL.

Questions externes précisément vérifiées : JSON conserve-t-il les clés dupliquées de façon sûre pour une extraction ? Une FK nullable impose-t-elle le parent ? Les contrôles de contraintes peuvent-ils être désactivés ? Quand le verrou de ligne prend-il effet ? Quelle limite doit être contrôlée pour le document de lot ?

Consultation effective **7 octobre 2026**, documentation officielle actuelle ; comportement exact de MariaDB **10.4.28** à éprouver, aucune connexion SQL dans cette phase.

| Titre et URL | Portée / fait publié | Recommandation / décision dépendante |
|---|---|---|
| [JSON Data Type — MariaDB](https://mariadb.com/docs/server/reference/data-types/string-data-types/json) | JSON est un alias LONGTEXT ; une extraction de clés dupliquées n'expose que la première valeur | Rejeter les clés dupliquées dans le parseur ; valider les tableaux/documents avant stockage, CON-006 |
| [Foreign Keys — MariaDB](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/foreign-keys) | FK avec composante nulle peut ne pas avoir de parent ; moteur/types/charset/collation compatibles nécessaires ; noms uniques par base avant 12.1 | FK composites non nulles explicites, types identiques, noms distincts ; DDL et recette à préparer séparément |
| [CONSTRAINT — MariaDB](https://mariadb.com/docs/server/reference/sql-statements/data-definition/constraint) | PK, UNIQUE, FK et CHECK disponibles ; contrôles CHECK désactivables | Vérifier présence et effet réels des contraintes sur cible, ne pas les contourner |
| [Server System Variables — MariaDB](https://mariadb.com/docs/server/server-management/variables-and-modes/server-system-variables#max_allowed_packet) | `max_allowed_packet` limite paquets/chaînes ; limites client et serveur à considérer ; contrôles de contraintes configurables | Lire les valeurs effectives et mesurer les paquets ; aucun défaut officiel assimilé à la configuration locale |
| [FOR UPDATE — MariaDB](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update) | En InnoDB, verrou effectif dans une transaction ; sans transaction avec autocommit activé, pas d'effet recherché | Protocole transactionnel commun et tests concurrents ; pas de garantie déclarée par simple lecture documentaire |
