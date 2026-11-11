<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class JournalService
{
    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->journalEntries()
            ->latest()
            ->paginate($perPage);
    }

    public function create(User $user, string $body, ?string $prompt): JournalEntry
    {
        return $user->journalEntries()->create([
            'body' => $body,
            'prompt' => $prompt,
        ]);
    }

    public function ensureOwner(User $user, JournalEntry $journalEntry): void
    {
        abort_unless($journalEntry->user_id === $user->id, 404);
    }

    public function delete(User $user, JournalEntry $journalEntry): void
    {
        $this->ensureOwner($user, $journalEntry);
        $journalEntry->delete();
    }
}
