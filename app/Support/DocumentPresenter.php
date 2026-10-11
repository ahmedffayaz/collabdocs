<?php

namespace App\Support;

use App\Models\Document;
use App\Models\User;

class DocumentPresenter
{
    public static function make(Document $document, User $user, bool $includeCollaborators = false): array
    {
        $permission = $document->owner_id === $user->id
            ? 'owner'
            : $document->collaborators->firstWhere('id', $user->id)?->pivot->permission;

        $data = [
            'id' => $document->id,
            'title' => $document->title,
            'content' => $document->content,
            'updated_at' => $document->updated_at,
            'owner' => $document->owner?->only('id', 'name', 'email'),
            'access' => $permission,
            'can_edit' => in_array($permission, ['owner', 'editor'], true),
        ];

        if ($includeCollaborators) {
            $data['collaborators'] = $document->collaborators->map(fn (User $collaborator) => [
                'id' => $collaborator->id,
                'name' => $collaborator->name,
                'email' => $collaborator->email,
                'permission' => $collaborator->pivot->permission,
            ])->values();
        }

        return $data;
    }
}
