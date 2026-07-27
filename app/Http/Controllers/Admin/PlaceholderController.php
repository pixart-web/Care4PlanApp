<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class PlaceholderController extends Controller
{
    public function __invoke(string $page)
    {
        $titles = [
            'reports' => 'Relatórios',
            'messages' => 'Mensagens',
            'settings' => 'Configurações',
        ];

        return view('admin.placeholder', ['title' => $titles[$page] ?? 'Página']);
    }
}

