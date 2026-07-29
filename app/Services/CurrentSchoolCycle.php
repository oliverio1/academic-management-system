<?php

namespace App\Services;

use App\Models\SchoolCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CurrentSchoolCycle
{
    public const SESSION_KEY = 'active_school_cycle_id';

    public function get(?User $user = null, ?int $campusId = null): ?SchoolCycle
    {
        $campusId = $this->campusId($user, $campusId);
        $selectedId = (int) session(self::SESSION_KEY, 0);

        if ($selectedId > 0) {
            $selected = $this->queryFor($user, $campusId)
                ->whereKey($selectedId)
                ->first();

            if ($selected) {
                return $selected;
            }
        }

        $fallback = $this->queryFor($user, $campusId)
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first()
            ?: $this->queryFor($user, $campusId)
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();

        if ($fallback) {
            session([self::SESSION_KEY => (int) $fallback->id]);
        } else {
            session()->forget(self::SESSION_KEY);
        }

        return $fallback;
    }

    public function id(?User $user = null, ?int $campusId = null): ?int
    {
        return $this->get($user, $campusId)?->id;
    }

    public function available(?User $user = null, ?int $campusId = null): Collection
    {
        return $this->queryFor($user, $this->campusId($user, $campusId))
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    public function set(int $cycleId, ?User $user = null, ?int $campusId = null): ?SchoolCycle
    {
        $cycle = $this->queryFor($user, $this->campusId($user, $campusId))
            ->whereKey($cycleId)
            ->first();

        if (! $cycle) {
            return null;
        }

        session([self::SESSION_KEY => (int) $cycle->id]);

        return $cycle;
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function queryFor(?User $user = null, ?int $campusId = null): Builder
    {
        $query = SchoolCycle::query()
            ->with(['campus', 'campuses', 'modality', 'modalities']);

        if ($campusId && $campusId > 0) {
            $query->where(function ($nested) use ($campusId) {
                $nested->where('campus_id', $campusId)
                    ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $campusId));
            });
        }

        if ($user && ! $user->hasRole('admin')) {
            $campusIds = $user->campuses()->pluck('campuses.id')->map(fn ($id) => (int) $id)->all();
            if ($campusIds) {
                $query->where(function ($nested) use ($campusIds) {
                    $nested->whereIn('campus_id', $campusIds)
                        ->orWhereHas('campuses', fn ($campuses) => $campuses->whereIn('campuses.id', $campusIds));
                });
            }
        }

        if ($user && $user->hasRole('teacher') && ! $user->hasAnyRole(['admin', 'coordinator'])) {
            $teacherId = (int) optional($user->teacher)->id;
            $query->whereHas('cycleGroups.assignments', function ($assignment) use ($teacherId) {
                $assignment->where('teacher_id', $teacherId)
                    ->where('is_active', true);
            });
        }

        return $query;
    }

    private function campusId(?User $user, ?int $campusId): int
    {
        return (int) ($campusId ?: session('active_campus_id', (int) ($user?->default_campus_id ?? 0)));
    }
}
