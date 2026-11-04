# Fiche du module — français

## Nom

Pathly - surveillance du tunnel de commande

## Résumé court (moins de 200 caractères)

Sachez avant vos clients quand votre tunnel casse. Ping vérifie que la boutique répond, Flow rejoue le parcours d'achat jusqu'à la page de paiement, après chaque mise à jour.

## Description

### Le problème

Le serveur répond, la page d'accueil s'affiche, et pourtant plus personne ne peut commander : un module mis à jour a cassé le bouton « Commander », le panier ne se remplit plus, la page de paiement reste blanche. Un simple test de disponibilité ne le voit pas. Vous l'apprenez par un client, ou par la chute des ventes.

### Ce que fait le module

Pathly watches the path. Ping if it's up. Flow if a user can walk it. Chain if the services behind it hold.

- **Ping** : dès la connexion, Pathly vérifie toutes les 5 minutes que votre boutique répond. Un Ping déjà créé pour la boutique est rattaché, sans doublon.
- **Flow préparé par Lumi** : Lumi, l'assistant de Pathly, reconnaît PrestaShop et prépare le Flow de votre tunnel : ajouter un produit au panier, puis aller jusqu'à la page de paiement. Chaque Flow proposé est rejoué avant de vous être montré. Vous le validez en un clic.
- **Rejeu après une mise à jour** : quand PrestaShop ou un module est mis à jour, Pathly rejoue le Flow tout de suite. Vous savez dans les minutes qui suivent si la mise à jour a cassé le tunnel.
- **Encart dans le tableau de bord** : état du Ping et du Flow, date et capture d'écran du dernier échec, offre et quotas, lien vers Pathly.
- **Badge de disponibilité** en pied de page, relié à votre page de statut publique. Éteint par défaut.

### Lumi ne fait rien à votre place

Il ne paie rien, n'envoie aucun formulaire et ne saisit que des valeurs de test. Vous validez chaque Flow avant son enregistrement.

### Données et sécurité

- Rien ne part vers Pathly avant que vous ayez connecté le module.
- Ensuite, seules l'adresse de la boutique et les versions de PrestaShop et du module sont envoyées. Aucune donnée de client, aucune commande.
- La clé d'API est chiffrée dans la boutique, jamais réaffichée.
- Nous appliquons le RGPD. Application, API, workers et captures : GCP europe-west1. DPA disponible.
- Écrans réservés aux administrateurs.

### Bon à savoir

Chaque exécution du Flow crée un panier de test sur la boutique. Il peut déclencher vos relances de panier abandonné.

### Prix

Le module est gratuit. Il se connecte à un compte Pathly : l'offre Solo est gratuite et sans carte bancaire. Les offres payantes donnent plus de Ping et de Flow, et des fréquences plus courtes (tarifs dans votre compte Pathly).

### Compatibilité

PrestaShop 1.7.7 à 9, PHP 7.2 ou plus. Multiboutique : le module surveille la boutique du contexte courant.

## Questions fréquentes

**Faut-il un compte Pathly ?**
Oui. Le module propose de créer un compte gratuit, sans carte bancaire, puis vous collez une clé d'API.

**Quelle clé d'API créer ?**
Dans Pathly, Réglages puis Clés d'API. De préférence une clé limitée à un Espace, avec les portées `org:read scenarios:read scenarios:write runs:read runs:trigger assistant:run assistant:save status:read status:write`.

**Le Flow passe-t-il de vraies commandes ?**
Non. Il s'arrête avant le paiement et ne saisit que des valeurs de test. Il crée en revanche un panier de test à chaque exécution.

**Que se passe-t-il après la mise à jour d'un module ?**
PrestaShop 9 et 8 préviennent le module par leurs hooks de mise à jour, et le module compare aussi les versions installées. Pathly rejoue alors le Flow. Deux rejeux sont espacés d'au moins 10 minutes.

**Mes clients voient-ils quelque chose ?**
Seulement si vous allumez le badge : une petite image en pied de page, chargée depuis les serveurs de Pathly, qui renvoie vers votre page de statut.

**Et si je désinstalle le module ?**
Ses réglages sont effacés de la boutique. Vos Ping et Flow restent dans votre compte Pathly, où vous pouvez les supprimer.

**Lumi reconnaît-il toutes les boutiques ?**
Il reconnaît PrestaShop. Si votre thème est très personnalisé et que Lumi ne trouve pas le tunnel, vous pouvez enregistrer le Flow vous-même dans Pathly, sans code.

## Journal des changements

### 1.0.0

- Connexion par clé d'API, lien de création de compte gratuit.
- Ping de la boutique créé ou rattaché automatiquement.
- Flow du tunnel préparé par Lumi, validé en un clic.
- Rejeu du Flow après la mise à jour de PrestaShop ou d'un module.
- Encart du tableau de bord : état, dernier échec avec capture, offre et quotas.
- Badge de disponibilité, éteint par défaut.
- Traductions français, anglais, espagnol et allemand.

### 1.0.1

- Feuille de style et script chargés par le contrôleur du back-office.
- Guide d'utilisation en anglais et en français (PDF) dans le dossier docs du module.
