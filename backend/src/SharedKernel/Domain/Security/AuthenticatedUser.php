<?php

declare(strict_types=1);

namespace SharedKernel\Domain\Security;

final class AuthenticatedUser
{
    private string $id;

    private string $email;

    /** @var array<string> */
    private array $roles;

    private string $type;

    /**
     * @param array<string> $roles
     */
    public function __construct(string $id, string $email, array $roles = [], string $type = UserType::CUSTOMER)
    {
        $this->id = $id;
        $this->email = $email;
        $this->roles = $roles;
        $this->type = $type;
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

    public function getType(): string
    {
        return $this->type;
    }
}
