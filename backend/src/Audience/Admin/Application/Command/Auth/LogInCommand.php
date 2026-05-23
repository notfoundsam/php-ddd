<?php

declare(strict_types=1);

namespace Audience\Admin\Application\Command\Auth;

use SharedKernel\Application\CqrsMessageBus\Commands\CommandInterface;
use SharedKernel\Domain\Security\PlaintextPassword;
use SharedKernel\Domain\ValueObjects\EmailAddress;

final class LogInCommand implements CommandInterface
{
    private EmailAddress $email;

    private PlaintextPassword $password;

    private bool $remember;

    public function __construct(EmailAddress $email, PlaintextPassword $password, bool $remember = false)
    {
        $this->email = $email;
        $this->password = $password;
        $this->remember = $remember;
    }

    public function getEmail(): EmailAddress
    {
        return $this->email;
    }

    public function getPassword(): PlaintextPassword
    {
        return $this->password;
    }

    public function isRemember(): bool
    {
        return $this->remember;
    }
}
