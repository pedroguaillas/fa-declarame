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

## Troubleshooting

| Síntoma | Causa probable | Solución |
|---|---|---|
| Botón "Agente Local" nunca se habilita | Agente no está corriendo | Verificar servicio; revisar `~/.sri-agent/agent.log` |
| "Agente desactualizado" | `version.json` tiene versión mayor | Reinstalar: `curl ... \| bash` |
| Job queda en `pending` para siempre | `callbackUrl` no alcanzable desde la PC del cliente | Verificar que el dominio resuelve correctamente desde el cliente |
| Login SRI falla | Clave SRI incorrecta o cambió | Actualizar clave en configuración de empresa |
| reCAPTCHA rechazado | Chrome fingerprint desactualizado | `scripts/sri-agent/server.py` actualiza fingerprint en cada arranque — reiniciar agente |
