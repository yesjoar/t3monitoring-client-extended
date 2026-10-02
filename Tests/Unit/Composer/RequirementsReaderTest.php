<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Tests\Unit\Composer;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Yesjoar\T3monitoringClientExtended\Composer\RequirementsReader;

final class RequirementsReaderTest extends UnitTestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = sys_get_temp_dir() . '/t3monitoring_client_extended_' . bin2hex(random_bytes(6)) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    #[Test]
    public function onlyPackageNamesAndConstraintsAreRead(): void
    {
        file_put_contents($this->file, json_encode([
            'name' => 'agency/project',
            'repositories' => [['type' => 'composer', 'url' => 'https://user:secret@repo.example.org']],
            'config' => ['http-basic' => ['repo.example.org' => ['username' => 'user', 'password' => 'secret']]],
            'require' => [
                'php' => '^8.2',
                'ext-json' => '*',
                'Georgringer/News' => ' ^12.3 ',
                'typo3/cms-core' => '^13.4',
                'broken/package' => ['not' => 'a string'],
            ],
            'require-dev' => ['typo3/testing-framework' => '^9.0'],
        ]));

        $requirements = (new RequirementsReader())->read($this->file);

        self::assertSame(
            [
                'require' => ['georgringer/news' => '^12.3', 'typo3/cms-core' => '^13.4'],
                'requireDev' => ['typo3/testing-framework' => '^9.0'],
            ],
            $requirements
        );
        self::assertStringNotContainsString('secret', (string)json_encode($requirements));
    }

    #[Test]
    public function aMissingOrInvalidManifestIsNotAvailable(): void
    {
        $reader = new RequirementsReader();

        self::assertNull($reader->read($this->file));

        file_put_contents($this->file, '{"require": ');
        self::assertNull($reader->read($this->file));

        file_put_contents($this->file, '{}');
        self::assertSame(['require' => [], 'requireDev' => []], $reader->read($this->file));
    }
}
