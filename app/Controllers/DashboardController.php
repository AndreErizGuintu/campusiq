<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\StatsService;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = new StatsService();

        return $this->view('dashboard/index', [
            'title' => 'Dashboard · CampusIQ',
            'topbar' => ['search' => true, 'logoutButton' => true],
            'summary' => $stats->summary(),
            'days' => $stats->attendanceDays(5),
            'activity' => $stats->recentActivity(6, Auth::id()),
        ]);
    }
}
