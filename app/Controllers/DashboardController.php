<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('dashboard/index', [
            'title' => 'Dashboard · CampusIQ',
            'topbar' => ['search' => true, 'logoutButton' => true],
        ]);
    }
}
