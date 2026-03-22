<?php

declare(strict_types=1);

namespace SharedKernel\Infrastructure\Storage;

use Aws\S3\S3Client;
use InvalidArgumentException;

class S3ClientFactory
{
    public function create(string $type, string $region): S3Client
    {
        if ($type === 'default') {
            return $this->createDefault($region);
        }

        throw new InvalidArgumentException('Invalid S3 client type');
    }

    private function createDefault(string $region): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => $region,
        ]);
    }
}
