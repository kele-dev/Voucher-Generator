<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Outlet;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', User::class);

        return view('users.index', [
            'users' => User::query()->with('outlets')->orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        $this->authorize('create', User::class);

        return view('users.form', [
            'user' => new User,
            'roles' => Role::cases(),
            'outlets' => Outlet::query()->with('brand')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'outlet_ids' => [
                Rule::requiredIf(fn (): bool => $request->input('role') === Role::OutletStaff->value),
                'array',
            ],
            'outlet_ids.*' => ['integer', 'exists:outlets,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active'),
            'password' => $data['password'],
        ]);

        if ($user->role === Role::OutletStaff) {
            $user->outlets()->sync($data['outlet_ids'] ?? []);
        }

        Audit::record('user.created', $user, ['changes' => ['email' => $user->email, 'role' => $user->role->value]]);

        return redirect('/users')->with('status', 'User '.$user->name.' disimpan.');
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return view('users.form', [
            'user' => $user->load('outlets'),
            'roles' => Role::cases(),
            'outlets' => Outlet::query()->with('brand')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['integer', 'exists:outlets,id'],
        ]);

        $isSelf = $request->user()->is($user);
        $errors = [];

        if ($isSelf && $data['role'] !== Role::SuperAdmin->value) {
            $errors['role'] = 'Super Admin tidak bisa menurunkan role sendiri.';
        }

        if ($isSelf && ! $request->boolean('is_active')) {
            $errors['is_active'] = 'Super Admin tidak bisa menonaktifkan akun sendiri.';
        }

        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();

        if ($user->role === Role::OutletStaff) {
            $user->outlets()->sync($data['outlet_ids'] ?? []);
        } else {
            $user->outlets()->sync([]);
        }

        Audit::record('user.updated', $user, ['changes' => [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ]]);

        return redirect('/users')->with('status', 'User '.$user->name.' diperbarui.');
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        Audit::record('user.deleted', $user, ['changes' => ['email' => $user->email]]);

        $user->delete();

        return redirect('/users')->with('status', 'User dihapus.');
    }
}
