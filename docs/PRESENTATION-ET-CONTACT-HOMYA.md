# Présentation et contact HOMYA — 08/10/2026

## Décisions et observations

Source utilisateur : HOMYA revendeur indépendant ; poêles/chauffage au bois, réfrigérateurs, lave-linge, robots de cuisine et mobilier ; appareils neufs sous scellé ; livraison en 3 à 7 jours par son propre transporteur ; garantie légale de 2 ans ; facture au nom du client. La gratuité France/Belgique/Allemagne reste confirmée par DECISION-054.

Ces déclarations métier sont intégrées en FR/EN/DE à l’accueil, à la présentation, aux informations de provenance et de livraison, ainsi qu’au pied de page. L’archive contient 3 709 produits dont l’état est `neuf`. Cette observation ne prouve pas l’état physique sous scellé, l’existence du transporteur ou sa performance ; ces points proviennent de la déclaration utilisateur. Aucun jour ouvré, point de départ du délai ou seuil de gratuité supplémentaire n’a été supposé. Les archives, prix, base de données, paiements et commandes restent inchangés.

## Sources officielles consultées le 08/10/2026

- [Your Europe — Garanties sur les biens acquis dans l’UE](https://europa.eu/youreurope/citizens/consumers/shopping/guarantees/index_fr.htm). Portée : achats de consommateurs auprès de professionnels dans l’UE ; minimum de deux ans à compter de la réception, avec des protections nationales supplémentaires possibles. L’affichage utilise « garantie légale de conformité » et en allemand « gesetzliche Gewährleistung », sans inventer une garantie commerciale supplémentaire. Les conditions détaillées FR/BE/DE et les CGV devront être confirmées par un professionnel avant l’ouverture des ventes ; aucune conformité n’est certifiée ici.
- [WhatsApp — How to use click to chat](https://faq.whatsapp.com/5913398998672934). Portée : lancement d’une conversation avec un lien `https://wa.me/<numéro international>`, sans envoyer automatiquement un message depuis le serveur. Le centre d’aide indique le format dans son résultat public ; l’extraction intégrale de la page était vide, ce qui ne constitue pas une recette de fonctionnement HOMYA.

## Contact préparé, numéro à compléter

Réponses utilisateur : « je te l enverrai plus tard » pour le numéro et « Boutons WhatsApp et SMS » pour le fonctionnement. Le numéro de la capture DAMDAM ne constitue pas un contact HOMYA confirmé. Aucun destinataire, compte, prestataire, API, tarif, webhook, envoi ou secret n’est supposé ou créé.

Préparation : `HOMYA_CONTACT_PHONE` dans `.env.example`, vide par défaut ; service `ContactChannels` acceptant uniquement un numéro international `+` suivi de 7 à 15 chiffres, premier chiffre non nul ; composant partagé en page Contact et pied de page. Aucun bouton n’est affiché si le numéro est absent ou invalide. Après renseignement du numéro confirmé, les boutons ouvrent `https://wa.me/...` et `sms:+...` dans les applications du client, sans préremplir de message ou envoyer depuis le serveur. Le numéro reste visible comme solution de repli. Les textes et descriptions sont traduits FR/EN/DE. Le fichier `.env` réel reste inchangé.

Les tests emploient un numéro synthétique dans la seule configuration en mémoire ; ils ne suivent pas les liens et n’émettent aucun message. La disponibilité réelle du compte WhatsApp, l’application SMS de l’appareil et la réception des messages restent à vérifier après réception du numéro. Aucun délai de réponse ou permanence de support n’est annoncé.

Les preuves locales sont conservées dans `output/qa-company-contact-20261008/`. Cette étape documente une présentation et ne constitue pas une activation commerciale ou une intégration de messagerie achevée.

## Coordonnées confirmées — 10/10/2026

L’utilisateur a communiqué `support@homya.eu` et `0756894951`. Le numéro français est enregistré au format international `+33756894951` ; le lien WhatsApp est `https://wa.me/33756894951` et le lien SMS est `sms:+33756894951`.

Ces valeurs publiques sont les valeurs par défaut de `config/contact.php` et sont reprises dans `.env.example`. Les paramètres d’environnement restent prioritaires. Le service partagé fournit les coordonnées aux pages Contact, mentions légales, CGV, confidentialité, aux futurs documents commerciaux et aux paramètres d’administration. Le pied de page affiche désormais l’e-mail et le téléphone cliquables ; les boutons WhatsApp du site utilisent le numéro confirmé. Les textes FR/EN/DE annonçant des coordonnées à venir sont corrigés. Les documents commerciaux déjà émis conservent leurs données archivées.

Vérification : les 37 tests existants de contact, informations légales et documents commerciaux passent (3 294 assertions), sans migration ni accès à une base de données réelle. L’installation locale doit être vérifiée sur les pages servies par XAMPP. Aucun message n’est envoyé ; le compte WhatsApp et la réception des e-mails ne sont pas vérifiés par ces tests.
