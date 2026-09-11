<?php

declare(strict_types=1);

namespace AlphaPay\Tests;

use AlphaPay\AlphaPayClient;
use AlphaPay\Exceptions\AlphaPayRateLimitException;
use AlphaPay\Exceptions\AlphaPayValidationException;
use AlphaPay\Pagination;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private static int $port = 8917;
    private static $serverProcess = null;
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = 'http://127.0.0.1:' . self::$port;
        $router = __DIR__ . '/Fixtures/router.php';
        // stdout/stderr vers /dev/null, jamais des pipes non lus : le serveur
        // intégré PHP logue chaque requête, un pipe qu'on ne draine pas se
        // remplit et bloque le process en écriture après quelques requêtes
        // (deadlock silencieux observé en pratique lors de la mise au point
        // de ce test). `exec` avant la commande : sans lui, proc_open()
        // suit le PID du shell qui lance `php -S`, pas celui de `php`
        // lui-même -- proc_terminate() ne tuait alors que le shell déjà
        // sorti, laissant le vrai serveur tourner en orphelin après les tests.
        $descriptors = [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
        self::$serverProcess = proc_open(
            sprintf('exec php -S 127.0.0.1:%d %s', self::$port, escapeshellarg($router)),
            $descriptors,
            $pipes
        );
        // Laisse le serveur intégré PHP le temps de démarrer avant le premier appel.
        $deadline = microtime(true) + 3;
        while (microtime(true) < $deadline) {
            $ch = curl_init(self::$baseUrl . '/reset-counters/');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_TIMEOUT => 1]);
            curl_exec($ch);
            $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
            curl_close($ch);
            if ($ok) {
                return;
            }
            usleep(50_000);
        }
        self::fail('Le serveur de test PHP intégré (router.php) ne répond pas après 3s.');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess === null) {
            return;
        }
        // SIGTERM d'abord ; le serveur intégré PHP ne s'arrête pas toujours
        // dessus s'il est en train de traiter une requête -- SIGKILL en
        // secours après une brève attente pour ne jamais laisser un process
        // fantôme écouter sur le port de test.
        proc_terminate(self::$serverProcess);
        $deadline = microtime(true) + 1;
        while (microtime(true) < $deadline) {
            $status = proc_get_status(self::$serverProcess);
            if (!$status['running']) {
                break;
            }
            usleep(50_000);
        }
        $status = proc_get_status(self::$serverProcess);
        if ($status['running']) {
            proc_terminate(self::$serverProcess, 9);
        }
        proc_close(self::$serverProcess);
    }

    protected function setUp(): void
    {
        $ch = curl_init(self::$baseUrl . '/reset-counters/');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'POST']);
        curl_exec($ch);
        curl_close($ch);
    }

    public function testDetectsSandboxVsLiveEnvironmentFromKeyPrefix(): void
    {
        $sandbox = new AlphaPayClient('sk_test_abc', self::$baseUrl);
        $live = new AlphaPayClient('sk_live_abc', self::$baseUrl);

        self::assertSame('sandbox', $sandbox->environment);
        self::assertSame('live', $live->environment);
    }

    public function testUnwrapsTheEnvelopeAndReturnsData(): void
    {
        $client = new AlphaPayClient('sk_test_abc', self::$baseUrl);

        $tx = $client->transactions->get('tx_1');

        self::assertSame(['id' => 'tx_1', 'reference' => 'REF1'], $tx);
    }

    public function testReadsPublicPaymentLinkOptions(): void
    {
        $client = new AlphaPayClient('sk_test_abc', self::$baseUrl);

        $link = $client->paymentLinks->getPublic('demo-slug');

        self::assertSame('123456789012345', $link['facebook_pixel_id']);
        self::assertSame('AW-123456789', $link['google_ads_id']);
        self::assertSame([
            ['key' => 'reference_client', 'label' => 'Référence client', 'required' => true],
        ], $link['custom_fields']);
    }

    public function testSendsPaymentLinkTrackingAndCustomFields(): void
    {
        $client = new AlphaPayClient('sk_test_abc', self::$baseUrl);

        $link = $client->paymentLinks->create([
            'name' => 'Lien de test',
            'amount_type' => 'FIXED',
            'amount' => 5000,
            'currency' => 'XOF',
            'facebook_pixel_id' => '123456789012345',
            'google_ads_id' => 'AW-123456789',
            'custom_fields' => [
                ['key' => 'reference_client', 'label' => 'Référence client', 'required' => true],
            ],
        ]);

        self::assertSame('123456789012345', $link['facebook_pixel_id']);
        self::assertSame('AW-123456789', $link['google_ads_id']);
        self::assertSame('reference_client', $link['custom_fields'][0]['key']);
    }

    public function testCreatesCheckoutFromPublicPaymentLink(): void
    {
        $client = new AlphaPayClient('sk_test_abc', self::$baseUrl);

        $result = $client->paymentLinks->createPublicCheckout('demo-slug', [
            'customer' => [
                'email' => 'client@example.com',
                'first_name' => 'Client',
                'last_name' => 'Test',
            ],
            'custom_field_values' => ['reference_client' => 'CMD-42'],
        ]);

        self::assertSame('checkout-slug', $result['slug']);
        self::assertSame('CMD-42', $result['received']['custom_field_values']['reference_client']);
    }

    public function testMapsA400FieldValidationErrorToAlphaPayValidationException(): void
    {
        $client = new AlphaPayClient('sk_test_abc', self::$baseUrl);

        try {
            $client->settlements->create(['country' => 'BJ', 'requested_amount' => 100, 'payout_method' => 'x']);
            self::fail('Aurait dû lever AlphaPayValidationException.');
        } catch (AlphaPayValidationException $e) {
            self::assertSame(400, $e->getStatus());
            self::assertSame(['amount' => ['Ce champ est requis.']], $e->getFieldErrors());
        }
    }

    public function testRetriesA429HonoringRetryAfterThenResolves(): void
    {
        $client = new AlphaPayClient('sk_test_abc', self::$baseUrl, maxRetries: 2);

        $result = $client->http->request('GET', '/retry-then-success/');

        self::assertSame(['id' => 'ok'], $result);
    }

    public function testGivesUpAfterExhaustingRetriesAndThrowsRateLimitException(): void
    {
        $client = new AlphaPayClient('sk_test_abc', self::$baseUrl, maxRetries: 1);

        $this->expectException(AlphaPayRateLimitException::class);
        $client->http->request('GET', '/always-429/');
    }

    public function testSendsAGeneratedIdempotencyKeyWhenRequested(): void
    {
        $client = new AlphaPayClient('sk_test_abc', self::$baseUrl);

        $result = $client->http->request('POST', '/echo-headers/', [], [], true);

        self::assertNotEmpty($result['idempotencyKey']);
    }

    public function testPaginateFollowsTheCursorBasedNextLink(): void
    {
        $client = new AlphaPayClient('sk_test_abc', self::$baseUrl);

        $collected = [];
        foreach (Pagination::paginate($client->http, $client->transactions->list()) as $tx) {
            $collected[] = $tx;
        }

        self::assertSame([['id' => 'tx_1'], ['id' => 'tx_2']], $collected);
    }
}
