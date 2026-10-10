# Documents commerciaux HOMYA

Demande fonctionnelle autorisée : DECISION-081 du registre central. Cette conception technique accompagne l’implémentation ; elle n’est pas une validation des règles fiscales manquantes.

## Périmètre

Trois types distincts : devis, bon de commande professionnel, facture de vente. Création libre ou reprise du snapshot d’une commande. Brouillons révisables, aperçu, PDF A4, émission contrôlée, téléchargement et archives privées. Les documents émis liés à une commande deviennent téléchargeables uniquement par son propriétaire dans sa session privée. Le récapitulatif e-commerce existant reste distinct.

L’émission ne crée pas de commande, ne réserve pas de stock, ne confirme pas un paiement et n’envoie pas d’email. Le téléchargement permet la remise manuelle. Avoirs, acomptes, remboursements, signatures et transmission structurée via une plateforme agréée sont hors de ce lot.

## Informations et contrôles

- Identité HOMYA issue de la configuration légale existante, figée à émission. Les trois PDF présentent le nom HOMYA, la forme EURL complète, le capital de 20 000,00 €, le siège à Morigny-Champigny, les SIREN/SIRET, la TVA intracommunautaire, le RCS Évry, les inscriptions RCS/RNE du 20/04/2012 et le gérant David Marechal. Téléphone et email HOMYA figurent uniquement lorsqu’ils ont été fournis et configurés ; aucune coordonnée du médiateur ou des sites de référence n’est utilisée à leur place. Clarification utilisateur du 10/10/2026. Les archives déjà émises conservent leur PDF d’origine.
- Prix TTC confirmés ; régime, taux et mentions fiscales à renseigner explicitement. Aucun taux déduit du TTC, aucune franchise présumée.
- Client, adresses, description / référence, quantité, montants, TVA ou motif légal d’exonération, conditions de paiement et livraison nécessaires avant émission.
- Validité du devis saisie, sans durée arbitraire. Facture : date réelle de vente/livraison, échéance, escompte ; conditions de retard et indemnité légale de 40 € pour le client professionnel. Vérification des éco-contributions / garanties demandée, sans montants inventés.
- Numérotation : proposition distincte par type et année, périmètre HOMYA explicitement confirmé par l’opérateur avant la première émission. Préfixe et premier numéro sont saisis après vérification de l’historique externe. Pas de numéro attribué au brouillon. Pas de réinitialisation silencieuse. La numérotation réelle reste NON DÉCIDÉE tant que cette confirmation n’est pas fournie.
- Confirmation par mot de passe à émission. Données et PDF figés ; duplication crée un nouveau brouillon. Une facture émise par commande au maximum ; une rectification nécessite un processus d’avoir séparé.

## Données et sécurité

Utilisation du stockage privé déjà employé pour les retours : fichiers chiffrés par la clé Laravel, dossiers 0700 / fichiers 0600, verrou exclusif, révisions et écritures atomiques. Une émission est un seul paquet contenant snapshot, numéro, historique, PDF et SHA256 ; l’allocation est calculée sous verrou à partir des émissions effectivement archivées. Un échec de génération ne consomme pas de numéro. Aucune nouvelle table/migration ni modification des droits MariaDB.

Préserver et sauvegarder le dossier `storage/app/private/commercial-documents` avec la clé de chiffrement privée ; perdre cette clé rend les archives illisibles. Aucun effacement automatique. Conservation / archivage long terme à organiser avec les obligations comptables. Le fonctionnement local mono-instance ne constitue pas une architecture multi-serveur ni un système de comptabilité certifié.

## Vérification prévue

Calculs en centimes côté serveur, validations bloquantes, concurrence et unicité, immutabilité, répétition d’émission, contrôle des commandes sources, autorisations admin/client, PDF Unicode et multipage, pages à 1440/375/320 px. Recette isolée avec données et taux fictifs explicitement nommés ; aucune facture fiscale réelle ni message externe.

## Sources vérifiées le 09/10/2026

- https://entreprendre.service-public.gouv.fr/vosdroits/F31808 : mentions des factures, numérotation, TVA, éco-participation et distinction particuliers/professionnels.
- https://www.impots.gouv.fr/facturation-electronique-qu-est-ce-que-ca-change-pour-moi : calendrier et facturation électronique. Un PDF téléchargé ne remplace pas la transmission structurée lorsque celle-ci est obligatoire ; aucun raccordement à une plateforme agréée dans ce lot.
- https://github.com/dompdf/dompdf : moteur PHP Dompdf, accès distant / scripts désactivés.

Les règles fiscales, historiques et conditions propres à HOMYA restent à confirmer ; aucune conformité générale certifiée.

## Vérification de l’identité du 10/10/2026

Les 17 tests documentaires passent (184 assertions), dont le contrôle des coordonnées complètes sur chacun des trois modèles, l’absence de contacts inventés et l’ajout des contacts lorsqu’ils seront configurés. Sept PDF synthétiques régénérés ; devis, bon de commande et facture A4 d’une page inspectés visuellement, facture de cinq pages contrôlée. Aucune émission fiscale réelle ni modification MariaDB.
