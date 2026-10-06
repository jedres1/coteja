<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    public function update(User $user, JournalEntry $entry): bool
    {
        return $entry->status === 'borrador';
    }

    public function delete(User $user, JournalEntry $entry): bool
    {
        return $entry->status === 'borrador';
    }

    public function approve(User $user, JournalEntry $entry): bool
    {
        return $user->canAccessAdmin() && $entry->status === 'borrador';
    }

    public function annul(User $user, JournalEntry $entry): bool
    {
        return $user->canAccessAdmin() && $entry->status === 'aprobado';
    }
}
