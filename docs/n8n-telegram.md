# Recordatorios de turno por Telegram (n8n)

Solo la notificación de **recordatorio** se manda por Telegram, y siempre **además** del email.
Laravel no habla con Telegram directamente: le pasa el mensaje a n8n y n8n lo envía.

```
cron turnos:enviar-recordatorios (cada hora)
  └─ AppointmentNotificationService::notifyReminder()
       ├─ email (como antes)
       └─ si el email salió y el paciente vinculó Telegram:
            POST N8N_REMINDER_WEBHOOK_URL  (header X-N8N-Secret)
              └─ n8n: Webhook → Telegram "Send message" → responde 200
```

Vinculación de la cuenta (para conocer el `chat_id` del paciente):

```
Mi perfil → "Vincular Telegram" → POST /api/me/telegram/link → https://t.me/<bot>?start=<token>
El paciente toca "Iniciar" en el bot → Telegram manda "/start <token>" a n8n
n8n → POST /api/integrations/n8n/telegram/link {token, chat_id} (header X-N8N-Secret)
Laravel guarda telegram_chat_id → n8n le responde al paciente "¡Listo!"
```

Si n8n falla o no está configurado, el email se envía igual (el error queda en el log de Laravel).

## 1. Crear el bot

1. En Telegram, hablale a **@BotFather** → `/newbot`.
2. Guardá el **token** del bot y el **usuario** (ej: `SigtTurnosBot`).

## 2. Levantar n8n

```bash
cd n8n
docker compose up -d        # http://localhost:5678
```

Telegram solo envía mensajes a URLs **HTTPS públicas**, así que para el workflow de vinculación
necesitás exponer n8n con un túnel, por ejemplo:

```bash
cloudflared tunnel --url http://localhost:5678     # o: ngrok http 5678
N8N_PUBLIC_URL=https://<tu-subdominio>/ docker compose up -d   # reiniciar n8n con la URL pública
```

Laravel tiene que escuchar en todas las interfaces para que n8n (en Docker) lo alcance:

```bash
cd backend && php artisan serve --host=0.0.0.0 --port=8000
```

## 3. Credenciales en n8n

En **Credentials → Add credential**:

| Tipo | Nombre sugerido | Valores |
|---|---|---|
| Telegram API | `SIGT Bot` | Access Token = token de BotFather |
| Header Auth | `SIGT X-N8N-Secret` | Name = `X-N8N-Secret`, Value = el mismo valor que `N8N_SECRET` |

Generá el secreto con `php -r "echo bin2hex(random_bytes(32));"`.

## 4. Importar los workflows

**Workflows → Import from file** con los dos archivos de [`n8n/workflows/`](../n8n/workflows):

- `recordatorio-turno-telegram.json`
  - *Webhook recordatorio*: elegir la credencial `SIGT X-N8N-Secret`.
  - *Enviar por Telegram*: elegir `SIGT Bot`.
- `vincular-telegram.json`
  - *Mensaje al bot*, *Responder resultado*, *Responder instrucciones*: elegir `SIGT Bot`.
  - *Confirmar en Laravel*: elegir `SIGT X-N8N-Secret` y, si Laravel no corre en tu máquina,
    cambiar la URL `http://host.docker.internal:8000/...`.

**Activá** los dos workflows (toggle *Active*). La URL de producción del webhook es
`<url de n8n>/webhook/turnos-recordatorio` (la de `/webhook-test/` solo sirve con el editor abierto).

## 5. Configurar Laravel

En `backend/.env`:

```env
N8N_REMINDER_WEBHOOK_URL=http://localhost:5678/webhook/turnos-recordatorio
N8N_SECRET=<el mismo secreto de la credencial Header Auth>
TELEGRAM_BOT_USERNAME=SigtTurnosBot
```

```bash
php artisan migrate        # agrega telegram_chat_id y telegram_link_token a patients
php artisan config:clear
```

Si `TELEGRAM_BOT_USERNAME` está vacío, la sección de Telegram no aparece en el perfil.
Si `N8N_REMINDER_WEBHOOK_URL` está vacío, los recordatorios salen solo por email.

## 6. Probar

1. Entrá al frontend → **Mi perfil** → **Vincular Telegram** → en Telegram tocá **Iniciar**.
   El bot responde "¡Listo, <nombre>!".
2. Creá un turno para ese paciente dentro de las próximas 24 hs y corré:
   ```bash
   php artisan turnos:enviar-recordatorios
   ```
3. Llega el email y el mensaje de Telegram. En n8n → *Executions* se ve cada envío.

## Endpoints

| Método | Ruta | Auth | Uso |
|---|---|---|---|
| GET | `/api/me/telegram` | Sanctum | `{ linked, available }` |
| POST | `/api/me/telegram/link` | Sanctum | Genera el link `t.me/<bot>?start=<token>` |
| DELETE | `/api/me/telegram` | Sanctum | Desvincula |
| POST | `/api/integrations/n8n/telegram/link` | `X-N8N-Secret` | Lo llama n8n con `{ token, chat_id }` |

Payload que recibe el webhook de n8n: `chat_id`, `texto` (mensaje ya armado), `notification_id`,
`appointment_id`, `paciente`, `medico`, `especialidad`, `fecha`, `hora`, `sala`.
