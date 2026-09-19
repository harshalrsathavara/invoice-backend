<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Rules;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owner accounts.
 *
 * A business belongs to an owner and sync is per owner, so an account is what
 * a handset signs in as. Until now the only way to make one was
 * `php artisan invoice:admin`, which marks whoever it touches an administrator
 * — so a plain owner, the ordinary case, could not be created at all.
 *
 * Panel access and ownership are separate things here. An owner with the box
 * unticked can sign in from the app and sync their books, and cannot open this
 * panel; an administrator can do both and sees every business.
 */
class UserController extends Controller
{
    public function index()
    {
        $users = User::withCount(['businesses', 'devices'])
            ->orderByDesc('is_admin')
            ->orderBy('name')
            ->get();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(Rules::user());

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'is_admin' => $request->boolean('is_admin'),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', $user->is_admin
                ? "{$user->name} added, with access to this panel."
                : "{$user->name} added. They sign in from the app with {$user->email}.");
    }

    public function edit(User $user)
    {
        $user->loadCount(['businesses', 'devices']);

        return view('admin.users.form', compact('user'));
    }

    /**
     * A blank password box leaves the password alone — an admin editing a phone
     * number must not silently reset how someone signs in.
     */
    public function update(Request $request, User $user)
    {
        $data = $request->validate(Rules::user($user));
        $isAdmin = $request->boolean('is_admin');

        // Removing your own panel access locks you out of the page you are on,
        // and if you are the only administrator it locks everyone out for good.
        if ($user->is($request->user()) && ! $isAdmin) {
            throw ValidationException::withMessages([
                'is_admin' => 'You cannot remove your own panel access. Ask another administrator to do it.',
            ]);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_admin' => $isAdmin,
        ]);

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();

        // Their tokens name a device, not a password, so a reset has to revoke
        // them by hand or an old handset keeps syncing on the previous one.
        if (filled($data['password'] ?? null)) {
            $user->tokens()->delete();
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', filled($data['password'] ?? null)
                ? "Saved {$user->name}. Their password changed, so their handsets have to sign in again."
                : "Saved {$user->name}.");
    }

    /**
     * Accounts are deleted for real — there is no deleted_at on users.
     *
     * That matters more than it looks: businesses.user_id is a foreign key with
     * cascade on delete, so removing an owner who still holds books would have
     * the database erase every business, customer, item, bill and receipt under
     * them, outright, with none of the soft-delete recovery the rest of the app
     * relies on. So an owner with businesses is refused until they are empty.
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'You cannot remove the account you are signed in as.',
            ]);
        }

        $businessCount = $user->businesses()->withTrashed()->count();

        if ($businessCount > 0) {
            throw ValidationException::withMessages([
                'user' => "{$user->name} still holds {$businessCount} ".Str::plural('business', $businessCount).
                    '. Deleting the account would erase those books outright, so move or remove them first.',
            ]);
        }

        $name = $user->name;

        // Devices cascade with the row; tokens are polymorphic and do not.
        $user->tokens()->delete();
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "{$name}'s account was removed.");
    }
}
