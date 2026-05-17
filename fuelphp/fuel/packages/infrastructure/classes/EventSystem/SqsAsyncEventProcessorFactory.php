<?php

declare(strict_types=1);

namespace Infrastructure\EventSystem;

use Aws\Sqs\SqsClient;
use Fuel\Core\Config;
use RuntimeException;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\EventSystem\AsyncEventProcessorInterface;
use SharedKernel\Domain\EventSystem\AsyncRepositoryInterface;
use SharedKernel\Domain\EventSystem\EventFactoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Infrastructure\EventSystem\AsyncEventProcessor;
use SharedKernel\Infrastructure\EventSystem\InMemoryAsyncRepository;
use SharedKernel\Infrastructure\EventSystem\SqsAsyncRepository;

/**
 * Factory for creating an SQS-based async event processor.
 *
 * Creates environment-specific repository internally:
 * - Test, or Development with no queue URL configured: InMemoryAsyncRepository (synchronous dispatch)
 * - Otherwise: SqsAsyncRepository (queue-based)
 *
 * Reads queue.async_events.* from the FuelPHP config cascade — see
 * app/config/queue.php for defaults and env overrides.
 */
final class SqsAsyncEventProcessorFactory
{
    private Environment $environment;
    private EventFactoryInterface $eventFactory;
    private ListenerProviderInterface $listenerProvider;
    private LoggerInterface $logger;

    public function __construct(
        Environment $environment,
        EventFactoryInterface $eventFactory,
        ListenerProviderInterface $listenerProvider,
        LoggerInterface $logger
    ) {
        $this->environment = $environment;
        $this->eventFactory = $eventFactory;
        $this->listenerProvider = $listenerProvider;
        $this->logger = $logger;
    }

    public function __invoke(): AsyncEventProcessorInterface
    {
        Config::load('queue', true);
        $queueUrl = (string) Config::get('queue.async_events.queue_url', '');
        $region = (string) Config::get('queue.async_events.region', '');

        $repository = $this->createRepository($queueUrl, $region);

        return new AsyncEventProcessor(
            $repository,
            $this->listenerProvider,
            $this->logger
        );
    }

    private function createRepository(string $queueUrl, string $region): AsyncRepositoryInterface
    {
        // Test, or Development without an SQS endpoint configured: in-memory (synchronous) dispatch.
        // The DEV fallback lets contributors run the app locally without standing up ElasticMQ —
        // async events are dispatched synchronously instead of being queued.
        if ($this->environment->isTest() || ($this->environment->isDevelopment() && $queueUrl === '')) {
            return new InMemoryAsyncRepository(
                $this->listenerProvider,
                $this->logger
            );
        }

        $config = [
            'region' => $region,
            'version' => '2012-11-05',
        ];

        // For local development with ElasticMQ, extract endpoint from queue URL
        // and provide fake credentials (ElasticMQ doesn't validate them).
        if ($this->environment->isDevelopment()) {
            $endpoint = $this->extractEndpointFromUrl($queueUrl);

            if ($endpoint === null) {
                throw new RuntimeException(
                    "Failed to extract endpoint from queue URL: $queueUrl. " .
                    "Expected format: host:port/queue/name"
                );
            }

            $config['endpoint'] = $endpoint;
            $config['credentials'] = ['key' => 'x', 'secret' => 'x'];
        }

        $client = new SqsClient($config);

        return new SqsAsyncRepository(
            $client,
            $queueUrl,
            $this->eventFactory,
            $this->logger,
            $this->environment
        );
    }

    private function extractEndpointFromUrl(string $url): ?string
    {
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['scheme'], $parsedUrl['host'])) {
            return null;
        }

        $endpoint = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        if (isset($parsedUrl['port'])) {
            $endpoint .= ':' . $parsedUrl['port'];
        }

        return $endpoint;
    }
}
