<div x-data="{
        users: @js($usersList),
        currentUserId: {{ Auth::id() }},
        storeUrl: @js(route('gerente.users.store', [], false)),
        csrfToken: @js(csrf_token()),
        search: '',
        classFilter: 'TODOS',
        roleFilter: 'TODOS',
        saving: false,
        successMsg: '',
        errorMsg: '',
        form: {
            name: '',
            email: '',
            password: '',
            role: 'operador',
            job_title: 'Operador de Almacén y Recepción',
            assigned_branch_code: 'SUC-01',
            supplier_rfc: '',
            is_active: true
        },
        roleLabels: {
            gerente: 'Gerente / Admin CEDIS',
            jefe_embarque: 'Jefe de Embarque y Tráfico',
            operador: 'Operador de Almacén y Recepción',
            proveedor: 'Proveedor Externo',
            sucursal: 'Receptor en Sucursal Destino'
        },
        onRoleChange() {
            const defaults = {
                gerente: 'Gerente General de Operaciones CEDIS',
                jefe_embarque: 'Jefe de Embarque y Tráfico',
                operador: 'Operador de Almacén y Recepción',
                proveedor: 'Proveedor Externo Autorizado',
                sucursal: 'Receptor en Sucursal Destino'
            };
            this.form.job_title = defaults[this.form.role] || '';
        },
        generatePassword() {
            const prefix = this.form.role.charAt(0).toUpperCase() + this.form.role.slice(1, 4);
            this.form.password = prefix + '#' + Math.floor(1000 + Math.random() * 8999);
        },
        get formClassification() {
            return ['proveedor', 'sucursal'].includes(this.form.role) ? 'EXTERNO' : 'INTERNO CEDIS';
        },
        get filteredUsers() {
            const q = this.search.trim().toLowerCase();
            return this.users.filter(u => {
                const matchClass = this.classFilter === 'TODOS' || u.classification === this.classFilter;
                const matchRole = this.roleFilter === 'TODOS' || u.role === this.roleFilter;
                const matchText = !q ||
                    (u.name || '').toLowerCase().includes(q) ||
                    (u.email || '').toLowerCase().includes(q) ||
                    (u.job_title || '').toLowerCase().includes(q) ||
                    (u.assigned_branch_code || '').toLowerCase().includes(q) ||
                    (u.supplier_rfc || '').toLowerCase().includes(q);
                return matchClass && matchRole && matchText;
            });
        },
        async createUser() {
            this.saving = true;
            this.successMsg = '';
            this.errorMsg = '';
            try {
                const res = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'Verifica los datos del nuevo usuario.');
                }
                this.users = data.users;
                this.successMsg = data.message + ' Ya puede iniciar sesión con su correo y contraseña.';
                this.form.name = '';
                this.form.email = '';
                this.form.password = '';
                this.form.supplier_rfc = '';
            } catch (err) {
                this.errorMsg = err.message;
            } finally {
                this.saving = false;
            }
        },
        async toggleUserStatus(u) {
            this.errorMsg = '';
            try {
                const res = await fetch('/gerente/usuarios/' + u.id + '/estatus', {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken
                    }
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'No se pudo actualizar el estatus.');
                }
                const idx = this.users.findIndex(x => x.id === u.id);
                if (idx !== -1) {
                    this.users[idx] = data.user;
                }
                this.successMsg = data.message;
            } catch (err) {
                this.errorMsg = err.message;
            }
        }
     }"
     class="space-y-5">

    <!-- Avisos -->
    <div x-show="successMsg !== ''" x-cloak class="p-3.5 rounded-md bg-zinc-950 text-white border-l-4 border-orange-500 text-xs flex items-center justify-between">
        <span><strong class="text-orange-400">✓ USUARIO GUARDADO:</strong> <span x-text="successMsg"></span></span>
        <button type="button" @click="successMsg = ''" class="text-zinc-400 hover:text-white cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <div x-show="errorMsg !== ''" x-cloak class="p-3.5 rounded-md bg-red-950 text-white border-l-4 border-red-500 text-xs flex items-center justify-between">
        <span><i class="fa-solid fa-triangle-exclamation text-red-400 mr-1.5"></i> <span x-text="errorMsg"></span></span>
        <button type="button" @click="errorMsg = ''" class="text-red-300 hover:text-white cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- 1. Formulario de Alta y Clasificación de Usuarios -->
    <div class="bg-white border-2 border-zinc-950 rounded-lg overflow-hidden shadow-2xs">
        <div class="bg-zinc-950 text-white px-5 py-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center space-x-2">
                <span class="w-5 h-5 rounded bg-orange-500 text-zinc-950 font-mono text-xs font-extrabold flex items-center justify-center">
                    <i class="fa-solid fa-user-plus"></i>
                </span>
                <h2 class="text-xs font-extrabold uppercase tracking-wider">
                    Alta y Clasificación de Nuevo Usuario (Interno CEDIS o Externo)
                </h2>
            </div>
            <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold"
                  :class="formClassification === 'EXTERNO' ? 'bg-orange-500 text-zinc-950' : 'bg-zinc-800 text-emerald-400'"
                  x-text="'CLASIFICACIÓN: ' + formClassification"></span>
        </div>

        <form @submit.prevent="createUser" class="p-5 space-y-4 text-xs">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-4">
                    <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Nombre Completo / Razón Social</label>
                    <input type="text" x-model="form.name" required placeholder="Ej. Ana Laura Méndez / Papelera del Sur"
                           class="w-full bg-white border border-zinc-300 rounded px-3 py-2 text-zinc-950 focus:outline-none focus:border-orange-500">
                </div>

                <div class="md:col-span-4">
                    <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Correo Electrónico de Acceso</label>
                    <input type="email" x-model="form.email" required placeholder="usuario@cedis.com"
                           class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono text-zinc-950 focus:outline-none focus:border-orange-500">
                </div>

                <div class="md:col-span-4">
                    <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Contraseña (mín. 8 caracteres)</label>
                    <div class="flex gap-1.5">
                        <input type="text" x-model="form.password" required minlength="8" placeholder="Ej. Oper#2026"
                               class="flex-1 bg-white border border-zinc-300 rounded px-3 py-2 font-mono text-zinc-950 focus:outline-none focus:border-orange-500">
                        <button type="button" @click="generatePassword()"
                                class="px-2.5 py-2 rounded border border-zinc-300 bg-zinc-50 hover:border-zinc-950 font-mono text-[10px] font-bold uppercase cursor-pointer">
                            Generar
                        </button>
                    </div>
                </div>

                <div class="md:col-span-4">
                    <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Clasificación / Tipo de Perfil (RBAC)</label>
                    <select x-model="form.role" @change="onRoleChange()"
                            class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono font-bold text-zinc-950 focus:outline-none focus:border-orange-500">
                        <optgroup label="Personal Interno CEDIS">
                            <option value="gerente">Gerente / Admin CEDIS (/gerente)</option>
                            <option value="jefe_embarque">Jefe de Embarque y Tráfico (/embarques)</option>
                            <option value="operador">Operador de Almacén y Recepción (/recepcion · /acomodo)</option>
                        </optgroup>
                        <optgroup label="Usuarios Externos">
                            <option value="proveedor">Proveedor Externo (/proveedor)</option>
                            <option value="sucursal">Receptor de Sucursal Destino (/sucursal)</option>
                        </optgroup>
                    </select>
                </div>

                <div class="md:col-span-4">
                    <label class="block text-[10px] font-bold uppercase text-zinc-600 mb-1">Puesto / Cargo Descriptivo</label>
                    <input type="text" x-model="form.job_title" placeholder="Ej. Operador Turno Vespertino"
                           class="w-full bg-white border border-zinc-300 rounded px-3 py-2 text-zinc-950 focus:outline-none focus:border-orange-500">
                </div>

                <div class="md:col-span-4" x-show="form.role === 'sucursal'" x-cloak>
                    <label class="block text-[10px] font-bold uppercase text-orange-600 mb-1">Sucursal Destino Asignada (CDMX)</label>
                    <select x-model="form.assigned_branch_code"
                            class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono font-bold text-zinc-950 focus:outline-none focus:border-orange-500">
                        @foreach($branches as $b)
                            <option value="{{ $b['code'] }}">{{ $b['code'] }} · {{ $b['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-4" x-show="form.role === 'proveedor'" x-cloak>
                    <label class="block text-[10px] font-bold uppercase text-orange-600 mb-1">ID / RFC del Proveedor Externo</label>
                    <input type="text" x-model="form.supplier_rfc" placeholder="Ej. PMS980412AB1"
                           class="w-full bg-white border border-zinc-300 rounded px-3 py-2 font-mono uppercase text-zinc-950 focus:outline-none focus:border-orange-500">
                </div>
            </div>

            <div class="pt-3 border-t border-zinc-200 flex items-center justify-between">
                <span class="text-[11px] font-mono text-zinc-500">
                    La cuenta quedará activa de inmediato con acceso exclusivo a su módulo RBAC.
                </span>
                <button type="submit" :disabled="saving"
                        class="px-5 py-2 rounded bg-orange-500 hover:bg-orange-600 disabled:bg-zinc-300 text-white text-xs font-bold uppercase tracking-wider transition cursor-pointer">
                    <i class="fa-solid fa-user-check mr-1"></i>
                    <span x-show="!saving">Crear y Clasificar Usuario</span>
                    <span x-show="saving" x-cloak>Guardando...</span>
                </button>
            </div>
        </form>
    </div>

    <!-- 2. Directorio Clasificado de Usuarios -->
    <div class="bg-white border border-zinc-200 rounded-lg overflow-hidden shadow-2xs">
        <div class="px-5 py-3.5 border-b border-zinc-200 bg-zinc-50/60 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <div>
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-zinc-950">
                    Directorio de Usuarios Registrados (<span x-text="filteredUsers.length"></span>)
                </h3>
                <p class="text-[11px] text-zinc-500">
                    Control de cuentas internas del CEDIS y cuentas externas (proveedores y sucursales destino).
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 font-mono text-xs">
                <input type="text" x-model="search" placeholder="Buscar nombre, correo o puesto..."
                       class="bg-white border border-zinc-300 rounded px-2.5 py-1 text-xs w-52 focus:outline-none focus:border-orange-500">

                <template x-for="cl in ['TODOS', 'INTERNO CEDIS', 'EXTERNO']" :key="cl">
                    <button type="button" @click="classFilter = cl"
                            :class="classFilter === cl ? 'bg-zinc-950 text-white' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200'"
                            class="px-2.5 py-1 rounded text-[10px] font-bold cursor-pointer"
                            x-text="cl"></button>
                </template>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse font-mono tabular-nums">
                <thead>
                    <tr class="bg-zinc-950 text-white text-[10px] uppercase tracking-wider">
                        <th class="py-2.5 px-4">Usuario / Correo</th>
                        <th class="py-2.5 px-3">Clasificación</th>
                        <th class="py-2.5 px-3">Perfil RBAC / Cargo</th>
                        <th class="py-2.5 px-3">Asignación</th>
                        <th class="py-2.5 px-3">Estatus</th>
                        <th class="py-2.5 px-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <template x-for="u in filteredUsers" :key="u.id">
                        <tr class="even:bg-zinc-50/80 hover:bg-orange-50/40 transition">
                            <td class="py-2.5 px-4 font-sans">
                                <div class="font-bold text-zinc-950" x-text="u.name"></div>
                                <div class="font-mono text-[11px] text-zinc-500" x-text="u.email"></div>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                      :class="u.classification === 'EXTERNO' ? 'bg-orange-50 border-orange-300 text-orange-900' : 'bg-zinc-950 border-zinc-950 text-white'"
                                      x-text="u.classification"></span>
                            </td>
                            <td class="py-2.5 px-3 font-sans">
                                <div class="font-mono font-bold text-zinc-950" x-text="roleLabels[u.role] || u.role"></div>
                                <div class="text-[11px] text-zinc-600" x-text="u.job_title"></div>
                            </td>
                            <td class="py-2.5 px-3 text-[11px] text-zinc-700">
                                <span x-show="u.assigned_branch_code" x-text="'Destino: ' + u.assigned_branch_code"></span>
                                <span x-show="u.supplier_rfc" x-text="'ID: ' + u.supplier_rfc"></span>
                                <span x-show="!u.assigned_branch_code && !u.supplier_rfc" class="text-zinc-400">CEDIS CDMX</span>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                      :class="u.is_active ? 'bg-emerald-50 border-emerald-300 text-emerald-800' : 'bg-red-50 border-red-300 text-red-800'"
                                      x-text="u.is_active ? '● ACTIVO' : '● SUSPENDIDO'"></span>
                            </td>
                            <td class="py-2.5 px-4 text-right">
                                <template x-if="u.id !== currentUserId">
                                    <button type="button" @click="toggleUserStatus(u)"
                                            class="px-2.5 py-1 rounded border border-zinc-300 bg-white hover:border-zinc-950 text-zinc-800 text-[10px] font-bold uppercase cursor-pointer"
                                            x-text="u.is_active ? 'Suspender' : 'Reactivar'"></button>
                                </template>
                                <template x-if="u.id === currentUserId">
                                    <span class="text-[10px] text-zinc-400 uppercase">Sesión actual</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>
