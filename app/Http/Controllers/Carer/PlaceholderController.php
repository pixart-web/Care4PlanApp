<?php

namespace App\Http\Controllers\Carer;

use App\Http\Controllers\Controller;

class PlaceholderController extends Controller
{
    public function __invoke(string $page)
    {
        $titles = [
            'reports' => 'Relatórios',
            'messages' => 'Mensagens',
        ];

        return view('carer.placeholder', ['title' => $titles[$page] ?? 'Página']);
    }
}

