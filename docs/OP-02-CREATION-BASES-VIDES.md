# HOMYA — OP-02, création des deux bases vides

7 octobre 2026 — **AUTORISÉ ; COMPLET ET VÉRIFIÉ après réparation B et reprise sous DECISION-021.** Les sections initiales conservent la procédure et la première tentative partielle ; la clôture actuelle figure dans « Reprise et résultat final ».

## Sources et portée

Le [plan technique v0.2](PLAN-TECHNIQUE-ARCHIVE-XAMPP.md), §4 et §10, approuvé sur le plan documentaire dans DECISION-019, prévoit deux schémas distincts : `homya_test` pour la recette et `homya_local` pour l'archive locale. Charset et collation prévus : `utf8mb4` / `utf8mb4_bin`.

[OP-01](OP-01-REGLAGE-XAMPP.md), autorisé et vérifié dans DECISION-020, a porté la limite de paquets à 16 Mio. Son dernier contrôle SQL du 07/10/2026 constate les deux noms absents sur MariaDB XAMPP 10.4.28, port 3307, socket et datadir exacts. Cette observation passée ne remplace pas le contrôle juste avant création. La connexion Laravel reste non vérifiée.

La présente fiche détaille une opération, sans créer de second registre de décisions. La réponse utilisateur « oui j autorise » a confirmé cette portée dans **DECISION-021**, section HOMYA du [registre central Specification](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md), avec sauvegarde et relecture avant SQL d'écriture. La fiche exacte soumise, SHA-256 `cdfb95e4358426228b5f9c9f47bd127ab264126bdccc49c99ad102c0bfd373f3`, est conservée dans `/private/tmp/homya-op02-accord-__7o572s/OP-02-CREATION-BASES-VIDES.md`.

## Procédure soumise à autorisation

1. Utiliser l'accès administratif local disponible, sans exporter de secret ni accorder cet accès à Laravel. En cas d'accès indisponible, arrêter l'opération.
2. Recontrôler en lecture seule l'identité du serveur : MariaDB 10.4.28, port 3307, socket `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`, datadir `/Applications/XAMPP/xamppfiles/var/mysql/` et écoute `127.0.0.1`. Recontrôler la limite globale et celle d'une nouvelle session à 16 777 216 octets, ainsi que la disponibilité de `utf8mb4_bin`.
3. Contrôler ensemble les deux noms dans `information_schema.SCHEMATA`. Si l'un existe, arrêter avant toute création : aucune réutilisation, suppression, substitution ou modification de la base rencontrée.
4. Soumettre les deux instructions ci-dessous **séparément**, d'abord `homya_test`. Après chaque création, contrôler le résultat et l'identité du serveur. Recontrôler l'absence de `homya_local` juste avant la seconde instruction. Toute erreur ou divergence arrête l'opération.
5. Vérifier les deux schémas, leurs paramètres et leur absence de tables/vues/routines/événements. Conserver les requêtes, résultats et horodatages expurgés ; documenter le résultat réel dans cette fiche et le registre.

**Seules écritures SQL autorisées :** première exécutée, seconde non soumise après l'erreur de contrôle.

```sql
CREATE DATABASE `homya_test`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
```

```sql
CREATE DATABASE `homya_local`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
```

Ni `IF NOT EXISTS` ni `OR REPLACE` : une collision doit produire un arrêt visible. Les créations sont du DDL ; ne pas compter sur une transaction pour annuler les deux opérations. Si seule la première réussit, conserver son état, signaler la création partielle et inspecter avant toute reprise. Aucun `DROP` automatique ou retour arrière destructif.

## Critères d'acceptation

| Critère | Preuve attendue après autorisation |
|---|---|
| Cible correcte | Métadonnées concordantes XAMPP avant et après ; aucune action sur Homebrew/3306 |
| Absence de collision | Résultats ciblés sur les deux noms avant la première création, puis sur le second juste avant sa création |
| Deux schémas seulement | Deux lignes SCHEMATA : noms exacts, DEFAULT_CHARACTER_SET_NAME=utf8mb4, DEFAULT_COLLATION_NAME=utf8mb4_bin |
| Bases vides | Zéro objet pour chacun dans TABLES (tables et vues), ROUTINES et EVENTS, sous le compte administratif de contrôle |
| Périmètre respecté | Trace des deux CREATE uniquement pour les écritures soumises ; aucun compte, droit, table, migration ou import ajouté |

## Exclusions, risques et suite

Aucun compte ou privilège créé/modifié ; aucune table, notamment aucun journal `migrations` ; aucun code applicatif, fichier `.env`, migration, seeder, import, restauration, bascule du catalogue, redémarrage ou configuration XAMPP modifiés. Le catalogue sur fichiers reste actif. Aucun paiement ni déploiement inclus.

L'opération crée des répertoires et métadonnées de deux schémas vides sur l'instance locale. Aucun contenu existant n'est à sauvegarder dans ces noms s'ils sont effectivement absents ; les autres bases ne seront pas exportées ou modifiées. La création ne démontre ni isolation des futurs comptes, ni compatibilité des contraintes, ni restauration ou connexion Laravel.

**Décision validée :** OP-02 autorisé dans cette portée exacte par « oui j autorise ». L'accord reste distinct des opérations suivantes ; aucun compte ou privilège ne devient autorisé.

## Résultat réel et preuves — 07/10/2026

| Action / contrôle | Résultat observé |
|---|---|
| Précontrôle puis recontrôle après enregistrement de l'accord | Connexions 403 puis 407 : même XAMPP 10.4.28 / 3307, socket/datadir exacts, écoute locale, global/session 16 Mio, collation disponible, deux noms absents |
| CREATE DATABASE homya_test | Soumis une fois, résultat vide normal de DDL ; création ensuite confirmée par SCHEMATA |
| Avis annexe phpMyAdmin après CREATE | `#1046 Aucune base n'a été sélectionnée` également affiché ; pas de répétition du CREATE. La preuve de création est le SELECT suivant réussi |
| Contrôle des routines via information_schema.ROUTINES | Échec `#1558` : mysql.proc attendu avec 21 colonnes, trouvé avec 20 ; créé avec MariaDB 100108, serveur courant 100428 |
| Contrôle des événements via information_schema.EVENTS | Échec `#1577 Cannot proceed, because event scheduler is disabled` ; aucun réglage du planificateur effectué |
| SELECT limité aux métadonnées accessibles | Connexion 431 : homya_test présente, utf8mb4 / utf8mb4_bin, zéro table/vue ; homya_local absente ; event_scheduler=OFF, FK/CHECK actifs, limite toujours 16 Mio |
| Continuité | Uptime 31 755 → 32 122 secondes ; aucune commande d'arrêt ou de redémarrage |
| État final | **PARTIEL — BLOQUÉ** : homya_test conservée, homya_local non créée ; absence de routines/événements non certifiée par les catalogues défaillants |

Opérateur : **un CREATE et cinq SELECT soumis**, dont deux SELECT en erreur. phpMyAdmin peut encadrer ces commandes par ses instructions internes de session ; le contrôle FK est resté activé. Aucune table, compte, privilège, migration ou import exécutés. Aucun mysql_upgrade/mariadb-upgrade, ALTER/REPAIR de table système, changement de planificateur ou suppression soumis.

Preuves : [autorisation relue](../output/qa-import/op02-autorisation.json), [précontrôle](../output/qa-import/op02-precontrole.json), [recontrôle immédiat](../output/qa-import/op02-controle-immediat.json), [création test](../output/qa-import/op02-creation-test.json), [erreur routines](../output/qa-import/op02-erreur-controle-routines.json), [erreur événements](../output/qa-import/op02-erreur-controle-evenements.json), [état partiel contrôlé](../output/qa-import/op02-verification-partielle.json), [capture](../output/qa-import/op02-etat-partiel.png).

## Vérification externe du blocage

Question examinée : l'erreur 1558 signale-t-elle un défaut des tables système, et sa correction peut-elle rester dans l'autorisation limitée à deux schémas vides ?

| Source officielle ; consultation 07/10/2026 | Portée et fait publié |
|---|---|
| [MariaDB — Error 1558](https://mariadb.com/docs/server/reference/error-codes/mariadb-error-codes-1500-to-1599/e1558) | Erreur de structure d'une table mysql par rapport au serveur ; une mise à niveau incomplète est une cause possible, et mariadb-upgrade est une piste de correction |
| [MariaDB — mariadb-upgrade](https://mariadb.com/docs/server/clients-and-utilities/deployment-tools/mariadb-upgrade) | Utilitaire anciennement nommé mysql_upgrade ; modifie des tables système et peut vérifier/modifier d'autres tables ; recommande une sauvegarde des bases avant usage |
| [MariaDB — code Error 1577, dépôt officiel](https://github.com/mariadb-corporation/mariadb-docs/blob/main/server/reference/error-codes/mariadb-error-codes-1500-to-1599/e1577.md) | Code et libellé associés à l'indisponibilité du planificateur d'événements ; pas une preuve de sa cause locale |

**Interprétation :** le défaut mysql.proc est extérieur aux tables HOMYA et cohérent avec une structure ancienne. Son origine précise et le lien éventuel avec l'erreur événements restent **À vérifier** ; aucune mise à niveau passée n'est présumée. `event_scheduler=OFF` a été observé, sans conclure qu'un simple passage à ON corrigerait le défaut.

**Recommandation / prochaine étape :** préparer un plan de correction XAMPP avec diagnostic borné, outil/version/options réellement installés, sauvegarde protégée et périmètre d'impact explicite. Toute réparation des tables système ou modification du planificateur exige une autorisation propre. Après correction et contrôles réussis, inspecter homya_test déjà créée et reprendre OP-02 sans la recréer, sous l'accord enregistré. Aucun nouvel objet ni retour destructif automatique ; les comptes restent une phase ultérieure.

## Reprise et résultat final — 07/10/2026

La recommandation précédente décrit l'état avant réparation. [COR-XAMPP-B](COR-XAMPP-B-REPARATION.md) a depuis été exécutée et contrôlée sous son accord propre DECISION-024, puis clôturée avant cette reprise. Les contrôles système mysql.proc21/ROUTINES/EVENTS sont désormais réussis, événements toujours OFF.

DECISION-021 et [l'autorisation OP-02](../output/qa-import/op02-autorisation.json) ont été relues : l'accord existant couvre la seconde création, aucune nouvelle autorisation B assimilée à une création de base. Précontrôle phpMyAdmin ID59 : cible XAMPP exacte/16 Mio, collation disponible, homya_test conservée vide et homya_local absente. Seul le CREATE homya_local a été soumis une fois, identique à l'instruction autorisée, sans `IF NOT EXISTS`/`OR REPLACE`.

phpMyAdmin a de nouveau affiché un résultat vide de DDL et l'avis annexe `#1046 Aucune base n'a été sélectionnée`. **La commande n'a pas été répétée.** La cause de cet affichage est non déterminée ; les SELECT suivants ont réussi sans cet avis et confirment la création, pas une simple interprétation du retour CREATE.

| Base | Charset / collation | Tables et vues | Routines | Événements |
|---|---|---|---|---|
| homya_local | utf8mb4 / utf8mb4_bin | 0 | 0 | 0 |
| homya_test | utf8mb4 / utf8mb4_bin | 0 | 0 | 0 |

Contrôle final ID69 : même MariaDB10.4.28/3307/socket/datadir XAMPP, 16 Mio global/session, FK/CHECK actifs, mysql.proc21, événements OFF. Vue compacte des deux schémas également vérifiée. Trois configurations et 3 868 fichiers témoins inchangés. Le correctif du lanceur relève de la réparation B séparée ; aucun fichier applicatif/.env modifié ici.

**Bilan des écritures OP-02 :** deux CREATE au total à des étapes distinctes, un par nom ; homya_test jamais recréée, aucune suppression/réutilisation d'une collision. Reprise : un CREATE restant et trois SELECT (précontrôle, vérification finale, vue compacte). Aucun nouveau compte/droit/table/journal migrations, migration/import/bascule, réglage, arrêt/redémarrage ou paiement.

DECISION-021 complétée et relue dans le registre central ; sauvegarde documentaire `/private/tmp/homya-op02-cloture-registre-u3bqdcwi/registre-avant-cloture.md`. [Synthèse finale](../output/qa-import/op02-reprise-synthese-verification.json), [précontrôle](../output/qa-import/op02-reprise-precontrole.json), [CREATE restant](../output/qa-import/op02-reprise-creation-local.json), [contrôle final](../output/qa-import/op02-reprise-verification-finale.json), [deux bases vides](../output/qa-import/op02-reprise-bases-vides.json), [capture de l'interface](../output/qa-import/op02-reprise-interface-verifiee.png). Les premières captures recadrées sont tronquées et ne servent pas de preuve lisible ; elles sont conservées comme essais QA. La capture pleine page montre les noms/charset/collation/tables ; les colonnes à droite sont hors de son viewport étroit. Les six valeurs complètes sont dans la preuve JSON, lue indépendamment du cadrage.

**Suite et limites :** OP-02 clôturée ; OP-03 comptes/droits et connexion Laravel restent une opération séparée à préparer et autoriser. Aucune migration/import autorisés ou exécutés. La présence des deux schémas vides ne prouve ni accès applicatif, isolation future, contraintes ni restauration. La sauvegarde A et sa restauration non testée conservent leur portée documentée.
