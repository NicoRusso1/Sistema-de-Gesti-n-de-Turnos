<div align="center">

![header](https://capsule-render.vercel.app/api?type=waving&color=0:1B4B5A,100:ED7D5C&height=140&section=header)

<img src="docs/logo.png" alt="SIGT - Sistema Integral de Gestión de Turnos" width="520">

[![Typing SVG](https://readme-typing-svg.demolab.com?font=Fira+Code&weight=600&size=22&pause=1200&color=ED7D5C&center=true&vCenter=true&width=700&height=45&lines=Sistema+Integral+de+Gesti%C3%B3n+de+Turnos;Roles+y+permisos+individuales;API+REST+con+Laravel+y+Sanctum;Frontend+en+Angular)](https://github.com/NicoRusso1/Sistema-de-Gesti-n-de-Turnos)

<img src="docs/heartbeat.svg" alt="" width="700">

<br>

![Laravel](https://img.shields.io/badge/Laravel-12-ED7D5C?style=for-the-badge&logo=laravel&logoColor=white&labelColor=1B4B5A)
![Angular](https://img.shields.io/badge/Angular-22-ED7D5C?style=for-the-badge&logo=angular&logoColor=white&labelColor=1B4B5A)
![PHP](https://img.shields.io/badge/PHP-8.2-ED7D5C?style=for-the-badge&logo=php&logoColor=white&labelColor=1B4B5A)
![TypeScript](https://img.shields.io/badge/TypeScript-5-ED7D5C?style=for-the-badge&logo=typescript&logoColor=white&labelColor=1B4B5A)
![MySQL](https://img.shields.io/badge/MySQL-DB-ED7D5C?style=for-the-badge&logo=mysql&logoColor=white&labelColor=1B4B5A)
![Swagger](https://img.shields.io/badge/Swagger-OpenAPI-ED7D5C?style=for-the-badge&logo=swagger&logoColor=white&labelColor=1B4B5A)

</div>

<br>

## 🩺 Sobre el proyecto

**SIGT** es un sistema web para gestionar los turnos de un hospital. Trabajo práctico de **Programación 4**, UTN FRC, **Grupo 3**.

Incluye autenticación con Sanctum, una jerarquía de roles, usuarios operativos (paciente, médico y secretaria) con permisos individuales que el administrador activa o desactiva, y una API documentada con Swagger.

![](https://capsule-render.vercel.app/api?type=rect&color=0:1B4B5A,100:ED7D5C&height=3)


![](https://capsule-render.vercel.app/api?type=rect&color=0:1B4B5A,100:ED7D5C&height=3)

## 🧩 Tecnologías

| Capa | Tecnología |
|---|---|
| Backend | Laravel 12, Sanctum, Eloquent, L5-Swagger |
| Frontend | Angular 22 (componentes standalone, signals, formularios reactivos) |
| Base de datos | MySQL |
| Documentación | Swagger (OpenAPI) |

## 📁 Estructura

```
.
├── backend/    API REST en Laravel
├── frontend/   Aplicación en Angular
└── docs/       Logo e imágenes del README
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

## 👤 Usuarios de prueba

Todos con contraseña `password`.

| Email | Rol |
|---|---|
| `superadmin@hospital.test` | SuperAdmin |
| `admin@hospital.test` | Administrator (propietario) |
| `medico@hospital.test` | OperationalUser (médico) |

![](https://capsule-render.vercel.app/api?type=rect&color=0:1B4B5A,100:ED7D5C&height=3)

## 📊 Estado del proyecto

| Módulo | Estado |
|---|---|
| Registro, login y recuperación de contraseña | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Roles y usuarios operativos | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Permisos individuales (API y panel Angular) | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Especialidades y médicos | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Documentación con Swagger | ![listo](https://img.shields.io/badge/listo-1B4B5A?style=flat-square) |
| Gestión de turnos | ![pendiente](https://img.shields.io/badge/pendiente-ED7D5C?style=flat-square) |
| Obras sociales | ![pendiente](https://img.shields.io/badge/pendiente-ED7D5C?style=flat-square) |
| Agenda de médicos | ![pendiente](https://img.shields.io/badge/pendiente-ED7D5C?style=flat-square) |
| Salas | ![pendiente](https://img.shields.io/badge/pendiente-ED7D5C?style=flat-square) |

## 👥 Equipo

<div align="center">

<a href="https://github.com/NicoRusso1/Sistema-de-Gesti-n-de-Turnos/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=NicoRusso1/Sistema-de-Gesti-n-de-Turnos" alt="Contribuyentes">
</a>

Nicolás Russo · Valentín Maschio · Jeremias Mendelovich

<br>

<img src="docs/heartbeat.svg" alt="" width="700">

![footer](https://capsule-render.vercel.app/api?type=waving&color=0:1B4B5A,100:ED7D5C&height=120&section=footer)

</div>