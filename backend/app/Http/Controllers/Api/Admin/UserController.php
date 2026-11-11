<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexUsersRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Services\Admin\AdminUserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private AdminUserService $users) {}

    public function index(IndexUsersRequest $request)
    {
        return AdminUserResource::collection(
            $this->users->paginate($request->validated())
        )->additional(['success' => true]);
    }

    public function show(int $user)
    {
        return response()->json([
            'success' => true,
            'user' => AdminUserResource::make($this->users->find($user)),
        ]);
    }

    public function block(Request $request, int $user)
    {
        return $this->stateResponse(
            $this->users->setBlocked($request->user(), $user, true),
            'تم حظر المستخدم وإلغاء جلساته بنجاح.',
        );
    }

    public function unblock(Request $request, int $user)
    {
        return $this->stateResponse(
            $this->users->setBlocked($request->user(), $user, false),
            'تم إلغاء حظر المستخدم بنجاح.',
        );
    }

    private function stateResponse($user, string $message)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'user' => AdminUserResource::make($user),
        ]);
    }
}
