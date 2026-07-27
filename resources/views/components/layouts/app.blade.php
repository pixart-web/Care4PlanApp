@props(['title' => 'Care4All'])
<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Care4All' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
</head>
<body>
<div class="min-h-screen lg:flex">
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-stone-200 bg-white lg:block">
        <div class="flex h-full flex-col">
            <div class="border-b border-stone-200 px-6 py-5">
                <div class="text-2xl font-bold text-care-green">Care4All</div>
                <div class="text-sm text-stone-500">Ciclone D'abraços</div>
            </div>
            <nav class="flex-1 space-y-1 px-4 py-5">
                @php($isCarer = auth()->user()?->isCarer())
                @if($isCarer)
                    <x-nav-link route="carer.scales.index" label="Escalas" />
                    <x-nav-link route="carer.pocket" label="Pocket" />
                    <x-nav-link route="carer.profile" label="Perfil" />
                    <x-nav-link route="carer.reports" label="Relatórios" />
                    <x-nav-link route="carer.messages" label="Mensagens" />
                @else
                    <x-nav-link route="admin.dashboard" label="Dashboard" />
                    <x-nav-link route="admin.scales.index" label="Escalas" />
                    <x-nav-link route="admin.carers.index" label="Cuidadoras" />
                    <x-nav-link route="admin.patients.index" label="Utentes" />
                    @if(auth()->user()?->isAdmin())
                        <x-nav-link route="admin.users.index" label="Utilizadores" />
                    @endif
                    <x-nav-link route="admin.reports" label="Relatórios" />
                    <x-nav-link route="admin.messages" label="Mensagens" />
                    <x-nav-link route="admin.settings" label="Configurações" />
                    <x-nav-link route="admin.dashboard" label="Perfil" />
                @endif
            </nav>
            <div class="border-t border-stone-200 p-4">
                <div class="mb-3 text-sm">
                    <div class="font-semibold">{{ auth()->user()->name }}</div>
                    <div class="text-stone-500">{{ auth()->user()->role }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-secondary w-full" type="submit">Sair</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-72 w-full">
        <header class="sticky top-0 z-30 border-b border-stone-200 bg-white/95 px-4 py-4 backdrop-blur lg:hidden">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xl font-bold text-care-green">Care4All</div>
                    <div class="text-xs text-stone-500">{{ auth()->user()->name }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-secondary" type="submit">Sair</button>
                </form>
            </div>
        </header>
        <main class="px-4 py-6 sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="mb-5 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>

