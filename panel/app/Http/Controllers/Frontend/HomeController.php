<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class HomeController extends Controller
{
    public function index()
    {
        return Inertia::render('frontend/index', []);
    }

    public function suspended(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Suspended', [
            'status_reason' => $user->status_reason,
            'status_updated_at' => $user->status_updated_at,
        ]);
    }
}
