<?php

namespace App\Http\Resources;

use App\Models\Note;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One note for the NotesPanel on the property and agency pages.
 *
 * @mixin Note
 */
class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            // whenLoaded: the controller eager loads author:id,name, so no N+1.
            // If the author was deleted (user_id set to NULL), the relation is loaded
            // but null: whenLoaded() then returns null WITHOUT calling the closure,
            // so the fallback goes outside it.
            'author' => $this->whenLoaded('author', fn (User $author): string => $author->name) ?? 'Deleted user',
            'created_ago' => $this->created_at?->diffForHumans(),
            'created_at' => $this->created_at?->format('j M Y, H:i'),
            // NotePolicy::delete(), so the page only shows the button when it will work.
            'can_delete' => (bool) $request->user()?->can('delete', $this->resource),
        ];
    }
}
