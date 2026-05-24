<?php

use Audience\Partner\Application\Command\Auth\LogOutCommand;
use Fuel\Core\Input;
use Fuel\Core\Lang;
use Fuel\Core\Security;
use SharedKernel\Domain\Security\Exception\InvalidCredentialsException;
use SharedKernel\Domain\Throttle\Exceptions\ThrottleException;

class Controller_Partner_Auth extends Controller_Partner_Abstract
{
    public function get_login()
    {
        return Blade::respond('partner.auth.login', [
            'csrf_token' => Security::fetch_token(),
        ]);
    }

    public function post_login()
    {
        if (!Security::check_token()) {
            return $this->renderLogin([__('errors.csrf_expired')]);
        }

        $form = Form_Partner_Login::fromHttpInput(Input::post());
        if (!$form->isValid()) {
            return $this->renderLogin(array_values($form->errors()), $form->email());
        }

        Lang::load('auth', true);

        try {
            $this->commandBus->dispatch($form->toCommand());
        } catch (InvalidCredentialsException $e) {
            return $this->renderLogin([__('auth.invalid_credentials')], $form->email());
        } catch (ThrottleException $e) {
            return $this->renderLogin([__('errors.throttled')], $form->email());
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

    /**
     * @param array<int, string> $errors
     */
    private function renderLogin(array $errors, string $email = '')
    {
        return Blade::respond('partner.auth.login', [
            'csrf_token' => Security::fetch_token(),
            'errors' => $errors,
            'email' => $email,
        ]);
    }
}
