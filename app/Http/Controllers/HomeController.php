<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('home', [
            'pendingUrl' => session('pending_url'),
            'otpRequired' => session('otp_required', false),
        ]);
    }
}
