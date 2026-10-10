# HOMYA — OP-01, réglage des paquets XAMPP

7 octobre 2026 — **AUTORISÉ, APPLIQUÉ et VÉRIFIÉ pour le fichier et une nouvelle connexion de contrôle**.

## Accord et périmètre

La réponse utilisateur « **Prochaine étape :** sauvegarder `my.cnf`, passer la limite à **16 Mio**, appliquer le réglage global et contrôler les nouvelles connexions, sans redémarrage. Aucune création ni migration incluse.je valide » autorise OP-01 uniquement. Elle est enregistrée et relue dans **DECISION-020**, registre [Specification](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md). DECISION-019 reste la validation documentaire du [plan v0.2](PLAN-TECHNIQUE-ARCHIVE-XAMPP.md).

Aucune base, aucun compte ni aucune table créés ; aucune migration, import, restauration, bascule ou modification applicative. Aucun arrêt ou redémarrage de service, changement de port/socket ou autre paramètre XAMPP.

## Actions et preuves

Le script a été vérifié en syntaxe puis exécuté sur des copies isolées avant usage réel. Il refuse une cible, empreinte ou contrôle préalable inattendus ; enregistre l'accord, sauvegarde et verrouille le fichier ; ne modifie qu'une valeur dans `[mysqld]`. Il n'exécute aucun SQL.

| Contrôle / action exécutée | Observation / preuve |
|---|---|
| SELECT préalable dans la session locale phpMyAdmin | MariaDB 10.4.28, port 3307, socket/datadir exacts, écoute 127.0.0.1 ; paquet global/session 1 048 576 ; connexion 383 ; uptime 30 769 secondes ; homya_local/homya_test absents |
| Sauvegarde privée | `/private/tmp/homya-op01-my-cnf-20261007-f52bz2t7/my.cnf` ; empreinte vérifiée ; metadata.json conserve les métadonnées source ; sauvegarde du registre avant DECISION-020 dans le même dossier |
| Modification du fichier | `/Applications/XAMPP/xamppfiles/etc/my.cnf` : seule ligne `[mysqld] max_allowed_packet=1M` → `16M` ; propriétaire uid 0, groupe gid 80 et mode 0664 inchangés |
| Empreinte avant | `b529b55878d6cd3a181ef92be21e55112b31fd031d81075b0ba90a666a6bc8f1` |
| Empreinte après | Voir [preuve fichier](../output/qa-import/op01-config-appliquee.json) ; comparaison inverse confirme que la seule modification est cette valeur |
| Réglage du serveur actif | `SET GLOBAL max_allowed_packet = 16777216` exécuté une fois dans phpMyAdmin ; SELECT suivant : global 16 777 216, session existante 1 048 576, connexion 389 |
| Nouvelle connexion de contrôle | Connexion 393, distincte de 389 ; global **et session 16 777 216** ; même version/port/socket/datadir et écoute locale ; FK/CHECK de session à 1 |
| Continuité du serveur | Uptime après 31 040 secondes, supérieur aux 30 769 secondes avant ; aucune commande d'arrêt/redémarrage exécutée |
| Schémas après réglage | homya_local/homya_test toujours absents |

Le SET a été accompagné d'un avis annexe phpMyAdmin `#1046 Aucune base n'a été sélectionnée` lors de l'obtention des informations de débogage SQL. Le résultat du SET et le SELECT ont confirmé la valeur globale ; aucun second SET n'a été lancé pour contourner cet avis. Le SELECT de la nouvelle connexion a ensuite réussi avec les valeurs attendues. L'avis est conservé dans la preuve intermédiaire ; il ne constitue pas une preuve d'échec du réglage ni une vérification complète de phpMyAdmin.

Preuves expurgées : [précontrôle](../output/qa-import/op01-precontrole.json), [fichier et sauvegarde](../output/qa-import/op01-config-appliquee.json), [application globale et avis annexe](../output/qa-import/op01-reglage-global.json), [nouvelle connexion](../output/qa-import/op01-verification-sql.json), [vue du résultat](../output/qa-import/op01-controle-16mio.png). Les preuves des phases précédentes restent inchangées.

## Limites et suite

Le fichier persiste le réglage pour le prochain démarrage, sans redémarrage effectué ou test de redémarrage dans ce lot. Le réglage global concerne les futures connexions de toute cette instance XAMPP ; les sessions déjà ouvertes doivent être renouvelées pour récupérer la nouvelle valeur. La session de contrôle ne prouve pas la connexion Laravel, ses droits ou le transport d'une capsule complète par PDO.

Le blocage serveur de 1 Mio est levé pour les connexions nouvelles contrôlées. Taille de capsule figée, messages client, contraintes, concurrence et sauvegarde/restauration restent à éprouver dans leurs lots autorisés. Aucun import ne devient autorisé ou garanti par cette correction.

Un retour à 1 Mio requerrait une décision propre et une analyse d'impact : les documents d'archive de plus de 1 Mio ne seraient plus garantis en lecture SQL. La sauvegarde est conservée ; aucun retour automatique exécuté. Le lecteur actuel sur fichiers reste actif.

**Prochaine étape :** présenter OP-02, création des seules bases vides homya_test/homya_local, avec recontrôle de collision et autorisation distincte. Les comptes, tables, code, migrations et imports restent des opérations séparées.
