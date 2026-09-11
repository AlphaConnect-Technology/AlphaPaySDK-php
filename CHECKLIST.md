# Avant publication Packagist

Ce SDK est fonctionnel et testé (`composer test` : 11/11 tests PHPUnit,
contre un vrai serveur HTTP local — pas un mock, cf. `tests/Fixtures/router.php`).
`list()`/`get()` des 9 ressources ont aussi été vérifiés en lecture seule
contre une vraie clé LIVE sur `api.alphapay.me` — mêmes résultats que le SDK
Node.js jumeau (mêmes formes de réponse, mêmes 403 `dashboard_only`,
`transactions->list()` bien sans `count`). Le parsing des headers de réponse
réels (`extractHeader()`) fonctionne donc correctement en conditions
réelles, pas seulement contre le serveur de test PHP intégré. À faire avant
`composer publish` :

## Bloquant

- [x] **Tester contre une vraie clé live**, en lecture seule uniquement, sur
      `api.alphapay.me` — fait, résultats identiques au SDK Node.js.
- [x] Tester les écritures — pas de clé sandbox disponible ; fait sur LIVE
      mais volontairement limité aux opérations sans risque financier
      (jamais `transactions->payinInitialize`/`payoutInitialize`, qui
      pousseraient un vrai prompt de paiement ou débiteraient réellement le
      wallet) :
        - `settlements->create()`, `apiKeys->create()`, `webhookEndpoints->create()`
          → confirmés 403 `dashboard_only` (aucun effet réel), cohérent avec
          le tableau "Ressources couvertes" du README.
        - `customers->create()` → `update()` → `delete()`, et
          `paymentLinks->create()` → `update()` → `delete()` : cycle complet
          réussi, objets de test nettoyés (vérifié via recherche a posteriori
          : 0 résultat restant).
      **Découverte au passage** : `customers->create()` attend `country` en
      UUID (Customer.country = ForeignKey côté API), pas en code ISO2 comme
      partout ailleurs dans ce SDK — `country: 'BJ'` renvoie 400 "n'est pas
      un UUID valide". Documenté dans le docblock de `CustomersResource::create()`.
      La clé live utilisée pour ce test a été collée en clair dans une
      conversation — **à révoquer/régénérer côté dashboard AlphaPay**, par
      précaution, indépendamment de ce test.
- [x] Vérifier le nom exact des codes réseau (`network`) et méthodes de
      payout (`method`) attendus par l'API — fait, lu directement dans
      AlphaPayBack (`apps/transactions/services/checkout.py::_resolve_network`,
      matché sur `Network.code` en DB, seedé par `apps/geo/management/commands/
      seed_geo.py`) : format réel `<opérateur>_<pays ISO2>` minuscules
      (`mtn_bj`, `moov_ci`, `orange_sn`...) + `card`/`crypto` pour les réseaux
      globaux. Le README et le docblock de `AlphaPayClient` utilisaient par
      erreur `MTN_BJ` (format provider_id Pawapay, `apps/gateways/adapters/
      payin_map.py`) — corrigé dans les deux fichiers.
- [x] Choisir le nom de package Packagist définitif — confirmé `alphapay/alphapay-php`,
      vérifié libre sur Packagist (404 sur `packagist.org/packages/alphapay/alphapay-php.json`).
- [x] Décider du PHP minimum supporté — tranché pour `>=7.4`, pour rester
      compatible avec un hébergement WordPress/WooCommerce mutualisé bas de
      gamme. Toute la syntaxe 8.0+/8.1+ a été retirée (`readonly`, promotion
      de propriétés dans les constructeurs, types union natifs `string|bool|
      null`/`string|int`, `mixed` en tant que type réel, `match`, `catch`
      sans variable, `str_starts_with()`). Vérifié à deux niveaux :
      `php7.4 -l` sur les 24 fichiers de `src/` (aucune erreur), **et** un
      smoke test fonctionnel exécuté avec le vrai binaire `php7.4` contre
      `tests/Fixtures/router.php` (client, retry 429, Idempotency-Key,
      erreurs de validation, pagination par curseur, vérification de
      signature webhook avec timestamp int et string) — 10/10 passés. La
      suite PHPUnit complète (`composer test`) reste verte sous PHP 8.3
      (aucune régression). Note : `require-dev.phpunit/phpunit` reste en
      `^10.5` (PHP 8.1+) volontairement — ça ne concerne que les
      contributeurs qui lancent les tests localement, pas les consommateurs
      du package (`require-dev` n'est jamais installé par `composer
      require`).

## Souhaitable avant v1.0.0

- [ ] Couvrir les ressources listées comme "pas encore couvertes" dans le
      README.
- [ ] CI (GitHub Actions) : `composer run lint` + `composer test` sur chaque PR.
- [ ] `Http::extractHeader()` fait un parsing regex maison des headers bruts
      cURL (`CURLOPT_HEADER => true` + `CURLINFO_HEADER_SIZE`) — fonctionne
      mais serait plus robuste avec `CURLOPT_HEADERFUNCTION` (callback ligne
      par ligne, pas de découpage manuel de bloc de texte). Pas changé ici
      pour rester au plus près du portage direct du SDK Node, à revoir si un
      cas réel expose une limite du parsing actuel (ex. header multi-lignes).
- [ ] Exemples d'intégration complets (`examples/plugin-woocommerce`,
      `examples/webhook-endpoint.php`) plutôt que les extraits du README.
- [ ] Si le plugin WooCommerce existant est repris pour utiliser ce SDK :
      vérifier qu'il n'y a pas de logique de paiement dupliquée/divergente à
      remplacer plutôt qu'à faire coexister.

## Fait

- [x] Client HTTP (`ext-curl`) : auth Bearer, détection sandbox/live,
      retries avec backoff sur 429/5xx/réseau, timeout configurable,
      Idempotency-Key optionnelle.
- [x] Enveloppe `{success, data, code}` déballée automatiquement ; erreurs
      mappées vers des exceptions typées (`AlphaPayValidationException` avec
      `getFieldErrors()`, `AlphaPayRateLimitException` avec `getRetryAfter()`, etc.).
- [x] `Webhook::verifySignature()` — HMAC-SHA256 identique à
      `apps.webhooks.services.sign_payload`, comparaison en temps constant
      (`hash_equals`), fenêtre anti-rejeu de 300s.
- [x] `Pagination::paginate()` — suit `next` (URL absolue), pas un numéro de
      page recalculé — corrige d'emblée le même piège que celui trouvé et
      corrigé côté SDK Node sur `transactions->list()` (pagination par
      curseur, pas par page).
- [x] 9 ressources (voir tableau README), restrictions `dashboard_only`
      documentées par méthode dès l'écriture (reprises du SDK Node,
      vérifiées en conditions réelles côté Node).
- [x] 11 tests PHPUnit verts, contre un vrai serveur HTTP local (pas un mock) :
      enveloppe, erreurs de validation, retry 429, épuisement des retries,
      Idempotency-Key, pagination par curseur.
- [x] `list()`/`get()` des 9 ressources vérifiés en lecture seule contre une
      vraie clé live sur `api.alphapay.me` — formes de réponse et
      restrictions `dashboard_only` identiques au SDK Node.js.
