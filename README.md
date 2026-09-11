# alphapay/alphapay-php

SDK PHP officiel pour l'API AlphaPay (agrégateur de paiement multi-gateway).

> **Statut : v0.1.0, non publié.** Couvre les ressources marchand principales
> (paiements, retraits, liens de paiement, checkout, clients, webhooks).
> Conçu en miroir du [SDK Node.js](../AlphaPaySDK-node) — mêmes garanties,
> mêmes restrictions d'API découvertes en conditions réelles. Voir
> [CHECKLIST.md](./CHECKLIST.md) pour ce qui manque avant une publication Packagist.

Aucune dépendance runtime — utilise `ext-curl` et `ext-json`, présentes sur
quasiment tout hébergement PHP (y compris WordPress/WooCommerce).

## Installation

```bash
composer require alphapay/alphapay-php
```

## Démarrage rapide

```php
<?php

use AlphaPay\AlphaPayClient;

$alphapay = new AlphaPayClient(getenv('ALPHAPAY_SECRET_KEY')); // sk_live_... ou sk_test_...

// Encaissement direct (push mobile money), sans page de checkout à suivre.
$payment = $alphapay->transactions->payinInitialize(
    [
        'amount' => 5000,
        'currency' => 'XOF',
        'country' => 'BJ',
        'network' => 'mtn_bj',
        'customer' => ['full_name' => 'Ayaba Client', 'phone' => '+22900000000'],
        'description' => 'Commande #1234',
    ],
    idempotencyKey: true // recommandé : évite un double push en cas de retry réseau
);

echo $payment['status'], "\n";
```

`network` (et `method` pour un payout) attend le code interne AlphaPay —
minuscules, `<opérateur>_<pays ISO2>` (ex. `mtn_bj`, `moov_ci`,
`orange_sn`), **pas** l'identifiant propriétaire d'un gateway sous-jacent
type Pawapay (`MTN_MOMO_BEN`). Codes globaux hors mobile money : `card`,
`crypto`. Liste exacte par pays : endpoint `/networks/` (référentiel pas
encore couvert par ce SDK, cf. section "Ressources couvertes").

## Sandbox vs live

L'environnement se déduit automatiquement du préfixe de la clé :

```php
$sandbox = new AlphaPayClient('sk_test_...'); // $sandbox->environment === 'sandbox'
$live = new AlphaPayClient('sk_live_...');    // $live->environment === 'live'
```

## Gestion des erreurs

Toute erreur API est normalisée en une sous-classe de `AlphaPayException` —
jamais un code HTTP brut à interpréter soi-même :

```php
use AlphaPay\Exceptions\AlphaPayValidationException;
use AlphaPay\Exceptions\AlphaPayRateLimitException;

try {
    $alphapay->paymentLinks->create(['name' => 'Facture', 'currency' => 'XOF']);
} catch (AlphaPayValidationException $e) {
    print_r($e->getFieldErrors()); // ["amount" => ["Ce champ est requis."]]
} catch (AlphaPayRateLimitException $e) {
    echo "Réessayer dans {$e->getRetryAfter()}s";
}
```

`AlphaPayAuthenticationException`, `AlphaPayPermissionException`,
`AlphaPayNotFoundException`, `AlphaPayIdempotencyException` (409 — clé
Idempotency-Key réutilisée avec un payload différent), `AlphaPayServerException`
et `AlphaPayConnectionException` (réseau/timeout, jamais atteint l'API)
couvrent le reste. Le client retente automatiquement (backoff exponentiel +
gigue) sur 429/5xx/erreur réseau — 2 tentatives supplémentaires par défaut,
configurable via le 4e argument du constructeur.

## Pagination

`Pagination::paginate()` suit le lien `next` renvoyé par l'API (pas un
numéro de page recalculé côté SDK) — fonctionne aussi bien sur les
ressources paginées par page (`count` présent) que sur
`transactions->list()`, paginée par curseur (`TransactionListView` utilise
`CreatedAtCursorPagination` côté API, sans `count` ; y passer `page` n'a
aucun effet) :

```php
use AlphaPay\Pagination;

foreach (Pagination::paginate($alphapay->http, $alphapay->transactions->list(['status' => 'SUCCESS'])) as $tx) {
    echo $tx['reference'], ' ', $tx['amounts']['net'], "\n";
}
```

## Vérifier un webhook reçu

Reproduit exactement le schéma de signature d'AlphaPayBack (HMAC-SHA256 de
`"<timestamp>.<corps>"`, comparaison en temps constant via `hash_equals()`,
fenêtre anti-rejeu de 300s) :

```php
use AlphaPay\Webhook;
use AlphaPay\Exceptions\AlphaPayWebhookSignatureException;

$rawBody = file_get_contents('php://input'); // corps BRUT, jamais déjà décodé en JSON

try {
    $event = Webhook::verifySignature(
        payload: $rawBody,
        signature: $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'],
        timestamp: $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'],
        secret: getenv('ALPHAPAY_WEBHOOK_SECRET')
    );
    // traiter $event['event'] / $event['data']
    http_response_code(200);
} catch (AlphaPayWebhookSignatureException $e) {
    http_response_code(400);
}
```

## ⚠️ Sécurité — ce SDK est côté serveur uniquement

La clé API (`sk_live_.../sk_test_...`) donne un accès complet au compte
marchand. **Ne l'exposez jamais côté client** (JS du navigateur, app
mobile) — dans un contexte PHP classique elle reste naturellement côté
serveur, mais si ce SDK est embarqué dans un plugin WordPress/WooCommerce,
assurez-vous que la clé est stockée chiffrée (options WP, jamais en clair
dans un fichier versionné) et jamais renvoyée dans une réponse AJAX
publique.

## Ressources couvertes

| Ressource | Méthodes | Via clé API |
|---|---|---|
| `transactions` | `list`, `get`, `export`, `downloadInvoice`, `payinInitialize/payinVerify/payinRetry/payinConfirmOtp`, `payoutInitialize/payoutVerify` | ✅ |
| `paymentLinks` | `list`, `create`, `get`, `update`, `delete` | ✅ |
| `checkoutSessions` | `list`, `create`, `get`, `cancel` | ✅ |
| `customers` | `list`, `create`, `get`, `update`, `delete`, `transactions` | ✅ |
| `settlements` | `list`, `create`, `get`, `cancel` | ❌ dashboard-only |
| `walletTransfers` | `list`, `create`, `get` | ❌ dashboard-only |
| `balances` | `list`, `get` | ✅ |
| `balances` | `ledgerEntries` | ❌ dashboard-only |
| `apiKeys` | `list`, `create`, `get`, `revoke`, `delete` | ❌ dashboard-only |
| `apiKeys` | `ipWhitelist->{list,create,update,delete}` | ✅ |
| `webhookEndpoints` | `list`, `get`, `subscriptions->list`, `logs->{list,get}` | ✅ |
| `webhookEndpoints` | `create`, `update`, `rotateSecret`, `delete`, `subscriptions->{subscribe,unsubscribe}`, `logs->resend` | ❌ dashboard-only |

### La colonne "Via clé API"

Vérifié en conditions réelles (`api.alphapay.me`, appels en lecture, via le
SDK Node.js jumeau — même API, même restriction) : une partie de l'API est
**volontairement inaccessible à une clé API**, même en lecture — jamais un
bug, toujours `apps.core.mixins.forbid_api_key` côté AlphaPayBack :

```php
try {
    $alphapay->settlements->list();
} catch (\AlphaPay\Exceptions\AlphaPayPermissionException $e) {
    if ($e->getErrorCode() === 'dashboard_only') {
        // "La demande de retrait n'est possible que depuis le dashboard (compte utilisateur) — jamais via une clé API."
    }
}
```

Logique : les retraits, l'historique détaillé du grand livre et la gestion
des clés API elles-mêmes exigent qu'un humain soit connecté au dashboard —
une clé compromise ne peut ni sortir d'argent, ni fabriquer d'autres clés
pour elle-même, ni consulter le détail comptable.

### Différences de nommage avec le SDK Node.js

PHP n'a pas d'équivalent ergonomique aux objets imbriqués de méthodes
(`client.transactions.payin.initialize(...)`) — ce SDK aplatit donc
`transactions->payin->initialize()` en `transactions->payinInitialize()`
(même chose pour `payout*`). Les sous-ressources qui restent de vraies
ressources indépendantes (`apiKeys->ipWhitelist`, `webhookEndpoints->subscriptions`,
`webhookEndpoints->logs`) gardent, elles, la notation avec flèche.

Pas encore couvert (endpoints existants côté API, absents du SDK pour l'instant) :
gestion d'équipe, KYC marchand, configs marchand, journal d'audit,
référentiels (pays/réseaux/taux de change), support.

## Développement

```bash
composer install
composer run lint    # php -l sur le code source
composer test         # PHPUnit — lance un vrai serveur PHP local (tests/Fixtures/router.php), aucun appel réseau réel vers AlphaPay
```

## Licence

MIT
