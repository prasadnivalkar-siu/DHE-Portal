<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        return auth()->loggedIn()
            ? redirect()->to(url_to('dashboard'))
            : redirect()->to(url_to('login'));
    }
}
