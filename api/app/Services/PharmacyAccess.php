<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PharmacyAccess
{
    public function locations(User $actor)
    {
        $q = DB::table('pharmacy_locations')->where('organization_id', $actor->organization_id);
        if ($actor->role !== 'admin') {
            $q->where('active', true)->whereIn('id', DB::table('pharmacy_staff_assignments')
                ->select('location_id')->where('user_id', $actor->id)->where('active', true)
                ->whereDate('valid_until', '>=', now()->toDateString()));
        }
        return $q;
    }

    public function scope($query, User $actor, string $column = 'location_id')
    {
        return $query->whereIn($column, $this->locations($actor)->select('id'));
    }

    public function requireLocation(User $actor, int $id): void
    {
        abort_unless($this->locations($actor)->where('id', $id)->where('active', true)->exists(), 404, 'Pharmacy location is unavailable.');
    }
}
