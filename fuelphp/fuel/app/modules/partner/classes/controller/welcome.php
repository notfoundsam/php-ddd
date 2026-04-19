<?php

namespace Partner;

use Fuel\Core\Response;
use Fuel\Core\View;

class Controller_Welcome extends Controller_Abstract
{
    public function action_index()
    {
        return Response::forge(View::forge('welcome/index'));
    }
}
