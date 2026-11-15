# API REST de Tareas (Laravel 13)

API REST para gestionar tareas, con autenticación por token **JWT**, roles y permisos (`super_admin`), documentación **Swagger/OpenAPI** y base de datos **MySQL**.

- Laravel 13 · PHP 8.3+ · MySQL
- Autenticación: [`php-open-source-saver/jwt-auth`](https://github.com/PHP-Open-Source-Saver/jwt-auth)
- Roles: [`spatie/laravel-permission`](https://github.com/spatie/laravel-permission)
- Documentación: [`darkaonline/l5-swagger`](https://github.com/DarkaOnLine/L5-Swagger)

## Cómo ejecutarlo

### Requisitos
- PHP 8.3 o superior con las extensiones `pdo_mysql` y **`sodium`** (la necesita la librería JWT; en Windows se activa quitando el `;` de `extension=sodium` en `php.ini`).
- Composer.
- MySQL en marcha.

### Pasos

```bash
# 1. Dependencias
composer install

# 2. Entorno
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# 3. Base de datos: crear una base vacía llamada test_task
mysql -u root -e "CREATE DATABASE test_task"

# 4. Ajustar en .env si tu MySQL no es root sin contraseña:
#    DB_HOST, DB_PORT, DB_DATABASE=test_task, DB_USERNAME, DB_PASSWORD

# 5. Migraciones y datos iniciales (rol super_admin + usuario administrador)
php artisan migrate --seed

# 6. Servidor
php artisan serve --port=8000
```

La API queda en `http://localhost:8000/api`.

### Usuario administrador (solo desarrollo)

| Email | Contraseña | Rol |
|---|---|---|
| `admin@admin.com` | `superadmin` | `super_admin` |

> Estas credenciales las crea el seeder para el entorno de desarrollo. Cámbialas antes de cualquier despliegue real.

### Documentación Swagger

- UI: http://localhost:8000/api/documentation
- JSON: http://localhost:8000/docs?api-docs.json
- Regenerar tras cambiar anotaciones: `php artisan l5-swagger:generate`

Para probar las rutas protegidas: ejecuta `POST /api/auth/login`, copia el `access_token` y pégalo en el botón **Authorize**.

### Tests

```bash
php artisan test
```

Los tests usan SQLite en memoria (definido en `phpunit.xml`), por lo que **no tocan** la base `test_task`.

## Endpoints

| Método | Ruta | Acceso | Respuestas |
|---|---|---|---|
| POST | `/api/auth/login` | Público | 200, 400, 401, 422 |
| POST | `/api/auth/logout` | Token | 200, 401 |
| POST | `/api/auth/refresh` | Token | 200, 401 |
| GET | `/api/auth/me` | Token | 200, 401 |
| GET | `/api/tasks` | **Público** | 200, 422 |
| GET | `/api/tasks/{id}` | **Público** | 200, 400, 404 |
| POST | `/api/tasks` | Token + `super_admin` | 201, 400, 401, 403, 422 |
| PUT/PATCH | `/api/tasks/{id}` | Token + `super_admin` | 200, 400, 401, 403, 404, 422 |
| DELETE | `/api/tasks/{id}` | Token + `super_admin` | 204, 400, 401, 403, 404 |

El token se envía en la cabecera `Authorization: Bearer <token>`.

### Tarea

| Campo | Tipo | Notas |
|---|---|---|
| `title` | string | Requerido, máx. 255 |
| `description` | string | Opcional |
| `status` | enum | `pendiente` (por defecto), `en_curso`, `hecha` |
| `assigned_to` | uuid | Opcional, UUID de un usuario existente (responsable) |
| `due_date` | date | Opcional, formato `Y-m-d` |

### Listado: filtros y paginación

`GET /api/tasks?status=en_curso&assigned_to=1&per_page=10&page=2`

- `status`: `pendiente`, `en_curso` o `hecha`.
- `assigned_to`: UUID del responsable.
- `per_page`: de 1 a 100 (15 por defecto). `page`: desde 1.
- Usa `paginate()` de Eloquent; la respuesta incluye `data`, `links` y `meta`.

### Códigos de error

Todas las respuestas de `/api/*` son JSON con `{"message": "..."}` (y `errors` en el 422).

| Código | Cuándo |
|---|---|
| 400 | JSON mal formado, o id que no es un UUID (`/tasks/abc`, `/tasks/1`) |
| 401 | Sin token, token inválido/expirado/invalidado, o credenciales incorrectas en el login |
| 403 | Usuario autenticado sin rol `super_admin` |
| 404 | Tarea o ruta inexistente |
| 405 | Método HTTP no permitido |
| 422 | Datos que no pasan la validación |

## Decisiones que tomé

- **JWT con `php-open-source-saver/jwt-auth`** en vez de `tymon/jwt-auth`: es el fork mantenido y su versión 2.10 funciona con Laravel 13. No usé Sanctum ni Passport porque el requisito era JWT.
- **`routes/api.php` creado a mano**, sin `php artisan install:api`, porque ese comando instala Sanctum, que no se necesita.
- **Guard `api` (driver `jwt`) como guard por defecto**, ya que el proyecto es solo API. El rol `super_admin` se crea con ese guard.
- **Lectura pública, escritura restringida**: listar y ver tareas no requieren token; crear, editar y borrar exigen token y rol `super_admin` (middleware `role:super_admin`).
- **El rol se comprueba antes de resolver la tarea de la ruta.** Si no, un usuario sin permiso recibiría 404 con ids inexistentes y 403 con los existentes, lo que revela qué ids hay. Lo resolví con `prependToPriorityList` en `bootstrap/app.php`.
- **Un `400` propio** para JSON mal formado (middleware `EnsureValidJson`) y para ids que no son UUID (`Route::bind`). Laravel por defecto trataría el JSON roto como un 422 vacío.
- **Errores siempre en JSON y sin filtrar detalles internos**: el 404 no expone el nombre de la clase del modelo.
- **Estado como Enum PHP** (`TaskStatus`) guardado como string, con índice en `status` por el filtro. Default `pendiente` también en el modelo para que la respuesta del POST ya lo incluya.
- **`assigned_to` con `nullOnDelete`**: si se borra un usuario, sus tareas quedan sin responsable en vez de borrarse.
- **Form Requests + API Resources** para separar validación y formato de salida del controlador.
- **Seeder idempotente**: `updateOrCreate` + `syncRoles`, se puede ejecutar varias veces sin duplicar al administrador.
- **Tests sobre SQLite en memoria**, para que `RefreshDatabase` nunca borre los datos de `test_task`.
- **Swagger con atributos PHP** (`OpenApi\Attributes`) en los controladores, y `api-docs.json` versionado para poder ver la documentación sin regenerarla.

## Qué haría con más tiempo

- Traducir los mensajes de validación (422) al español; hoy salen en inglés (los demás mensajes están en español).
- Rate limiting en el login (protección contra fuerza bruta) y rotación/refresh de tokens más estricta.
- Más filtros y orden en el listado: rango de fechas límite, búsqueda por título, `sort`.
- Soft deletes en tareas y auditoría de quién las modifica.
- Roles y permisos más finos (por ejemplo, que el responsable pueda cambiar el estado de su propia tarea) usando una Policy.
- Endpoint para gestionar usuarios (hoy solo existe el `super_admin` del seeder).
- Validar que `due_date` no sea pasada al crear, si el negocio lo requiere.
- CI (GitHub Actions) con tests y análisis estático (Pint, Larastan), y Docker para levantar el entorno completo.
- Pruebas de contrato contra el `api-docs.json` para detectar desajustes entre la documentación y la API.

## Cómo usé la IA

Trabajé con Claude Code (Claude Sonnet 5.5) en el editor, **fase por fase**: primero le pedí un plan por escrito (`plan.md`), lo revisé y lo ajusté, y después autoricé cada fase por separado, con un commit al final de cada una. Los tests se escribieron junto con el código desde la fase de login.

**Qué le pedí**
- Un plan de desarrollo a partir de los requisitos, sin implementar nada.
- Implementar cada fase siguiendo el plan: base de datos, paquetes, usuarios y roles, JWT, CRUD, errores, Swagger, tests y este README.
- Tests PHPUnit por endpoint que cubran todos los códigos HTTP aplicables (200, 201, 204, 400, 401, 403, 404, 422).

**Qué decidí yo y se aplicó al plan**
- Que listar y ver tareas fueran públicos, y que solo el `super_admin` pudiera crear, editar y borrar.
- Que no hubiera endpoint de registro.
- Credenciales de MySQL local y que la base `test_task` la creaba yo.
- Que los tests se escribieran por fase, a partir del login, y no al final.

**Qué acepté sin cambios importantes**
- Elección de paquetes, estructura de controladores/Form Requests/Resources, el Enum de estado y el esquema de la tabla.
- Documentación Swagger con atributos y la matriz de tests por endpoint.

**Qué se corrigió durante el desarrollo**
- **Extensión `sodium` ausente:** `composer require` falló por la librería JWT. Se activó en `php.ini` en lugar de ignorar el requisito con `--ignore-platform-req`.
- **Estado por defecto en el POST:** un test detectó que la respuesta de crear devolvía `status: null` aunque la BD guardaba `pendiente`. Se corrigió con un valor por defecto en el modelo.
- **403 frente a 404:** un test que añadí por seguridad mostró que un usuario sin rol recibía 404 con ids inexistentes (revela qué ids existen). Se corrigió cambiando la prioridad del middleware de rol.
- **Estado compartido en tests con JWT:** el guard y el singleton JWT conservaban el token de la petición anterior dentro de un mismo test, dando 401 falsos. Se corrigió en un helper de tests (`resetAuth()`), no en el código de la aplicación.
- **Swagger:** la primera generación falló por faltar la anotación `Info` y al menos un endpoint documentado; se añadieron y se regeneró el JSON.
- **Plan inicial ajustado:** tras mi revisión, el plan cambió (rutas públicas de lectura, tests por fase, BD de tests separada de `test_task`).

**Cómo verifiqué su trabajo:** ejecutando los tests tras cada fase (78 en total) y probando los endpoints con `curl` contra el servidor real y MySQL, no solo contra la suite de tests.

## Identificadores UUID

Las tareas y los usuarios usan **UUID v7** como clave primaria (`HasUuids`), en lugar de enteros autoincrementales. Así los ids no se pueden adivinar ni recorrer en secuencia (`/tasks/1`, `/tasks/2`...), y no revelan cuántas tareas o usuarios existen. Es una medida de defensa en profundidad: **no sustituye** a la autorización, que sigue siendo la protección real (solo `super_admin` escribe). Un id que no es UUID devuelve `400`; un UUID válido que no existe devuelve `404`.
