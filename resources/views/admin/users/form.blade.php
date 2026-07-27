<x-layouts.app :title="($user->exists ? 'Editar utilizador' : 'Novo utilizador') . ' - Care4All'">
    <h1 class="mb-6 text-2xl font-bold">{{ $user->exists ? 'Editar utilizador' : 'Novo utilizador' }}</h1>
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card max-w-2xl space-y-5 p-6">
        @csrf
        @if($user->exists) @method('PUT') @endif
        <div><label class="label">Nome</label><input class="input" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div><label class="label">Nome de utilizador</label><input class="input" name="username" value="{{ old('username', $user->username) }}" required></div>
        <div>
            <label class="label">Função</label>
            <select class="input" name="role" required>
                @foreach(['ADMIN', 'EDITOR', 'CARER'] as $role)
                    <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ $role }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Cuidadora associada</label>
            <select class="input" name="carer_id">
                <option value="">Sem associação</option>
                @foreach($carers as $carer)<option value="{{ $carer->id }}" @selected(old('carer_id', $user->carer_id) == $carer->id)>{{ $carer->name }}</option>@endforeach
            </select>
        </div>
        <div><label class="label">Palavra-passe</label><input class="input" name="password" type="password" @required(! $user->exists)><p class="mt-1 text-xs text-stone-500">Deixar em branco para manter a palavra-passe atual.</p></div>
        <div class="flex gap-3"><button class="btn-primary">Guardar</button><a class="btn-secondary" href="{{ route('admin.users.index') }}">Cancelar</a></div>
    </form>
</x-layouts.app>

