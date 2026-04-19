<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Throttle;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Infrastructure\Throttle\ThrottleConfigResolver;

class ThrottleConfigResolverTest extends TestCase
{
    private function createConfig(array $overrides = []): array
    {
        return array_merge([
            'defaults' => [
                UserType::ADMIN => null,
                UserType::PARTNER => [
                    'warning_limit' => 100,
                    'block_limit' => 200,
                    'window' => 60,
                    'penalties' => [300, 3600],
                    'recovery_period' => 3600,
                ],
                UserType::CUSTOMER => [
                    'warning_limit' => 30,
                    'block_limit' => 50,
                    'window' => 60,
                    'penalties' => [300, 3600, 10800],
                    'recovery_period' => 10800,
                ],
            ],
            'commands' => [],
            'queries' => [],
            'excluded' => [],
        ], $overrides);
    }

    public function testExcludedMessageReturnsNull(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'excluded' => ['App\\Query\\HealthCheckQuery'],
        ]));

        $result = $service->resolve('App\\Query\\HealthCheckQuery', 'query', UserType::CUSTOMER);

        $this->assertNull($result);
    }

    public function testExcludedMessageReturnsNullForAnonymous(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'excluded' => ['App\\Command\\PingCommand'],
        ]));

        $result = $service->resolve('App\\Command\\PingCommand', 'command', null);

        $this->assertNull($result);
    }

    public function testPerCommandOverrideForSpecificUserType(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'commands' => [
                'App\\Command\\CreateOrder' => [
                    UserType::PARTNER => [
                        'warning_limit' => 500,
                        'block_limit' => 800,
                        'window' => 60,
                    ],
                ],
            ],
        ]));

        $result = $service->resolve('App\\Command\\CreateOrder', 'command', UserType::PARTNER);

        $this->assertNotNull($result);
        $this->assertSame(500, $result->getConfig()->getWarningLimit());
        $this->assertSame(800, $result->getConfig()->getBlockLimit());
        $this->assertSame('CreateOrder', $result->getScope());
    }

    public function testPerCommandSplitConfigAuthenticatedKey(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'commands' => [
                'App\\Command\\ContactForm' => [
                    'authenticated' => [
                        'warning_limit' => 20,
                        'block_limit' => 30,
                        'window' => 3600,
                    ],
                    UserType::ANONYMOUS => [
                        'warning_limit' => 3,
                        'block_limit' => 5,
                        'window' => 3600,
                    ],
                ],
            ],
        ]));

        // Customer resolves via 'authenticated' catch-all
        $result = $service->resolve('App\\Command\\ContactForm', 'command', UserType::CUSTOMER);

        $this->assertNotNull($result);
        $this->assertSame(20, $result->getConfig()->getWarningLimit());
        $this->assertSame(30, $result->getConfig()->getBlockLimit());
    }

    public function testPerCommandSplitConfigAuthenticatedKeyForPartner(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'commands' => [
                'App\\Command\\ContactForm' => [
                    'authenticated' => [
                        'warning_limit' => 20,
                        'block_limit' => 30,
                        'window' => 3600,
                    ],
                    UserType::ANONYMOUS => [
                        'warning_limit' => 3,
                        'block_limit' => 5,
                        'window' => 3600,
                    ],
                ],
            ],
        ]));

        // Partner also resolves via 'authenticated' catch-all
        $result = $service->resolve('App\\Command\\ContactForm', 'command', UserType::PARTNER);

        $this->assertNotNull($result);
        $this->assertSame(20, $result->getConfig()->getWarningLimit());
    }

    public function testPerCommandSplitConfigAnonymousKey(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'commands' => [
                'App\\Command\\ContactForm' => [
                    'authenticated' => [
                        'warning_limit' => 20,
                        'block_limit' => 30,
                        'window' => 3600,
                    ],
                    UserType::ANONYMOUS => [
                        'warning_limit' => 3,
                        'block_limit' => 5,
                        'window' => 3600,
                    ],
                ],
            ],
        ]));

        $result = $service->resolve('App\\Command\\ContactForm', 'command', null);

        $this->assertNotNull($result);
        $this->assertSame(3, $result->getConfig()->getWarningLimit());
        $this->assertSame(5, $result->getConfig()->getBlockLimit());
    }

    public function testExactUserTypeOverridesTakePriorityOverAuthenticatedCatchAll(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'commands' => [
                'App\\Command\\BulkImport' => [
                    UserType::PARTNER => [
                        'warning_limit' => 500,
                        'block_limit' => 1000,
                        'window' => 60,
                    ],
                    'authenticated' => [
                        'warning_limit' => 20,
                        'block_limit' => 30,
                        'window' => 60,
                    ],
                ],
            ],
        ]));

        // Partner gets its specific config, not the 'authenticated' catch-all
        $result = $service->resolve('App\\Command\\BulkImport', 'command', UserType::PARTNER);

        $this->assertNotNull($result);
        $this->assertSame(500, $result->getConfig()->getWarningLimit());

        // Customer falls through to 'authenticated' catch-all
        $result = $service->resolve('App\\Command\\BulkImport', 'command', UserType::CUSTOMER);

        $this->assertNotNull($result);
        $this->assertSame(20, $result->getConfig()->getWarningLimit());
    }

    public function testPerCommandFlatConfigAppliesToAllUserTypes(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'commands' => [
                'App\\Command\\CreateOrder' => [
                    'warning_limit' => 10,
                    'block_limit' => 15,
                    'window' => 60,
                ],
            ],
        ]));

        $customerResult = $service->resolve('App\\Command\\CreateOrder', 'command', UserType::CUSTOMER);
        $anonResult = $service->resolve('App\\Command\\CreateOrder', 'command', null);

        $this->assertNotNull($customerResult);
        $this->assertNotNull($anonResult);
        $this->assertSame(10, $customerResult->getConfig()->getWarningLimit());
        $this->assertSame('CreateOrder', $customerResult->getScope());
        $this->assertSame(10, $anonResult->getConfig()->getWarningLimit());
        $this->assertSame('CreateOrder', $anonResult->getScope());
    }

    public function testFallsBackToDefaultsForUserType(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig());

        $result = $service->resolve('App\\Command\\SomeCommand', 'command', UserType::CUSTOMER);

        $this->assertNotNull($result);
        $this->assertSame(30, $result->getConfig()->getWarningLimit());
        $this->assertSame(50, $result->getConfig()->getBlockLimit());
        $this->assertSame('__default__', $result->getScope());
    }

    public function testFallsBackToDefaultsForPartner(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig());

        $result = $service->resolve('App\\Command\\SomeCommand', 'command', UserType::PARTNER);

        $this->assertNotNull($result);
        $this->assertSame(100, $result->getConfig()->getWarningLimit());
        $this->assertSame(200, $result->getConfig()->getBlockLimit());
        $this->assertSame('__default__', $result->getScope());
    }

    public function testAdminDefaultNullReturnsNull(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig());

        $result = $service->resolve('App\\Command\\SomeCommand', 'command', UserType::ADMIN);

        $this->assertNull($result);
    }

    public function testAnonymousWithNoDefaultAndNoPerCommandConfigReturnsNull(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig());

        $result = $service->resolve('App\\Command\\SomeCommand', 'command', null);

        // No anonymous default — broad anonymous throttling handled at HTTP layer
        $this->assertNull($result);
    }

    public function testAnonymousWithPerCommandOverrideReturnsConfig(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'commands' => [
                'App\\Command\\ContactForm' => [
                    UserType::ANONYMOUS => [
                        'warning_limit' => 3,
                        'block_limit' => 5,
                        'window' => 3600,
                    ],
                ],
            ],
        ]));

        $result = $service->resolve('App\\Command\\ContactForm', 'command', null);

        $this->assertNotNull($result);
        $this->assertSame(3, $result->getConfig()->getWarningLimit());
        $this->assertSame(5, $result->getConfig()->getBlockLimit());
        $this->assertSame('ContactForm', $result->getScope());
    }

    public function testUnknownUserTypeWithNoDefaultReturnsNull(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'defaults' => [
                UserType::CUSTOMER => [
                    'warning_limit' => 30,
                    'block_limit' => 50,
                    'window' => 60,
                ],
            ],
        ]));

        $result = $service->resolve('App\\Command\\SomeCommand', 'command', 'unknown_type');

        $this->assertNull($result);
    }

    public function testQueryResolution(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'queries' => [
                'App\\Query\\SearchProducts' => [
                    'warning_limit' => 80,
                    'block_limit' => 120,
                    'window' => 60,
                ],
            ],
        ]));

        $result = $service->resolve('App\\Query\\SearchProducts', 'query', UserType::CUSTOMER);

        $this->assertNotNull($result);
        $this->assertSame(80, $result->getConfig()->getWarningLimit());
    }

    public function testQueryFallsBackToDefaults(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig());

        $result = $service->resolve('App\\Query\\SomeQuery', 'query', UserType::CUSTOMER);

        $this->assertNotNull($result);
        $this->assertSame(30, $result->getConfig()->getWarningLimit());
    }

    public function testEmptyConfigReturnsNull(): void
    {
        $service = new ThrottleConfigResolver([]);

        $result = $service->resolve('App\\Command\\SomeCommand', 'command', UserType::CUSTOMER);

        $this->assertNull($result);
    }

    public function testCommandConfigDoesNotAffectQueryResolution(): void
    {
        $service = new ThrottleConfigResolver($this->createConfig([
            'commands' => [
                'App\\Shared\\SomeName' => [
                    'warning_limit' => 999,
                    'block_limit' => 1000,
                    'window' => 60,
                ],
            ],
        ]));

        // Same class name in 'query' section should not find the command config
        $result = $service->resolve('App\\Shared\\SomeName', 'query', UserType::CUSTOMER);

        $this->assertNotNull($result);
        // Falls through to customer default, not the command config
        $this->assertSame(30, $result->getConfig()->getWarningLimit());
    }
}
