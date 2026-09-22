<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
      
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
             
                    <p class="mt-2 text-sm text-gray-600">
                
                        <span class="font-semibold">{{ auth()->user()->role->name }}</span>
                    </p>
                </div>
            </div>

            @if (auth()->user()->hasAdminAccess())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-4">
                    <div class="p-6 text-gray-900">
                        <a href="{{ route('admin.medicos.create') }}" class="text-indigo-600 underline">
                        
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>