<?php

namespace App\Livewire\Settings;

use App\Livewire\Actions\Logout;
use App\Services\UserSecurityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeleteUserForm extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout, UserSecurityService $security): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        try {
            $security->ensureCanDelete(Auth::user(), Auth::user());
        } catch (AuthorizationException $exception) {
            $this->addError('password', $exception->getMessage());

            return;
        }

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}
