# HOMYA — corrections après recette du catalogue

Phase : correction ciblée du catalogue consultable local, 08/10/2026 — DECISION-050.

## Résultat vérifié

- Compteurs résultats, univers et catalogue : singulier/pluriel FR/EN/DE. Zéro et pluriels conservés ; aucun sélecteur de traduction affiché.
- Bosch KGN36NWEA : titre et volume total affichés à **305 L**, dans les trois langues, dans les listes et la fiche. Compartiments **216 / 89 L** et prix archivé **44730 centimes** conservés.
- Correction de présentation uniquement, gardée par slug, référence, nom source et traduction exacts ; le total détaillé attendu doit également correspondre. Changement de source/traduction/modèle : aucune substitution automatique. Aucune réécriture des archives ou tables SQL.
- Source de lecture effective du site installé : SQL XAMPP. Dix fichiers applicatifs/tests/manifest synchronisés vers htdocs avec empreintes et sauvegarde privée ; dernier durcissement du service recopié séparément avec sauvegarde.

## Faits et sources primaires

| Source | URL | Consultation | Portée et fait publié |
|---|---|---|---|
| Bosch, Produktdatenblatt KGN36NWEA, daté 06.12.2025 | https://media3.bosch-home.com/Documents/eudatasheet/de-AT/KGN36NWEA.pdf | 08/10/2026 | Pages 1–2 : modèle exact, total 305 L, réfrigération 216 L, congélation 89 L ; capacité de congélation 10 kg/24h. Fiche fabricant UE en allemand/Autriche, pas une certification de l'offre HOMYA. |
| Bosch Belgique, KGN36NWEA — aperçu technique | https://www.bosch-home.be/fr/product/KGN36NWEA | 08/10/2026 | Volumes des compartiments 216 / 89 L. Aucune reprise de disponibilité commerciale du fabricant dans HOMYA. |
| Bosch, fiche technique KGN36NWEA, version fr-LU | https://media3.bosch-home.com/Documents/specsheet/fr-LU/KGN36NWEA.pdf | 08/10/2026 | PDF consulté, dimensions 1860×600×660 mm, poids net 62,1 kg, autonomie 12 h. Référence Luxembourg : confronter au modèle effectivement fourni avant correction des autres valeurs. |

Le total 305 L est publié dans la fiche UE ; il ne repose pas uniquement sur l'addition interprétée de 216 + 89. La recherche indexée et la fiche fr-LU ouverte diffèrent de présentation : la fiche UE ouverte est la preuve retenue pour le total.

## Contrôles exécutés

- 42 tests ciblés hors base, **917 assertions**, sans erreur/échec : projection ciblée et refus d'altérer un autre modèle/source/traduction, catalogue, recherche Froid, filtres, contexte/pagination et pluriels.
- Pint des fichiers PHP modifiés : réussi. Build Vite : réussi ; CSS/JS inchangés (mêmes noms et octets attendus).
- **15 GET HTTP réels** sur le site installé : FR/EN/DE × zéro/un/tous résultats Bosch, recherche du modèle et fiche ; titres, total et compartiments, prix vérifiés.
- **8 observations navigateur** : changements de langue, fiche et compteurs à 375/768/1440 px ; aucun débordement observé, photo mobile décodée, aucune erreur/alerte console capturée. Ces contrôles ne certifient pas tous les navigateurs ni WCAG.
- Empreintes avant/après conservées pour .env, index, fiche archivée, dictionnaires et configurations applicatives protégées ; fichiers source et htdocs contrôlés. Aucune commande SQL persistante, migration/import ou configuration de serveur.

Revue indépendante de l’agent Qualité : lecture du correctif et de la fiche fabricant, aucun défaut bloquant confirmé dans le code ; écart 10/11 kg par 24 h confirmé. Cet agent n’a pas réexécuté les tests de l’agent principal.

## Points ouverts — À vérifier

La comparaison de ce modèle fait apparaître d'autres écarts non modifiés dans ce lot :

| Caractéristique | Archive HOMYA | Fiche Bosch consultée | Décision dépendante |
|---|---|---|---|
| Autonomie | 19 h | 12 h (fr-LU) | Confirmer révision effectivement fournie ; correction publique à préparer. |
| Congélation | 11 kg/24h | 10 kg/24h (fiche UE) | Revue de la donnée fournisseur avant réconciliation du reste de la fiche. |
| Largeur | 60,1 cm | 60 cm (fr-LU) | Confirmer variante et convention de dimensions. |
| Poids net | 60,8 kg | 62,1 kg (fr-LU) | Confirmer révision/modèle fournisseur. |

La totalité des caractéristiques n'est donc pas déclarée validée. Des photos et la description restent masquées conformément au contrôle des images déjà décidé ; ce lot ne modifie pas cette règle. Catalogue consultable ; commandes/paiement et ouverture commerciale restent différés, avec données entreprise/stock/livraison/TVA encore manquantes.

## Preuves et retour arrière

Preuves : `output/qa-catalogue-corrections-20261008/{before.json,protected.json,tests.xml,installation.json,http-evidence.json,browser-evidence.json,fiche-corrigee.png,verification.json}`. Sauvegarde privée dans le reçu installation ; restauration des seuls fichiers remplacés possible sans toucher aux archives/SQL. Restauration non exécutée pendant cette phase.

Prochaine étape : revoir les autres écarts de caractéristiques signalés et les éléments de contenu encore masqués, avec traçabilité par modèle et source fabricant.
