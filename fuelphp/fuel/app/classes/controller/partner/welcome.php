<?php

class Controller_Partner_Welcome extends Controller_Partner_Abstract
{
    public function action_index()
    {
        return Blade::respond('partner.welcome.index');
    }
}
