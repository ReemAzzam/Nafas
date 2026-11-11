<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Progress\ProgressInsightRequest;
use App\Http\Resources\ProgressInsightResource;
use App\Services\ProgressInsightService;

class ProgressInsightController extends Controller
{
    public function __construct(private ProgressInsightService $insights) {}

    public function show(ProgressInsightRequest $request)
    {
        $data = $this->insights->build(
            $request->user(),
            $request->validated('period'),
            $request->validated('date')
        );

        return response()->json([
            'success' => true,
            ...ProgressInsightResource::make($data)->resolve($request),
        ]);
    }
}
