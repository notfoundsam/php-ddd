<?php

namespace Fuel\Core;

use Infrastructure\Redis\PhpRedisClient;
use RuntimeException;

/**
 * @group App
 * @group Redis
 */
class Test_PhpRedisClient extends TestCase
{
    public function test_from_config_section_throws_when_primary_host_is_empty()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('redis.primary.host is not configured (env REDIS_PRIMARY_ENDPOINT)');

        PhpRedisClient::fromConfigSection([], 'primary');
    }

    public function test_from_config_section_throws_when_reader_host_is_empty()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('redis.reader.host is not configured (env REDIS_READER_ENDPOINT)');

        PhpRedisClient::fromConfigSection(['host' => null], 'reader');
    }
}
