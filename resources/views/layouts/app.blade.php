<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dokra — Control Operativo')</title>
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
    @php
        $pendingEmergencyCount = 0;
        if (Auth::user()->isGerente() && \Illuminate\Support\Facades\Schema::hasTable('emergency_dispatch_requests')) {
            $pendingEmergencyCount = \App\Models\EmergencyDispatchRequest::query()->where('status', 'pending')->count();
        }
    @endphp
    @if($pendingEmergencyCount > 0 && !request()->routeIs('dashboard.gerente'))
        <div class="bg-red-700 text-white px-4 py-2 text-xs font-mono border-b border-red-900">
            <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
                <span>
                    <strong>⚠ NOTIFICACIÓN DE URGENCIA:</strong> Hay {{ $pendingEmergencyCount }} solicitud(es) de Salida de Emergencia Tortón (&lt;90%) esperando tu autorización.
                </span>
                <a href="{{ route('dashboard.gerente') }}" class="px-3 py-1 rounded bg-white text-red-900 font-extrabold uppercase shrink-0">
                    Revisar y Autorizar →
                </a>
            </div>
        </div>
    @endif
    <header class="bg-zinc-950 text-white border-b border-zinc-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between gap-3">
            <!-- Identidad Corporativa -->
            <div class="flex items-center space-x-3 min-w-0">
                <div class="w-8 h-8 rounded bg-orange-500 flex items-center justify-center text-zinc-950 font-extrabold text-sm shrink-0">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
                <div class="flex items-center space-x-2.5 truncate">
                    <span class="font-extrabold text-sm tracking-tight text-white uppercase">Dokra</span>
                    <span class="hidden sm:inline-block h-4 w-px bg-zinc-700"></span>
                    <span class="hidden sm:inline-block text-xs font-mono uppercase tracking-wider text-orange-500">
                        @yield('module-code', 'OPERACIONES')
                    </span>
                </div>
            </div>

            <!-- Información Fija de Sesión Activa y Cierre de Sesión Obligatorio para Cambiar Perfil -->
            <div class="flex items-center space-x-3 shrink-0">
                @if(Auth::user()->isOperador())
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

                <div class="flex items-center space-x-2 bg-zinc-900 border border-zinc-800 rounded-md px-3 py-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <div class="leading-tight">
                        <div class="text-xs font-bold text-white">{{ Auth::user()->name }}</div>
                        <div class="text-[10px] font-mono text-zinc-400">{{ Auth::user()->job_title }}</div>
                    </div>
                </div>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                            title="Cerrar sesión para ingresar con otra cuenta"
                            class="h-8 px-3 rounded-md border border-zinc-700 bg-zinc-900 text-zinc-200 hover:bg-orange-500 hover:text-zinc-950 hover:border-orange-500 transition text-xs font-bold flex items-center space-x-1.5 cursor-pointer">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span class="hidden sm:inline">Cerrar Sesión</span>
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
                <button type="button" @click="show = false" class="text-zinc-400 hover:text-white text-xs cursor-pointer">
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
            <span>Dokra · NAVE 100×40×9M</span>
            <span>RECIBO R1–R4 (08:00–18:00) · EMBARQUE E1–E3 · 32 RACKS (1,024 POS) · TORTÓN ≥90%</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script>
        window.CedisQrScanner = {
            async startLiveCamera(elementId, onDecode) {
                if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                    throw new Error('Tu navegador móvil bloquea la cámara en vivo en conexiones HTTP (sin HTTPS). Abre el enlace HTTPS (*.trycloudflare.com) o toca "Foto Cámara Móvil".');
                }

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Este navegador no expone cámara en vivo. Usa el botón "Foto Cámara Móvil".');
                }

                const container = document.getElementById(elementId);
                if (!container) {
                    throw new Error('No se encontró el contenedor de video QR.');
                }

                let stream;
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: { ideal: 'environment' },
                            width: { ideal: 1280 },
                            height: { ideal: 720 },
                        },
                        audio: false,
                    });
                } catch (_) {
                    stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                }

                container.innerHTML = '';
                const video = document.createElement('video');
                video.setAttribute('playsinline', 'true');
                video.setAttribute('webkit-playsinline', 'true');
                video.playsInline = true;
                video.muted = true;
                video.autoplay = true;
                video.className = 'absolute inset-0 w-full h-full object-cover';
                video.srcObject = stream;
                container.appendChild(video);

                await video.play();

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                const nativeDetector = ('BarcodeDetector' in window)
                    ? new window.BarcodeDetector({ formats: ['qr_code'] })
                    : null;

                let active = true;
                let rafId = null;
                let lastScanAt = 0;

                const tick = async (ts) => {
                    if (!active) return;

                    if (video.readyState >= video.HAVE_ENOUGH_DATA && (ts - lastScanAt) >= 140) {
                        lastScanAt = ts;
                        try {
                            if (nativeDetector) {
                                const found = await nativeDetector.detect(video);
                                if (found && found.length > 0 && found[0].rawValue) {
                                    onDecode(found[0].rawValue);
                                }
                            } else if (typeof window.jsQR === 'function' && video.videoWidth > 0 && video.videoHeight > 0) {
                                const scale = Math.min(1, 720 / Math.max(video.videoWidth, video.videoHeight));
                                canvas.width = Math.round(video.videoWidth * scale);
                                canvas.height = Math.round(video.videoHeight * scale);
                                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                const result = window.jsQR(imgData.data, imgData.width, imgData.height, {
                                    inversionAttempts: 'attemptBoth',
                                });
                                if (result && result.data) {
                                    onDecode(result.data);
                                }
                            }
                        } catch (_) {}
                    }

                    if (active) {
                        rafId = requestAnimationFrame(tick);
                    }
                };

                rafId = requestAnimationFrame(tick);

                return {
                    async stop() {
                        active = false;
                        if (rafId !== null) {
                            cancelAnimationFrame(rafId);
                            rafId = null;
                        }
                        if (stream) {
                            stream.getTracks().forEach((t) => {
                                try { t.stop(); } catch (_) {}
                            });
                        }
                        video.srcObject = null;
                    },
                    clear() {
                        if (container) {
                            container.innerHTML = '';
                        }
                    },
                };
            },

            async decodeImageFile(file) {
                if (!file) {
                    throw new Error('Archivo vacío.');
                }

                const img = await new Promise((resolve, reject) => {
                    const el = new Image();
                    const url = URL.createObjectURL(file);
                    el.onload = () => { URL.revokeObjectURL(url); resolve(el); };
                    el.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Imagen no legible')); };
                    el.src = url;
                });

                const maxDim = 1400;
                const scale = Math.min(1, maxDim / Math.max(img.width, img.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(img.width * scale);
                canvas.height = Math.round(img.height * scale);
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                if (typeof window.jsQR === 'function') {
                    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const result = window.jsQR(imgData.data, imgData.width, imgData.height, {
                        inversionAttempts: 'attemptBoth',
                    });
                    if (result && result.data) {
                        return result.data;
                    }
                }

                if ('BarcodeDetector' in window) {
                    const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
                    const found = await detector.detect(canvas);
                    if (found && found.length > 0 && found[0].rawValue) {
                        return found[0].rawValue;
                    }
                }

                throw new Error('No se encontró código QR en la imagen.');
            },
        };
    </script>
    @yield('extra-scripts')
</body>
</html>
