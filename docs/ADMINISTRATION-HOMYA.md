# Administration locale HOMYA

Accès : http://127.0.0.1:8080/admin (connexion : `/admin/connexion`). Installation servie : `/Applications/XAMPP/xamppfiles/htdocs/HOMYA`.

État des paiements au 10 octobre 2026 : Stripe retiré à la demande explicite de l’utilisateur. La page Paramètres conserve les coordonnées HOMYA et le RIB, sans champs Stripe. L’enregistrement des réglages ne requiert plus de mode ou de clé de ce prestataire ; mot de passe administrateur, version et sauvegarde chiffrée restent obligatoires. Le formulaire carte public est conservé et désactivé, en attente de configuration d’un prestataire. Les commandes, réservations et références historiques de paiement restent conservées ; aucune migration. Recette finale : 417 tests / 10 320 assertions, sept affichages réels du formulaire et six aperçus SEPA fictifs. Détails et sauvegarde dans `docs/PARCOURS-ACHAT-HOMYA.md`, section Retrait du prestataire carte.

## Fonctionnalités

- Vue d’ensemble : commandes enregistrées, paiements à suivre, montants TTC des commandes `paid` en EUR, produits en inventaire, unités réservées et disponibilité des canaux de paiement.
- Commandes : filtre par statut, pagination, détail des articles et coordonnées déchiffrées exclusivement après authentification.
- Produits et stocks : recherche par référence ou identifiant produit, prix TTC validé, quantités totales/réservées/disponibles et statut de vente. Ces quantités restent privées ; le catalogue public conserve uniquement son indicateur de disponibilité.
- Retours : déclarations enregistrées et détail, avec signalement des dossiers illisibles. Une déclaration ne réserve pas un transporteur, ne crée pas une étiquette et ne déclenche pas de remboursement.

Lorsque `HOMYA_ADMIN_MANAGEMENT_ENABLED` est activé après installation autorisée des six tables, le dashboard permet aussi l’édition des produits, les réponses aux clients, la confirmation explicite d’un virement effectivement reçu, l’annulation des commandes impayées sans tentative carte en attente et le suivi de livraison. Le socle reste consultable sans cette extension. Les montants payés ne constituent pas un chiffre d’affaires net comptable : les remboursements et leur historique ne sont pas encore intégrés. L’expiration des réservations reste assurée par la tâche commerce existante, pas par la consultation du dashboard.

## Compte et protections

Cette première administration est réservée à l’environnement local et à une connexion loopback réelle avec un hôte local. Elle est refusée en production, y compris lorsque des en-têtes transférés prétendent provenir de localhost. La rendre accessible sur un serveur distant nécessite un cadrage spécifique d’authentification, de rôles et d’exploitation.

Le compte unique se configure dans `.env` : `HOMYA_ADMIN_USERNAME` et `HOMYA_ADMIN_PASSWORD_HASH`. Aucun compte public ni migration de table `users` n’est nécessaire.

Création interactive (PHP 8.3+ ; PHP 8.5 local) :

```sh
cd /Applications/XAMPP/xamppfiles/htdocs/HOMYA
/opt/homebrew/bin/php artisan homya:admin-create
```

Le mot de passe est saisi masqué et haché. L’option `--generate` crée des accès aléatoires dans un fichier `storage/app/private/admin/access-*.txt`, permissions 0600, sans les afficher dans la sortie ni les intégrer au dépôt. Copier ces accès dans un gestionnaire de mots de passe. Les anciennes valeurs de `.env` sont sauvegardées dans le même dossier privé avant modification. La commande refuse de remplacer un compte existant ; `--rotate` sert au remplacement volontaire et révoque les sessions à leur prochaine requête.

- CSRF sur connexion/déconnexion, limitation à 5 tentatives par minute et 30 par heure depuis le poste.
- Identifiant de session et jeton CSRF renouvelés à la connexion et déconnexion.
- Expiration après 30 minutes d’inactivité, durée absolue 8 heures et révocation lors d’un changement des accès.
- Pages sans cache ni indexation ; scripts et rafraîchissement des notifications limités au site lui-même et non intégrables dans une iframe.
- Messages d’erreur sans données SQL ni secrets ; données client échappées dans Blade.

Les secrets ne doivent pas être partagés dans le chat. Aucun paiement, email, accès bancaire ou réservation de transport n’est exécuté par l’installation.

## Vérification

Tests d’authentification, restrictions réseau/environnement, expiration, changement de hash, limitation des tentatives, protection des données, lecture sans mutation et configuration privée. Les fixtures SQL utilisent SQLite en mémoire et ne migrent pas MariaDB locale. Vérifications locales complémentaires : connexion réelle, navigation responsive, recherche et pagination sur l’inventaire existant, déconnexion et refus CSRF.

### Résultats du 9 octobre 2026

54 tests ciblés réussis, 1 716 assertions : administration, commerce, retours, en-têtes de sécurité et navigation de l’accueil. La recette du navigateur vérifie 12 affichages (4 pages aux largeurs 1 440, 375 et 320 px), sans débordement de page ni erreur JavaScript. Connexion, accès aux données réelles, filtrage vide, pagination, détail d’une commande existante, refus d’un POST sans CSRF et déconnexion vérifiés. Le parcours fonctionne également sans JavaScript. Sur mobile, les tableaux conservent des colonnes lisibles dans une zone de défilement horizontal accessible.

L’inventaire installé comporte 3 709 produits lors de cette observation. Aucun ordre, stock, paiement ni demande de retour n’a été créé ou modifié par cette recette. Installation sauvegardée, sans migration ni changement de services ; les réglages de commandes différées ont été conservés. Le compte a été créé dans la copie locale servie ; les données d’accès restent hors dépôt.

Preuves expurgées : `output/qa-commerce/admin-tests.txt`, `output/qa-commerce/admin-browser.json` et captures `output/playwright/admin-*.png`.

## Gestion quotidienne (extension)

- **Messages clients** : ouvrir une conversation, répondre, marquer comme lue. Réponses privées accessibles au client depuis `/fr/assistance`, avec sa référence et son code hors URL. Le client conserve ces accès et actualise la conversation. L’option email apparaît seulement après configuration SMTP et adresse expéditrice HOMYA ; « accepté par le service » ne prouve pas la réception. WhatsApp n’est pas une boîte de réception connectée.
- **Produits et stocks** : ajouter un produit ou cliquer Modifier ; prix TTC final, référence existante stable, stock partagé entre variantes, langues et photo. Le stock total ne peut pas descendre sous les réservations. Sauvegarde simultanée catalogue/inventaire, révision anti-écrasement, source historique conservée. Un nouveau produit nécessite une photo avant publication ; chaque langue publiée exige son nom et sa catégorie.
- **Commandes** : annuler explicitement une commande impayée sans tentative carte en attente ; la réservation est libérée sans toucher aux autres commandes. Confirmer un virement exige montant exact, référence bancaire unique, réception vérifiée et mot de passe administrateur. Les anciennes références de paiement sont conservées pour leur historique.
- **Livraisons** : sur une commande payée, enregistrer préparation puis expédition (transporteur et numéro requis) puis livraison, avec confirmation de l’événement réel. Le client voit ce suivi dans sa commande privée. Pas d’étiquette valable ni d’enlèvement acheté sans service transporteur.
- **Vendeur et paiements** : adresse email, téléphone WhatsApp, RIB professionnel ; formulaire carte conservé en attente de configuration ; formulaire privé et nouvelle vérification du mot de passe. Les secrets restent dans `.env`, les sauvegardes sont chiffrées. Aucun prestataire carte n’est actuellement configuré. La conformité juridique n’est pas basculée automatiquement.
- **Activité** : notifications métier et compteurs quotidiens agrégés ; pas de visiteurs uniques inventés. Montants de commandes payées distincts des conversions attribuées Google/Meta.
- **Google et Meta** : OAuth Google et synchronisations en lecture GA4/Search Console/Ads/Merchant ; accès Meta Insights à configurer. Les comptes et permissions restent à fournir ; aucun service n’est présenté connecté avant vérification effective. GTM et Business Profile ouvrent leurs consoles. Aucun budget, campagne ni balise publicitaire n’est modifié.

Plan SQL et périmètre : `docs/DASHBOARD-GESTION-HOMYA.md`. Préparation hors base : `python3 scripts/install_admin_management.py`. Exécution `--apply --sha256=…` réservée à l’autorisation distincte ; le programme sauvegarde la base complète, crée uniquement les six tables et conserve les données préexistantes. Aucun rollback destructif automatique.

## Extension installée le 9 octobre 2026

L’utilisateur a expressément autorisé cette installation locale. Les six tables sont installées sur XAMPP MariaDB 10.4.28 dans `homya_local`, avec SELECT/INSERT/UPDATE limités aux tables nouvelles pour le compte existant. Sauvegarde complète privée : `storage/app/private/admin/install-20261009T215137Z/`. Les empreintes des neuf tables préexistantes et de l’archive catalogue étaient identiques après installation ; aucun stock remis à zéro. Le module est actif dans les deux copies locales.

Vérifications : suite complète de 377 tests / 9 676 assertions avant les derniers correctifs, puis contrôles ciblés après ceux-ci. Recette réelle de 33 vues de gestion et 12 vues du socle aux largeurs 1440/375/320, sans débordement ni erreur JS. Connexion sans JS, pagination, recherche, consultation de la commande existante, refus CSRF et déconnexion contrôlés. Conversation fictive créée puis réponse admin lue par le client, reprise par code depuis une autre session, réponse client et marquage lu/non lu vérifiés. Un défaut de retour de page lié au rafraîchissement des notifications a été corrigé : requête AJAX identifiée et redirections explicites vers les écrans concernés.

Deux brouillons nommés `RECETTE DASHBOARD — ne pas publier` sont conservés pour la recette : zéro stock, vente désactivée et URL publique refusée. Deux conversations clairement fictives sont également conservées. Aucune commande réelle modifiée. Une image de recette marquée « NE PAS PUBLIER » a été ajoutée à un brouillon, convertie en WebP et affichée ; une deuxième photo a validé la galerie. Aucune photo de recette n’est publiée comme photo commerciale.

La recette MariaDB des annulations et livraisons utilise exclusivement une transaction annulée et des objets fictifs : annulation répétée sans double libération, stock total conservé, livraison interdite avant paiement, confirmation et révision requises, progression préparation/expédition/livraison, annulation d’une commande payée refusée. Après rollback : une commande existante et une unité réservée, empreintes des tables métier inchangées.

Preuves expurgées : `output/qa-commerce/admin-management-{live,upload,mariadb,visual}.json`, `admin-browser.json` et captures `output/playwright/admin-management-live-*.png`. Les codes privés et identifiants ne figurent pas dans les rapports. Le paramétrage Google/Meta a été testé sur réponses simulées ; les comptes externes restent à configurer et ne sont pas connectés. SMTP, numéro WhatsApp, RIB restent à fournir ; le formulaire carte attend une configuration. Aucune communication externe ni dépense exécutée.

La notice FR/EN/DE décrit les conversations et les agrégats internes. Sources relues : [information des personnes, CNIL](https://www.cnil.fr/fr/conformite-rgpd-information-des-personnes-et-transparence) et [mesure d’audience, CNIL](https://www.cnil.fr/fr/cookies-solutions-pour-les-outils-de-mesure-daudience). Pas de certification CNIL ou Google annoncée. Les durées et procédures de suppression avant mise en ligne restent à finaliser. Le lecteur Merchant v1 utilise les champs documentés de [Product](https://developers.google.com/merchant/api/reference/rest/products_v1/accounts.products).

## Documents commerciaux

Accès : http://127.0.0.1:8080/admin/documents (connexion administrateur habituelle).

1. Dans « Documents commerciaux », choisir **Devis**, **Bon de commande** ou **Facture**. Pour reprendre une vente enregistrée, ouvrir la commande et utiliser « Documents de cette commande ».
2. Vérifier le client et l’adresse de facturation. Renseigner les articles d’un document libre ; les articles et prix d’une commande liée sont protégés. « Ajouter un article » / « Retirer cet article » permettent de gérer les lignes ; sans JavaScript, les lignes déjà enregistrées restent modifiables.
3. Renseigner le régime et les taux de TVA réels, les éventuelles éco-participations déjà incluses dans le TTC, les mentions de garantie et les conditions commerciales. Aucun taux de TVA n’est présumé. L’autoliquidation demande un client professionnel identifié et son numéro de TVA. Les totaux sont recalculés **à l’enregistrement**, en centimes, avec arrondi de TVA par ligne et regroupement des taux identiques.
4. Pour un devis, indiquer la date de validité. Pour une facture, indiquer la date réelle de vente/livraison, l’échéance et l’escompte ; pour un professionnel, compléter son identité et les pénalités applicables. Un avoir ou une facture d’acompte nécessite un processus dédié.
5. Enregistrer puis consulter le PDF du brouillon (marqué **BROUILLON — NON ÉMIS**). Le panneau récapitulatif indique les informations encore manquantes.
6. À la première émission d’un type pour l’année, confirmer le périmètre proposé HOMYA/type/année, le préfixe et le premier numéro disponible **après vérification des autres outils**. Exemples proposés : DEV-AAAA, BC-AAAA, FAC-AAAA. Ils ne constituent pas une numérotation métier déjà validée. La série est ensuite figée et incrémentée sous verrou ; ne pas émettre dans cette série depuis un autre outil.
7. Cocher la confirmation de revue et saisir le mot de passe administrateur pour **Émettre et archiver le PDF**. Les données et le PDF original sont figés ; cliquer deux fois ne crée pas deux numéros. Une facture émise par commande au maximum.
8. Télécharger le PDF pour le remettre au client. Les documents émis liés à une commande sont également accessibles au propriétaire de cette commande dans sa session privée (FR/EN/DE : navigation traduite, PDF administratif en français). Aucun email envoyé automatiquement, aucun paiement ou stock modifié par l’émission.

La reprise d’un document en un nouveau type crée un brouillon distinct et conserve la référence de sa version d’origine. Elle ne signifie pas que le client a accepté le devis, et ne crée pas de commande e-commerce.

Stockage : `storage/app/private/commercial-documents`, chiffré et non public. Inclure ce dossier et la clé de chiffrement privée dans les sauvegardes ; ne jamais supprimer une émission ni réutiliser son numéro. Aucune migration supplémentaire. Fonctionnement local mono-instance ; hébergement distant, archivage long terme, facturation électronique structurée et comptabilité certifiée à traiter selon le besoin réel.

Recette : trois brouillons **RECETTE DOCUMENTS … — NON COMMERCIAL** sont conservés pour démonstration locale. Leur TVA reste non renseignée et leur émission bloquée. Ils ne représentent ni une vente ni une facture réelle.

Conception, limites et sources : [DOCUMENTS-COMMERCIAUX-HOMYA.md](DOCUMENTS-COMMERCIAUX-HOMYA.md).
