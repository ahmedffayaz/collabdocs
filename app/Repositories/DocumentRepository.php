<?php

namespace App\Repositories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DocumentRepository
{
    public function visibleTo(User $user): Collection
    {
        return Document::query()
            ->with(['owner:id,name,email', 'collaborators:id,name,email'])
            ->where('owner_id', $user->id)
            ->orWhereHas('collaborators', fn ($query) => $query->where('users.id', $user->id))
            ->latest('updated_at')
            ->get();
    }

    public function find(int|string $id): ?Document
    {
        return Document::with(['owner:id,name,email', 'collaborators:id,name,email'])->find($id);
    }

    public function create(User $owner, array $attributes): Document
    {
        return $owner->ownedDocuments()->create($attributes);
    }

    public function update(Document $document, array $attributes): Document
    {
        $document->update($attributes);
        return $document->fresh(['owner:id,name,email', 'collaborators:id,name,email']);
    }

    public function delete(Document $document): void
    {
        $document->delete();
    }

    public function grantAccess(Document $document, User $user, string $permission): Document
    {
        $document->collaborators()->syncWithoutDetaching([$user->id => ['permission' => $permission]]);
        return $document->fresh(['owner:id,name,email', 'collaborators:id,name,email']);
    }

    public function permissionFor(Document $document, User $user): ?string
    {
        if ($document->owner_id === $user->id) {
            return 'owner';
        }

        return $document->collaborators
            ->firstWhere('id', $user->id)?->pivot->permission;
    }
}
