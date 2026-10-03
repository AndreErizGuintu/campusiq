<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

class PortalController extends Controller
{
    public function records(Request $request): Response
    {
        $student = $this->linkedStudent();

        return $this->view('portal/records', [
            'title' => 'My records · CampusIQ',
            'student' => $student,
            'topbar' => ['crumbs' => [['Home', '/'], ['My records', null]]],
        ], 'portal');
    }
}
