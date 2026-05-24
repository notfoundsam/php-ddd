<?php

namespace Fuel\Core;

require_once __DIR__ . '/../login_form_test_case.php';

use Audience\Partner\Application\Command\Auth\LogInCommand;
use Form_Partner_Login;

/**
 * @group App
 * @group FormPartnerLogin
 */
class Test_Form_Partner_Login extends Test_Login_Form_TestCase
{
    protected function formClass(): string
    {
        return Form_Partner_Login::class;
    }

    protected function commandClass(): string
    {
        return LogInCommand::class;
    }
}
