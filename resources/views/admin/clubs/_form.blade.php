{{-- Basic Information --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
            Nome Club *
        </label>
        <input type="text"
               name="name"
               id="name"
               value="{{ old('name', $club->name ?? '') }}"
               required
               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
            Codice Club *
        </label>
        <input type="text"
               name="code"
               id="code"
               value="{{ old('code', $club->code ?? '') }}"
               required
               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('code')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div>
        <label for="zone_id" class="block text-sm font-medium text-gray-700 mb-2">
            Zona *
        </label>
        <select name="zone_id" id="zone_id" required
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Seleziona una zona</option>
            @foreach($zones as $zone)
                <option value="{{ $zone->id }}" {{ old('zone_id', $club->zone_id ?? '') == $zone->id ? 'selected' : '' }}>
                    {{ $zone->name }}
                </option>
            @endforeach
        </select>
        @error('zone_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="city" class="block text-sm font-medium text-gray-700 mb-2">
            Città *
        </label>
        <input type="text"
               name="city"
               id="city"
               value="{{ old('city', $club->city ?? '') }}"
               required
               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('city')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div>
        <label for="province" class="block text-sm font-medium text-gray-700 mb-2">
            Provincia
        </label>
        <input type="text"
               name="province"
               id="province"
               value="{{ old('province', $club->province ?? '') }}"
               maxlength="2"
               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('province')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
            Email
        </label>
        <input type="email"
               name="email"
               id="email"
               value="{{ old('email', $club->email ?? '') }}"
               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('email')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div>
        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
            Telefono
        </label>
        <input type="text"
               name="phone"
               id="phone"
               value="{{ old('phone', $club->phone ?? '') }}"
               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('phone')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-2">
            Persona di Contatto
        </label>
        <input type="text"
               name="contact_person"
               id="contact_person"
               value="{{ old('contact_person', $club->contact_person ?? '') }}"
               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('contact_person')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div>
    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
        Indirizzo
    </label>
    <input type="text"
           name="address"
           id="address"
           value="{{ old('address', $club->address ?? '') }}"
           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('address')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
        Note
    </label>
    <textarea name="notes"
              id="notes"
              rows="3"
              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $club->notes ?? '') }}</textarea>
    @error('notes')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

<div class="flex items-center">
    <input type="checkbox"
           name="is_active"
           id="is_active"
           value="1"
           {{ old('is_active', $club->is_active ?? true) ? 'checked' : '' }}
           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
    <label for="is_active" class="ml-2 block text-sm text-gray-900">
        Club attivo
    </label>
</div>
