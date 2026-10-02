<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use T3Monitor\T3monitoringClient\Client;
use T3Monitor\T3monitoringClient\Provider\DataProviderInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ServerRequest;
use Yesjoar\T3monitoringClientExtended\Provider\ComposerProvider;
use Yesjoar\T3monitoringClientExtended\Provider\LogFileProvider;
use Yesjoar\T3monitoringClientExtended\Provider\SchedulerProvider;
use Yesjoar\T3monitoringClientExtended\Provider\SystemLogProvider;

/**
 * The providers as part of the endpoint of t3monitoring_client.
 */
final class EndpointTest extends AbstractProviderTestCase
{
    private const OWN_PROVIDERS = [SchedulerProvider::class, SystemLogProvider::class, LogFileProvider::class, ComposerProvider::class];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            't3monitoring_client' => ['secret' => 'functional-test-secret', 'allowedIps' => '*', 'enableDebugForErrors' => '1'],
            't3monitoring_client_extended' => ['scheduler' => ['enabled' => '0']],
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    private function requestEndpoint(): array
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        // Only the providers of this extension: the test must not depend on the client's own providers.
        $GLOBALS['TYPO3_CONF_VARS']['EXT']['t3monitoring_client']['provider'] = self::OWN_PROVIDERS;

        $request = (new ServerRequest('https://example.org/?eID=t3monitoring&secret=functional-test-secret', 'GET'))
            ->withQueryParams(['eID' => 't3monitoring', 'secret' => 'functional-test-secret']);
        $response = (new Client())->run($request);

        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());

        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function theProvidersAreRegisteredInTheClient(): void
    {
        $providers = $GLOBALS['TYPO3_CONF_VARS']['EXT']['t3monitoring_client']['provider'];

        foreach (self::OWN_PROVIDERS as $provider) {
            self::assertContains($provider, $providers);
            self::assertInstanceOf(DataProviderInterface::class, new $provider());
        }
    }

    #[Test]
    public function theEndpointDeliversTheInsightsAsJson(): void
    {
        $logDirectory = Environment::getVarPath() . '/log';
        @mkdir($logDirectory, 0777, true);
        file_put_contents(
            $logDirectory . '/typo3_functionaltest.log',
            sprintf(
                "%s [ERROR] request=\"abc\" component=\"TYPO3.CMS.Frontend\": Unable to call method \"getQueryParams\" - {\"url\":\"https://example.org/?secret=x\"}\n",
                date('r', time() - 60)
            )
        );
        file_put_contents($logDirectory . '/typo3_deprecations_functionaltest.log', sprintf("%s [ERROR] request=\"abc\" component=\"X\": Deprecated\n", date('r')));

        $data = $this->requestEndpoint();

        self::assertSame(1, $data['extended']['version']);
        self::assertArrayNotHasKey('scheduler', $data['extended'], 'Disabled providers deliver nothing.');
        self::assertSame(0, $data['extended']['sysLog']['errors']);
        self::assertSame(1, $data['extended']['logFiles']['entries']);
        self::assertSame('Unable to call method "getQueryParams"', $data['extended']['logFiles']['groups'][0]['message']);
        self::assertSame('TYPO3.CMS.Frontend', $data['extended']['logFiles']['groups'][0]['component']);
        self::assertArrayHasKey('Log files - 1 error(s) in the last 24 hours', $data['extra']['warning']);
        // The functional test instance does not run in Composer mode.
        self::assertSame(['available' => false], $data['extended']['composer']);
    }

    #[Test]
    public function aFailingProviderDoesNotBreakTheEndpoint(): void
    {
        $this->getConnectionPool()->getConnectionForTable('sys_log')->executeStatement('DROP TABLE sys_log');

        $data = $this->requestEndpoint();

        self::assertArrayHasKey('sysLog', $data['extended']['errors']);
        self::assertArrayNotHasKey('sysLog', array_diff_key($data['extended'], ['errors' => true]));
        self::assertArrayHasKey('System log - not available', $data['extra']['warning']);
        self::assertArrayHasKey('logFiles', $data['extended']);
    }
}
