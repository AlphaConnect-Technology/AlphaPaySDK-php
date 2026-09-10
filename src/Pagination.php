<?php

declare(strict_types=1);

namespace AlphaPay;

final class Pagination
{
    private function __construct()
    {
        // Classe utilitaire statique, jamais instanciée.
    }

    /**
     * Parcourt automatiquement toutes les pages d'une ressource paginée,
     * qu'elle soit paginée par page (avec "count") ou par curseur (sans
     * "count" -- ex. TransactionsResource::list()).
     *
     * Suit le lien "next" renvoyé TEL QUEL par l'API plutôt que de
     * recalculer un numéro de page soi-même : un curseur n'est pas un
     * numéro de page (c'est un jeton opaque encodant une position dans le
     * tri), l'incrémenter à la main ne ferait qu'interroger indéfiniment la
     * même première page.
     *
     * @param Http $http `$client->http` -- nécessaire pour requêter l'URL absolue "next".
     * @param array{next: ?string, previous: ?string, results: array<int, mixed>} $firstPage Le premier appel déjà résolu, ex. `$client->transactions->list(['status' => 'SUCCESS'])`.
     *
     * @return \Generator<int, mixed>
     *
     * @example
     * foreach (Pagination::paginate($client->http, $client->transactions->list(['status' => 'SUCCESS'])) as $tx) {
     *     echo $tx['reference'];
     * }
     */
    public static function paginate(Http $http, array $firstPage): \Generator
    {
        $page = $firstPage;
        while (true) {
            foreach ($page['results'] as $item) {
                yield $item;
            }
            if (empty($page['next'])) {
                return;
            }
            $page = $http->request('GET', $page['next']);
        }
    }
}
