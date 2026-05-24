<?php

namespace Fuel\Core;

require_once __DIR__ . '/../login_form_test_case.php';

use Audience\Site\Application\Command\Auth\LogInCommand;
use Form_Site_Login;

/**
 * @group App
 * @group FormSiteLogin
 */
class Test_Form_Site_Login extends Test_Login_Form_TestCase
{
    protected function formClass(): string
    {
        return Form_Site_Login::class;
    }

    protected function commandClass(): string
    {
        return LogInCommand::class;
    }
}
