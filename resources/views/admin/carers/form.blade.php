<x-layouts.app :title="($carer->exists ? 'Editar cuidadora' : 'Nova cuidadora') . ' - Care4All'">
    <h1 class="mb-6 text-2xl font-bold">{{ $carer->exists ? 'Editar cuidadora' : 'Nova cuidadora' }}</h1>
    <form method="POST" action="{{ $carer->exists ? route('admin.carers.update', $carer) : route('admin.carers.store') }}" class="card max-w-3xl space-y-5 p-6" x-data="{ color: '{{ old('color_hex', $carer->color_hex) }}' }">
        @csrf
        @if($carer->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Nome</label><input class="input" name="name" value="{{ old('name', $carer->name) }}" required></div>
            <div><label class="label">Telefone</label><input class="input" name="phone" value="{{ old('phone', $carer->phone) }}" required></div>
            <div><label class="label">Email</label><input class="input" name="email" type="email" value="{{ old('email', $carer->email) }}"></div>
            <div><label class="label">Idade</label><input class="input" name="age" type="number" min="16" max="100" value="{{ old('age', $carer->age) }}"></div>
        </div>
        @unless($carer->exists)
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label">Nome de utilizador</label><input class="input" name="username" value="{{ old('username') }}" required></div>
                <div><label class="label">Palavra-passe</label><input class="input" name="password" type="password" required></div>
            </div>
        @endunless
        <input type="hidden" name="color_hex" x-model="color">
        <div class="flex items-center gap-3">
            <span class="h-10 w-16 rounded border border-stone-200" :style="'background:' + color"></span>
            <button class="btn-secondary" type="button" @click="fetch('{{ route('admin.carers.generate-color') }}').then(r => r.json()).then(d => color = d.color_hex)">Gerar cor</button>
            <span class="text-sm text-stone-500" x-text="color || 'Gerada automaticamente ao guardar'"></span>
        </div>
        <div class="flex gap-3">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('admin.carers.index') }}">Cancelar</a>
        </div>
    </form>
</x-layouts.app>

