<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\Group;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Campus;

class UserController extends Controller
{
    public function index() {
        $users = User::with('roles')->get();
        return view('users.index', compact('users'));
    }

    public function create() {
        $groups = Group::get();
        $campuses = Campus::query()->where('is_active', true)->orderBy('name')->get();
        $roles = Role::query()->orderBy('name')->pluck('name');
        return view('users.create', compact('roles','groups','campuses'));
    }

    public function store(UserRequest $request) {
        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'default_campus_id' => $this->resolveDefaultCampusId($request),
            ]);
            $user->campuses()->sync($this->campusIds($request));
            $user->assignRole($request->role);
            $this->storeRoleData($user, $request);
        });
        return redirect()->route('users.index')->with('info','Usuario creado correctamente');
    }

    protected function storeRoleData(User $user, Request $request) {
        switch ($request->role) {
            case 'student':
                Student::create([
                    'user_id' => $user->id,
                    'group_id' => $request->student['group_id'],
                    'enrollment_number' => $request->student['enrollment_number'],
                    'phone' => $request->student['phone'],
                    'address' => $request->student['address'],
                    'is_active' => true,
                ]);
                break;
            case 'teacher':
                Teacher::create([
                    'user_id' => $user->id,
                    'phone' => $request->teacher['phone'] ?? null,
                    'address' => $request->teacher['address'] ?? null,
                    'is_active' => true,
                ]);
                break;
        }
    }

    public function edit(User $user) {
        $groups = Group::get();
        $campuses = Campus::query()->where('is_active', true)->orderBy('name')->get();
        $roles = Role::query()->orderBy('name')->pluck('name');
        return view('users.edit', compact('roles','groups','user','campuses'));
    }


    public function update(UserRequest $request, User $user) {
        DB::transaction(function () use ($request, $user) {
            $user->update([
                'name'  => $request->name,
                'email' => $request->email,
                'default_campus_id' => $this->resolveDefaultCampusId($request),
            ]);
            $user->campuses()->sync($this->campusIds($request));
            if ($request->filled('password')) {
                $user->update([
                    'password' => Hash::make($request->password),
                ]);
            }
            if (! $user->hasRole($request->role)) {
                $user->syncRoles([$request->role]);
            }
            $this->updateRoleData($user, $request);
        });
        return redirect()->route('users.index')->with('info', 'Usuario actualizado correctamente');
    }

    protected function updateRoleData(User $user, Request $request) {
        switch ($request->role) {
            case 'student':
                Student::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'group_id'          => $request->student['group_id'] ?? null,
                        'enrollment_number' => $request->student['enrollment_number'] ?? null,
                        'phone'             => $request->student['phone'] ?? null,
                        'address'           => $request->student['address'] ?? null,
                        'is_active'         => true,
                    ]
                );
                break;
            case 'teacher':
                Teacher::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'phone'     => $request->teacher['phone'] ?? null,
                        'address'   => $request->teacher['address'] ?? null,
                        'is_active' => true,
                    ]
                );
                break;
        }
    }

    public function deactivate(User $user) {
        $this->toggleUserStatus($user, false);
        return back()->with('info', 'Usuario dado de baja');
    }

    public function activate(User $user) {
        $this->toggleUserStatus($user, true);
        return back()->with('info', 'Usuario activado');
    }

    protected function toggleUserStatus(User $user, bool $status): void {
        if ($user->hasRole('student') && $user->student) {
            $user->student->update(['is_active' => $status]);
        }
        if ($user->hasRole('teacher') && $user->teacher) {
            $user->teacher->update(['is_active' => $status]);
        }
    }

    private function campusIds(Request $request): array
    {
        return collect($request->input('campus_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function resolveDefaultCampusId(Request $request): ?int
    {
        $campusIds = $this->campusIds($request);
        if (empty($campusIds)) {
            return null;
        }

        $defaultCampusId = (int) ($request->input('default_campus_id') ?? 0);
        if ($defaultCampusId > 0 && in_array($defaultCampusId, $campusIds, true)) {
            return $defaultCampusId;
        }

        return (int) $campusIds[0];
    }
}
