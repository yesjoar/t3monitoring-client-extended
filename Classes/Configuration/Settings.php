<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Typed access to the extension configuration (see ext_conf_template.txt).
 * Missing or invalid values fall back to the given defaults.
 */
final class Settings
{
    public const EXTENSION_KEY = 't3monitoring_client_extended';

    /**
     * @param array<string, mixed> $values nested configuration, e.g. ['scheduler' => ['enabled' => '1']]
     */
    public function __construct(private readonly array $values = []) {}

    public static function fromExtensionConfiguration(): self
    {
        try {
            $values = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get(self::EXTENSION_KEY);
        } catch (\Throwable) {
            $values = [];
        }

        return new self(is_array($values) ? $values : []);
    }

    public function bool(string $path, bool $default): bool
    {
        $value = $this->value($path);

        return $value === null || $value === '' ? $default : (bool)filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function int(string $path, int $default, int $minimum = 0): int
    {
        $value = $this->value($path);

        return max($minimum, is_numeric($value) ? (int)$value : $default);
    }

    public function string(string $path, string $default): string
    {
        $value = $this->value($path);

        return is_scalar($value) && trim((string)$value) !== '' ? trim((string)$value) : $default;
    }

    /**
     * @return list<string> the non-empty items of a comma separated value
     */
    public function list(string $path): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $this->string($path, ''))),
            static fn(string $item): bool => $item !== ''
        ));
    }

    private function value(string $path): mixed
    {
        $value = $this->values;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
