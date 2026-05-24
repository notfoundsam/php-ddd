<?php

declare(strict_types=1);

use Audience\Admin\Application\Command\Auth\LogInCommand;
use Fuel\Core\Lang;
use Fuel\Core\Validation;
use SharedKernel\Domain\Security\PlaintextPassword;
use SharedKernel\Domain\ValueObjects\EmailAddress;

class Form_Admin_Login
{
    /** @var array<string, string> field => localized error message */
    private array $errors = [];

    /** @var array<string, mixed> sanitised values; populated only when validation succeeds */
    private array $values = [];

    /** @var string raw email kept for re-rendering the form on error */
    private string $rawEmail = '';

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromHttpInput(array $raw): self
    {
        Lang::load('admin/login', true);

        $form = new self();
        if (is_string($raw['email'] ?? null)) {
            $raw['email'] = trim($raw['email']);
        }
        $form->rawEmail = $raw['email'] ?? '';

        $v = Validation::forge('admin_login');
        $v->add_field('email', __('admin/login.email'), 'required|valid_email');
        $v->add_field('password', __('admin/login.password'), 'required|max_length[72]');

        if ($v->run($raw)) {
            $form->values = $v->validated();
            $form->values['remember'] = !empty($raw['remember']);
        } else {
            $form->errors = $v->error_message();
        }

        return $form;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function email(): string
    {
        return $this->rawEmail;
    }

    public function toCommand(): LogInCommand
    {
        if (!$this->isValid()) {
            throw new LogicException('Cannot build command from invalid form.');
        }
        return new LogInCommand(
            new EmailAddress((string)$this->values['email']),
            new PlaintextPassword((string)$this->values['password']),
            (bool)$this->values['remember'],
        );
    }
}
