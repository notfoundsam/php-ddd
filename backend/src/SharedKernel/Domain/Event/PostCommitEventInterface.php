<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Event;

interface PostCommitEventInterface extends EventInterface
{
    public function serialize(): array;
}
