<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain\Security\Exception;

use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SharedKernel\Domain\Security\Exception\SecurityConfigurationException;
use SharedKernel\Domain\Security\Exception\UnauthenticatedException;
use SharedKernel\Domain\Security\Exception\UnauthorizedException;

class SecurityExceptionsTest extends TestCase
{
    public function testUnauthenticatedException(): void
    {
        $e = new UnauthenticatedException();

        $this->assertInstanceOf(RuntimeException::class, $e);
        $this->assertSame('Authentication required', $e->getMessage());
    }

    public function testUnauthorizedExceptionExposesRequiredPermission(): void
    {
        $e = new UnauthorizedException('user.delete');

        $this->assertInstanceOf(RuntimeException::class, $e);
        $this->assertSame('user.delete', $e->getRequiredPermission());
        $this->assertSame('Access denied', $e->getMessage());
    }

    /**
     * @dataProvider securityConfigurationFactoryProvider
     */
    public function testSecurityConfigurationExceptionFactories(
        SecurityConfigurationException $exception,
        string $expectedClassFragment,
        string $expectedKeywordFragment
    ): void {
        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertStringContainsString($expectedClassFragment, $exception->getMessage());
        $this->assertStringContainsString($expectedKeywordFragment, $exception->getMessage());
    }

    /**
     * @return array<string, array{0: SecurityConfigurationException, 1: string, 2: string}>
     */
    public function securityConfigurationFactoryProvider(): array
    {
        return [
            'commandNotRegistered' => [
                SecurityConfigurationException::commandNotRegistered('App\\CreateFooCommand'),
                'App\\CreateFooCommand',
                'command',
            ],
            'queryNotRegistered' => [
                SecurityConfigurationException::queryNotRegistered('App\\GetFooQuery'),
                'App\\GetFooQuery',
                'query',
            ],
            'duplicateCommandEntry' => [
                SecurityConfigurationException::duplicateCommandEntry('App\\CreateFooCommand'),
                'App\\CreateFooCommand',
                'Duplicate',
            ],
            'duplicateQueryEntry' => [
                SecurityConfigurationException::duplicateQueryEntry('App\\GetFooQuery'),
                'App\\GetFooQuery',
                'Duplicate',
            ],
        ];
    }
}
