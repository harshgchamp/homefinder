<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

/**
 * Who may do what with a property.
 *
 * A Policy groups the authorization rules for ONE model. Laravel finds it by
 * name: App\Models\Property -> App\Policies\PropertyPolicy. No registration.
 *
 * Each method answers one question with true/false and is called through
 * Gate::authorize('update', $property), $user->can('update', $property),
 * or the "can:update,property" middleware. false -> 403 Forbidden.
 *
 * Note: a policy decides about ONE model. It cannot filter a list of models;
 * that is the job of Property::visibleTo($user) on the query.
 */
class PropertyPolicy
{
    /**
     * Runs BEFORE every other method.
     *   true  -> allowed, the ability method is skipped (admins can do anything)
     *   null  -> no decision here, carry on to the ability method
     * (Returning false here would deny everything, even abilities that exist.)
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    /**
     * Open the property list. Agents see only their agency's rows (visibleTo scope).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAgent();
    }

    public function view(User $user, Property $property): bool
    {
        return $this->ownsListing($user, $property);
    }

    /**
     * No $property argument: there is no model yet. The agency an agent may
     * choose is limited by StorePropertyRequest.
     */
    public function create(User $user): bool
    {
        return $user->isAgent();
    }

    public function update(User $user, Property $property): bool
    {
        return $this->ownsListing($user, $property);
    }

    /**
     * Move to the trash.
     */
    public function delete(User $user, Property $property): bool
    {
        return $this->ownsListing($user, $property);
    }

    public function restore(User $user, Property $property): bool
    {
        return $this->ownsListing($user, $property);
    }

    /**
     * Delete forever: admins only (they pass in before()), never agents.
     */
    public function forceDelete(User $user, Property $property): bool
    {
        return false;
    }

    private function ownsListing(User $user, Property $property): bool
    {
        return $user->isAgent() && $user->agency_id === $property->agency_id;
    }
}
