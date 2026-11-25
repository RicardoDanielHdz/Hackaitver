<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'CEDIS CDMX — Control Operativo')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
        .industrial-grid {
            background-image: linear-gradient(to right, rgba(255,255,255,0.04) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 32px 32px;
        }
    </style>
    @yield('extra-head')
</head>
<body class="min-h-full flex flex-col font-sans antialiased @yield('body-class', 'bg-zinc-100 text-zinc-950')">
    @auth
    <header class="bg-zinc-950 text-white border-b border-zinc-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between gap-3">
            <!-- Identidad Corporativa -->
            <div class="flex items-center space-x-3 min-w-0">
                <div class="w-8 h-8 rounded bg-orange-500 flex items-center justify-center text-zinc-950 font-extrabold text-sm shrink-0">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
                <div class="flex items-center space-x-2.5 truncate">
                    <span class="font-extrabold text-sm tracking-tight text-white uppercase">CEDIS CDMX</span>
                    <span class="hidden sm:inline-block h-4 w-px bg-zinc-700"></span>
                    <span class="hidden sm:inline-block text-xs font-mono uppercase tracking-wider text-orange-500">
                        @yield('module-code', 'OPERACIONES')
                    </span>
                </div>
            </div>

            <!-- Controles de Sesión y Selector Rápido de Puesto -->
            <div class="flex items-center space-x-3 shrink-0">
                @if(Auth::user()->role === 'operador' || request()->routeIs('dashboard.recepcion') || request()->routeIs('wms.*'))
                    <div class="hidden sm:flex items-center bg-zinc-900 border border-zinc-800 rounded-md p-0.5 text-[11px] font-mono">
                        <a href="{{ route('dashboard.recepcion') }}"
                           class="px-2.5 py-1 rounded transition flex items-center space-x-1 {{ request()->routeIs('dashboard.recepcion') ? 'bg-orange-500 text-zinc-950 font-bold' : 'text-zinc-300 hover:text-white' }}">
                            <i class="fa-solid fa-qrcode"></i>
                            <span>Handheld QR</span>
                        </a>
                        <a href="{{ route('wms.index') }}"
                           class="px-2.5 py-1 rounded transition flex items-center space-x-1 {{ request()->routeIs('wms.*') ? 'bg-orange-500 text-zinc-950 font-bold' : 'text-zinc-300 hover:text-white' }}">
                            <i class="fa-solid fa-cubes-stacked"></i>
                            <span>Motor de Acomodo</span>
                        </a>
                    </div>
                @endif

                <div class="flex items-center space-x-2 bg-zinc-900 border border-zinc-800 rounded-md px-2.5 py-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                    <label for="role-switcher" class="hidden md:inline text-[11px] font-mono uppercase tracking-wider text-zinc-400">Puesto:</label>
                    <select id="role-switcher"
                            onchange="window.location.href=this.value"
                            class="bg-zinc-900 text-xs font-semibold text-white focus:outline-none cursor-pointer pr-1">
                        <option value="{{ route('login.quick', 'gerente') }}" {{ Auth::user()->role === 'gerente' && !request()->routeIs('wms.*') ? 'selected' : '' }}>01 · Gerente / Admin</option>
                        <option value="{{ route('login.quick', 'jefe_embarque') }}" {{ Auth::user()->role === 'jefe_embarque' && !request()->routeIs('wms.*') ? 'selected' : '' }}>02 · Jefe de Embarque</option>
                        <option value="{{ route('login.quick', 'operador') }}" {{ Auth::user()->role === 'operador' && !request()->routeIs('wms.*') ? 'selected' : '' }}>03 · Operador (Handheld QR)</option>
                        <option value="{{ route('login.quick', 'operador_acomodo') }}" {{ request()->routeIs('wms.*') ? 'selected' : '' }}>03 · Operador (Motor de Acomodo)</option>
                        <option value="{{ route('login.quick', 'proveedor') }}" {{ Auth::user()->role === 'proveedor' && !request()->routeIs('wms.*') ? 'selected' : '' }}>04 · Proveedor Externo</option>
                        <option value="{{ route('login.quick', 'sucursal') }}" {{ Auth::user()->role === 'sucursal' && !request()->routeIs('wms.*') ? 'selected' : '' }}>05 · Receptor Sucursal</option>
                    </select>
                </div>

                <div class="hidden lg:flex flex-col text-right leading-tight">
                    <span class="text-xs font-semibold text-white">{{ Auth::user()->name }}</span>
                    <span class="text-[11px] text-zinc-400">{{ Auth::user()->job_title }}</span>
                </div>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                            title="Cerrar sesión"
                            class="h-8 px-2.5 rounded-md border border-zinc-800 bg-zinc-900 text-zinc-400 hover:text-white hover:border-orange-500 transition text-xs flex items-center space-x-1.5">
                        <i class="fa-solid fa-power-off"></i>
                        <span class="hidden sm:inline font-medium">Salir</span>
                    </button>
                </form>
            </div>
        </div>
    </header>
    @endauth

    @if(session('status'))
        <div x-data="{ show: true }" x-show="show" x-cloak
             class="bg-zinc-900 border-b border-zinc-800 text-zinc-100 text-xs px-4 py-2">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="inline-block w-2 h-2 bg-orange-500 rounded-full"></span>
                    <span class="font-medium">{{ session('status') }}</span>
                </div>
                <button type="button" @click="show = false" class="text-zinc-400 hover:text-white text-xs">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
    @endif

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="bg-zinc-950 text-zinc-400 border-t border-zinc-800 py-2 px-4">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between text-[11px] font-mono tabular-nums gap-1">
            <span>CEDIS CDMX · NAVE 100×40×9M</span>
            <span>RECIBO R1–R4 (08:00–18:00) · EMBARQUE E1–E3 · 32 RACKS (1,024 POS) · TORTÓN ≥90%</span>
        </div>
    </footer>

    @yield('extra-scripts')
</body>
</html>
