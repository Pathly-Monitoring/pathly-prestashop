# Pathly pour PrestaShop

Sachez avant vos clients quand votre tunnel de commande casse.

[Pathly](https://pathlyhq.com) watches the path. **Ping** if it's up. **Flow** if a user can walk it. **Chain** if the services behind it hold.

Ce module relie une boutique PrestaShop à Pathly :

- **Ping** : dès que la clé d'API est enregistrée, Pathly vérifie toutes les 5 minutes que la boutique répond. Un Ping existant de la boutique est rattaché, jamais dupliqué.
- **Flow préparé par Lumi** : Lumi, l'assistant de Pathly, reconnaît PrestaShop et prépare le Flow du tunnel (ajouter un produit au panier, aller jusqu'à la page de paiement). Chaque Flow proposé est rejoué avant de vous être montré. Vous le validez en un clic.
- **Rejeu après une mise à jour** : quand PrestaShop ou un module est mis à jour, Pathly rejoue le Flow tout de suite.
- **Encart du tableau de bord** : état du Ping et du Flow, date et capture du dernier échec, offre et quotas, liens vers Pathly.
- **Badge de disponibilité** en pied de page, relié à votre page de statut publique. Éteint par défaut.

Compatible avec PrestaShop 1.7.7 à 9, PHP 7.2 ou plus.

## Installation

1. Téléchargez `pathly-x.y.z.zip` depuis les [versions](https://github.com/Pathly-Monitoring/pathly-prestashop/releases) (ou depuis PrestaShop Addons une fois la fiche en ligne).
2. Dans le back-office : **Modules > Gestionnaire de modules > Installer un module**, puis choisissez le zip.
3. Cliquez sur **Configurer**. Pas encore de compte ? **Créer un compte gratuit** (l'offre Solo est gratuite, sans carte).
4. Dans Pathly, créez une clé d'API dans **Réglages > Clés d'API**. Préférez une clé limitée à un Espace, avec ces portées :
   `org:read scenarios:read scenarios:write runs:read runs:trigger assistant:run assistant:save status:read status:write`
5. Collez la clé dans le module et cliquez sur **Connecter**. Le Ping est créé. Cliquez ensuite sur **Préparer le Flow du tunnel avec Lumi**.

Seul un administrateur (profil SuperAdmin) voit la page de réglages et l'encart.

## Ce qui part vers Pathly

- Rien avant qu'un administrateur enregistre une clé d'API.
- Ensuite seulement : l'adresse de la boutique, et les versions de PrestaShop et de ce module (dans l'en-tête `User-Agent`).
- Aucune donnée de client, aucune commande, aucune liste de modules : les versions des modules installés sont comparées dans la boutique, et seule la demande d'exécution part vers Pathly.
- Le module n'appelle que `https://api.pathlyhq.com`. Il n'appelle jamais une adresse saisie par un utilisateur.
- La clé d'API est chiffrée avec le secret propre à la boutique (`PhpEncryption`), jamais réaffichée et jamais écrite dans les journaux.

Quand le badge est allumé, le navigateur de vos visiteurs charge l'image du badge depuis `api.pathlyhq.com`, comme toute image hébergée ailleurs. Mentionnez-le dans votre politique de confidentialité si vous l'allumez.

## Bon à savoir

- **Paniers de test.** Chaque exécution du Flow crée un vrai panier sur la boutique, avec des valeurs de test. Il peut déclencher vos relances de panier abandonné et apparaître dans vos statistiques de paniers.
- **Mises à jour.** PrestaShop 9 signale la mise à jour d'un module par le hook `actionModuleUpgradeAfter`, PrestaShop 8 par `actionBeforeUpgradeModule` (le rejeu part à la fin de la requête de mise à jour). Sur toutes les versions, le module compare aussi les versions installées de PrestaShop et des modules, au plus toutes les 5 minutes, quand un administrateur ouvre le back-office. Deux rejeux sont espacés d'au moins 10 minutes.
- **La désinstallation** efface tous les réglages du module dans la boutique. Vos Ping et Flow restent dans votre compte Pathly.

Développement : voir le [README anglais](README.md#development).

## Licence

[Academic Free License 3.0](LICENSE.md).
