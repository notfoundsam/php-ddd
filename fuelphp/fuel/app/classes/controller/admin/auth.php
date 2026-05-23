<?php

use Audience\Admin\Application\Command\Auth\LogInCommand;
use Audience\Admin\Application\Command\Auth\LogOutCommand;
use Fuel\Core\Input;
use Fuel\Core\Security;
use Fuel\Core\Validation;
use SharedKernel\Domain\Security\Exception\InvalidCredentialsException;
use SharedKernel\Domain\Security\PlaintextPassword;
use SharedKernel\Domain\Throttle\Exceptions\ThrottleException;
use SharedKernel\Domain\ValueObjects\EmailAddress;

class Controller_Admin_Auth extends Controller_Admin_Abstract
{
    public function get_login()
    {
        return Blade::respond('admin.auth.login', [
            'csrf_token' => Security::fetch_token(),
        ]);
    }

    public function post_login()
    {
        if (!Security::check_token()) {
            return Blade::respond('admin.auth.login', [
                'csrf_token' => Security::fetch_token(),
                'errors' => ['Your session has expired. Please try again.'],
                'email' => (string)Input::post('email', ''),
            ]);
        }

        $validation = Validation::forge('admin_login');
        $validation->add('email', 'Email')->add_rule('required')->add_rule('valid_email');
        $validation->add('password', 'Password')->add_rule('required')->add_rule('min_length', 1)->add_rule('max_length', 72);

        if (!$validation->run(Input::post())) {
            return Blade::respond('admin.auth.login', [
                'csrf_token' => Security::fetch_token(),
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
            return Blade::respond('admin.auth.login', [
                'csrf_token' => Security::fetch_token(),
                'errors' => ['Invalid email or password.'],
                'email' => (string)Input::post('email', ''),
            ]);
        } catch (ThrottleException $e) {
            return Blade::respond('admin.auth.login', [
                'csrf_token' => Security::fetch_token(),
                'errors' => ['Too many login attempts. Please try again later.'],
                'email' => (string)Input::post('email', ''),
            ]);
        }

        return $this->redirect();
    }

    // Any logout button/form must include `fuel_csrf_token` (via Security::fetch_token()).
    // Without it this action silently redirects to /login — no-op rather than logout.
    public function post_logout()
    {
        if (!Security::check_token()) {
            return $this->redirect('login');
        }
        $this->commandBus->dispatch(new LogOutCommand());
        return $this->redirect('login');
    }
}
