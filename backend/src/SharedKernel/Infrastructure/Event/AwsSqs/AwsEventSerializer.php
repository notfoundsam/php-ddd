<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Event\AwsSqs;

use SharedKernel\Domain\Event\EventInterface;

final class AwsEventSerializer
{
    public function serialize(EventInterface $event): array
    {
        return [
            'type' => get_class($event),
            'payload' => json_encode($event),
        ];
    }
}
