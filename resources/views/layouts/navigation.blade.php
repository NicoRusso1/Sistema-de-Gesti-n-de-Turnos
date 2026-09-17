<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                {{-- Enlaces de la barra superior --}}
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    <button 
                        type="button" 
                        x-data 
                        x-on:click="$dispatch('open-modal', 'edit-user-navigation-modal')" 
                        class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
                        {{ __('Editar Usuarios') }}
                    </button>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->first_name ?? Auth::user()->name }} {{ Auth::user()->last_name ?? '' }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Menú móvil --}}
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <button 
                type="button" 
                x-data
                x-on:click="$dispatch('open-modal', 'edit-user-navigation-modal'); open = false" 
                class="block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 transition duration-150 ease-in-out">
                {{ __('Editar Usuarios') }}
            </button>
        </div>

        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->first_name ?? Auth::user()->name }} {{ Auth::user()->last_name ?? '' }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>

    {{-- Pop-up Modal para Administrar y Editar Usuarios --}}
    <x-modal name="edit-user-navigation-modal" :show="false" focusable>
        <div x-data="{
            search: '',
            selectedUserId: '',
            selectedName: '',
            selectedEmail: '',
            selectedRole: '1',
            users: [
                { id: 1, name: 'Dr. Carlos Mendoza', email: 'mendoza@hospital.com', role_id: 2 },
                { id: 2, name: 'Dra. Ana Martinez', email: 'martinez@hospital.com', role_id: 3 },
                { id: 3, name: 'Admin HospitalTest', email: 'admin@hospital.com', role_id: 1 }
            ],
            get filteredUsers() {
                if (this.search === '') return this.users;
                return this.users.filter(user => 
                    user.name.toLowerCase().includes(this.search.toLowerCase()) || 
                    user.email.toLowerCase().includes(this.search.toLowerCase())
                );
            },
            selectUser(user) {
                this.selectedUserId = user.id;
                this.selectedName = user.name;
                this.selectedEmail = user.email;
                this.selectedRole = user.role_id;
            }
        }" class="p-6">
            
            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Administración y Edición de Médicos/Usuarios') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                {{ __('Busca al profesional o usuario para cargar y actualizar su perfil.') }}
            </p>

            {{-- Filtro de Búsqueda --}}
            <div class="mt-4">
                <x-input-label for="search" value="Buscar usuario o médico" />
                <x-text-input 
                    id="search" 
                    type="text" 
                    x-model="search"
                    class="mt-1 block w-full" 
                    placeholder="Escribí un nombre o email..." />
            </div>

            {{-- Selector de Resultados Filtrados --}}
            <div class="mt-3">
                <x-input-label value="Seleccionar de los resultados" />
                <div class="mt-1 max-h-32 overflow-y-auto border border-gray-200 rounded-md divide-y">
                    <template x-for="user in filteredUsers" :key="user.id">
                        <div 
                            @click="selectUser(user)" 
                            :class="selectedUserId === user.id ? 'bg-indigo-50 border-indigo-500' : 'hover:bg-gray-50'"
                            class="p-2 cursor-pointer flex justify-between items-center text-sm transition duration-150">
                            <div>
                                <span class="font-semibold text-gray-800" x-text="user.name"></span>
                                <span class="text-xs text-gray-500 ml-2" x-text="'(' + user.email + ')'"></span>
                            </div>
                            <span x-show="selectedUserId === user.id" class="text-indigo-600 font-bold text-xs">Seleccionado</span>
                        </div>
                    </template>
                    <div x-show="filteredUsers.length === 0" class="p-3 text-sm text-gray-500 text-center">
                        No se encontraron coincidencias.
                    </div>
                </div>
            </div>

            {{-- Formulario de Edición --}}
            <form :action="'/users/' + selectedUserId" method="POST" class="mt-5 pt-4 border-t border-gray-100">
                @csrf
                @method('PATCH')

                {{-- Campo Nombre --}}
                <div>
                    <x-input-label for="edit_name" value="Nombre Completo" />
                    <x-text-input 
                        id="edit_name" 
                        name="name" 
                        type="text" 
                        x-model="selectedName"
                        class="mt-1 block w-full" 
                        required />
                </div>

                {{-- Campo Email --}}
                <div class="mt-4">
                    <x-input-label for="edit_email" value="Correo Electrónico" />
                    <x-text-input 
                        id="edit_email" 
                        name="email" 
                        type="email" 
                        x-model="selectedEmail"
                        class="mt-1 block w-full" 
                        required />
                </div>

                {{-- Campo Rol --}}
                <div class="mt-4">
                    <x-input-label for="edit_role_id" value="Rol Asignado" />
                    <select 
                        id="edit_role_id" 
                        name="role_id" 
                        x-model="selectedRole"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="1">OperationalUser</option>
                        <option value="2">Médico / Especialista</option>
                        <option value="3">Administrador</option>
                    </select>
                </div>

                <div class="mt-6 flex justify-end">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Cancelar') }}
                    </x-secondary-button>

                    <x-primary-button class="ms-3" ::disabled="!selectedUserId">
                        {{ __('Guardar Cambios') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </x-modal>
</nav>