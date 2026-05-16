<?php

class Controller_Admin_Welcome extends Controller_Admin_Abstract
{
    public function action_index()
    {
        return Blade::respond('admin.welcome.index');
    }
}
