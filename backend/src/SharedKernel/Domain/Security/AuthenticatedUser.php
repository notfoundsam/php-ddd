<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security;

final class AuthenticatedUser
{
    private string $id;

    private string $email;

    /** @var array<string> */
    private array $roles;

    public function __construct(string $id, string $email, array $roles = [])
    {
        $this->id = $id;
        $this->email = $email;
        $this->roles = $roles;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }
}
