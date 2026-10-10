# HOMYA — bascule locale après recette technique

08/10/2026 — périmètre autorisé DECISION-045 et **bascule locale exécutée et vérifiée**. La proposition et ses conditions ci-dessous sont conservées ; voir la clôture et les preuves dans `ACCES-LOCAL-XAMPP.md`.

But borné : faire lire au site local le même lot déjà vérifié dans `homya_local`, par le seul reader dédié, en utilisant les adaptateurs testés. Il s'agit toujours d'un catalogue consultatif ; aucune vente ouverte, correction de données ou publication.

## Opérations à autoriser ensemble

1. Recontrôler le contexte local PHP8.5, configuration non cachée, clé source et accès reader. Ne pas afficher `.env` ou secret. En cas de cache/conflit/source inconnue, arrêter sans purge/reconfiguration.
2. Créer une copie privée vérifiée de `.env` sous un nouveau dossier0700 ignoré par Git ; fichier0600, sans symlink/ACL élargie. Conserver son empreinte et tous les autres octets. Ce n'est pas une sauvegarde des données SQL.
3. Modifier uniquement la clé non secrète `HOMYA_CATALOGUE_SOURCE` de `files` à `sql` (ajout unique si absente). Aucun `DB_*`, autre secret ou paramètre XAMPP changé. Modification atomique, vérification que les autres valeurs sont identiques ; pas d'édition aveugle d'un fichier concurrent.
4. Vérifier de nouvelles requêtes PHP/HTTP locales avec la configuration réelle, sans override de source : accueil/catalogue/fiches FR/EN/DE, recherche/filtres/pagination, images et états attendus. Ne pas arrêter un serveur existant ; tout éventuel processus de contrôle sera propre à cette opération et fermé à fin.
5. Si ces contrôles échouent et si les témoins sont inchangés, effectuer le retour autorisé de cette seule clé à `files` puis recontrôler. En cas d'autre divergence/concurrence, arrêter et préserver les preuves ; ne pas écraser toute la configuration ni restaurer une base.
6. Clôturer les preuves et le registre avec état source réellement observé, garde reader et connexion dédiée ; aucune réutilisation des reçus natifs043/044 clôturés. Les commandes/outils/empreintes précis seront relus et figés avant l'action.

## Conditions et limites

La garde SQL reste locale/loopback, seul lot figé ; cette architecture n'est pas une préparation d'hébergement public. Mesures de recette : pic HTTP SQL56,1Mo sous128M, latence locale médiane HTTP0,841s sur18 GET répétés, incluant les gardes de recette. Aucun objectif de latence métier ou CWV validé.

Avant vente : confirmer/réviser prix, stocks, fiscalité, livraison/retours, prestataire de paiement et exploitation ; relecture linguistique et627 signalements encore ouverts. Tout futur changement d'archive/droits/schema/import/vente nécessite son périmètre propre. Ces inconnues ne sont pas remplacées par des règles inventées.

**Accord distinct proposé :** « J’autorise la bascule locale vers SQL et le retour borné aux fichiers en cas d’échec, dans ce périmètre ». Il n'autorise ni changement des données, paiement réel, déploiement ou ouverture commerciale.

## Clôture locale — 08/10/2026

Accord utilisateur « J'autorise » en réponse au périmètre, consigné avant action DECISION-045. Sauvegarde privée `.env.before` vérifiée dans `output/qa-import/op10-local-switch-20261008T180142Z-46a4a341-d922-437d-b368-5db2b39b31af` ; seule clé source ajoutée/modifiée vers `sql`, aucun `DB_*` ou autre octet modifié. Relecture PHP réelle sans cache/override et diagnostic reader dédiés réussis ; recette HTTP réelle depuis la copie intégrale `htdocs` autorisée DECISION-046 :78 cas, sept contrôles supplémentaires,13 médias délivrés identiques aux fichiers installés. Source SQL conservée ; aucun retour fichiers nécessaire, aucune écriture SQL/migration/import/droit/service MariaDB modifié.

Les corrections UX et les quatre correctifs éditoriaux sont des projections communes, distinctes de l'archive approuvée. Les anciennes empreintes de parité044 ne sont pas revendiquées comme preuve du nouveau HTML. Nouveau navigateur375/768/1440, saut de page clavier, recherche/tri/page→fiche→langue→retour, galerie, filtres invalides et état vide contrôlés. Aucun audit exhaustif linguistique/CWV ni readiness commerciale/production déduit.
