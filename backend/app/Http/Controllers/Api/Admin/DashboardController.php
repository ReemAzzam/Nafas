<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;

class DashboardController extends Controller
{
    public function __construct(private AdminDashboardService $dashboard) {}

    public function show()
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboard->overview(),
        ]);
    }
}
