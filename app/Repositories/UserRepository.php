<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    public function find(int|string $id): User
    {
        return User::findOrFail($id);
    }

    public function findByEmail(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function selectableAccounts(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'email']);
    }
}
