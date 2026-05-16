<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\EventSystem;

use Aws\Sqs\SqsClient;
use RuntimeException;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\EventSystem\AsyncEventProcessorInterface;
use SharedKernel\Domain\EventSystem\AsyncRepositoryInterface;
use SharedKernel\Domain\EventSystem\EventFactoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\Logger\LoggerInterface;

/**
 * Factory for creating an SQS-based async event processor
 *
 * Creates environment-specific repository internally:
 * - Test: InMemoryAsyncRepository (synchronous dispatch)
 * - Production/Staging/Development: SqsAsyncRepository (queue-based)
 */
final class SqsAsyncEventProcessorFactory
{
    private Environment $environment;
    private EventFactoryInterface $eventFactory;
    private ListenerProviderInterface $listenerProvider;
    private LoggerInterface $logger;
    private string $queueUrl;
    private string $region;

    public function __construct(
        Environment $environment,
        EventFactoryInterface $eventFactory,
        ListenerProviderInterface $listenerProvider,
        LoggerInterface $logger,
        string $queueUrl,
        string $region
    ) {
        $this->environment = $environment;
        $this->eventFactory = $eventFactory;
        $this->listenerProvider = $listenerProvider;
        $this->logger = $logger;
        $this->queueUrl = $queueUrl;
        $this->region = $region;
    }

    public function __invoke(): AsyncEventProcessorInterface
    {
        $repository = $this->createRepository();

        return new AsyncEventProcessor(
            $repository,
            $this->listenerProvider,
            $this->logger
        );
    }

    private function createRepository(): AsyncRepositoryInterface
    {
        // Test, or Development without an SQS endpoint configured: in-memory (synchronous) dispatch.
        // The DEV fallback lets contributors run the app locally without standing up ElasticMQ —
        // async events are dispatched synchronously instead of being queued.
        if ($this->environment->isTest() || ($this->environment->isDevelopment() && $this->queueUrl === '')) {
            return new InMemoryAsyncRepository(
                $this->listenerProvider,
                $this->logger
            );
        }

        // Production/Staging/Development (with SQS configured): SQS-based repository
        $config = [
            'region' => $this->region,
            'version' => '2012-11-05',
        ];

        // For local development with ElasticMQ, extract endpoint from queue URL
        // and provide fake credentials (ElasticMQ doesn't validate them)
        if ($this->environment->isDevelopment()) {
            $endpoint = $this->extractEndpointFromUrl($this->queueUrl);

            if ($endpoint === null) {
                throw new RuntimeException(
                    "Failed to extract endpoint from queue URL: $this->queueUrl. " .
                    "Expected format: host:port/queue/name"
                );
            }

            $config['endpoint'] = $endpoint;
            $config['credentials'] = ['key' => 'x', 'secret' => 'x'];
        }

        $client = new SqsClient($config);

        return new SqsAsyncRepository(
            $client,
            $this->queueUrl,
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
