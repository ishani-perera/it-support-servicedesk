<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class ProfileController extends Controller
{
    /**
     * Update the signed-in user's own name / phone.
     * No profile page exists yet (UI phase); this is the secure write path.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse|Response
    {
        // fill() honours $fillable AND only validated keys are passed in.
        $request->user()->fill($request->validated())->save();

        return $request->wantsJson()
            ? response()->noContent()
            : back()->with('status', 'profile-updated');
    }
}
