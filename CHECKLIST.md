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
- [ ] Tester les écritures (create/update/rotateSecret) contre une clé
      sandbox — pas encore fait (pas de clé sandbox disponible, seulement
      une clé live testée en lecture seule pour ne rien déclencher de réel).
- [ ] Vérifier le nom exact des codes réseau (`network`) et méthodes de
      payout (`method`) attendus par l'API — documentés comme chaînes libres.
- [ ] Choisir le nom de package Packagist définitif (`alphapay/alphapay-php`
      est provisoire).
- [ ] Décider du PHP minimum supporté. `composer.json` déclare `>=8.0`
      (types union, `match`, propriétés `readonly`, arguments nommés) —
      à confirmer contre les exigences d'hébergement WordPress/WooCommerce
      si ce SDK doit servir de base au plugin WooCommerce existant (cf.
      historique git AlphaPayMarchand : "refactor: update WooCommerce
      plugin"). Si un hébergement cible tourne encore en PHP 7.4, il
      faudra dégrader la syntaxe (pas de `readonly`, pas de types union).

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
