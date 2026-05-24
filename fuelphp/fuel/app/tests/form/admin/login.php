<?php

namespace Fuel\Core;

require_once __DIR__ . '/../login_form_test_case.php';

use Audience\Admin\Application\Command\Auth\LogInCommand;
use Form_Admin_Login;

/**
 * @group App
 * @group FormAdminLogin
 */
class Test_Form_Admin_Login extends Test_Login_Form_TestCase
{
    protected function formClass(): string
    {
        return Form_Admin_Login::class;
    }

    protected function commandClass(): string
    {
        return LogInCommand::class;
    }
}
