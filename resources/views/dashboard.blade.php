<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    {{ __("You're logged in!") }}
                    <p class="mt-2 text-sm text-gray-600">
                        {{ __('Tu rol es: ') }}
                        <span class="font-semibold">{{ auth()->user()->getRoleNames()->join(', ') }}</span>
                    </p>
                </div>
            </div>

            @role('administrador')
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-4">
                    <div class="p-6 text-gray-900">
                        <a href="{{ route('admin.medicos.create') }}" class="text-indigo-600 underline">
                            {{ __('Registrar nuevo Medico') }}
                        </a>
                    </div>
                </div>
            @endrole
        </div>
    </div>
</x-app-layout>
