# HOMYA — COR-XAMPP-A : sauvegarde physique XAMPP

7 octobre 2026 — **Première tentative INCOMPLÈTE conservée ; reprise SIGTERM sous DECISION-023 exécutée et vérifiée. COR-XAMPP-A clôturée, restauration non testée.**

## Autorisation et périmètre

L'accord utilisateur « j autorise » est enregistré et relu dans DECISION-022, section HOMYA du [registre central Specification](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md). Cette fiche suit une opération ; elle ne constitue pas un autre registre de décisions.

Le [plan](PLAN-CORRECTION-XAMPP-SYSTEME.md) autorise exclusivement l'inventaire administrateur, l'arrêt propre de MariaDB XAMPP, la copie physique complète et privée de son instance locale, la comparaison des fichiers/métadonnées puis le redémarrage du même service. La copie comprend les autres bases locales et les tables d'authentification. Apache et MySQL Homebrew ne sont pas concernés.

Aucune réparation, restauration, activation d'événements, modification de configuration/accès, création de base ou compte, migration ou import n'est autorisée par cet accord. COR-XAMPP-B exige une autorisation distincte après les contrôles A.

## Préparation effectivement vérifiée

- [Précontrôle SQL](../output/qa-import/cor-a-precontrole.json) : MariaDB 10.4.28 sur 3307, datadir/socket/PID XAMPP confirmés, écoute locale, paquet 16 Mio ; homya_test présente sans table/vue, homya_local absente ; aucun tablespace extérieur déclaré ni DATA DIRECTORY, journal binaire désactivé. SELECT seulement.
- Processus XAMPP identifié dans `sbin/mysqld`, distinct de MySQL Homebrew.
- [Tests isolés](../output/qa-import/cor-a-tests-script.json) : syntaxe, copie et comparaison contenu/modes/propriétaires du jeu d'essai/horodatages/flags/ACL/attributs, détection d'altération, refus des liens, fichiers `.isl` et destinations existantes. Aucun test d'exécution root, aucune commande de service XAMPP durant ces tests.
- [Accord et empreintes](../output/qa-import/cor-a-autorisation.json) : registre relu ; versions exactes du plan, du script, du précontrôle et des trois configurations identifiées.
- `sudo -n` hors sandbox demande un mot de passe. L'exécution administrateur n'est pas disponible automatiquement ; aucune interruption, copie réelle ni réparation effectuée.

La taille complète et l'espace nécessaire restent à mesurer avec les privilèges administrateur. Le script les contrôle avant arrêt : espace libre supérieur à deux fois la taille mesurée plus 1 Gio. Les fichiers persistants extérieurs, liens ou objets inattendus provoquent un refus avant copie.

## Commande locale à exécuter

Laisser les autres applications utilisant MariaDB XAMPP sans écritures pendant cette fenêtre. Ne pas arrêter manuellement le service avant le script. Dans Terminal :

```sh
sudo /usr/bin/python3 /private/tmp/homya-cor-a-sauvegarde-20261007.py --apply
```

Saisir le mot de passe administrateur uniquement dans Terminal. Aucun mot de passe ou contenu de configuration ne doit être transmis dans le chat.

Le script vérifie à nouveau l'accord, ses empreintes, la configuration, le processus/socket, l'inventaire et l'espace avant toute interruption. Il refuse un précontrôle SQL âgé de plus de deux heures : dans ce cas, demander son actualisation en lecture seule ; cet échec ne nécessite pas un nouvel accord A. Ne pas modifier le script ou retirer ses contrôles.

Une destination neuve privée est créée sous `/Applications/XAMPP/xamppfiles/homya-backups/<UTC>-<UUID>/` ; le datadir complet et trois configurations sont copiés, sans `.env`. Les manifestes détaillés et journaux restent dans ce dossier privé. À l'arrêt, la comparaison porte sur source/copie/source et leurs métadonnées. En cas d'échec après l'arrêt, le script tente de remettre le service initial en fonctionnement, conserve les preuves et marque le résultat incomplet. Il ne supprime pas la copie et ne restaure rien automatiquement.

## Résultat attendu et contrôle restant

Le résumé expurgé est écrit dans [cor-a-resultat-script.json](../output/qa-import/cor-a-resultat-script.json), uniquement après une exécution ayant atteint la fenêtre de sauvegarde. Il est maintenant disponible avec le statut INCOMPLET ; ne pas relancer la commande ci-dessus.

Une copie vérifiée suivie d'un retour du processus/socket/port produit notamment :

```json
{
  "physical_copy_verified": true,
  "service_ready_without_sql_validation": true,
  "sql_after_restart_verified": false,
  "restoration_tested": false,
  "repair_executed": false,
  "status": "COPIE_VERIFIEE_SQL_A_CONTROLER"
}
```

Ce résultat ne suffit pas à clôturer A : une nouvelle connexion SQL doit confirmer la cible, le paquet, homya_test et l'absence de homya_local, puis la trace centrale doit être actualisée. Répondre « exécuté » après la commande ou transmettre son résumé final/erreur expurgée, sans secret. Ne pas relancer aveuglément en présence d'un résultat INCOMPLET.

La sauvegarde reste sur le même volume ; la restauration n'est pas testée. Elle conserve aussi le défaut système initial et ne constitue pas sa réparation. La durée réelle d'interruption et le fonctionnement des autres applications ne sont pas encore vérifiés.

## Tentative du 07/10/2026 et diagnostic restant

**Faits :** le précontrôle administratif est passé ; le script a appelé l'arrêt XAMPP, puis a produit `physical_copy_verified=false`, `service_ready_without_sql_validation=false` et `status=INCOMPLET` à 20:47:54 UTC. Le dossier privé de tentative est `/Applications/XAMPP/xamppfiles/homya-backups/20261007T204622Z-782ab54d-06a5-484d-ae66-94d5fcadefe4`. Son existence n'atteste pas une sauvegarde.

Une [nouvelle connexion phpMyAdmin](../output/qa-import/cor-a-controle-apres-echec.json), ID 459, confirme ensuite XAMPP 10.4.28/3307/socket/datadir, 16 Mio et événements OFF ; homya_test présente sans table/vue, homya_local absente. Le processus initial 18340 est encore observé (UID 74), distinct de Homebrew. Le fichier PID configuré `AKM-2.local.pid` est absent. Cela confirme le service SQL actif au contrôle, sans prouver un arrêt/redémarrage réussi ou le fonctionnement des autres applications.

**Interprétation à confirmer :** le lanceur `share/mysql/mysql.server` utilise `su` pour tester/signaler le processus et contient une branche supprimant le PID lorsqu'il estime le processus inactif. Cette branche pourrait expliquer le PID absent. Le journal privé doit confirmer l'échec effectif du lanceur ; le message générique du script ne conserve pas la cause initiale lorsque le contrôle de récupération échoue. Aucune cause précise ni correction n'est encore validée. Ne pas recréer le PID, envoyer de signal ou démarrer une seconde instance.

La lecture du journal privé nécessite encore sudo local. Diagnostic préparé et testé sur texte fictif, sans arrêt, redémarrage ou SQL :

```sh
sudo /usr/bin/python3 /private/tmp/homya-cor-a-diagnostic-20261007.py
```

Ce diagnostic lit le journal de cette tentative et les métadonnées du service/dossier ; il n'affiche pas les données ni le journal brut. Il écrit uniquement un résumé expurgé dans `output/qa-import/cor-a-diagnostic-service.json`. Saisir le mot de passe dans Terminal, puis répondre « exécuté » ou transmettre le résumé sans secret. Après analyse, préparer la reprise exacte de A ; aucune relance automatique, réparation B, restauration, création ou migration à ce stade.

## Diagnostic reçu et reprise proposée

Le [diagnostic de 20:54:25 UTC](../output/qa-import/cor-a-diagnostic-service.json) confirme le faux succès extérieur de l'arrêt : « ok », même processus actif, PID absent, aucune copie datadir ni manifeste. L'option `su -s` n'est pas prise en charge par le binaire macOS et le compte du serveur a `/usr/bin/false` comme shell ; le code du lanceur contient le chemin de suppression du PID après échec de son test. Voir les faits, limites du diagnostic et [proposition concrète de reprise](COR-XAMPP-A-REPRISE.md).

La reprise par SIGTERM normal, identification stricte du processus, copie complète et redémarrage XAMPP a été autorisée par « j autorise », enregistrée puis relue dans DECISION-023 et exécutée par l'utilisateur. Copie vérifiée par le helper root, retour SQL confirmé par une nouvelle connexion ID13, PID présent et nouveau processus identifié. Voir les preuves, destination et limites dans la [fiche de reprise clôturée](COR-XAMPP-A-REPRISE.md). B reste non autorisé ; ne relancer aucune commande historique de ces fiches.
