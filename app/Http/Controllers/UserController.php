<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Accountverwaltung für das Familienoberhaupt.
 */
class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        $roleOrder = array_flip(array_column(Role::cases(), 'value'));

        return view('users.index', [
            'users' => User::query()
                ->withCount('favoriteRecipes')
                ->orderBy('name')
                ->get()
                ->sortBy(fn (User $user) => $roleOrder[$user->role->value], SORT_NUMERIC, false)
                ->values(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', [
            'user' => new User,
            'roles' => Role::cases(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only('name', 'username', 'password'));
        $user->role = $request->enum('role', Role::class);
        $user->save();

        return to_route('users.index')->with('status', "{$user->name} wurde angelegt.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'roles' => Role::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only('name', 'username'));
        $user->role = $request->enum('role', Role::class);

        if (filled($request->validated('password'))) {
            $user->password = $request->validated('password');
        }

        $user->save();

        return to_route('users.index')->with('status', "Änderungen an {$user->name} wurden gespeichert.");
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return to_route('users.index')->with('status', "{$user->name} wurde gelöscht.");
    }
}
