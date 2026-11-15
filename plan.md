# Plan de desarrollo: API REST de Tareas (Laravel 13)

> Estado: **solo planificación**. Nada de lo descrito aquí está implementado todavía.

## 1. Objetivo

API REST con Laravel 13 que permita gestionar tareas, protegida con autenticación JWT, con roles y permisos, documentada con Swagger y respaldada por MySQL (`test_task`).

## 2. Requisitos y decisiones técnicas

| Requisito | Decisión |
|---|---|
| Framework | Laravel 13.42 (ya creado), PHP 8.4 |
| Base de datos | MySQL, base `test_task` (reemplaza el SQLite por defecto en `.env`) |
| Autenticación | JWT con `php-open-source-saver/jwt-auth` (fork mantenido de tymon; **verificar compatibilidad con Laravel 13** antes de instalar) |
| Roles/permisos | `spatie/laravel-permission` |
| Documentación | Swagger/OpenAPI con `darkaonline/l5-swagger` (anotaciones `OpenApi\Attributes`; verificar compatibilidad con Laravel 13) |
| Tests | PHPUnit/Pest (el que traiga el esqueleto) con `RefreshDatabase`, mínimo 3 tests |
| Paginación | `paginate()` de Eloquent |

Riesgo: si algún paquete aún no soporta Laravel 13, evaluar alternativa o fijar versión compatible y documentarlo en el README.

## 3. Modelo de datos

### users
- `id`, `name`, `email` (único), `password` (hash), `timestamps`
- Usa `HasRoles` (spatie) e implementa `JWTSubject`.

### tasks
- `id`
- `title` (string, requerido)
- `description` (text, nullable)
- `status` (enum: `pendiente`, `en_curso`, `hecha`; default `pendiente`)
- `assigned_to` (FK nullable a `users.id`, responsable; `nullOnDelete`)
- `due_date` (date, nullable)
- `timestamps`
- Índices en `status` y `assigned_to` (filtros).

Se usará un Enum PHP (`TaskStatus`) para el estado.

### Tablas de spatie/laravel-permission
Migración publicada del paquete: `roles`, `permissions`, `model_has_roles`, etc.

## 4. Fases de desarrollo

### Fase 1: Entorno y base de datos
1. Crear base `test_task` en MySQL.
2. Configurar `.env` (`DB_CONNECTION=mysql`, `DB_DATABASE=test_task`, credenciales) y actualizar `.env.example`.
3. Verificar conexión con `php artisan migrate:status`.

### Fase 2: Instalación de paquetes
1. `composer require php-open-source-saver/jwt-auth spatie/laravel-permission darkaonline/l5-swagger`.
2. Publicar configuraciones y migraciones de cada paquete.
3. Generar secreto JWT (`php artisan jwt:secret`).
4. Configurar guard `api` con driver `jwt` en `config/auth.php`.
5. Crear/instalar rutas API (`php artisan install:api` si aplica en Laravel 13, para tener `routes/api.php`).

### Fase 3: Usuarios, roles y seeder
1. Ajustar el modelo `User` (`HasRoles`, `JWTSubject`: `getJWTIdentifier`, `getJWTCustomClaims`). Mantener `name`, `email`, `password`.
2. Seeder `RoleSeeder`: crea el rol `super_admin` (guard `api`).
3. Seeder `SuperAdminSeeder`: crea usuario `admin@admin.com` / contraseña `superadmin` (hasheada) y le asigna `super_admin`.
4. Registrar ambos en `DatabaseSeeder` y ejecutar `migrate --seed`.
5. Nota: la contraseña es solo para entorno de desarrollo; mencionarlo en el README.

### Fase 4: Autenticación JWT
Endpoints bajo `/api/auth`:
- `POST /auth/login` (público): valida `email` y `password`, devuelve token, tipo y expiración. Credenciales inválidas → `401`.
- `POST /auth/logout` (protegido): invalida el token.
- `POST /auth/refresh` (protegido): renueva el token.
- `GET /auth/me` (protegido): devuelve el usuario autenticado y sus roles.

`login` es público; `logout`, `refresh` y `me` van bajo `auth:api`. No habrá endpoint de registro. Respuestas `401` en JSON para token ausente, inválido o expirado (ajustar el handler de excepciones en `bootstrap/app.php` para que la API nunca redirija).

### Fase 5: CRUD de tareas
Arquitectura:
- `Task` model + `TaskFactory`.
- `TaskController` (`apiResource`) delgado.
- `StoreTaskRequest` y `UpdateTaskRequest` (Form Requests).
- `TaskResource` y colección paginada.
- Autorización: las rutas de escritura usan `auth:api` + middleware de rol `role:super_admin` (spatie). Sin policy necesaria.

Endpoints:

| Método | Ruta | Acceso | Descripción | Éxito |
|---|---|---|---|---|
| GET | `/api/tasks` | **Público** | Listar con filtros y paginación | 200 |
| GET | `/api/tasks/{id}` | **Público** | Ver | 200 |
| POST | `/api/tasks` | `auth:api` + `super_admin` | Crear | 201 |
| PUT/PATCH | `/api/tasks/{id}` | `auth:api` + `super_admin` | Editar | 200 |
| DELETE | `/api/tasks/{id}` | `auth:api` + `super_admin` | Borrar | 204 |

Rutas: `apiResource('tasks')->only(['index','show'])` fuera del grupo protegido; `only(['store','update','destroy'])` dentro de `middleware(['auth:api','role:super_admin'])`. Registrar el alias de middleware `role` de spatie en `bootstrap/app.php`.

Listado:
- Filtros: `?status=pendiente|en_curso|hecha` y `?assigned_to={user_id}`.
- Paginación con `paginate()` (`?per_page=`, por defecto 15, máximo 100; `?page=`).
- Scopes en el modelo (`scopeStatus`, `scopeAssignedTo`) o `when()` en el controlador.
- Eager loading del responsable para evitar N+1.

### Fase 6: Validación y manejo de errores
- `title`: requerido, string, máx. 255.
- `description`: opcional, string.
- `status`: opcional, `Rule::enum(TaskStatus::class)`.
- `assigned_to`: opcional, `exists:users,id`.
- `due_date`: opcional, fecha válida (`date`, formato `Y-m-d`; valorar `after_or_equal:today` al crear).
- Filtros del listado validados también (status válido, `per_page` entero 1-100).

Códigos HTTP:
- `200` OK, `201` creado, `204` borrado sin contenido.
- `401` no autenticado / credenciales inválidas.
- `403` usuario autenticado sin rol `super_admin` en crear/editar/borrar.
- `404` recurso no encontrado (JSON, no HTML).
- `422` errores de validación con estructura `{message, errors}`.
- `500` error inesperado con mensaje genérico (sin filtrar detalles fuera de debug).

Configurar en `bootstrap/app.php` el render de excepciones para `/api/*` siempre en JSON.

### Fase 7: Documentación Swagger
1. Anotaciones OpenAPI: info general, servidor, esquema de seguridad `bearerAuth` (JWT).
2. Documentar cada endpoint: parámetros, request bodies, respuestas (200/201/204/401/404/422) y esquemas (`Task`, `User`, `LoginRequest`, `Paginated`).
3. Generar con `php artisan l5-swagger:generate` y verificar la UI en `/api/documentation`.
4. Comprobar el flujo "Authorize" con el token obtenido en login.

### Fase 8: Tests con PHPUnit (cubren todos los códigos HTTP por endpoint)

**Estrategia: los tests se escriben junto con cada fase a partir de la Fase 4** (login), no al final. Al cerrar cada fase, sus tests deben pasar antes de continuar:
- Fase 4 → tests de `Auth` (login, logout, refresh, me).
- Fase 5 → tests de `Tasks` (index, show, store, update, destroy) con sus casos felices.
- Fase 6 → se completan los casos de error (400, 401, 403, 404, 422) de cada endpoint.
- Fase 3 → test unitario del seeder (se escribe aquí porque lo necesitan los tests de login).

**Configuración de tests**
- Clases PHPUnit (`extends Tests\TestCase`), con atributos `#[Test]`/métodos `test_*` según lo que traiga el esqueleto; confirmar que el esqueleto usa PHPUnit y no Pest.
- **Importante:** `phpunit.xml` debe usar una BD **distinta** a `test_task` (SQLite en memoria, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), porque `RefreshDatabase` borra las tablas y destruiría los datos reales y el super_admin.
- Trait `RefreshDatabase`; en `setUp` se ejecutan los seeders de roles. Helpers en un trait de test (`CreatesUsers`): `superAdmin()`, `normalUser()` y `tokenFor($user)` (token JWT real con `JWTAuth::fromUser`) para enviar `Authorization: Bearer`.
- Factories: `UserFactory`, `TaskFactory`.

**Definición de los códigos usados** (decisiones de diseño a implementar en Fase 6):
- `400`: cuerpo JSON mal formado (en endpoints con body) **o** identificador no numérico en la URL (`/tasks/abc`). Se implementa con `whereNumber` en rutas + render de `BadRequestHttpException`/`JsonException` en `bootstrap/app.php`.
- `401`: sin token, token inválido, expirado o en blacklist; en login, credenciales incorrectas.
- `403`: usuario autenticado sin rol `super_admin`.
- `404`: tarea inexistente o ruta inexistente.
- `422`: validación de campos fallida.
- Códigos que **no aplican** a un endpoint se marcan "N/A" en la matriz con el motivo, y no se fuerzan.

#### Matriz de tests por endpoint

**POST `/api/auth/login`** (`tests/Feature/Auth/LoginTest.php`)
| Código | Test |
|---|---|
| 200 | Credenciales correctas devuelven `access_token`, `token_type=bearer`, `expires_in`. El token es utilizable en `/auth/me`. |
| 400 | Cuerpo con JSON mal formado devuelve `400` con mensaje JSON. |
| 401 | Contraseña incorrecta devuelve `401`. Email inexistente devuelve `401` (mismo mensaje, sin revelar cuál falló). |
| 422 | Sin `email`, sin `password`, `email` con formato inválido devuelven `422` con errores por campo. |
| 403 / 404 | N/A (ruta pública y existente). |

**POST `/api/auth/logout`** (`LogoutTest.php`)
| Código | Test |
|---|---|
| 200 | Con token válido responde `200` y mensaje de cierre de sesión. |
| 401 | Sin token; con token inválido; reutilizar el mismo token después del logout (blacklist). |
| 400 / 403 / 404 / 422 | N/A (sin body, sin roles, sin recursos). |

**POST `/api/auth/refresh`** (`RefreshTest.php`)
| Código | Test |
|---|---|
| 200 | Devuelve un token nuevo distinto del anterior y el nuevo funciona. |
| 401 | Sin token; token inválido; token anterior queda invalidado tras el refresh. |
| 400 / 403 / 404 / 422 | N/A. |

**GET `/api/auth/me`** (`MeTest.php`)
| Código | Test |
|---|---|
| 200 | Devuelve `id`, `name`, `email` y roles (el super_admin incluye `super_admin`); nunca expone `password`. |
| 401 | Sin token; token inválido; token expirado (`Carbon::setTestNow` / TTL corto). |
| 400 / 403 / 404 / 422 | N/A. |

**GET `/api/tasks`** (`tests/Feature/Tasks/IndexTaskTest.php`, público)
| Código | Test |
|---|---|
| 200 | Sin token devuelve lista paginada (`data`, `links`, `meta`) con 15 por página por defecto. |
| 200 | Filtro `status=pendiente` devuelve solo pendientes (y lo mismo para `en_curso`, `hecha`). |
| 200 | Filtro `assigned_to={id}` devuelve solo las de ese responsable. |
| 200 | Filtros combinados `status` + `assigned_to`. |
| 200 | `per_page` y `page` respetados; página fuera de rango devuelve `data` vacío. |
| 200 | Sin resultados devuelve `data: []` (no 404). |
| 422 | `status=invalido`, `assigned_to=abc` o inexistente, `per_page=0`, `per_page=101`. |
| 400 | N/A (sin body ni identificador). 401 / 403: N/A (ruta pública). 404: N/A. |

**GET `/api/tasks/{id}`** (`ShowTaskTest.php`, público)
| Código | Test |
|---|---|
| 200 | Sin token devuelve la tarea con todos sus campos y el responsable. |
| 400 | `/tasks/abc` (id no numérico). |
| 404 | `/tasks/99999` inexistente, respuesta JSON. |
| 401 / 403 / 422 | N/A (ruta pública, sin body). |

**POST `/api/tasks`** (`StoreTaskTest.php`)
| Código | Test |
|---|---|
| 201 | super_admin crea tarea válida: respuesta con el recurso y `assertDatabaseHas`. Estado por defecto `pendiente` si no se envía. |
| 400 | Body JSON mal formado. |
| 401 | Sin token; token inválido. |
| 403 | Usuario autenticado sin rol `super_admin`; no se crea registro (`assertDatabaseCount`). |
| 422 | Sin `title`; `title` > 255; `status` fuera del enum; `assigned_to` inexistente; `due_date` inválida; errores por campo en `errors`. |
| 404 | N/A (la colección siempre existe). |

**PUT/PATCH `/api/tasks/{id}`** (`UpdateTaskTest.php`)
| Código | Test |
|---|---|
| 200 | super_admin edita (PUT completo y PATCH parcial); persiste en BD y devuelve datos nuevos. |
| 400 | id no numérico; body JSON mal formado. |
| 401 | Sin token; token inválido. |
| 403 | Usuario sin rol `super_admin`; la tarea no cambia. |
| 404 | Tarea inexistente. |
| 422 | `title` vacío, `status` inválido, `assigned_to` inexistente, `due_date` inválida. |

**DELETE `/api/tasks/{id}`** (`DestroyTaskTest.php`)
| Código | Test |
|---|---|
| 204 | super_admin borra; sin contenido en el body y `assertDatabaseMissing`. |
| 400 | id no numérico. |
| 401 | Sin token; token inválido; la tarea sigue existiendo. |
| 403 | Usuario sin rol `super_admin`; la tarea sigue existiendo. |
| 404 | Tarea inexistente; borrar dos veces la misma devuelve `404` la segunda. |
| 422 | N/A (sin body). |

**Rutas inexistentes** (`tests/Feature/FallbackTest.php`)
- `GET /api/no-existe` → `404` en JSON (no HTML).
- Método no permitido (p. ej. `DELETE /api/tasks`) → `405` en JSON.

**Tests unitarios complementarios** (`tests/Unit/`)
- `TaskStatusTest`: valores del enum (`pendiente`, `en_curso`, `hecha`).
- `TaskModelTest`: scopes de filtro por estado y por responsable; casts de `status` y `due_date`.
- `SuperAdminSeederTest`: crea `admin@admin.com`, la contraseña `superadmin` verifica con `Hash::check`, y tiene rol `super_admin`; ejecutarlo dos veces no duplica el usuario.

**Resumen de cantidad:** ~9 archivos feature + 3 unit, aproximadamente 55-65 tests, muy por encima del mínimo de 3. Comando: `php artisan test` (o `vendor/bin/phpunit`).

### Fase 9: README
Secciones requeridas:
1. **Cómo ejecutarlo**: requisitos, `composer install`, `.env`, crear BD `test_task`, `key:generate`, `jwt:secret`, `migrate --seed`, `serve`, URL de Swagger, credenciales del super admin, cómo correr tests.
2. **Decisiones tomadas**: paquete JWT elegido, Form Requests, Resources, Enum de estado, índices, `paginate()`, formato de errores.
3. **Qué haría con más tiempo**: policies por rol/propietario, filtros adicionales (rango de fechas, búsqueda por título), ordenamiento, soft deletes, rate limiting en login, refresh tokens con blacklist, CI, Docker, más tests.
4. **Uso de la IA**: qué se pidió, qué se aceptó y qué se corrigió (se irá registrando durante el desarrollo para que sea fiel).

### Fase 10: Cierre
- Revisar `.env.example`, `.gitignore` (sin secretos en el repo).
- Ejecutar toda la suite de tests y verificar manualmente con Swagger.
- Commit y push a `main` en https://github.com/LANGEL0128/test-task.

### Fase 11: Endurecimiento de seguridad (opcional, tiempo libre)

> **Alcance:** esta fase **no forma parte de la entrega de la prueba técnica**. Es trabajo que haría en mi tiempo libre, con más tiempo del que da la prueba. Se deja documentada para mostrar que el análisis de seguridad se hizo y qué se priorizaría. Está mencionada en la sección "Qué haría con más tiempo" del README.

Origen: análisis de seguridad hecho tras migrar los ids a UUID. Dos hallazgos se confirmaron con pruebas reales (login sin límite de intentos y error 500 con descripciones muy largas).

#### 11.1 Prioridad alta (lo primero que haría)
1. **Datos personales en rutas públicas.** `GET /api/tasks` y `GET /api/tasks/{id}` devuelven nombre y email del responsable sin token. Acción: `TaskResource` solo expone `id` y `name` del responsable (sin `email`) y se añade un test que lo verifique. Decisión de producto pendiente: si el listado debe seguir siendo público.
2. **Sin límite de intentos en el login.** Verificado: 20 contraseñas erróneas seguidas dan 401 sin bloqueo. Acción: `RateLimiter` en `login` (p. ej. 5 intentos por minuto por email + IP, respuesta `429`) y `throttle:api` general para el resto. Tests: el intento 6 devuelve `429`, y un login correcto no se ve afectado antes del límite.
3. **Credenciales por defecto en el seeder.** `admin@admin.com` / `superadmin` se crean en cualquier entorno. Acción: leer email y contraseña de variables de entorno (`ADMIN_EMAIL`, `ADMIN_PASSWORD`), y no sembrar el usuario si `APP_ENV=production` sin contraseña definida. Los valores de la prueba se mantienen solo para desarrollo, documentados en el README.
4. **`APP_DEBUG=true` en `.env.example`.** Con debug activo los errores exponen SQL, host, nombre de la BD y rutas. Acción: `APP_DEBUG=false` en `.env.example` y nota en el README para activarlo solo en local.
5. **Error 500 con textos muy largos.** Verificado: una `description` de 70 000 caracteres rompe MySQL (`Data too long`) y devuelve 500. Acción: `max` en `description` (p. ej. 5000) en `StoreTaskRequest` y `UpdateTaskRequest`, y test que devuelva `422` en lugar de `500`.

#### 11.2 Prioridad media
6. **Refresh de 14 días.** Un token robado puede renovarse 2 semanas. Acción: reducir `JWT_REFRESH_TTL` (p. ej. 1 día) y valorar rotación.
7. **Cabeceras de seguridad, HTTPS y CORS.** Añadir `X-Content-Type-Options`, `Strict-Transport-Security`, `X-Frame-Options`; forzar HTTPS en producción; restringir `allowed_origins` en lugar de `*`.
8. **Swagger público en producción.** Desactivar o proteger `/api/documentation` fuera de local.
9. **Lista negra de tokens en caché.** Un `cache:clear` reactiva tokens revocados hasta que expiren. Acción: usar un almacén persistente y no limpiar la caché de la lista negra.

#### 11.3 Prioridad baja
10. Igualar los tiempos de respuesta del login para que no se pueda inferir si un email existe.
11. Valorar UUID v4 si no se quiere exponer la fecha de creación (v7 la incluye); implicaría ordenar por `created_at` y no por `id`.
12. Registro de auditoría (quién crea, edita o borra tareas) y roles/permisos más granulares.

#### Criterios de aceptación de la fase
- [ ] Ninguna respuesta pública expone emails.
- [ ] El login responde `429` tras superar el límite.
- [ ] El seeder no crea credenciales débiles en producción.
- [ ] Con `APP_DEBUG=false` los errores no muestran SQL ni rutas.
- [ ] Descripciones demasiado largas devuelven `422`.
- [ ] Todos los tests nuevos y los 84 existentes pasan.

## 5. Estructura de archivos prevista

```
app/
  Enums/TaskStatus.php
  Http/Controllers/Api/{AuthController,TaskController}.php
  Http/Requests/{LoginRequest,StoreTaskRequest,UpdateTaskRequest}.php
  Http/Resources/{TaskResource,UserResource}.php
  Models/{User,Task}.php
  Policies/TaskPolicy.php            (opcional)
database/
  factories/TaskFactory.php
  migrations/..._create_tasks_table.php
  seeders/{RoleSeeder,SuperAdminSeeder,DatabaseSeeder}.php
routes/api.php
tests/Feature/Auth/{LoginTest,LogoutTest,RefreshTest,MeTest}.php
tests/Feature/Tasks/{IndexTaskTest,ShowTaskTest,StoreTaskTest,UpdateTaskTest,DestroyTaskTest}.php
tests/Feature/FallbackTest.php
tests/Unit/{TaskStatusTest,TaskModelTest,SuperAdminSeederTest}.php
tests/Concerns/CreatesUsers.php
README.md
plan.md
```

## 6. Criterios de aceptación

- [ ] `migrate --seed` sobre MySQL `test_task` deja el sistema listo con `admin@admin.com` / `superadmin` con rol `super_admin`.
- [ ] Login devuelve JWT válido; `logout`, `refresh`, `me` y las rutas de crear/editar/borrar tareas rechazan peticiones sin token con `401` y a usuarios sin rol `super_admin` con `403`. Listar y ver tareas son públicos.
- [ ] CRUD de tareas completo con los campos pedidos.
- [ ] Filtros por estado y responsable funcionan junto con paginación (`paginate()`).
- [ ] Validación con `422` y códigos HTTP correctos en todos los casos.
- [ ] Swagger accesible, documentando todos los endpoints y con autenticación Bearer.
- [ ] Tests PHPUnit pasando: cada endpoint cubre todos sus códigos aplicables (200, 201, 204, 400, 401, 403, 404, 422) según la matriz de la Fase 8, escritos desde la Fase 4.
- [ ] Los tests corren en una BD distinta a `test_task` (SQLite en memoria) y no la alteran.
- [ ] README completo con las cuatro secciones.

## 7. Decisiones confirmadas

1. MySQL local: usuario `root`, sin contraseña, host `127.0.0.1`, puerto `3306`. La BD `test_task` ya existe.
2. Solo el `super_admin` puede crear, editar y borrar tareas.
3. Listar y ver tareas son rutas públicas (sin token).
4. No hay endpoint de registro; el único usuario inicial es el `super_admin` del seeder.
