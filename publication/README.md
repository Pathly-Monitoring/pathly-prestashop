# Dossier de publication du module PrestaShop

Tout ce qu'il faut pour publier le module `pathly`. Rien n'a été soumis, aucun compte n'a été créé et aucune condition n'a été acceptée : ces étapes sont pour Simon.

## Contenu

| Fichier | Rôle |
|---|---|
| `champs-addons.md` | **Champs du formulaire Addons, dans l'ordre, avec le texte anglais à coller** |
| `guide/user-guide-{en,fr}.html` | Source du guide d'utilisation livré en PDF dans `docs/` du module (`node tools/user-guide.cjs`) |
| `fiche-fr.md` | Fiche en français : nom, résumé court, description, FAQ, journal des changements |
| `listing-en.md` | La même fiche en anglais |
| `screenshots/*-fr.png`, `screenshots/*-en.png` | Captures 1440 × 900 faites sur PrestaShop 8.2 en Docker, back-office en français puis en anglais : 1 connexion, 2 propositions de Lumi, 3 réglages, 4 encart du tableau de bord, 5 badge en pied de page |
| `assets/icon.svg`, `assets/icon-{57,128,200,512}.png` | Icône (le `logo.png` du module est l'icône en 200 × 200) |
| `assets/banner-{fr,en}-1280x400.png` | Bannière |
| Archive | Construite par la CI (`build/pathly.zip`) et jointe à chaque release GitHub |

Les captures viennent de la boutique de démonstration de l'image Docker officielle, avec l'API Pathly simulée : le nom « Boutique test », le Flow en échec et la capture d'échec sont des données de test. Le badge et la capture d'échec sont servis par le simulateur.

## Ce qui n'est pas vérifié

- **Module gratuit sur Addons.** Une page d'aide aux partenaires PrestaShop (2024, non relue : accès refusé au robot) indique que seuls les partenaires Elite ou Premium peuvent publier un module gratuit. Le guide du contributeur renvoie vers le service partenaires pour un module gratuit. À confirmer dans l'espace vendeur avant tout autre travail sur Addons.
- **Commission et frais.** Non vérifiés. Addons prélève une commission sur les ventes d'un module payant. Le taux est à lire dans le contrat vendeur.
- **Délai de revue.** Non vérifié. Une ancienne page vendeur annonce une validation technique et marketing en 10 jours.

## Étapes pour Simon

### A. Distribution par archive ZIP (possible tout de suite, sans Addons)

1. Passer le dépôt `Pathly-Monitoring/pathly-prestashop` en public (il est privé). Relire avant : README, `publication/`, aucun secret (la CI le vérifie déjà par construction : aucune clé n'est dans le code).
2. Fusionner la PR « chore(main): release 1.0.0 » ouverte par release-please. La release `v1.0.0` est créée, avec `pathly-1.0.0.zip` et `pathly.zip` en pièces jointes.
3. Lien stable à donner aux marchands : `https://github.com/Pathly-Monitoring/pathly-prestashop/releases/latest/download/pathly.zip`.
4. Sur pathlyhq.com : une page « Module PrestaShop » avec ce lien, les captures et la FAQ (chantier du monorepo, à ouvrir en issue : non fait ici).
5. Côté marchand : Modules > Gestionnaire de modules > Installer un module, puis le zip.

### B. PrestaShop Addons

1. Créer le compte vendeur sur https://addons.prestashop.com avec `simon@pathlyhq.com` (un seul compte, à votre nom, identité claire).
2. Vérifier dans l'espace vendeur si un module gratuit est proposé à un vendeur non partenaire.
   - Oui : prix gratuit, continuer.
   - Non : trois choix, à trancher par vous (c'est un prix) :
     - demander au service partenaires l'accès au module gratuit ;
     - publier à petit prix (le module reste gratuit sur GitHub) ;
     - rester sur la distribution A seulement.
3. Passer `build/pathly.zip` (le nom doit être celui du module) dans le Validator : https://validator.prestashop.com. Les règles qu'il contrôle tournent déjà dans la CI (`tools/validate.php`, php-cs-fixer de PrestaShop). En cas de remarque, la remonter : on corrige dans le dépôt.
4. Créer la fiche produit :
   - textes de `fiche-fr.md` et `listing-en.md` ;
   - captures de `screenshots/` ;
   - icône de `assets/` ;
   - versions compatibles : 1.7.7 à 9 ;
   - catégorie : Administration, ou Outils de performance si elle existe.
5. Addons fournit une clé de module (`module_key`). Elle doit être ajoutée dans le constructeur de `pathly.php` (`$this->module_key = '…';`) avant d'envoyer l'archive : demandez-le, c'est un commit et une nouvelle release.
6. Envoyer l'archive et la fiche en revue. Répondre aux retours de l'équipe technique.
7. Ne jamais solliciter d'avis, ni en acheter (règle de `legal-comptes.md`).

## Clé d'API à créer pour un marchand (rappel dans la fiche)

Clé limitée à un Espace, portées : `org:read scenarios:read scenarios:write runs:read runs:trigger assistant:run assistant:save status:read status:write`.
