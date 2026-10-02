<?php

declare(strict_types=1);

namespace Yesjoar\T3monitoringClientExtended\Provider;

use Psr\Http\Message\ServerRequestInterface;
use T3Monitor\T3monitoringClient\Provider\DataProviderInterface;
use Yesjoar\T3monitoringClientExtended\Configuration\Settings;
use Yesjoar\T3monitoringClientExtended\Log\MessageNormalizer;
use Yesjoar\T3monitoringClientExtended\Report\Insight;
use Yesjoar\T3monitoringClientExtended\Report\Severity;

/**
 * Base of the providers registered in t3monitoring_client. Adds the result twice:
 *
 * - structured below the key "extended" (with a format version) for monitoring servers that evaluate it,
 * - as messages in "extra" ("warning" and "danger"), which the t3monitoring server imports and shows.
 *
 * A failing provider must never break the endpoint: errors are caught and reported as a message.
 */
abstract class AbstractProvider implements DataProviderInterface
{
    /**
     * Key of the structured data in the response of the client.
     */
    public const DATA_KEY = 'extended';

    /**
     * Version of the structured data format; increased on incompatible changes.
     */
    public const FORMAT_VERSION = 1;

    /**
     * The client instantiates providers with the current request as constructor argument.
     */
    public function __construct(protected readonly ?ServerRequestInterface $request = null) {}

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    final public function get(array $data): array
    {
        $settings = $this->settings();
        $data[self::DATA_KEY]['version'] = self::FORMAT_VERSION;

        if (!$settings->bool($this->section() . '.enabled', true)) {
            return $data;
        }

        try {
            $insight = $this->collect($settings, $this->now());
        } catch (\Throwable $exception) {
            $message = (new MessageNormalizer())->sanitize($exception::class . ': ' . $exception->getMessage());
            $data[self::DATA_KEY]['errors'][$this->section()] = $message;
            $data['extra'][Severity::Warning->value][$this->title() . ' - not available'] = $message;

            return $data;
        }

        $data[self::DATA_KEY][$this->section()] = $insight->report;

        foreach ($insight->messages as $message) {
            $data['extra'][$message->severity->value][$message->title] = $message->text;
        }

        return $data;
    }

    /**
     * Key of the provider in the configuration and in the structured data, e.g. "scheduler".
     */
    abstract protected function section(): string;

    /**
     * Title used as prefix of the messages, e.g. "Scheduler".
     */
    abstract protected function title(): string;

    abstract protected function collect(Settings $settings, int $now): Insight;

    protected function settings(): Settings
    {
        return Settings::fromExtensionConfiguration();
    }

    protected function now(): int
    {
        return time();
    }

    protected function normalizer(Settings $settings): MessageNormalizer
    {
        return new MessageNormalizer($settings->list('ignoredMessages'));
    }
}
