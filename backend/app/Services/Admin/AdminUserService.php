<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->baseQuery($filters['status'] ?? null)
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->where('role', $role))
            ->when($filters['onboarding_status'] ?? null, fn (Builder $query, string $status) => $query->where('onboarding_status', $status))
            ->when($filters['registered_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['registered_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->withCount($this->countRelations())
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function find(int $id): User
    {
        $user = User::withTrashed()
            ->with(['goals:id,title,icon', 'supportMethods:id,slug,title'])
            ->withCount($this->countRelations())
            ->findOrFail($id);

        $user->setAttribute('last_activity_at', collect([
            $user->moodLogs()->latest()->first()?->created_at,
            $user->activityCompletions()->latest()->first()?->created_at,
            $user->journalEntries()->latest()->first()?->created_at,
        ])->filter()->max()?->toISOString());

        return $user;
    }

    public function setBlocked(User $admin, int $id, bool $blocked): User
    {
        $user = User::query()->findOrFail($id);

        if ($user->id === $admin->id || $user->role === 'admin') {
            throw ValidationException::withMessages([
                'user' => ['لا يمكن تعديل حالة حساب إداري من هذا المسار.'],
            ]);
        }

        $user->forceFill(['is_blocked' => $blocked])->save();

        if ($blocked) {
            $user->tokens()->delete();
        }

        return $this->find($user->id);
    }

    private function baseQuery(?string $status): Builder
    {
        return match ($status) {
            'deleted' => User::onlyTrashed(),
            'blocked' => User::query()->where('is_blocked', true),
            'active' => User::query()->where('is_blocked', false),
            default => User::withTrashed(),
        };
    }

    private function countRelations(): array
    {
        return [
            'moodLogs',
            'activityCompletions',
            'journalEntries',
            'articleComments',
            'articleCommentReports',
        ];
    }
}
