<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomeResource;
use App\Services\HomeService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(private HomeService $homeService) {}

    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            ...HomeResource::make(
                $this->homeService->build($request->user())
            )->resolve($request),
        ]);
    }
}
