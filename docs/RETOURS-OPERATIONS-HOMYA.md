# Retours HOMYA — fonctionnement et limites

Version du 09/10/2026. Périmètre : site local, France, Belgique et Allemagne. Gratuité sous 14 jours et réception au siège, biens volumineux compris : décisions utilisateur déjà confirmées. Aucune quantité de stock publiée.

Le client consulte librement `/fr/retractation`, puis ouvre `/fr/retours/declaration`. Le nom, une identification suffisante du contrat et le choix du téléchargement sont nécessaires. Référence perdue, absence de motif, absence de compte ou absence de RIB ne bloquent pas la déclaration. E-mail et renseignements de transport facultatifs. Le contrôle puis le bouton de confirmation enregistrent la déclaration. L'accusé HTML téléchargeable contient le texte, la référence, la date et l'heure UTC ; il reste lisible hors ligne. Le téléchargement est explicitement choisi avant confirmation. Aucun e-mail n'est prétendu envoyé.

## Réception et sécurité

Les fichiers sont chiffrés avec la clé de l'application dans `storage/app/private/returns/*.encrypted`, permissions 0600, répertoire 0700. Ne pas déplacer vers `public/` ni les joindre à une sauvegarde non protégée. Conserver la clé avec les sauvegardes privées. Le document et la confirmation exigent la session d'origine ; réponses privées non mises en cache et non indexables. Validation des champs, CSRF, limitation des requêtes, verrou de session et confirmation répétée sans doublon. En cas d'échec d'enregistrement, aucune réussite n'est affichée.

L'opérateur peut vérifier les nouvelles références avec `php artisan homya:returns-list` dans la copie utilisée par le serveur. La commande n'affiche aucun nom, e-mail ni contenu de déclaration. Une revue opérateur régulière est nécessaire ; aucune notification automatique ni tâche de traitement n'est annoncée comme active. Les dossiers privés sont consultables via le service `ReturnRequests`; leur consultation et leur export de données doivent rester réservés aux personnes habilitées. Le dashboard administrateur demandé précédemment reste un chantier distinct.

## Transport, étiquettes et enlèvements

**État observé : aucun contrat, transporteur, identifiant ou accès d'émission d'étiquettes communiqué.** L'utilisateur demande de poursuivre sans préciser un prestataire. Cette instruction permet de préparer le parcours ; elle ne fournit pas un service de transport ni une autorisation de dépense. Aucun achat de transport, rendez-vous, étiquette expédiable, appel de transporteur ou message externe réalisé.

1. Enregistrer la notification indépendamment de la logistique ; l'attente d'une étiquette ne conditionne pas l'exercice du droit.
2. Rapprocher la déclaration et le contrat, identifier les biens, l'adresse de collecte, les contraintes d'accès, le poids/dimensions et l'emballage approprié. Ne pas supposer ces informations à partir du produit.
3. Colis : organiser une étiquette prépayée via un service réellement souscrit. À défaut, la politique actuelle prévoit la prise en charge des frais directs avancés sur justificatifs, et non un coût de retour à la charge du client.
4. Biens volumineux : obtenir la capacité et le tarif d'un transporteur adapté, puis convenir d'un rendez-vous réel. La préférence « enlèvement » dans le formulaire reste une demande. Ne pas afficher « réservé » ni inventer une date avant confirmation du prestataire et du client.
5. Vérifier l'étiquette effectivement émise : destination HOMYA, service, validité, colis/poids, suivi et facturation. Un accusé de rétractation ou une adresse imprimée ne remplace pas une étiquette de transport.
6. Conserver la preuve d'expédition/récupération et les frais pris en charge. Suivre réception, remboursement et preuve financière séparément. Une déclaration, une photo ou une preuve de virement n'entraîne pas un remboursement automatique.

Destination confirmée : HOMYA, 18 Route de la Grange des Noyers, 91150 Morigny-Champigny, France. Aucun magasin, dépôt partenaire ou créneau d'accueil libre n'est inventé.

## Délais et garanties

Notification dans les 14 jours suivant réception ; renvoi au plus tard 14 jours après notification sauf récupération proposée par HOMYA. Remboursement des sommes dues dans les 14 jours après notification, avec report possible jusqu'à récupération ou preuve d'expédition, première date retenue, sauf si HOMYA propose de récupérer le bien. Même moyen de paiement, sauf accord exprès sans frais. Le remboursement effectif exige un traitement financier distinct.

Pas de frais forfaitaires de remise en stock, ni exclusion générale des produits ouverts, soldés ou volumineux. Emballage protecteur recommandé, emballage d'origine non imposé comme condition du droit. Garanties légales séparées : elles ne sont pas limitées à 14 jours, et la mise en conformité est sans frais nécessaires de transport.

## Google : préparation, pas approbation

Sources officielles vérifiées le 09/10/2026 :
- [Merchant Center — conditions de retour](https://support.google.com/merchants/answer/14011730?hl=fr) : accès sans compte, cohérence du site et du compte, méthodes, frais, délais et remboursement visibles.
- [Merchant Center — transparence](https://support.google.com/merchants/answer/6150127?hl=fr) : informations exactes et processus de retour effectivement utilisable.
- [Merchant Center — règlement](https://support.google.com/merchants/answer/9158778?hl=fr) : politiques et moyen de contact accessibles.
- [L221-21](https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000053310520) et [D221-5, version au 19/06/2026](https://www.legifrance.gouv.fr/codes/id/LEGIARTI000053303365/2026-06-19) : déclaration en ligne, confirmation et accusé durable.
- [Rétractation : délais et remboursement](https://www.service-public.gouv.fr/particuliers/vosdroits/F10485).

Avant paramétrage Merchant Center, confirmer le transport réellement disponible et ses pays : retour postal, téléchargement d'étiquette seulement si réellement fourni, collecte adaptée hors des champs proposés par le compte. Ne pas déclarer une étiquette incluse ou disponible immédiatement sans service effectif. Ne pas réduire les retours aux produits défectueux ou non ouverts. Gratuité HOMYA et absence de frais forfaitaires à conserver. Le champ Google sur la fenêtre de retour doit être interprété sans supprimer le délai légal distinct de renvoi après notification. Le délai de remboursement ne doit pas être présenté comme un simple délai débutant arbitrairement à la réception.

**Readiness : en cours.** Aucun compte Google modifié, aucune soumission ou approbation vérifiée. Transporteur/étiquettes/enlèvements réels, remboursement opérationnel, coordonnées électroniques et téléphoniques du vendeur, hébergeur public, organisation des conservations/suppressions de données et contrôle final des informations de garanties demeurent à finaliser. Pour les CGV françaises, le résumé ajouté ne remplace pas l'intégration du modèle réglementaire complet ni la notice européenne harmonisée dans son format officiel. Les droits nationaux et les modalités des biens numériques nécessitent une revue adaptée avant vente publique.
