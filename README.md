<div align="center">

![header](https://capsule-render.vercel.app/api?type=waving&color=0:1B4B5A,100:ED7D5C&height=140&section=header)

<img src="docs/logo.png" alt="SIGT - Sistema Integral de Gestión de Turnos" width="520">

[![Typing SVG](https://readme-typing-svg.demolab.com?font=Fira+Code&weight=600&size=22&pause=1200&color=ED7D5C&center=true&vCenter=true&width=700&height=45&lines=Sistema+Integral+de+Gesti%C3%B3n+de+Turnos;Roles+y+permisos+individuales;API+REST+con+Laravel+y+Sanctum;Frontend+en+Angular;Pagos+con+Mercado+Pago;Recordatorios+por+Telegram)](https://github.com/NicoRusso1/Sistema-de-Gesti-n-de-Turnos)

<img src="docs/heartbeat.svg" alt="" width="700">

<br>

![Laravel](https://img.shields.io/badge/Laravel-12-ED7D5C?style=for-the-badge&logo=laravel&logoColor=white&labelColor=1B4B5A)
![Angular](https://img.shields.io/badge/Angular-22-ED7D5C?style=for-the-badge&logo=angular&logoColor=white&labelColor=1B4B5A)
![PHP](https://img.shields.io/badge/PHP-8.2-ED7D5C?style=for-the-badge&logo=php&logoColor=white&labelColor=1B4B5A)
![TypeScript](https://img.shields.io/badge/TypeScript-6-ED7D5C?style=for-the-badge&logo=typescript&logoColor=white&labelColor=1B4B5A)
![MySQL](https://img.shields.io/badge/MySQL-DB-ED7D5C?style=for-the-badge&logo=mysql&logoColor=white&labelColor=1B4B5A)
![Swagger](https://img.shields.io/badge/Swagger-OpenAPI-ED7D5C?style=for-the-badge&logo=swagger&logoColor=white&labelColor=1B4B5A)
![Docker](https://img.shields.io/badge/Docker-Railway-ED7D5C?style=for-the-badge&logo=docker&logoColor=white&labelColor=1B4B5A)

</div>

<br>

## 🩺 Sobre el proyecto

**SIGT** es un sistema web para gestionar los turnos de un hospital. Trabajo práctico de **Programación 4**, UTN FRC, **Grupo 3**.

Incluye autenticación con Sanctum (también con Google), una jerarquía de roles, usuarios operativos (paciente, médico y secretaria) con permisos individuales que el administrador activa o desactiva, y una API documentada con Swagger.

### Funcionalidades

- **Turnos:** búsqueda de horarios disponibles en franjas de 15 minutos, reserva, cancelación, reprogramación y asignación de sala (automática o manual).
- **Pagos:** cobro con Mercado Pago (Checkout Pro), descuento por obra social, confirmación por webhook y facturación con reintento manual.
- **Documentos:** certificados de turno y recetas en PDF, con historial para pacientes y médicos.
- **Notificaciones:** avisos dentro del sistema, correo (Resend) y recordatorios de turno por Telegram mediante n8n.
- **Administración:** usuarios, permisos, especialidades, obras sociales, salas y agenda de cada médico.
- **Control y métricas:** historial de auditoría inmutable (SuperAdmin), reporte de turnos por estado y métricas globales.

![](https://capsule-render.vercel.app/api?type=rect&color=0:1B4B5A,100:ED7D5C&height=3)

## 🧩 Tecnologías

| Capa | Tecnología |
|---|---|
| Backend | Laravel 12, PHP 8.2, Sanctum, Eloquent, L5-Swagger, DomPDF |
| Frontend | Angular 22 (componentes standalone, signals, formularios reactivos), TypeScript |
| Base de datos | MySQL |
| Pagos | Mercado Pago (Checkout Pro) |
| Acceso | Correo y contraseña, o Google (Google Identity Services) |
| Correos | Resend |
| Recordatorios | n8n + bot de Telegram |
| Documentación | Swagger (OpenAPI) |
| Despliegue | Docker y Railway |

## 📁 Estructura

```
.
├── backend/              API REST en Laravel
├── frontend/             Aplicación en Angular
├── n8n/                  Flujo de recordatorios por Telegram
├── docs/                 Logo, imágenes y guía de n8n
└── docker-compose.yml    MySQL, backend y frontend para correr todo con Docker
```

## 🔐 Roles y permisos

| Rol | Alcance |
|---|---|
| **SuperAdmin** | Todos los permisos. Único que puede otorgar o revocar `is_owner` |
| **Administrator** | Gestiona usuarios operativos y sus permisos |
| **OperationalUser** | Paciente, médico o secretaria, según `user_type` |

Permisos individuales por usuario:

`view_own_appointments` · `view_all_appointments` · `view_assigned_patients` · `cancel_appointments` · `edit_schedules`

![](https://capsule-render.vercel.app/api?type=rect&color=0:1B4B5A,100:ED7D5C&height=3)

## 🚀 Instalación

<details>
<summary><b>Backend (Laravel)</b></summary>

<br>

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Configurá la conexión a la base de datos en `.env` y después:

```bash
php artisan migrate:fresh --seed
php artisan l5-swagger:generate
php artisan serve
```

- API: `http://localhost:8000`
- Documentación: `http://localhost:8000/api/documentation`

</details>

<details>
<summary><b>Frontend (Angular)</b></summary>

<br>

```bash
cd frontend
npm install
npm start
```

- Aplicación: `http://localhost:4200`

</details>

<details>
<summary><b>Todo junto con Docker</b></summary>

<br>

```bash
docker compose up --build
```

Levanta MySQL, la API y el frontend con la configuración de desarrollo.

</details>

<details>
<summary><b>Variables de entorno del backend</b></summary>

<br>

Se configuran en `backend/.env` (hay un ejemplo en `backend/.env.example`):

| Variable | Para qué sirve |
|---|---|
| `FRONTEND_URL` | Dirección del frontend (CORS y enlace de recuperación de contraseña) |
| `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_RETURN_URL`, `MERCADOPAGO_SANDBOX` | Cobro con Mercado Pago |
| `GOOGLE_CLIENT_ID` | Ingreso y registro con Google |
| `MAIL_MAILER`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS` | Envío de correos con Resend |
| `N8N_REMINDER_WEBHOOK_URL`, `N8N_SECRET`, `TELEGRAM_BOT_USERNAME` | Recordatorios por Telegram con n8n |
| `REMINDER_HOURS_BEFORE` | Horas de anticipación del recordatorio (24 por defecto) |

</details>

<details>
<summary><b>Datos de demostración y tareas programadas</b></summary>

<br>

```bash
php artisan db:seed --class=DemoSeeder
```

Carga usuarios, turnos, pagos, notificaciones, certificados, recetas y registros de auditoría de ejemplo. Se puede ejecutar más de una vez.

Tareas programadas (`php artisan schedule:work`):

- `turnos:cancelar-expirados`: libera los turnos reservados sin pago.
- `turnos:enviar-recordatorios`: envía los recordatorios de turnos próximos.
- `facturas:reintentar`: reintenta la emisión de facturas fallidas.

La integración con Telegram está explicada en [docs/n8n-telegram.md](docs/n8n-telegram.md).

</details>

## 🌐 Versión desplegada

| Servicio | Dirección |
|---|---|
| Aplicación | https://sigt-turnos.up.railway.app |
| API | https://sigt-api.up.railway.app |
| n8n | https://n8n-production-a59860b2.up.railway.app |

Corre en Railway con imágenes Docker. Los pagos usan el modo de pruebas de Mercado Pago.

## 👤 Usuarios de prueba

Todos con contraseña `password`.

| Email | Rol |
|---|---|
| `superadmin@hospital.test` | SuperAdmin |
| `admin@hospital.test` | Administrator (propietario) |
| `medico@hospital.test` | OperationalUser (médico) |
| `paciente@hospital.test` | OperationalUser (paciente) |
| `secretaria@hospital.test` | OperationalUser (secretaria) |

![](https://capsule-render.vercel.app/api?type=rect&color=0:1B4B5A,100:ED7D5C&height=3)

## 📊 Estado del proyecto

| Módulo | Estado |
|---|---|
| Registro, login, recuperación de contraseña y acceso con Google | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Roles y usuarios operativos | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Permisos individuales (API y panel Angular) | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Especialidades y médicos | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Obras sociales | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Agenda de médicos | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Salas | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Gestión de turnos | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Pagos con Mercado Pago y facturación | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Certificados y recetas | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Notificaciones por correo y Telegram | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Historial de auditoría y métricas | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Documentación con Swagger | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Despliegue en Railway | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |

## 👥 Equipo

<div align="center">

<a href="https://github.com/NicoRusso1/Sistema-de-Gesti-n-de-Turnos/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=NicoRusso1/Sistema-de-Gesti-n-de-Turnos" alt="Contribuyentes">
</a>

Nicolás Russo · Valentín Maschio · Jeremías Mendelovich

<br>

<img src="docs/heartbeat.svg" alt="" width="700">

![footer](https://capsule-render.vercel.app/api?type=waving&color=0:1B4B5A,100:ED7D5C&height=120&section=footer)

</div>