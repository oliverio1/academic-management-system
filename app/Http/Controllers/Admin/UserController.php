<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\Group;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index() {
        $users = User::with('roles')->get();
        return view('users.index', compact('users'));
    }

    public function create() {
        $groups = Group::get();
        $roles = Role::pluck('name');
        return view('users.create', compact('roles','groups'));
    }

    public function store(Request $request) {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'role' => 'required|exists:roles,name',
        ]);
        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
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
                    'phone' => $request->student['phone'],
                    'address' => $request->student['address'],
                    'is_active' => true,
                ]);
                break;
            case 'coordination':
                Coordinator::create([
                    'user_id' => $user->id,
                    'area' => $request->coordinator['area'],
                    'phone' => $request->coordinator['phone'],
                    'is_active' => true,
                ]);
                break;
        }
    }

    public function edit(User $user) {
        $groups = Group::get();
        $roles = Role::pluck('name');
        return view('users.edit', compact('roles','groups','user'));
    }


    public function update(Request $request, User $user) {
        $request->validate([
            'name'  => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role'  => 'required|exists:roles,name',
        ]);
        DB::transaction(function () use ($request, $user) {
            $user->update([
                'name'  => $request->name,
                'email' => $request->email,
            ]);
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
            case 'coordination':
                Coordinator::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'area'      => $request->coordinator['area'] ?? null,
                        'phone'     => $request->coordinator['phone'] ?? null,
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
        if ($user->hasRole('coordination') && $user->coordinator) {
            $user->coordinator->update(['is_active' => $status]);
        }
    }
}
