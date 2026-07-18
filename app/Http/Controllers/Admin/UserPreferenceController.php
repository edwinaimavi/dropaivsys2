<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserPreferenceController extends Controller
{
    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'theme_mode' => ['required', Rule::in(['light', 'dark', 'system'])],
        ]);

        $preference = $request->user()->preference()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['theme_mode' => $validated['theme_mode']]
        );

        return response()->json([
            'success' => true,
            'theme_mode' => $preference->theme_mode,
            'message' => 'Tema actualizado correctamente.',
        ]);
    }
}
