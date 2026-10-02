<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Composer;

/**
 * Reads the version constraints of the packages required by the root composer.json of a
 * project. Only package names and constraints are read; repositories, credentials and
 * all other settings stay untouched.
 */
final class RequirementsReader
{
    private const MAX_PACKAGES = 500;

    /**
     * @return array{require: array<string, string>, requireDev: array<string, string>}|null null if the file is missing or invalid
     */
    public function read(string $composerJsonFile): ?array
    {
        if (!is_file($composerJsonFile) || !is_readable($composerJsonFile)) {
            return null;
        }

        $manifest = json_decode((string)file_get_contents($composerJsonFile), true);

        if (!is_array($manifest)) {
            return null;
        }

        return [
            'require' => $this->packages($manifest['require'] ?? null),
            'requireDev' => $this->packages($manifest['require-dev'] ?? null),
        ];
    }

    /**
     * Packages with a vendor (no platform requirements like "php" or "ext-json") and their constraint.
     *
     * @return array<string, string>
     */
    private function packages(mixed $requirements): array
    {
        $packages = [];

        foreach (is_array($requirements) ? $requirements : [] as $name => $constraint) {
            if (!is_string($name) || !is_string($constraint) || !str_contains($name, '/')) {
                continue;
            }

            $packages[strtolower($name)] = mb_substr(trim($constraint), 0, 100);

            if (count($packages) >= self::MAX_PACKAGES) {
                break;
            }
        }

        ksort($packages);

        return $packages;
    }
}
