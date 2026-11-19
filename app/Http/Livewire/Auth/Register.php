<?php

namespace App\Http\Livewire\Auth;

use Livewire\Component;

class Register extends Component
{
    /** @var string */
    public $name = '';

    /** @var string */
    public $email = '';

    /** @var string */
    public $password = '';

    /** @var string */
    public $passwordConfirmation = '';

    public function register()
    {
        abort(404);

    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
