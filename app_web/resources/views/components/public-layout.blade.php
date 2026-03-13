<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'B&B Store | Tienda virtual al alcance de todos' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --brand-orange: #ff7f2a;
            --brand-pink: #f4289d;
            --brand-purple: #8c3dff;
            --brand-cyan: #23c8f5;
            --brand-dark: #111827;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: linear-gradient(180deg, #f9fafb 0%, #ffffff 45%, #f3f4f6 100%);
            color: #1f2937;
        }

        main {
            flex: 1;
        }

        .site-header {
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.88);
            border-bottom: 1px solid rgba(17, 24, 39, 0.06);
        }

        .brand-gradient {
            background: linear-gradient(115deg, var(--brand-orange), var(--brand-pink), var(--brand-purple), var(--brand-cyan));
        }

        .btn-brand {
            color: #fff;
            border: 0;
            background: linear-gradient(120deg, var(--brand-orange), var(--brand-pink), var(--brand-purple));
            box-shadow: 0 10px 24px rgba(140, 61, 255, 0.24);
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
        }

        .btn-brand:hover {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(140, 61, 255, 0.28);
            filter: brightness(1.04);
        }

        .logo-wrap {
            width: 90px;
            height: 90px;
            border-radius: 16px;
            padding: 6px;
            background: white;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.16);
        }


        .btn-outline-brand {
            border: 1px solid rgba(140, 61, 255, 0.4);
            color: var(--brand-purple);
            background: rgba(255, 255, 255, 0.9);
            transition: transform .2s ease, box-shadow .2s ease, color .2s ease, border-color .2s ease;
        }

        .btn-outline-brand:hover {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(120deg, var(--brand-orange), var(--brand-pink), var(--brand-purple));
            transform: translateY(-1px);
            box-shadow: 0 14px 26px rgba(244, 40, 157, 0.24);
        }

        .btn-soft-brand {
            border: 1px solid rgba(140, 61, 255, 0.22);
            color: #5b21b6;
            background: linear-gradient(130deg, rgba(255, 255, 255, 0.95), rgba(245, 243, 255, 0.95));
            box-shadow: 0 6px 18px rgba(99, 102, 241, 0.12);
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, color .2s ease;
        }

        .btn-soft-brand:hover {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(120deg, var(--brand-orange), var(--brand-pink), var(--brand-purple));
            transform: translateY(-1px);
            box-shadow: 0 14px 26px rgba(140, 61, 255, 0.22);
        }

        .credit-form-header {
            border: 0;
            background: linear-gradient(120deg, rgba(255, 127, 42, 0.95), rgba(244, 40, 157, 0.95), rgba(140, 61, 255, 0.95));
        }

        .social-link {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-decoration: none;
            background: linear-gradient(130deg, var(--brand-pink), var(--brand-purple));
            transition: transform .2s ease;
        }

        .social-link:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <header class="site-header sticky-top">
        <div class="container py-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('home') }}" class="logo-wrap" aria-label="Ir a la página principal">
                    <img src="{{ asset('imagenes/logo.jpg') }}" alt="Logo B&B Store" class="img-fluid rounded-3">
                </a>
                <div>
                    <h1 class="h4 mb-0 fw-bold">B&B Store</h1>
                    <small class="text-muted">Tienda virtual al alcance de todos</small>
                </div>
            </div>
            <a href="{{ route('credit-applications.create') }}" class="btn btn-brand rounded-pill px-4">Solicitar crédito</a>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="py-5 mt-4" style="background: #0f172a; color: #e5e7eb;">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <h3 class="h5 fw-bold mb-3">B&B Store</h3>
                    <p class="mb-0 text-white-50">Tienda virtual con atención cálida, soluciones de crédito y envíos a todo el país.</p>
                </div>
                <div class="col-md-4">
                    <h4 class="h6 fw-bold text-uppercase mb-3">Contacto</h4>
                    <p class="mb-1"><i class="bi bi-geo-alt-fill me-2"></i>Carrera 59 bb # 40c - 50 Bello - Antioquia</p>
                    <p class="mb-0"><i class="bi bi-phone-fill me-2"></i>Celular / WhatsApp: +57 3042933031</p>
                </div>
                <div class="col-md-4">
                    <h4 class="h6 fw-bold text-uppercase mb-3">Síguenos</h4>
                    <div class="d-flex gap-2 mb-2">
                        <a href="#" class="social-link" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-link" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="social-link" aria-label="TikTok"><i class="bi bi-tiktok"></i></a>
                        <a href="#" class="social-link" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    </div>
                    <small class="text-white-50 d-block">© {{ now()->year }} B&B Store. Todos los derechos reservados.</small>
                    <small class="text-white-50">Powered by <a href="https://yefersonsossa.com/" target="_blank" rel="noopener noreferrer" class="text-white">yefersonsossa.com</a></small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
