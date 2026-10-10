# HOMYA — conception UX/UI

**Version 0.1 — 7 octobre 2026 — PROPOSÉE pour validation de design, sans implémentation.**

**Actualisation du 7 octobre 2026 :** l’utilisateur a ensuite autorisé le lot « Accueil et navigation responsive FR/EN/DE selon WF-01/WF-02 ». Cette validation partielle est persistée dans le registre central, section HOMYA, DECISION-009. L’implémentation et ses preuves figurent dans [la recette du lot](/Users/akm/Documents/ChatGPT/HOMYA/docs/INCREMENT-ACCUEIL-NAVIGATION.md). Les autres pages/wireframes et règles métier restent proposés ou ouverts. Les observations et contrôles ci-dessous décrivent la phase de conception initiale.

**Lot suivant autorisé :** catalogue complet consultable, fiches/recherche/filtres avec prix et images DAMDAM, puis corrections et amélioration de pagination demandées par l’utilisateur (HOMYA DECISION-010/011). Voir [la recette catalogue](/Users/akm/Documents/ChatGPT/HOMYA/docs/INCREMENT-CATALOGUE-CORRECTIONS.md). Cette portée ne valide pas les parcours de panier, commande, paiement, comptes ou administration proposés ci-dessous.

Projet confirmé : `/Users/akm/Documents/ChatGPT/HOMYA`. Ce document et les [wireframes PDF](/Users/akm/Documents/ChatGPT/HOMYA/output/pdf/HOMYA-WIREFRAMES-v0.1.pdf) décrivent une expérience cible. Les blocs de données et photos sont des emplacements de conception, pas un catalogue ni une boutique fonctionnelle.

## 1. Sources et portée

- [Cahier des charges](/Users/akm/Documents/ChatGPT/HOMYA/docs/CAHIER-DES-CHARGES-HOMYA.md) : REQ-001 à REQ-035, critères AC correspondants et PAR-01 à PAR-07.
- [Architecture et plan](/Users/akm/Documents/ChatGPT/HOMYA/docs/ARCHITECTURE-ET-PLAN-HOMYA.md) : application Laravel/Blade, composants partagés, données serveur, interfaces publiques/admin et intégrations conditionnelles.
- [Registre unique](/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md), section HOMYA : huit décisions relues ; adaptation DAMDAM avec identité originale et droits de réutilisation déclarés. Aucune copie de fichier photo réalisée dans cette phase.
- Inspection : layout et accueil Blade, styles, langues et arborescence lus ; seule la page d’attente existe, sans catalogue, prix, comptes ou checkout. Le symbole maison/flamme et les couleurs actuelles constituent un point de départ, pas une marque enregistrée ou une charte approuvée.

La demande désigne cahier et architecture comme validés ; les fichiers portent encore des statuts de proposition, et leurs approbations globales ne figurent pas dans les huit entrées du registre. Cette lacune de traçabilité reste à réconcilier avant l’implémentation dépendante. Les documents servent de références pour concevoir ; ni leurs inconnues ni les présentes propositions ne deviennent des règles métier acceptées.

**Qualification :** Décision validée = portée du registre ; Fait vérifié = fichier/observation cité ; Hypothèse ou Recommandation = solution proposée ; Inconnu / À confirmer / Décision nécessaire = élément ouvert. « Bloquante » précise l’étape concernée. Les critères UX ci-dessous sont des critères de recette future, pas des tests déjà réussis.

## 2. Principes d’expérience

**Recommandation :** une boutique lisible par univers, avec recherche visible et fiche produit factuelle. Une action principale par étape ; pas d’achat rapide depuis une carte quand variante/unité doit être comprise. Présenter les conditions et le coût connu avant l’engagement. Un changement de prix, une disponibilité inconnue ou un paiement incertain doit être compréhensible et récupérable.

L’offre reste bois/combustibles, appareils de chauffage, électroménager, mobilier et TV ; les catégories détaillées et l’inventaire réel restent À confirmer. Aucun rayon validé n’est écarté par la mise en page. La langue de lecture FR/EN/DE et la destination de livraison FR/BE/DE sont distinctes. Le visiteur peut explorer sans inscription ; achat invité/compte facultatif/obligatoire reste une décision métier avant checkout.

## 3. Arborescence proposée

```text
HOMYA / {fr | en | de}
  Accueil
  Catalogue / univers
    Bois & combustibles
    Appareils de chauffage
    Électroménager
    Mobilier
    Téléviseurs
      Catégories réelles à confirmer
        Produits / variantes et unités approuvées
  Recherche et résultats filtrés
  Panier
    Commande : coordonnées -> livraison -> récapitulatif et paiement
      Retour de paiement / confirmation
  Suivre une commande : accès protégé -> détail de suivi
  Aide : contact/SAV, livraison, retours, FAQ si contenu validé
  HOMYA : présentation et informations vendeur approuvées
  Documents : vente, confidentialité, cookies selon analyse retenue
  Compte client [conditionnel]
    Connexion/récupération -> profil, adresses, commandes autorisées
Administration [hors navigation publique, authentifiée]
  Connexion -> catalogue/publication -> commandes/rapprochement
  Accès et actions limitées à la matrice de permissions approuvée
```

En-tête public : marque vers accueil, recherche nom/référence, catalogue, aide/suivi, panier avec quantité réelle, langue. L’accès compte apparaît seulement s’il est retenu. Pas de menu de tous les produits ni de lien admin pour le public. Footer : identité/contact validés, aide et documents utiles ; aucune certification ou coordonnées DAMDAM présentée comme HOMYA.

URLs : préfixe de langue et routes nommées selon architecture ; slugs et éventuelle traduction des chemins à confirmer avant publication. Le changement de langue conserve produit/panier et destination quand ils existent ; il signale une traduction absente sans afficher une autre langue comme traduction validée.

## 4. Pages et critères d’acceptation UX

P désigne une page ou famille d’écrans ; les étapes de checkout sont logiques et ne fixent pas seules trois URLs. Chaque **UX-Pxx** est un critère de recette futur lié aux exigences existantes. Toutes les pages héritent des critères transversaux de la section 8.

| ID / page | Hiérarchie et action principale proposées | Critère observable | Traçabilité et dépendance |
|---|---|---|---|
| P01 Accueil | Message descriptif HOMYA → « Explorer le catalogue » → cinq univers → sélection seulement si réelle → aide. | UX-P01 : chacun des cinq univers et la recherche sont accessibles ; aucune preuve ou sélection fictive. | PAR-01 ; REQ-001 à REQ-005 ; contenu d’accueil. |
| P02 Univers / catalogue | Titre → catégories réelles → produits pertinents ; « Voir les produits ». | UX-P02 : l’acheteur distingue univers/catégorie/produit et peut revenir à son parent. | PAR-01 ; REQ-001, REQ-005 ; taxonomy approuvée. |
| P03 Catégorie | Fil d’Ariane → titre → résultats, filtres/tri → cartes → pagination. | UX-P03 : filtre actif et nombre réel de résultats visibles ; remise à zéro et retour à la liste conservent le contexte prévu. | PAR-01 ; REQ-005, REQ-026 ; attributs et tri. |
| P04 Recherche | Requête conservée → résultats → affinage ; « Rechercher ». | UX-P04 : requête vide, référence connue et aucun résultat ont une issue claire ; aucun filtre ne suggère une donnée absente. | PAR-01 ; REQ-005 ; contrat de recherche. |
| P05 Produit | Nom/référence → média → prix/unité/disponibilité → variante/quantité → « Ajouter au panier » → caractéristiques/livraison. | UX-P05 : unité et sélection sont explicites ; achat impossible si données indispensables manquent ; contenu concordant avec fiche approuvée. | PAR-02 ; REQ-006 à REQ-011 ; données métier. |
| P06 Panier | Lignes éditables → sous-total/frais connus → total ou état de calcul → « Continuer la commande ». | UX-P06 : changer/retirer un article recalcule le récapitulatif et annonce les écarts ; frais inconnus jamais affichés à zéro implicitement. | PAR-02/03/04 ; REQ-008 à REQ-011. |
| P07 Coordonnées | Progression → champs nécessaires → destination → récapitulatif → « Continuer vers la livraison ». | UX-P07 : libellés persistants, correction des champs et reprise depuis l’étape suivante sans ressaisie inutile ; aucun champ sans finalité. | PAR-03/04 ; REQ-012, REQ-023, REQ-024 ; mode client/champs. |
| P08 Livraison | Destination rappelée → méthodes réellement disponibles → prix/conditions → « Continuer vers le paiement ». | UX-P08 : destination non desservie signalée avant engagement ; modifier l’adresse ne conserve pas une méthode devenue invalide. | REQ-011, REQ-012, REQ-018 ; règles logistiques. |
| P09 Récapitulatif / paiement | Articles, adresses, livraison, total → carte/virement → informations/accords retenus → action finale explicite. | UX-P09 : toutes les informations peuvent être revues ; CTA et total distinguent carte et virement ; aucune option payante présélectionnée par commodité UX. | REQ-009, REQ-012 à REQ-015, REQ-033 ; cadre vendeur/prestataire. |
| P10 Retour de paiement | Résultat réellement connu → référence → prochaine action sûre. | UX-P10 : succès, refus, abandon et résultat en attente sont distincts ; un retour navigateur ne déclare pas seul « payé ». | REQ-013, REQ-015 ; états validés. |
| P11 Confirmation | Référence et état commande → état paiement séparé → récapitulatif → « Suivre ma commande ». | UX-P11 : virement attendu reste distinct de paiement reçu ; coordonnées/instructions réelles uniquement ; aucune date de livraison inventée. | REQ-014 à REQ-017, REQ-021. |
| P12 Accès au suivi | Méthode protégée choisie → « Consulter le suivi » ; aide d’accès. | UX-P12 : un accès invalide reçoit une réponse neutre sans révéler une commande ; tokens absents de la mesure/logs publics. | PAR-05 ; REQ-017, REQ-025 ; contrat d’accès. |
| P13 Suivi | Référence → paiement/commande/livraison séparés → événements sourcés → aide. | UX-P13 : seules les étapes réelles sont marquées atteintes ; détail limité à la commande autorisée ; aucune fausse progression. | REQ-016 à REQ-018 ; source de suivi. |
| P14 Contact / SAV | Canaux disponibles → motif/champs nécessaires → « Envoyer la demande ». | UX-P14 : confirmation uniquement après réception effective ; échec préserve la saisie autorisée ; aucun conseiller/délai promis sans preuve. | REQ-018, REQ-021, REQ-024 ; exploitation réelle. |
| P15 Livraison / retours | Sections repérables → conditions approuvées → procédure → contact. | UX-P15 : la fiche produit et ces pages ne présentent pas de conditions contradictoires. | REQ-011, REQ-018, REQ-033 ; textes validés. |
| P16 Présentation / FAQ | Qui vend / offre décrite → réponses sourcées → aide ou catalogue. | UX-P16 : seules les informations réelles apparaissent ; une FAQ absente n’est pas remplacée par des réponses métier inventées. | REQ-002, REQ-006, REQ-018, REQ-033. |
| P17 Documents / confidentialité / choix de mesure | Document lisible et version pertinente → contacts/procédures prévus ; réglages si applicables. | UX-P17 : liens accessibles depuis le contexte utile ; choix modifiable et information compréhensible selon cadre validé. | REQ-024, REQ-027, REQ-033 ; traitements/textes à vérifier. |
| P18 Connexion / inscription / récupération client [conditionnel] | Champs minimaux → action nommée → récupération et retour au parcours. | UX-P18 : le mode client approuvé est respecté ; coller/utiliser un gestionnaire de mots de passe possible ; erreurs neutres. | PAR-07 ; REQ-023, REQ-025. |
| P19 Espace client [conditionnel] | Commandes autorisées → détail ; profil/adresses seulement si retenus. | UX-P19 : l’accès direct à une commande tierce échoue ; état sans commande propose le catalogue, sans inciter artificiellement à acheter. | PAR-07 ; REQ-020, REQ-023 à REQ-025. |
| P20 Connexion administration | Identité de l’espace → authentification retenue → reprise du travail. | UX-P20 : échec ou expiration reste clair et sans fuite ; aucune inscription admin publique ni accès attribué automatiquement. | PAR-06 ; REQ-020, REQ-025 ; authentification/rôles. |
| P21 Administration catalogue | Liste/filtre → détail/édition → complétude → aperçu/enregistrement/publication autorisée. | UX-P21 : brouillon et publié distincts ; champs manquants expliqués ; changement non enregistré averti ; pas de publication silencieuse. | REQ-019, REQ-020 ; règles de publication. |
| P22 Administration commandes | Liste → détail → états/historique → action permise, rapprochement ou exception. | UX-P22 : montant, paiement et exécution distingués ; action sensible présente portée et confirmation ; double clic ne double pas l’effet. | REQ-014 à REQ-016, REQ-019, REQ-020. |
| P23 Erreurs 404 / accès / service indisponible | Message neutre → retour sûr ou reprise adaptée → aide si disponible. | UX-P23 : aucun détail SQL/secret ; aucune redirection incitant à repayer une opération incertaine. | REQ-017, REQ-025, REQ-034. |

## 5. Parcours, actions et hiérarchie

**PAR-01 découverte :** accueil → univers/catégorie ou recherche → affinage → fiche. L’action principale de la carte est « Voir le produit ». Sur mobile, afficher « Filtrer et trier » et les filtres actifs ; le nombre de résultats vient des données, pas d’un nombre décoratif.

**PAR-02 choix :** fiche → unité/variante/quantité → ajout → confirmation non bloquante avec nom et sélection → « Voir le panier » ou « Continuer les achats ». Recommandation : pas de tiroir panier automatique prenant le focus à chaque ajout ; annoncer l’ajout et laisser le choix. Le bouton n’est actif que si les données et règles serveur rendent le produit achetable.

**PAR-03/04 commande :** panier → coordonnées → livraison → récapitulatif/paiement → résultat → confirmation/suivi. Recommander trois étapes courtes avec progression textuelle et liens de modification ; le récapitulatif est visible au desktop, accessible en bloc dépliable au mobile. L’achat invité est proposé comme option à arbitrer, jamais installé comme décision déjà prise.

CTA final proposé pour la carte : « Commander et payer [total validé] » ; pour le virement : « Confirmer la commande par virement », accompagné du montant et du fait que le paiement reste attendu. Ce sont des formulations de conception **À confirmer avec le cadre commercial/juridique** ; ni le texte ni son état ne certifient un paiement. Pas de CTA générique « Valider » pour un engagement financier.

**PAR-05 suivi/assistance :** confirmation ou accès protégé → état sourcé → « Contacter le support » ; demande de retour seulement si procédure retenue. Virement : instructions/référence approuvées, pas de bouton « J’ai payé » transformant une déclaration client en paiement confirmé.

**PAR-06 exploitation :** accéder selon rôle → liste → détail → action permise → retour contextualisé. Recommander une entrée opérationnelle (catalogue/commandes/actions à traiter) plutôt qu’un tableau de KPI vide ou inventé. Les droits financiers ne sont pas déduits des droits catalogue. **PAR-07** reste une branche conditionnelle ; aucun écran de fidélité ou abonnement ajouté.

## 6. États vides, erreurs et récupération

| État | Message de conception / action | Critère UX |
|---|---|---|
| Catalogue vide | « Aucun produit publié dans ce rayon » ; revenir au catalogue/aide réelle. | Pas de cartes de démonstration présentées comme ventes réelles. |
| Aucun résultat | Rappeler la recherche ; modifier la requête ou retirer les filtres. | Saisie conservée, filtres visibles, réinitialisation sans perdre le contexte inutilement. |
| Photo manquante | Emplacement neutre nommé, aucune autre référence affichée comme substitut. | Alt/texte utile ; dimensions stables. |
| Donnée produit/traduction manquante | Situation identifiée ; pas de prix, stock ni texte généré comme fait. | État non publiable ou non achetable selon règle validée ; langue manquante signalée honnêtement. |
| Panier vide | « Votre panier est vide » ; « Explorer le catalogue ». | Aucun total/livraison factice ni CTA de commande actif. |
| Prix/stock modifié | Article concerné, changement réel et récapitulatif ; inviter à revoir avant achat. | Modification annoncée ; pas d’engagement silencieux avec l’ancienne valeur. |
| Adresse ou champ invalide | Résumé d’erreurs + erreur près du champ et exemple de format si validé. | Focus au résumé après soumission ; champs reliés aux erreurs ; données autorisées conservées. |
| Destination non desservie | Message explicite ; modifier la destination ou consulter l’aide. | Blocage avant paiement ; aucun coût inconnu converti à gratuit. |
| Paiement refusé/abandonné | Résultat confirmé et moyen de reprendre la commande existante. | Pas de double commande ni effacement arbitraire du panier. |
| Confirmation de paiement en cours | « Le résultat du paiement est en cours de vérification » ; consulter le suivi/aide. | Pas d’étiquette « payé » ni invitation immédiate à repayer ; politique de reprise à valider. |
| Virement attendu | État de commande reçu/paiement attendu ; instructions validées et suivi. | Pas de délai d’expiration ou promesse de réservation inventés. |
| Suivi inaccessible | « Nous ne pouvons pas afficher ce suivi avec cet accès » ; récupération/aide prévue. | Même niveau d’information pour accès inconnu et non autorisé. |
| Session expirée | Message de reprise adaptée ; confirmation des actions critiques si nécessaire. | Pas de perte inutile de champs et pas de resoumission financière automatique. |
| Échec contact/email/service | Distinguer réception et transmission ; reprise sans doublon, aide disponible. | Ne pas afficher « envoyé » avant résultat connu ; secrets absents. |
| Admin sans données/droit | État vide ou accès limité ; création/action uniquement si permise. | Aucune donnée inventée, action financière non autorisée impossible côté serveur. |

Messages publics sans code de base, trace ou nom de serveur. Identifiant de diagnostic non secret possible pour le support. Les libellés sont proposés et devront être traduits/validés ; règles d’échéance, compensation et transitions restent métier.

## 7. Adaptations mobile, tablette et desktop

Plages de travail **recommandées**, à ajuster au contenu plutôt qu’à des appareils précis : mobile 320–767 px, tablette 768–1023 px, desktop dès 1024 px. Les wireframes compressent certaines hauteurs ; ce ne sont pas des mesures de composants finaux.

| Élément | Mobile | Tablette | Desktop |
|---|---|---|---|
| En-tête/navigation | Marque + menu + panier ; recherche sur ligne dédiée ; langue dans menu accessible. | Recherche visible ; univers dans menu compact si nécessaire. | Recherche visible et navigation univers ; langue/aide/suivi/panier repérables. |
| Accueil | Message et CTA puis univers en 1–2 colonnes selon largeur/libellés. Image d’ambiance non prioritaire. | Univers 2–3 colonnes ; introduction courte. | Introduction puis cinq univers ; image d’ambiance secondaire si obtenue. |
| Catalogue | 1 colonne à largeur contrainte, 2 seulement si titres/prix/unités restent lisibles ; filtres en panneau. | 2–3 colonnes ; panneau de filtres si espace insuffisant. | Filtres latéraux et 3 colonnes initiales ; plus seulement si données restent lisibles. |
| Produit | Nom → image → données d’achat → caractéristiques ; action dans le flux. | Galerie + achat côte à côte si la longueur le permet. | Galerie à gauche, achat à droite ; sections techniques ensuite. |
| Panier | Lignes en cartes ; quantité/retrait nommés ; récapitulatif après les lignes. | Cartes/liste large ; récapitulatif en dessous ou côté selon place. | Lignes et récapitulatif latéral ; pas de tableau comprimé. |
| Checkout | Étape courante, progression, résumé dépliable ; champs sur 1 colonne ; action finale jamais masquée par clavier. | Formulaire 1–2 colonnes selon groupes ; total visible. | Formulaire + résumé latéral ; modifications accessibles. |
| Suivi et aide | Statuts verticaux sans faux jalons ; pièces de contexte lisibles. | Sections verticales ou deux colonnes de contexte. | Statuts et détails côte à côte ; mêmes informations/actions. |
| Administration | Listes en cartes conservant référence, état, montant et accès au détail ; priorité lecture. | Navigation repliable, formulaires adaptés ; tableaux seulement lisibles. | Navigation latérale, listes filtrées et détail ; actions sensibles séparées. |

Pas de barre d’achat fixe dans le socle proposé : éviter recouvrement, CTA répété et confusion avant sélection d’unité. Une barre fixe pourra faire l’objet d’un essai ultérieur avec contenu réel et contrôles de focus/clavier. Dialogues/panneaux : ouverture nommée, bouton fermer visible, Échap, focus initial et retour au déclencheur ; si repli natif possible, préférer une solution simple.

Langues : prévoir libellés allemands longs, retours à la ligne et hauteur variable ; pas de prix/CTA tronqué, drapeaux seuls ou largeur fixe imposant des abréviations. Formats lisibles dans la langue sans changer EUR ni la destination. Au changement de langue, ne pas déclencher d’achat ni de déplacement inattendu.

## 8. Accessibilité et critères transversaux

**Objectif recommandé, À confirmer :** WCAG 2.2 niveau AA pour les parcours retenus. Question vérifiée : quels critères officiels encadrent contraste, reflow, focus, erreurs et cibles ? Source primaire [W3C — WCAG 2.2](https://www.w3.org/TR/WCAG22/), recommandation consultée le 2026-10-07. Portée : critères techniques du contenu web ; pas une détermination des obligations HOMYA ni une certification.

Critères futurs **UX-T01 à UX-T08** :

1. UX-T01 — Du lien d’évitement au footer, navigation clavier complète, ordre cohérent, focus visible et non masqué ; menus sans survol obligatoire, pas de piège de focus.
2. UX-T02 — Texte ordinaire avec contraste au moins 4,5:1 ; états/commandes utiles distingués autrement que par couleur ; contrôles et focus vérifiés sur leurs fonds réels.
3. UX-T03 — Zoom texte à 200 % et reflow à une largeur équivalente à 320 CSS px sans perte d’action/contenu ni défilement horizontal global ; cas de tableaux intrinsèquement larges traités séparément.
4. UX-T04 — Titres/headings, landmarks, liens et images informatives nommés ; images décoratives ignorées ; ordre de lecture identique à la logique du parcours.
5. UX-T05 — Champs avec libellés persistants, indication nécessaire/optionnelle, groupes nommés, erreurs associées ; résumé et retour à un champ permettent une correction sans dépendre de la couleur.
6. UX-T06 — Ajout panier, calcul et résultat annoncé aux technologies d’assistance sans répéter des alertes ni déplacer inutilement le focus ; état de chargement compréhensible.
7. UX-T07 — Cibles tactiles recommandées de 44 × 44 CSS px pour les actions usuelles ; aucune cible hors exceptions acceptées ne descend sous le critère AA applicable de 24 × 24. Tester l’espacement et les libellés, pas seulement l’icône.
8. UX-T08 — Animations non indispensables, réduction du mouvement respectée ; authentification sans obstacle de copie/gestionnaire de mot de passe ; choix/engagements relisibles et modifiables avant soumission.

Les seuils ci-dessus sont un objectif de design proposé. Une recette manuelle clavier/lecteur d’écran/zoom s’ajoutera aux contrôles automatisés ; aucune fonctionnalité du site n’a été testée en accessibilité pour cette phase. Vérifier séparément les interfaces hébergées des fournisseurs retenus.

## 9. Direction visuelle HOMYA

**Recommandation :** sobre et chaleureuse, centrée sur les produits et la clarté des unités. Continuité avec la page d’attente : forêt/crème/terre cuite, marque maison/flamme existante à revoir, espaces généreux et hiérarchie typographique calme. Éviter un style promotionnel saturé, un compte à rebours sans règle réelle ou une esthétique évoquant un label écologique absent.

| Token proposé | Couleur / usage | Contraste calculé et limite |
|---|---|---|
| Forêt | `#173D32` ; titres, CTA principal, liens. | Texte forêt sur crème : 10,75:1 ; blanc sur forêt : 12,00:1. |
| Crème | `#F6F2EA` ; fond général. | Préserver les surfaces de lecture ; contrôler textes et états composés. |
| Blanc | `#FFFFFF` ; cartes/champs. | Blanc sur terre cuite : 4,88:1, mais pas d’automatisme de rôle pour toutes les actions. |
| Encre | `#202720` ; corps du texte. | Encre sur crème : 13,70:1. |
| Secondaire | `#47554E` ; aides/légendes. | Sur blanc : 7,84:1. |
| Terre cuite | `#B9522C` ; accent et décoration. | Sur crème : 4,37:1, insuffisant pour texte ordinaire à 4,5:1 ; réserver aux usages vérifiés, pas aux petits textes sur crème. |

Ratios calculés localement le 2026-10-07 à partir de la luminance relative des couleurs opaques ; ils ne constituent pas un audit de toutes les transparences, erreurs, états disabled ou photos. Couleurs d’erreur/avertissement/succès accompagnées d’un texte et pictogramme si utile ; variants à contrôler avant code.

**Typographie proposée :** pile sans-serif système existante pour contenu/contrôles, Georgia ponctuelle pour un accent éditorial ; pas de police distante nécessaire. Corps initial 16–18 px, interligne 1,5–1,7 ; titres à échelle responsive, pas un titre de produit géant. Montants/références lisibles, EUR et unité proches ; la mise en forme ne détermine pas leur fiscalité. Niveaux visuels réversibles après essais avec FR/EN/DE réels.

**Composants visuels :** grille et espacements partagés fondés sur pas de 4/8 px, cartes à contour discret, rayons modérés 8–12 px, CTA primaire plein forêt, secondaire contour, lien tertiaire souligné ; états hover/focus/pressed/disabled/loading documentés. Illustrations et photos ne remplacent pas un titre descriptif. Aucun nouveau logo final livré dans cette phase.

## 10. Composants et critères d’acceptation UX

Composants Blade à proposer après validation ; réutiliser le layout et les styles existants. Identifiants **UX-Cxx**, pas un nouveau registre de décisions.

| Composant | Critère observable | Pages / exigences |
|---|---|---|
| C01 Layout et landmarks | UX-C01 : un main, navigation/footer repérables, titre/langue corrects et lien d’évitement fonctionnel. | Toutes ; REQ-003, REQ-004, REQ-026. |
| C02 Navigation/menu | UX-C02 : ouverture/fermeture clavier, état annoncé et retour au déclencheur ; liens utiles sans survol. | P01–P06 ; REQ-004, REQ-005. |
| C03 Recherche | UX-C03 : libellé nommé, bouton soumettre et requête conservée ; pas de suggestion inaccessible. | P01–P04 ; REQ-005. |
| C04 Langue/destination | UX-C04 : langue nommée et active ; destination distincte ; changement sans perte du contexte autorisé. | Public ; REQ-003, REQ-011. |
| C05 Fil d’Ariane | UX-C05 : parent accessible et page actuelle distinguée ; chemins cohérents. | P02–P05 ; REQ-005, REQ-026. |
| C06 Carte produit | UX-C06 : nom, média honnête et données réelles ; une action nommée vers la fiche, titre non tronqué. | P01–P04 ; REQ-006, REQ-007. |
| C07 Filtres/tri/pagination | UX-C07 : état actif, réinitialisation, ordre et résultats annoncés ; historique navigateur cohérent. | P03/P04 ; REQ-005. |
| C08 Galerie | UX-C08 : image/alt cohérents, commandes nommées ; pas de swipe seul ni zoom piégeant le focus. | P05 ; REQ-004, REQ-006. |
| C09 Unité/variante/quantité | UX-C09 : choix utile et unité explicites ; bornes validées, saisie clavier et erreur compréhensible. | P05/P06 ; REQ-007, REQ-008, REQ-010. |
| C10 Prix/récapitulatif | UX-C10 : montant, EUR, unité et frais connus cohérents ; total inconnu distinct de zéro. | P05–P11 ; REQ-009, REQ-011. |
| C11 Disponibilité/état | UX-C11 : libellé et source correspondants ; pas de vert/rouge seul ni de délai fabriqué. | Produit/suivi/admin ; REQ-010, REQ-016. |
| C12 Bouton/lien d’action | UX-C12 : libellé annonce le résultat ; cible/focus/état vérifiés ; loading ne permet pas un double engagement. | Toutes ; REQ-004, REQ-015. |
| C13 Champ et groupe | UX-C13 : label persistant, aide/erreur liée et saisie adaptée ; choix nécessaire/optionnel visible. | P07–P09/P14/P18/P20 ; REQ-012, REQ-024. |
| C14 Résumé d’erreurs/alerte | UX-C14 : annonce pertinente, erreurs corrigeables, pas de contenu sensible ; aucun focus volé pour une notification simple. | Toutes ; REQ-004, REQ-025. |
| C15 Progression de commande | UX-C15 : étape actuelle et précédentes modifiables ; aucun futur résultat présenté comme atteint. | P07–P09 ; REQ-012. |
| C16 Choix carte/virement | UX-C16 : groupe nommé, moyen et engagement distincts ; résultat incertain non présenté payé. | P09–P11 ; REQ-013 à REQ-015. |
| C17 Suivi/historique | UX-C17 : événements et états réels, sans jalon rempli artificiellement ; détail autorisé seulement. | P11/P13/P22 ; REQ-016, REQ-017. |
| C18 Accordéon/panneau/dialogue | UX-C18 : nom/état, fermeture visible et clavier ; contenu utile accessible, focus restitué si modal. | Filtres/FAQ/summary ; REQ-004. |
| C19 Liste/table admin | UX-C19 : colonnes/titres nommés, tri/filtre, actions propres à la ligne ; mobile conserve les informations utiles. | P21/P22 ; REQ-019, REQ-020. |
| C20 Confirmation d’action sensible | UX-C20 : portée réelle et conséquence visibles, annulation possible avant exécution ; aucune action répétée par double clic. | P21/P22 ; REQ-015, REQ-020. |
| C21 État vide / média manquant | UX-C21 : situation et prochaine action possibles, sans données commerciales décoratives ni image trompeuse. | Toutes ; REQ-006, REQ-034. |

Les confirmations de paiement/stock relèvent du serveur. Les composants UI affichent ces résultats et ne créent pas leurs propres états métier. Textes et variants centralisés ; critères repris en recette avec les AC du cahier, pas dupliqués comme règles commerciales.

## 11. Contenus, preuves de confiance et visuels

| Élément | Statut / origine | Traitement avant publication |
|---|---|---|
| Marque texte et symbole maison/flamme | Présents dans le layout HOMYA ; création locale antérieure. | Charte et usage du symbole À confirmer ; aucune prétention de marque déposée. |
| Couleurs/styles et libellés de page d’attente | Présents localement FR/EN/DE. | Point de départ ; les traductions de boutique restent à produire/valider. |
| Photos produits et notices | DAMDAM/source métier : droits de réutilisation déclarés, mais fichiers non fournis/importés et provenance par actif À vérifier. | Obtenir actifs autorisés correspondant aux références réelles ; inventorier origine/droits/usages. Aucun téléchargement/copier-coller dans cette phase. |
| Photos d’ambiance | Encore à obtenir : actifs autorisés, production originale ou licence adaptée. | Choisir une image correspondant à l’offre, sans suggérer installation/conseil/services non fournis. Pas indispensable au wireframe. |
| Pictogrammes | À créer en SVG simple ou choisir une bibliothèque avec licence vérifiée. | Signification et nom accessibles ; pas de certificat représenté par un pictogramme décoratif. |
| Identité vendeur, contacts et responsables | Inconnu. | Afficher seulement les informations approuvées HOMYA ; pas de nom/conseiller/adresse DAMDAM repris automatiquement. |
| Prix, stocks, unités, taxes, frais, délais | Inconnu ou règles ouvertes selon cahier. | Source et validation métier ; les placeholders PDF restent hors interface publique. |
| Livraison/retours/garanties/SAV | Textes/périmètre à vérifier. | Cohérence entre fiche, panier, commande et aide ; pas de gratuité ou durée inventée. |
| Témoignages, avis, labels, certifications | Aucun élément HOMYA fourni/validé. | Ne pas afficher de note, étoiles, logo de label, nombre de clients ou avis DAMDAM présenté comme achat HOMYA. |
| Paiements/logos prestataires | Prestataire non choisi. | Logos seulement si usage autorisé et moyen réellement disponible ; pas de certification de sécurité fabriquée. |

La confiance viendra d’informations vérifiables : vendeur identifiable, caractéristiques/utilité de l’unité, coût explicite, conditions cohérentes, état de paiement clair et accès réel à l’aide. Ces éléments restent manquants lorsqu’aucune source ne les établit. Aucun achat d’image, generation photo produit ou banque payante utilisé. Une image de produit générée ne saurait prouver l’apparence/caractéristique d’une référence réelle.

## 12. Wireframes livrés pour la revue

[HOMYA — wireframes v0.1, PDF](/Users/akm/Documents/ChatGPT/HOMYA/output/pdf/HOMYA-WIREFRAMES-v0.1.pdf) : neuf planches statiques, non cliquables et non contractuelles.

| Planche | Objet / pages | Décision rendue visible |
|---|---|---|
| WF-01 | Direction visuelle et composants | Forêt/crème/terre cuite, priorité typographique et états. |
| WF-02 | Accueil desktop/mobile, P01 | Recherche et cinq univers avant sélections conditionnelles. |
| WF-03 | Catégorie desktop/mobile, P03/P04 | Filtres latéraux ou panneau, grille et pagination. |
| WF-04 | Produit desktop/mobile, P05 | Unité/variante/quantité près du prix et de l’action, galerie et informations. |
| WF-05 | Panier desktop/mobile, P06 | Lignes modifiables et récapitulatif sans frais fictifs. |
| WF-06 | Checkout desktop/mobile, P07–P09 | Trois étapes proposées, récapitulatif, moyens de paiement distincts. |
| WF-07 | Résultat et suivi, P10–P13 | Paiement en cours/attendu/confirmé distinct de l’exécution. |
| WF-08 | Exploitation desktop/mobile, P21/P22 | Listes et détail opérationnels, droits/actions sensibles séparés. |
| WF-09 | Variantes tablette, P03/P06 | Colonnes intermédiaires et récapitulatif adapté. |

Les cadres barrés nomment les photos à obtenir ; « Prix réel / unité » et « Total validé » désignent un emplacement, sans valeur chiffrée de démonstration. Les CTA représentent les états achetables/complets de recette ; ils ne peuvent être actifs en production en l’absence des données/règles nécessaires. Pour les écrans conditionnels de compte et les textes légaux, la spécification UX suffit à cette phase ; maquettes détaillées après choix du parcours et contenus.

## 13. Validation structurante attendue et suites

**Propositions regroupées à valider avant code d’interface :**

- **Navigation et catalogue :** recherche visible, cinq univers, fiches comme point d’achat après unité/variante ; grille mobile adaptable et filtres en panneau.
- **Commande et récupération :** trois étapes proposées avec modifications et récapitulatif accessible, distinction carte/virement/résultat incertain ; pas de tiroir automatique ni barre d’achat fixe dans le socle. Le mode invité/compte reste un arbitrage métier distinct avant checkout.
- **Identité et composants :** continuité forêt/crème/terre cuite, typo système + accent Georgia et composants partagés ; objectif de recette WCAG 2.2 AA proposé.

Une validation peut être partielle et désigner précisément les groupes/WF concernés. Elle ne confirme pas les valeurs commerciales, l’éligibilité d’un produit, les obligations légales, les prestataires ou les illustrations manquantes. Les choix confirmés seront consignés dans le registre canonique ; aucune entrée VALIDÉE ajoutée par cette proposition.

**Étape suivante après validation :** préciser contenus/actifs du premier lot et règles de publication ; produire les variantes utiles avec données réelles et les traductions ; préparer un lot Blade ciblé et ses contrôles seulement après autorisation de développement. Les prix/stock/livraison/client non décidés bloquent le checkout fonctionnel, pas la revue actuelle. Préférences de police détaillée, espacements et variantes réversibles ne nécessitent pas une succession d’approbations séparées.

## 14. Vérifications de cette phase

Réellement effectuées avant création : inspection des fichiers d’interface et documents, lecture des règles Design/PDF/UI Laravel et du registre, calcul des contrastes des paires opaques et consultation officielle WCAG. Aucun navigateur commercial, formulaire, appel paiement ou contenu DAMDAM importé dans cette phase.

Contrôles de livraison effectués : références REQ/PAR et liens locaux vérifiés ; 23 critères de pages, 21 de composants et 8 transversaux uniques ; empreintes des 46 fichiers applicatifs/conventions comparées et inchangées, hors secrets et fichiers runtime. Le PDF contient neuf pages, toutes avec texte extrait et titre attendu ; chaque planche a été rendue en PNG et inspectée visuellement, sans défaut de chevauchement ou texte tronqué relevé. Contrôle des tables effectué à la relecture finale. Ces contrôles documentaires ne prouvent pas l’accessibilité ni le fonctionnement d’une application non développée. Aucun code applicatif, base, service ou registre de décisions modifié.
