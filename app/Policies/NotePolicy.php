<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

/**
 * Found automatically by name: App\Models\Note -> App\Policies\NotePolicy.
 *
 * Adding a note is checked on the PARENT instead (PropertyPolicy::update, or
 * the manage-site gate for agencies), because "may I write on this property?"
 * is a question about the property, not about a note that does not exist yet.
 */
class NotePolicy
{
    /**
     * Only the author may delete their note, plus admins (moderation).
     * An agent cannot delete a colleague's note on the same property.
     */
    public function delete(User $user, Note $note): bool
    {
        return $user->is_admin || $note->user_id === $user->id;
    }
}
