<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use PHPUnit\Framework\TestCase;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryResponseInterface;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\AuthorizationServiceInterface;
use SharedKernel\Domain\Security\Exception\SecurityConfigurationException;
use SharedKernel\Domain\Security\Exception\UnauthenticatedException;
use SharedKernel\Domain\Security\Exception\UnauthorizedException;
use SharedKernel\Domain\Security\SecurityConfigInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\SecurityQueryDecorator;
use Tests\Fixtures\SharedKernel\Security\SpyQueryBus;

class SecurityQueryDecoratorTest extends TestCase
{
    public function testMissingConfigEntryThrowsAndDoesNotDispatch(): void
    {
        $query = $this->query();
        $inner = new SpyQueryBus();
        $decorator = new SecurityQueryDecorator(
            $inner,
            $this->context(null),
            $this->authz(false),
            $this->config([])
        );

        try {
            $decorator->dispatch($query);
            $this->fail('Expected SecurityConfigurationException');
        } catch (SecurityConfigurationException $e) {
            $this->assertStringContainsString(get_class($query), $e->getMessage());
        }
        $this->assertSame(0, $inner->dispatched);
    }

    public function testNullPermissionAllowsAnonymousDispatchAndReturnsResponse(): void
    {
        $query = $this->query();
        $expectedResponse = $this->response();
        $inner = new SpyQueryBus($expectedResponse);
        $decorator = new SecurityQueryDecorator(
            $inner,
            $this->context(null),
            $this->authz(false),
            $this->config([get_class($query) => null])
        );

        $result = $decorator->dispatch($query);
        $this->assertSame(1, $inner->dispatched);
        $this->assertSame($expectedResponse, $result);
    }

    public function testPermissionRequiredButNoUserThrowsUnauthenticated(): void
    {
        $query = $this->query();
        $inner = new SpyQueryBus();
        $decorator = new SecurityQueryDecorator(
            $inner,
            $this->context(null),
            $this->authz(true),
            $this->config([get_class($query) => 'foo.read'])
        );

        $this->expectException(UnauthenticatedException::class);
        try {
            $decorator->dispatch($query);
        } finally {
            $this->assertSame(0, $inner->dispatched);
        }
    }

    public function testUserLacksPermissionThrowsUnauthorized(): void
    {
        $query = $this->query();
        $inner = new SpyQueryBus();
        $user = new AuthenticatedUser('1', 'a@example.com', ['viewer']);
        $decorator = new SecurityQueryDecorator(
            $inner,
            $this->context($user),
            $this->authz(false),
            $this->config([get_class($query) => 'foo.sensitive'])
        );

        try {
            $decorator->dispatch($query);
            $this->fail('Expected UnauthorizedException');
        } catch (UnauthorizedException $e) {
            $this->assertSame('foo.sensitive', $e->getRequiredPermission());
        }
        $this->assertSame(0, $inner->dispatched);
    }

    public function testUserWithPermissionDispatchesAndReturnsResponse(): void
    {
        $query = $this->query();
        $expectedResponse = $this->response();
        $inner = new SpyQueryBus($expectedResponse);
        $user = new AuthenticatedUser('1', 'a@example.com', ['admin']);
        $decorator = new SecurityQueryDecorator(
            $inner,
            $this->context($user),
            $this->authz(true),
            $this->config([get_class($query) => 'foo.read'])
        );

        $result = $decorator->dispatch($query);

        $this->assertSame(1, $inner->dispatched);
        $this->assertSame($query, $inner->lastQuery);
        $this->assertSame($expectedResponse, $result);
    }

    public function testCliMisuseFailsClosed(): void
    {
        $query = $this->query();
        $inner = new SpyQueryBus();
        $decorator = new SecurityQueryDecorator(
            $inner,
            $this->context(null),
            $this->authz(true),
            $this->config([get_class($query) => 'bulk.read'])
        );

        $this->expectException(UnauthenticatedException::class);
        try {
            $decorator->dispatch($query);
        } finally {
            $this->assertSame(0, $inner->dispatched);
        }
    }

    private function query(): QueryInterface
    {
        return new class implements QueryInterface {
        };
    }

    private function response(): QueryResponseInterface
    {
        return new class implements QueryResponseInterface {
        };
    }

    private function context(?AuthenticatedUser $user): SecurityContextInterface
    {
        return new class ($user) implements SecurityContextInterface {
            private ?AuthenticatedUser $user;

            public function __construct(?AuthenticatedUser $user)
            {
                $this->user = $user;
            }

            public function getCurrentUser(): ?AuthenticatedUser
            {
                return $this->user;
            }

            public function setCurrentUser(AuthenticatedUser $user): void
            {
                $this->user = $user;
            }

            public function hasAuthenticatedUser(): bool
            {
                return $this->user !== null;
            }

            public function clearUser(): void
            {
                $this->user = null;
            }
        };
    }

    private function authz(bool $allowed): AuthorizationServiceInterface
    {
        return new class ($allowed) implements AuthorizationServiceInterface {
            private bool $allowed;

            public function __construct(bool $allowed)
            {
                $this->allowed = $allowed;
            }

            public function isAllowed(AuthenticatedUser $user, string $permission): bool
            {
                return $this->allowed;
            }
        };
    }

    /**
     * @param array<class-string, string|null> $queries
     */
    private function config(array $queries): SecurityConfigInterface
    {
        return new class ($queries) implements SecurityConfigInterface {
            /** @var array<class-string, string|null> */
            private array $queries;

            /**
             * @param array<class-string, string|null> $queries
             */
            public function __construct(array $queries)
            {
                $this->queries = $queries;
            }

            public function getCommandPermissions(): array
            {
                return [];
            }

            public function getQueryPermissions(): array
            {
                return $this->queries;
            }

            public function getRolePermissions(): array
            {
                return [];
            }
        };
    }
}
