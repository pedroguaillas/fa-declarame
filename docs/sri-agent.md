# SRI Agent — Agente de escritorio

El SRI Agent es un proceso Python que corre en la computadora del cliente (no en el servidor) y descarga comprobantes del portal SRI usando un navegador Chrome real. Esto evita los problemas de reCAPTCHA que ocurren cuando el scraper corre en un servidor remoto con display virtual.

## Arquitectura

```
Cliente (PC del usuario)               Servidor Laravel
┌──────────────────────────────┐       ┌────────────────────────────────┐
│  ~/.sri-agent/               │       │                                │
│  ├── server.py               │       │  POST /sri-scrape/agent-      │
│  ├── test-scraper.py         │◄──────│       dispatch                 │
│  └── venv/                   │       │  ← crea SriScrapeJob           │
│                               │       │  ← devuelve {jobId, config}    │
│  localhost:8765               │       │                                │
│                               │───────►  POST /scrape-callback        │
│  Abre Chrome real             │       │  ← valida token HMAC           │
│  Login SRI → descarga TXT     │       │  ← ProcessScrapeCallbackJob    │
└──────────────────────────────┘       └────────────────────────────────┘
```

### Flujo completo

1. Usuario abre la página **Comprobantes SRI** en la web app
2. Frontend hace `fetch('http://localhost:8765/health')` — detecta si el agente está corriendo
3. Si detectado: botón **"Agente Local"** se habilita
4. Usuario selecciona período y tipo, hace click en **"Agente Local"**
5. Frontend POST → `/sri-scrape/agent-dispatch` → Laravel crea `SriScrapeJob` con `source=agent` y devuelve `{jobId, config}` con `callbackUrl` firmado
6. Frontend POST → `localhost:8765/scrape` con ese config
7. Agente abre Chrome en pantalla del usuario, hace login en SRI, descarga TXT
8. Agente POST resultado → `callbackUrl` en Laravel
9. Laravel procesa con `ProcessScrapeCallbackJob` (mismo pipeline que el scraper de servidor)
10. Polling en el frontend actualiza el estado del job cada 3 s

## Archivos clave

| Archivo | Descripción |
|---|---|
| `scripts/sri-agent/server.py` | HTTP server local (puerto 8765), gestiona browser lifecycle |
| `scripts/sri-agent/test-scraper.py` | Lógica de scraping SRI (login, navegación, descarga TXT) |
| `scripts/sri-agent/install.sh` | Instalador Mac/Linux |
| `scripts/sri-agent/install.ps1` | Instalador Windows |
| `public/agent/version.json` | Versión actual del agente (auto-update) |

## Endpoints de distribución

Todos son rutas públicas, sin autenticación, en `routes/web.php`:

| Ruta | Contenido |
|---|---|
| `GET /agent/version.json` | `{"version": "1.0.0"}` |
| `GET /agent/install.sh` | Script instalador Mac/Linux |
| `GET /agent/install.ps1` | Script instalador Windows |
| `GET /agent/server.py` | Script del servidor (sirve desde `scripts/sri-agent/`) |
| `GET /agent/test-scraper.py` | Script del scraper (sirve desde `scripts/sri-agent/`) |

## Endpoints de Laravel

### `POST /sri-scrape/agent-dispatch` (tenant, autenticado)

Crea un `SriScrapeJob` con `source='agent'` y devuelve la configuración para el agente local.

**Request:**
```json
{
    "type": "compras",
    "year": 2026,
    "month": 6,
    "day": null,
    "voucher_types": ["1", "3", "4"],
    "full_semester": false
}
```

**Response:**
```json
{
    "jobId": 123,
    "config": {
        "ruc": "0990123456001",
        "password": "clave_desencriptada",
        "type": "compras",
        "year": 2026,
        "month": 6,
        "mode": "txt_download",
        "voucherTypes": ["1", "3", "4"],
        "callbackUrl": "https://tenant.fa-declarame.com/scrape-callback?job=123&tenant=abc&token=xxx",
        "skipClaves": ["clave1", "clave2"]
    }
}
```

El `callbackUrl` está firmado con HMAC-SHA256 usando `app.key`. El agente lo llama cuando termina el scrape.

### `POST /scrape-callback` (central, sin autenticación de sesión)

Recibe el resultado del agente. Valida el token HMAC, guarda el payload en storage local, y despacha `ProcessScrapeCallbackJob` que corre el mismo pipeline de importación que el scraper de servidor.

## API del agente local

### `GET http://localhost:8765/health`

```json
{
    "status": "ok",
    "version": "1.0.0",
    "logged_in_ruc": null
}
```

El frontend compara `version` contra `MIN_AGENT_VERSION` definido en `Index.vue`. Si el agente es antiguo, el botón se deshabilita y se muestra aviso de actualización.

### `POST http://localhost:8765/scrape`

Recibe el objeto `config` del `agent-dispatch` endpoint. Responde inmediatamente con `{"status": "accepted"}` cuando hay `callbackUrl` (modo asíncrono). El scrape ocurre en un thread separado.

## Instalación (cliente)

### Mac / Linux

```bash
curl -sSL https://TU_DOMINIO/agent/install.sh | bash
```

### Windows (PowerShell, sin admin)

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force
iwr https://TU_DOMINIO/agent/install.ps1 | iex
```

### Qué hace el instalador

1. Detecta / instala Python 3.9+ (brew en Mac, apt en Linux, winget en Windows)
2. Descarga `server.py` y `test-scraper.py` desde el servidor
3. Crea `~/.sri-agent/venv/` e instala `playwright` + `playwright-stealth`
4. Instala Chromium para Playwright
5. Registra el agente como servicio que arranca con el sistema:
   - **Mac**: `~/Library/LaunchAgents/com.declarame.sri-agent.plist`
   - **Linux**: `~/.config/systemd/user/sri-agent.service`
   - **Windows**: tarea en Task Scheduler
6. Verifica que responda en `localhost:8765/health`

**Para actualizar:** ejecutar el mismo comando instalador de nuevo (idempotente).

## Auto-update

Al arrancar, el agente compara su versión con `GET {AGENT_URL}/version.json`. Si hay versión nueva:
1. Descarga `server.py` y `test-scraper.py` nuevos
2. Se reinicia con `os.execv` (Mac/Linux) preservando los argumentos

Para publicar una nueva versión:
1. Actualizar `scripts/sri-agent/server.py` y/o `test-scraper.py`
2. Incrementar `"version"` en `public/agent/version.json`
3. Deploy — en el próximo arranque del agente se auto-actualiza

## CORS

El agente corre en `localhost:8765`. Cuando el navegador (HTTPS) hace `fetch` a `http://localhost`, los navegadores modernos lo permiten porque `localhost` es considerado origen seguro. El server Python responde con `Access-Control-Allow-Origin: *` en todas las respuestas, incluyendo el preflight `OPTIONS`.

## Seguridad

- El `callbackUrl` incluye un token `HMAC-SHA256(job_id:tenant_id, app.key)` — Laravel lo valida antes de procesar
- El agente solo escucha en `127.0.0.1` (no accesible desde la red)
- La clave SRI viaja en la respuesta de `agent-dispatch` sobre HTTPS (la misma que el usuario ingresó)
- Los scripts Python son públicos pero no contienen credenciales

## Ver logs del agente

El agente escribe todo su output (progreso + errores) a `agent.log` dentro de su carpeta de instalación (`~/.sri-agent/`).

**Mac / Linux:**
```bash
tail -n 150 ~/.sri-agent/agent.log      # últimas líneas
tail -f ~/.sri-agent/agent.log          # en vivo
```

**Windows (PowerShell):**
```powershell
Get-Content "$env:USERPROFILE\.sri-agent\agent.log" -Tail 150        # últimas líneas
Get-Content "$env:USERPROFILE\.sri-agent\agent.log" -Wait -Tail 30   # en vivo
```

Cada línea del progreso del scraper se loguea dos veces: una legible (`[HH:MM:SS] [step] mensaje`) y una en JSON (`{"event": "progress", ...}`) — esta última es la que el frontend recibiría si el agente corriera en modo streaming. Buscar `[callback] Error al enviar callback:` para diagnosticar fallos de entrega del resultado a Laravel (ver tabla de troubleshooting).

## Jobs atascados en `running`

Si el proceso del agente se reinicia (crash, actualización, cierre de sesión) a mitad de un scrape, el `SriScrapeJob` correspondiente queda en `status=running` para siempre — nunca llega el callback que lo marcaría `completed`/`failed`, y el frontend lo sigue mostrando "en proceso" indefinidamente.

Comando de rescate: `php artisan sri:rescue-stuck-jobs`

```bash
# 1. Ver qué hay atascado sin tocar nada (recomendado primero)
php artisan sri:rescue-stuck-jobs --dry-run --tenant=TENANT_ID

# 2. Re-despachar (resetea a pending y vuelve a lanzar ScrapeFromSriJob)
php artisan sri:rescue-stuck-jobs --tenant=TENANT_ID

# Jobs puntuales por ID (ignora el filtro de horas)
php artisan sri:rescue-stuck-jobs --ids=123,124

# Marcar como failed en vez de reintentar
php artisan sri:rescue-stuck-jobs --tenant=TENANT_ID --mark-failed
```

Opciones: `--hours=1` (default; mínimo de horas en `running` para considerarse atascado), `--date=YYYY-MM-DD`, `--ids`, `--tenant`, `--mark-failed`, `--dry-run`.

- **Re-despachar (default, sin `--mark-failed`)**: no consume el cupo de intentos por período (`SriScrapeJob::blockReason()` solo cuenta `completed`/`failed`) — es la opción segura para reintentar.
- **`--mark-failed`**: consume 1 de los 3 intentos fallidos permitidos por período (`MAX_FAILED_ATTEMPTS=3`). Usar solo si ya no se quiere reintentar ese job.
- Un job `running`/`pending` para la misma empresa+tipo **bloquea** un nuevo dispatch manual ("Ya existe una descarga en progreso para este tipo") — hay que rescatar el atascado antes de poder lanzar uno nuevo desde la UI.
- Re-correr un período ya parcialmente importado es seguro: `getExistingClavesForPeriod()` arma `skipClaves` desde los documentos ya guardados en `Order`/`Shop`, así que el re-run solo trae lo que faltó, sin duplicar.

## Troubleshooting

| Síntoma | Causa probable | Solución |
|---|---|---|
| Botón "Agente Local" nunca se habilita | Agente no está corriendo | Verificar servicio; revisar `~/.sri-agent/agent.log` |
| "Agente desactualizado" | `version.json` tiene versión mayor | Reinstalar: `curl ... \| bash` |
| Job queda en `pending`/`running` para siempre | `callbackUrl` no alcanzable desde la PC del cliente, o error SSL en la máquina del agente | Ver `agent.log` → línea `[callback] Error al enviar callback:`. Rescatar el job con `sri:rescue-stuck-jobs` (ver sección arriba) |
| En dev local, job nunca completa aunque el agente terminó | `APP_URL` en `.env` sin puerto (ej. `http://localhost` en vez de `http://localhost:8000`) — el callback pega a un puerto sin nada escuchando y falla silencioso en el agente | Corregir `APP_URL` con el puerto de `php artisan serve`, `php artisan config:clear`, reiniciar `composer dev` |
| Callback falla con `CERTIFICATE_VERIFY_FAILED: certificate has expired` en Windows, pero el navegador/.NET sí conecta bien | El almacén de certificados raíz de Windows tiene una raíz nueva (ej. tras rotación de Let's Encrypt) que Python no ve porque no delega en CryptoAPI como sí hace .NET | `.\venv\Scripts\pip.exe install pip-system-certs` dentro de `~/.sri-agent`, reiniciar el agente |
| Windows: agente arranca bien manual pero no tras reiniciar la PC | Tarea de Task Scheduler con `DisallowStartIfOnBatteries` en `true` (default) — no arranca si la laptop está en batería | Ya corregido en `install.ps1` (se asigna como propiedad post-creación, no parámetro del cmdlet, porque algunos hosts de PowerShell —ej. pwsh vía WinCompat— no exponen esos parámetros) |
| Windows: `.\start-agent.ps1` da error "la ejecución de scripts está deshabilitada" | Execution policy restringe scripts en sesión interactiva (Task Scheduler sí usa `-ExecutionPolicy Bypass`) | `powershell.exe -ExecutionPolicy Bypass -File "$env:USERPROFILE\.sri-agent\start-agent.ps1"` |
| Ventas (emitidos) descarga día-por-día: un mes entero sale en cero pese a que sí hay comprobantes | Un día con timeout de descarga deja la página en estado inconsistente; el día siguiente revienta con excepción no atrapada, que se propagaba hasta `handle_scrape` y descartaba TODO lo acumulado de los días anteriores | Corregido: cada día está aislado en su propio try/except en `download_for_voucher_type_by_day` y recarga la página (`navigate_to_comprobantes`) tras cualquier fallo antes de seguir al día siguiente |
| Comprobante visible en el portal pero no se importó, sin error visible | Modal/XML falló al abrir tras reintentos y se descartaba en silencio | Corregido: el scraper ahora reporta `failed_claves`/`incomplete_days`; `SriScraperService` los suma en `result.missing` y pone `error_message` de advertencia aunque el job quede `completed` |
| Login SRI falla | Clave SRI incorrecta o cambió | Actualizar clave en configuración de empresa |
| reCAPTCHA rechazado | Chrome fingerprint desactualizado | `scripts/sri-agent/server.py` actualiza fingerprint en cada arranque — reiniciar agente |
