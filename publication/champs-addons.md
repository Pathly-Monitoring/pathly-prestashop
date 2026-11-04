# PrestaShop Addons : champs à remplir, dans l'ordre

Pour Simon. Les textes à coller sont en anglais (l'anglais est obligatoire sur Addons). La version française de chaque texte est dans `fiche-fr.md`, à coller dans la langue « Français » si vous l'ajoutez.

Rien n'a été créé ni soumis. Source des règles : documentation vendeur PrestaShop (« Submitting your module ») et liste de contrôle de la validation technique, lues le 04/11/2026.

## 0. Avant de commencer : le prix

Addons n'autorise un module **gratuit** qu'aux partenaires Elite ou Premium (documentation vendeur, lue le 04/11/2026). Trois choix, à trancher par vous puisque c'est un prix :

1. Demander au service partenaires l'accès au module gratuit.
2. Publier à petit prix sur Addons (le module reste gratuit sur GitHub et sur pathlyhq.com/modules).
3. Ne pas passer par Addons et garder le téléchargement direct.

Sans cette décision, la fiche ne peut pas être envoyée.

## 1. Compte vendeur

- Adresse : https://addons.prestashop.com, espace vendeur, avec `simon@pathlyhq.com`. Un seul compte, à votre nom.
- Ajouter `addons.prestashop.com` aux contacts de la boîte : les retours de revue arrivent par e-mail.

## 2. Fiche produit : Products > New > Add a Module

Interdit sur la fiche et dans la documentation : votre adresse e-mail et l'adresse d'un site web. Les textes ci-dessous n'en contiennent pas.

| Champ | Valeur |
|---|---|
| Module name | `Pathly - checkout monitoring` |
| Technical name | `pathly` |
| Icon (57 × 57, PNG) | `assets/icon-57.png` |
| Available countries | Tous les pays de l'Union européenne, Royaume-Uni, Suisse, Norvège. À élargir si vous le souhaitez : le service n'a pas de limite de pays. |
| Category | Administration (ou « Performance / Monitoring » si la catégorie existe dans la liste) |
| PrestaShop versions | 1.7.7.0 à 9.x |
| Price | Selon la décision du point 0 |
| Languages | English (obligatoire), Français |
| Screenshots (au moins 3) | `screenshots/1-connect-en.png` à `5-badge-en.png`, dans l'ordre. Version française : `*-fr.png` |
| Demo shop | Ne pas remplir (facultatif, et le module a besoin d'un compte Pathly) |
| Video | Ne pas remplir |

### Short description

```
Know before your customers when your checkout breaks. Ping checks that the shop answers, Flow walks the checkout up to the payment page, after every update.
```

### Benefits for merchants (au moins un)

```
Learn that your checkout is broken before a customer tells you, with the screenshot of the step that failed.
```
```
Every update of PrestaShop or of a module is followed by a replay of the checkout: you know within minutes if it broke something.
```
```
Set up in two minutes: paste a key, Lumi prepares the checkout Flow, you validate it in one click. No code.
```

### Benefits for customers (au moins un)

```
Customers find a checkout that works: problems are fixed before they meet them.
```
```
Fewer abandoned orders caused by a broken cart, a blank payment page or an "Order" button that does nothing.
```

### Features

```
- Ping: the shop is checked every 5 minutes. A Ping already created for the shop is attached, never duplicated.
- Checkout Flow prepared by Lumi, the Pathly assistant: add a product to the cart, then go up to the payment page, in a real browser. Each proposed Flow is replayed before it is shown. You validate it in one click.
- Replay after an update of PrestaShop or of a module (hooks of PrestaShop 8 and 9, and comparison of the installed versions on every version).
- Dashboard widget: status of the Ping and the Flow, date and screenshot of the last failure, plan and quotas.
- Availability badge in the footer, linked to your public status page. Off by default.
- Lumi never pays, never sends a form and only types test values.
- Nothing is sent before you connect. Then only the shop address and the versions of PrestaShop and of the module. No customer data, no order.
- API key stored encrypted, settings reserved to SuperAdmin employees.
- English, French, Spanish and German.
```

### Description (si le formulaire a un champ de description longue)

Coller les sections « The problem », « What the module does », « Lumi does nothing behind your back », « Data and security », « Good to know », « Price » et « Compatibility » de `listing-en.md`.

### Keywords (au moins 5)

```
monitoring, uptime, checkout, cart, alert, status page, synthetic monitoring
```

Version française : `surveillance, disponibilité, tunnel de commande, panier, alerte, page de statut`.

### Installation / requirements (si le champ existe)

```
Requires a Pathly account (the Solo plan is free, without a credit card) and outgoing HTTPS from the shop server. The shop must be reachable from the internet. Install the zip from Modules > Module Manager > Upload a module, click Configure and follow the steps. Full guide in the docs folder of the module (English and French PDF).
```

### FAQ (si le champ existe)

Coller la section « FAQ » de `listing-en.md`.

## 3. Après l'enregistrement de la fiche

1. Accepter les conditions vendeur (à lire avant).
2. Addons affiche la **clé du module** (`module_key`). Me la transmettre : je l'ajoute dans le constructeur de `pathly.php` (`$this->module_key = '…';`), je reconstruis l'archive, et c'est cette archive qu'il faut envoyer. Ne pas envoyer l'archive sans la clé.

## 4. Envoi de l'archive : Submit your zipped module

| Champ | Valeur |
|---|---|
| Fichier | `pathly.zip` (nom exact, sans numéro de version), reconstruit avec la clé |
| Version | 1.0.1 |
| Compatible PrestaShop versions | 1.7.7.0 à 9.x |
| Changelog | voir ci-dessous |
| Cases à cocher | Les lire avant de cocher (Validator passé, liste de contrôle respectée) |

```
1.0.1
- Style sheet and script loaded through the back office controller.
- User guide in English and French (PDF) in the docs folder of the module.

1.0.0
- First version: API key, Ping created or attached, checkout Flow prepared by Lumi, replay after updates, dashboard widget, availability badge (off by default), four languages.
```

Avant l'envoi, passer l'archive dans le Validator officiel (https://validator.prestashop.com, connexion avec le compte PrestaShop). Les règles connues tournent déjà dans la CI (`tools/validate.php`), mais l'outil officiel n'a pas pu être lancé : il demande une connexion.

## 5. Revue

- Validation technique : 5 à 10 jours ouvrés annoncés, puis relecture marketing de la fiche dans chaque langue sous 10 jours ouvrés.
- Répondre aux retours en anglais. Chaque correction passe par le dépôt, la CI, puis une nouvelle archive.
- Aucun avis sollicité, acheté ou demandé à des proches.
