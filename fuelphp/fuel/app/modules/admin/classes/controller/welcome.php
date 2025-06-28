<?php

namespace Admin;

use Fuel\Core\Controller;
use Fuel\Core\Response;
use Fuel\Core\View;

class Controller_Welcome extends Controller
{
	public function action_index()
	{
		return Response::forge(View::forge('welcome/index'));
	}
}
