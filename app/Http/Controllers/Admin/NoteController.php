<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreNoteRequest;
use App\Models\Agency;
use App\Models\Note;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Private admin notes. One controller for both parents: the polymorphic
 * relation means saving a note works the same way for a property or an agency.
 */
class NoteController extends Controller
{
    /**
     * Agents may write notes on their own agency's properties: the same rule
     * as editing the property (PropertyPolicy::update; admins always pass).
     */
    public function storeForProperty(StoreNoteRequest $request, Property $property): RedirectResponse
    {
        Gate::authorize('update', $property);

        return $this->store($request, $property);
    }

    /**
     * Agencies are admin-only: the route sits inside the can:manage-site group,
     * so there is nothing more to check here.
     */
    public function storeForAgency(StoreNoteRequest $request, Agency $agency): RedirectResponse
    {
        return $this->store($request, $agency);
    }

    public function destroy(Note $note): RedirectResponse
    {
        // NotePolicy::delete(): the author or an admin, else 403.
        Gate::authorize('delete', $note);

        $note->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Note deleted.']);

        return back();
    }

    /**
     * Shared by both store actions. It only needs "a model that has notes()".
     *
     * $notable->notes()->create() fills notable_type and notable_id by itself:
     * notable_type = 'property' or 'agency' (the morph map alias), notable_id = its id.
     */
    private function store(StoreNoteRequest $request, Property|Agency $notable): RedirectResponse
    {
        /** @var array{body: string} $data */
        $data = $request->validated();

        $notable->notes()->create([
            'body' => $data['body'],
            // From the session, never from the form: nobody can post as someone else.
            'user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Note added.']);

        // back(): the note form lives on the property page and the agency page.
        return back();
    }
}
