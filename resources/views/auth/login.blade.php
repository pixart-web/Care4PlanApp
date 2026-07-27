<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar - Care4All</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100">
<main class="flex min-h-screen items-center justify-center px-4">
    <section class="w-full max-w-md rounded-lg border border-stone-200 bg-white p-8 shadow-sm">
        <div class="mb-8">
            <div class="text-3xl font-bold text-care-green">Care4All</div>
            <p class="mt-1 text-sm text-stone-500">Gestão de escalas de apoio domiciliário</p>
        </div>
        @if($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf
            <div>
                <label class="label" for="username">Nome de utilizador</label>
                <input class="input" id="username" name="username" value="{{ old('username') }}" required autofocus>
            </div>
            <div>
                <label class="label" for="password">Palavra-passe</label>
                <input class="input" id="password" name="password" type="password" required>
            </div>
            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input class="rounded border-stone-300 text-care-green focus:ring-care-green" type="checkbox" name="remember">
                Manter sessao iniciada
            </label>
            <button class="btn-primary w-full" type="submit">Entrar</button>
        </form>
    </section>
</main>
</body>
</html>

