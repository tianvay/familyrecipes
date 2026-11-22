<?php

namespace App\Http\Controllers;

use App\Enums\Theme;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * „Mein Konto“: Jedes Familienmitglied kann das eigene Passwort und das Farbdesign ändern.
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', ['user' => $request->user()]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return back()->with('status', 'Passwort wurde geändert.');
    }

    /**
     * Farbdesign wählen. Zusätzlich als Cookie gemerkt, damit auch die
     * Anmeldeseite auf diesem Gerät im gewählten Design erscheint.
     */
    public function updateTheme(Request $request): RedirectResponse
    {
        $theme = Theme::from($request->validate([
            'theme' => ['required', Rule::enum(Theme::class)],
        ])['theme']);

        $request->user()->update(['theme' => $theme]);

        return back()
            ->with('status', "Design „{$theme->label()}“ ist aktiv.")
            ->withCookie(cookie()->forever(Theme::COOKIE, $theme->value));
    }
}
