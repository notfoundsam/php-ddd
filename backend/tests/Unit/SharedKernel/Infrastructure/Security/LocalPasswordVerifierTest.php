<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Infrastructure\Security;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Security\PasswordVerifier\AdminPasswordVerifierInterface;
use SharedKernel\Domain\Security\UserRepository\AdminUserRepositoryInterface;
use SharedKernel\Domain\Security\PasswordVerifier\PartnerPasswordVerifierInterface;
use SharedKernel\Domain\Security\UserRepository\PartnerUserRepositoryInterface;
use SharedKernel\Domain\Security\PlaintextPassword;
use SharedKernel\Domain\Security\PasswordVerifier\SitePasswordVerifierInterface;
use SharedKernel\Domain\Security\UserRepository\SiteUserRepositoryInterface;
use SharedKernel\Domain\Security\UserType;
use SharedKernel\Domain\ValueObjects\EmailAddress;
use SharedKernel\Infrastructure\Security\AdminLocalPasswordVerifier;
use SharedKernel\Infrastructure\Security\BcryptPasswordHasher;
use SharedKernel\Infrastructure\Security\PartnerLocalPasswordVerifier;
use SharedKernel\Infrastructure\Security\SiteLocalPasswordVerifier;
use Tests\Fixtures\SharedKernel\Security\InMemoryUserRepository;
use Tests\Fixtures\SharedKernel\Security\SpyingHasher;

class LocalPasswordVerifierTest extends TestCase
{
    private BcryptPasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new BcryptPasswordHasher(4);
    }

    public function testVerifyReturnsUserOnCorrectCredentials(): void
    {
        $users = $this->makeAdminRepo();
        $users->addUser(
            InMemoryUserRepository::makeUser('42', 'admin@example.com', UserType::ADMIN, ['admin']),
            $this->hasher->hash('s3cr3t!')
        );

        $verifier = new AdminLocalPasswordVerifier($users, $this->hasher);

        $user = $verifier->verify(new EmailAddress('admin@example.com'), new PlaintextPassword('s3cr3t!'));

        $this->assertNotNull($user);
        $this->assertSame('42', $user->getId());
        $this->assertSame(UserType::ADMIN, $user->getType());
    }

    public function testVerifyReturnsNullForWrongPassword(): void
    {
        $users = $this->makeAdminRepo();
        $users->addUser(
            InMemoryUserRepository::makeUser('42', 'admin@example.com'),
            $this->hasher->hash('correct')
        );

        $verifier = new AdminLocalPasswordVerifier($users, $this->hasher);

        $this->assertNull(
            $verifier->verify(new EmailAddress('admin@example.com'), new PlaintextPassword('wrong'))
        );
    }

    public function testVerifyReturnsNullForUnknownUserAndStillCallsHasher(): void
    {
        $users = $this->makeAdminRepo();
        $hasher = new SpyingHasher($this->hasher);

        $verifier = new AdminLocalPasswordVerifier($users, $hasher);

        $result = $verifier->verify(new EmailAddress('ghost@example.com'), new PlaintextPassword('whatever'));

        $this->assertNull($result);
        $this->assertSame(1, $hasher->verifyCalls, 'verify() must run on a dummy hash for timing equalization');
    }

    public function testDummyHashMatchesConfiguredCostForTimingEqualization(): void
    {
        $users = $this->makeAdminRepo();
        $hasher = new SpyingHasher($this->hasher);

        $verifier = new AdminLocalPasswordVerifier($users, $hasher);
        $verifier->verify(new EmailAddress('ghost@example.com'), new PlaintextPassword('whatever'));

        $info = password_get_info((string)$hasher->lastVerifyHash);
        $this->assertSame(PASSWORD_BCRYPT, $info['algo'], 'dummy hash must be bcrypt');
        $this->assertSame(4, $info['options']['cost'] ?? null, 'dummy hash cost must match the configured hasher cost');
    }

    public function testPartnerVerifierAssignsPartnerUserType(): void
    {
        $users = $this->makePartnerRepo();
        $users->addUser(
            InMemoryUserRepository::makeUser('7', 'p@example.com', UserType::PARTNER, ['partner_owner']),
            $this->hasher->hash('pw')
        );

        $verifier = new PartnerLocalPasswordVerifier($users, $this->hasher);

        $user = $verifier->verify(new EmailAddress('p@example.com'), new PlaintextPassword('pw'));

        $this->assertNotNull($user);
        $this->assertSame(UserType::PARTNER, $user->getType());
    }

    public function testSiteVerifierAssignsCustomerUserType(): void
    {
        $users = $this->makeSiteRepo();
        $users->addUser(
            InMemoryUserRepository::makeUser('100', 'c@example.com', UserType::CUSTOMER, []),
            $this->hasher->hash('pw')
        );

        $verifier = new SiteLocalPasswordVerifier($users, $this->hasher);

        $user = $verifier->verify(new EmailAddress('c@example.com'), new PlaintextPassword('pw'));

        $this->assertNotNull($user);
        $this->assertSame(UserType::CUSTOMER, $user->getType());
    }

    public function testConcreteVerifiersImplementMarkerInterfaces(): void
    {
        $this->assertInstanceOf(
            AdminPasswordVerifierInterface::class,
            new AdminLocalPasswordVerifier($this->makeAdminRepo(), $this->hasher)
        );
        $this->assertInstanceOf(
            PartnerPasswordVerifierInterface::class,
            new PartnerLocalPasswordVerifier($this->makePartnerRepo(), $this->hasher)
        );
        $this->assertInstanceOf(
            SitePasswordVerifierInterface::class,
            new SiteLocalPasswordVerifier($this->makeSiteRepo(), $this->hasher)
        );
    }

    /**
     * @return InMemoryUserRepository&AdminUserRepositoryInterface
     */
    private function makeAdminRepo(): InMemoryUserRepository
    {
        return new class extends InMemoryUserRepository implements AdminUserRepositoryInterface {
        };
    }

    /**
     * @return InMemoryUserRepository&PartnerUserRepositoryInterface
     */
    private function makePartnerRepo(): InMemoryUserRepository
    {
        return new class extends InMemoryUserRepository implements PartnerUserRepositoryInterface {
        };
    }

    /**
     * @return InMemoryUserRepository&SiteUserRepositoryInterface
     */
    private function makeSiteRepo(): InMemoryUserRepository
    {
        return new class extends InMemoryUserRepository implements SiteUserRepositoryInterface {
        };
    }
}
