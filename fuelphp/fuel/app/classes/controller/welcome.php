<?php

class Controller_Welcome extends Controller
{
    public function action_index()
    {
        return Blade::respond('welcome.index');
    }

    public function action_hello()
    {
        $name = $this->request->param('name', 'World');
        return Blade::respond('welcome.hello', ['name' => $name]);
    }

    public function action_404()
    {
        $messages = ['Aw, crap!', 'Bloody Hell!', 'Uh Oh!', 'Nope, not here.', 'Huh?'];
        $title = $messages[array_rand($messages)];
        return Blade::respond('welcome.404', ['title' => $title], 404);
    }
}
