# Identité visuelle HOMYA — 9 octobre 2026

Demande utilisateur : créer un logo, un favicon et rendre l’accueil plus attirant. Adaptation autorisée, jaune HOMYA conservé, composants et parcours commerce existants préservés.

Logo original : maison en forme de H, ouverture jaune, mot HOMYA en capitales arrondies. Création via l’outil image_gen intégré ; aucune API CLI ou clé utilisée. Favicon assorti créé par édition de ce logo avec le même outil. Masters conservés sous output/imagegen/homya-{logo,icon}-v1.png ; exports publics sous public/branding/ et public/favicon.ico. Exports de taille/format uniquement, alpha du logo conservé ; l’original généré reste conservé. PNG logo720×240, WebP sans perte pour le site ; favicon16/32/48ICO, PNG32/96 et icôneApple180. Pas de promesse de marque déposée ou de recherche d’antériorité.

Accueil : la proposition en deux colonnes a été remplacée à la demande utilisateur par l’ancienne présentation, avec photo pleine largeur et textes superposés. Le nouveau logo, le favicon et les logos d’en-tête/pied de page sont conservés. Les titres et textes principaux antérieurs sont rétablis. Catalogue, prix et fonctionnement restent issus des mêmes composants. Les anciennes mentions non confirmées de transporteur propre, appareils tous sous scellé et facture automatique sont retirées de l’introduction ; aucune nouvelle promesse métier ajoutée. Traductions FR/EN/DE conservées.

## Prompt logo — outil intégré

Use case: logo-brand. Asset type: final horizontal logo for the HOMYA home and heating ecommerce website. Create a polished original flat minimal logo on a genuinely transparent background. One horizontal lockup only: a compact house-shaped monogram incorporating the letter H on the left, followed by the exact word HOMYA on the right (H O M Y A, uppercase, no other text, no punctuation). Strong rounded geometric sans-serif lettering, carefully balanced kerning, contemporary, welcoming, premium but approachable. Colors: very dark warm charcoal #28241b for the wordmark; the house monogram uses the same charcoal with a restrained warm golden yellow #f5c842 accent, clearly distinguishable on both white and yellow backgrounds. The house silhouette must be simple and distinctive, with a subtle open doorway/countershape, not a generic roof pasted over text. The symbol must remain legible as a small favicon. Wide horizontal composition, the complete logo fills most of the canvas with modest even transparent margins; no badge, no slogan, no photo, no mockup, no gradients, no texture, no shadows, no fine details, no additional variants. Crisp clean vector-style edges.

## Prompt favicon — outil intégré, logo référencé

Use case: logo-brand. Edit the referenced HOMYA logo to create its matching favicon / app icon. Keep the exact same house-shaped H monogram with rounded corners, warm dark charcoal structure and golden yellow doorway. Remove the entire HOMYA wordmark: icon only, no letters outside the monogram, no text. Center this exact house monogram, filling 80% of a square canvas. Background solid warm ivory #fffaf0 so the dark symbol is legible in light and dark browser tabs. Flat clean crisp graphic, no shadow, no texture, no additional strokes, no gradient, no mockup. Preserve the identity and proportions of the house mark from the reference.


## Ajustement utilisateur

« Ramène l’ancienne page d’accueil mais tout en y laissant le nouveau format de HOMYA » : vue d’accueil d’origine restaurée depuis la sauvegarde préalable, styles d’accueil ajoutés retirés, titres/intro FR/EN/DE antérieurs rétablis. Logo, favicons et ajustement d’en-tête mobile conservés. L’introduction générale corrigée continue de ne pas annoncer le transporteur propre, des scellés systématiques ou une facture automatique non confirmés. Les captures de la proposition initiale ne décrivent pas le rendu final.

## Vérification finale de l’accueil restauré

Compilation réussie. Neuf tests accueil/navigation/merchandising, 952 assertions réussies. Huit observations navigateur FR/EN/DE, largeurs 320/375/768/1440 selon langue : logo chargé, menus mobiles, focus clavier visible, aucun débordement horizontal ni erreur JavaScript. Liens catalogue et ancre conseil vérifiés ; lien catalogue également fonctionnel sans JavaScript. Trois favicons servis correctement, logo du pied de page chargé. Captures finales françaises mobile/ordinateur inspectées : output/playwright/brand-home-restored-{375,1440}.png. Résultats : output/qa-commerce/brand-home-restored-{junit.xml,browser.json}.

Synchronisation locale sauvegardée : commerce-backup-20261009T184507Z pour la création initiale, 185015Z pour l’en-tête mobile et 185403Z pour le retour à l’ancien accueil. Vue home.blade.php identique à sa sauvegarde préalable ; 18 fichiers courants source/copie concordants, deux anciens assets conservés sur cible, .env cible inchangé. Preuve : output/qa-commerce/brand-home-installation.json. Aucun SQL, changement de service, commande, paiement ou message externe ; ouverture différée locale conservée.
