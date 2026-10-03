<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home/index', ['title' => 'CampusIQ: academic records, without the digging'], 'public');
    }
}
