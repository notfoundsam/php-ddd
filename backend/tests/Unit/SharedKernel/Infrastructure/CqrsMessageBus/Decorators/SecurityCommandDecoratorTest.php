<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\CqrsMessageBus\Decorators;

use PHPUnit\Framework\TestCase;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\AuthorizationServiceInterface;
use SharedKernel\Domain\Security\Exception\SecurityConfigurationException;
use SharedKernel\Domain\Security\Exception\UnauthenticatedException;
use SharedKernel\Domain\Security\Exception\UnauthorizedException;
use SharedKernel\Domain\Security\SecurityConfigInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\SecurityCommandDecorator;

class SecurityCommandDecoratorTest extends TestCase
{
    public function testMissingConfigEntryThrowsAndDoesNotDispatch(): void
    {
        $command = $this->command();
        $inner = $this->innerBus();
        $decorator = new SecurityCommandDecorator(
            $inner,
            $this->context(null),
            $this->authz(false),
            $this->config([])
        );

        try {
            $decorator->dispatch($command);
            $this->fail('Expected SecurityConfigurationException');
        } catch (SecurityConfigurationException $e) {
            $this->assertStringContainsString(get_class($command), $e->getMessage());
        }
        $this->assertSame(0, $inner->dispatched);
    }

    public function testNullPermissionAllowsAnonymousDispatch(): void
    {
        $command = $this->command();
        $inner = $this->innerBus();
        $decorator = new SecurityCommandDecorator(
            $inner,
            $this->context(null),
            $this->authz(false),
            $this->config([get_class($command) => null])
        );

        $decorator->dispatch($command);
        $this->assertSame(1, $inner->dispatched);
    }

    public function testPermissionRequiredButNoUserThrowsUnauthenticated(): void
    {
        $command = $this->command();
        $inner = $this->innerBus();
        $decorator = new SecurityCommandDecorator(
            $inner,
            $this->context(null),
            $this->authz(true),
            $this->config([get_class($command) => 'foo.do'])
        );

        $this->expectException(UnauthenticatedException::class);
        try {
            $decorator->dispatch($command);
        } finally {
            $this->assertSame(0, $inner->dispatched);
        }
    }

    public function testUserLacksPermissionThrowsUnauthorized(): void
    {
        $command = $this->command();
        $inner = $this->innerBus();
        $user = new AuthenticatedUser('1', 'a@example.com', ['viewer']);
        $decorator = new SecurityCommandDecorator(
            $inner,
            $this->context($user),
            $this->authz(false),
            $this->config([get_class($command) => 'foo.delete'])
        );

        try {
            $decorator->dispatch($command);
            $this->fail('Expected UnauthorizedException');
        } catch (UnauthorizedException $e) {
            $this->assertSame('foo.delete', $e->getRequiredPermission());
        }
        $this->assertSame(0, $inner->dispatched);
    }

    public function testUserWithPermissionDispatches(): void
    {
        $command = $this->command();
        $inner = $this->innerBus();
        $user = new AuthenticatedUser('1', 'a@example.com', ['admin']);
        $decorator = new SecurityCommandDecorator(
            $inner,
            $this->context($user),
            $this->authz(true),
            $this->config([get_class($command) => 'foo.do'])
        );

        $decorator->dispatch($command);

        $this->assertSame(1, $inner->dispatched);
        $this->assertSame($command, $inner->lastCommand);
    }

    public function testCliMisuseFailsClosed(): void
    {
        // A bulk-import script that forgets to populate SecurityContext should fail closed,
        // not silently allow execution. This is the most likely production bug class.
        $command = $this->command();
        $inner = $this->innerBus();
        $decorator = new SecurityCommandDecorator(
            $inner,
            $this->context(null),
            $this->authz(true),
            $this->config([get_class($command) => 'bulk.import'])
        );

        $this->expectException(UnauthenticatedException::class);
        try {
            $decorator->dispatch($command);
        } finally {
            $this->assertSame(0, $inner->dispatched);
        }
    }

    private function command(): CommandInterface
    {
        return new class implements CommandInterface {
        };
    }

    /**
     * @return CommandBusInterface&object{dispatched: int, lastCommand: ?CommandInterface}
     */
    private function innerBus(): CommandBusInterface
    {
        return new class implements CommandBusInterface {
            public int $dispatched = 0;
            public ?CommandInterface $lastCommand = null;

            public function dispatch(CommandInterface $command): void
            {
                $this->dispatched++;
                $this->lastCommand = $command;
            }
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
     * @param array<class-string, string|null> $commands
     */
    private function config(array $commands): SecurityConfigInterface
    {
        return new class ($commands) implements SecurityConfigInterface {
            /** @var array<class-string, string|null> */
            private array $commands;

            /**
             * @param array<class-string, string|null> $commands
             */
            public function __construct(array $commands)
            {
                $this->commands = $commands;
            }

            public function getCommandPermissions(): array
            {
                return $this->commands;
            }

            public function getQueryPermissions(): array
            {
                return [];
            }

            public function getRolePermissions(): array
            {
                return [];
            }
        };
    }
}
