<?php

namespace Fuel\Core;

use DateTimeImmutable;
use Infrastructure\Security\Resolver\AdminSessionResolver;
use SharedKernel\Domain\Security\AuthenticatedUser;
use SharedKernel\Domain\Security\RememberMe\AdminRememberMeServiceInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;
use SharedKernel\Domain\Security\UserRepository\AdminUserRepositoryInterface;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Domain\ValueObjects\EmailAddress;

/**
 * @group App
 * @group Security
 */
class Test_AdminSessionResolver extends TestCase
{
    public function test_returns_user_when_session_type_matches_audience()
    {
        $admin = new AuthenticatedUser('42', 'admin@example.com', ['admin'], UserType::ADMIN);

        $resolver = new AdminSessionResolver(
            $this->sessionWith('42', UserType::ADMIN),
            $this->stubRememberMe(),
            $this->stubUsersWith($admin)
        );

        $this->assertSame($admin, $resolver->resolve());
    }

    public function test_returns_null_when_session_type_is_for_another_audience()
    {
        // Partner user id=42 must not authenticate as admin id=42 even if admin_users.id=42 exists.
        $admin = new AuthenticatedUser('42', 'admin@example.com', ['admin'], UserType::ADMIN);

        $resolver = new AdminSessionResolver(
            $this->sessionWith('42', UserType::PARTNER),
            $this->stubRememberMe(),
            $this->stubUsersWith($admin)
        );

        $this->assertNull($resolver->resolve());
    }

    public function test_returns_null_when_session_type_is_missing()
    {
        $admin = new AuthenticatedUser('42', 'admin@example.com', ['admin'], UserType::ADMIN);

        $resolver = new AdminSessionResolver(
            $this->sessionWith('42', null),
            $this->stubRememberMe(),
            $this->stubUsersWith($admin)
        );

        $this->assertNull($resolver->resolve());
    }

    private function sessionWith(?string $userId, ?string $userType): SessionAuthenticatorInterface
    {
        return new class ($userId, $userType) implements SessionAuthenticatorInterface {
            private ?string $userId;
            private ?string $userType;
            public function __construct(?string $userId, ?string $userType)
            {
                $this->userId = $userId;
                $this->userType = $userType;
            }
            public function login(AuthenticatedUser $user): void
            {
            }
            public function logout(): void
            {
            }
            public function getCurrentUserId(): ?string
            {
                return $this->userId;
            }
            public function getCurrentUserType(): ?string
            {
                return $this->userType;
            }
        };
    }

    private function stubRememberMe(): AdminRememberMeServiceInterface
    {
        return new class implements AdminRememberMeServiceInterface {
            public function rememberUser(AuthenticatedUser $user): void
            {
            }
            public function tryReanimate(): ?AuthenticatedUser
            {
                return null;
            }
            public function forget(): void
            {
            }
            public function forgetAllForUser(AuthenticatedUser $user): void
            {
            }
        };
    }

    private function stubUsersWith(AuthenticatedUser $user): AdminUserRepositoryInterface
    {
        return new class ($user) implements AdminUserRepositoryInterface {
            private AuthenticatedUser $user;
            public function __construct(AuthenticatedUser $user)
            {
                $this->user = $user;
            }
            public function findById(string $id): ?AuthenticatedUser
            {
                return $id === $this->user->getId() ? $this->user : null;
            }
            public function findByEmail(EmailAddress $email): ?AuthenticatedUser
            {
                return null;
            }
            public function getPasswordHashByEmail(EmailAddress $email): ?string
            {
                return null;
            }
            public function updateLastLogin(string $id, DateTimeImmutable $at): void
            {
            }
        };
    }
}
