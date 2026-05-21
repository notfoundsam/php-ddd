<?php

use Audience\Partner\Application\Command\Auth\LogInCommand;
use Audience\Partner\Application\Command\Auth\LogOutCommand;
use Fuel\Core\Input;
use Fuel\Core\Validation;
use SharedKernel\Domain\Security\Exception\InvalidCredentialsException;
use SharedKernel\Domain\Security\PlaintextPassword;
use SharedKernel\Domain\ValueObjects\EmailAddress;

class Controller_Partner_Auth extends Controller_Partner_Abstract
{
    public function get_login()
    {
        return Blade::respond('partner.auth.login');
    }

    public function post_login()
    {
        $validation = Validation::forge('partner_login');
        $validation->add('email', 'Email')->add_rule('required')->add_rule('valid_email');
        $validation->add('password', 'Password')->add_rule('required')->add_rule('min_length', 1);

        if (!$validation->run(Input::post())) {
            return Blade::respond('partner.auth.login', [
                'errors' => $validation->error_message(),
                'email' => (string)Input::post('email', ''),
            ]);
        }

        try {
            $this->commandBus->dispatch(new LogInCommand(
                new EmailAddress((string)$validation->validated('email')),
                new PlaintextPassword((string)$validation->validated('password')),
                (bool)Input::post('remember', false)
            ));
        } catch (InvalidCredentialsException $e) {
            return Blade::respond('partner.auth.login', [
                'errors' => ['Invalid email or password.'],
                'email' => (string)Input::post('email', ''),
            ]);
        }

        return $this->redirect();
    }

    public function action_logout()
    {
        $this->commandBus->dispatch(new LogOutCommand());
        return $this->redirect('login');
    }
}
