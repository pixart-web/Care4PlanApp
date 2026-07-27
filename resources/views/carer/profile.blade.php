<x-layouts.app title="Perfil - Care4All">
    <h1 class="mb-6 text-2xl font-bold">Perfil</h1>
    <form method="POST" action="{{ route('carer.profile.update') }}" class="card max-w-2xl space-y-5 p-6">
        @csrf @method('PUT')
        <div><label class="label">Nome</label><input class="input bg-stone-100" value="{{ $carer->name }}" disabled></div>
        <div><label class="label">Idade</label><input class="input" name="age" type="number" min="16" max="100" value="{{ old('age', $carer->age) }}"></div>
        <div><label class="label">Telefone</label><input class="input" name="phone" value="{{ old('phone', $carer->phone) }}" required></div>
        <div><label class="label">Email</label><input class="input" name="email" type="email" value="{{ old('email', $carer->email) }}"></div>
        <button class="btn-primary">Guardar</button>
    </form>
</x-layouts.app>
