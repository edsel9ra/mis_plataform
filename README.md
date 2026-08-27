# MIS — Mentorías Integrales Sistémicas

Plataforma de mentoría que conecta mentores y mentees usando ciencia de datos y neurociencia para relaciones de mentoría personalizadas y efectivas.

## Stack

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.4 + Laravel 13 |
| Frontend | Next.js 15 + React 19 + Tailwind CSS 4 |
| Matching Service | Python 3.13 + FastAPI |
| Base de datos | PostgreSQL 16 |
| Cache / Cola | Redis 7 |
| Búsqueda | Meilisearch |
| WebSockets | Laravel Reverb |
| Blockchain | Polygon + IPFS (Pinata) |
| Infraestructura | Docker Compose |

## Características Principales

- **4 tipos de cliente**: personal, familiar, grupal, empresa — cada uno con dashboard dedicado
- **6 roles de usuario**: super_admin, admin, company_admin, employee, mentor, mentee
- **Test de personalidad**: IPIP-NEO-120 Big Five (OCEAN) con scoring via FastAPI
- **Matching inteligente**: algoritmos ponderados por tipo de cliente
- **Certificados**: emisión en IPFS + verificación on-chain en Polygon
- **i18n**: 3 idiomas (es, en, pt) con routing por subdirectorio
- **Auth**: Sanctum + OAuth (Google, LinkedIn, GitHub)
- **Tiempo real**: WebSockets para mensajes y notificaciones
- **Búsqueda full-text**: Meilisearch indexado por Laravel Scout

## Requisitos Previos

- Docker y Docker Compose
- Git

## Instalación Rápida

```bash
git clone <url-del-repositorio> mis-project
cd mis-project
bash setup.sh
```

El script `setup.sh` crea `.env` y los tres archivos de servicio solo si no existen, genera secretos locales únicamente para placeholders y conserva los valores existentes. Después construye, inicia y espera la readiness de los servicios antes de instalar dependencias. El contenedor `backend` ejecuta las migraciones y los seeders automáticamente antes de iniciar PHP-FPM, y repite ese bootstrap al volver a levantarse; los seeders son idempotentes y no eliminan usuarios. `APP_KEY` se prepara desde el entorno de Compose; no se ejecuta `key:generate` sobre `backend/.env`.

Para preparar únicamente la configuración local, sin iniciar Docker:

```bash
bash setup.sh --env-only
```

## Instalación Manual

```bash
# 1. Crear/validar entornos y construir contenedores
make env
make build
make up

# 2. Esperar las dependencias que tienen healthcheck
bash wait-for-services.sh postgres redis meilisearch matching frontend

# 3. Instalar dependencias
make composer-install
make npm-install

# 4. Configurar Laravel
make storage
bash wait-for-services.sh backend

# 5. Verificar
curl http://localhost/up
curl http://localhost:8000/api/v1/health
```

## Acceso

| Servicio | URL |
|----------|-----|
| Frontend | http://localhost:3000 |
| Laravel health | http://localhost/up |
| Backend API | http://localhost/api/v1 (Nginx) |
| Matching health | http://localhost:8000/api/v1/health |
| Mailpit (emails) | http://localhost:8025 |
| Meilisearch | http://localhost:7700 |

### Usuario Admin por Defecto

- Email: `admin@mis.com`
- Contraseña: `Password123!` (solo desarrollo; cámbiala inmediatamente)

## Comandos Disponibles

### Infraestructura

| Comando | Descripción |
|---------|-------------|
| `make build` | Construir imágenes Docker |
| `make up` | Iniciar todos los servicios y ejecutar el bootstrap de base de datos |
| `make down` | Detener todos los servicios |
| `make restart` | Reiniciar todos los servicios |
| `make logs` | Ver logs de todos los servicios |
| `make env` | Crear/validar archivos `.env` locales sin sobrescribirlos |
| `make wait` | Esperar healthchecks/readiness de la pila |
| `make production-config` | Validar Compose de producción con `.env.production` |

### Backend (Laravel)

| Comando | Descripción |
|---------|-------------|
| `make shell` | Abrir Tinker |
| `make migrate` | Ejecutar migraciones |
| `make seed` | Sembrar datos |
| `make fresh` | Migrar desde cero + seed |
| `make storage` | Crear enlace de storage |
| `make queue` | Iniciar worker de colas |
| `make reverb` | Iniciar servidor WebSocket |
| `make cache` | Optimizar caché de Laravel |
| `make meili` | Indexar usuarios en Meilisearch |
| `make test-backend` | Ejecutar tests (Pest) sobre `mis_project_testing` |
| `make composer-install` | Instalar dependencias PHP |
| `make backend` | Shell del contenedor backend |

### Frontend (Next.js)

| Comando | Descripción |
|---------|-------------|
| `make npm-install` | Instalar dependencias Node |
| `make test-frontend` | Ejecutar tests unitarios (Vitest) |
| `make frontend` | Shell del contenedor frontend |

### Matching Service (FastAPI)

| Comando | Descripción |
|---------|-------------|
| `make matching` | Shell del contenedor matching |
| `make test-matching` | Ejecutar tests del servicio matching |

## Estructura del Proyecto

```
mis-project/
├── backend/                    # Laravel 13 API
│   ├── app/
│   │   ├── Actions/            # Lógica de negocio (6 clases)
│   │   ├── Console/Commands/   # Tareas programadas
│   │   ├── Enums/              # 7 enums (ClientType, UserRole, etc.)
│   │   ├── Events/             # Eventos broadcast
│   │   ├── Http/
│   │   │   ├── Controllers/Api/ # 21 controladores REST
│   │   │   ├── Requests/       # Form Request validation
│   │   │   └── Resources/      # 17 API Resource transformers
│   │   ├── Models/             # 24 modelos Eloquent (UUID)
│   │   └── Services/           # GoogleMeet, IPFS, Blockchain
│   ├── config/
│   ├── database/
│   │   ├── factories/          # 5 factories
│   │   ├── migrations/         # 20 migraciones
│   │   └── seeders/            # Roles, permisos, planes, skills
│   ├── routes/api.php          # /api/v1/*
│   └── tests/                  # Pest (Feature + Unit)
│
├── frontend/                   # Next.js 15 SPA
│   └── src/
│       ├── app/                # App Router por locale
│       │   └── [locale]/
│       │       ├── page.tsx
│       │       ├── login/
│       │       ├── register/[type]/
│       │       ├── dashboard/{personal,familiar,grupal,empresa}/
│       │       ├── onboarding/test-personalidad/
│       │       ├── mentors/
│       │       ├── sessions/
│       │       ├── messages/
│       │       ├── certificates/
│       │       └── admin/
│       ├── components/ui/      # 12 componentes reutilizables
│       ├── hooks/              # Custom hooks (auth, relationships, sessions)
│       ├── i18n/messages/      # {es,en,pt}/*.json
│       ├── lib/                # API client, schemas Zod, utilidades
│       └── middleware.ts       # Locale routing
│
├── matching-service/           # FastAPI microservice
│   └── app/
│       ├── routes/             # health, personality, matching
│       └── services/           # BigFive scorer, Matcher
│
├── blockchain/contracts/       # CertificateRegistry.sol (Solidity ^0.8.20)
│
├── docker/                     # Dockerfiles, Nginx y PostgreSQL
│   ├── php/Dockerfile
│   ├── php/Dockerfile.production
│   ├── node/Dockerfile
│   ├── python/Dockerfile
│   └── python/Dockerfile.production
│
├── docker-compose.yml          # Stack de desarrollo
├── docker-compose.production.yml # Stack de artefactos sin bind mounts
├── Makefile                    # Targets operativos
├── setup.sh                    # Instalación automática
├── wait-for-services.sh        # Readiness basada en healthchecks
└── AGENTS.md                   # Guía para agentes de código
```

## Servicios Docker

| Servicio | Puerto | Descripción |
|----------|--------|-------------|
| nginx | 80 (desarrollo) | Proxy reverso y frontend |
| backend | 9000 (interno) | API Laravel |
| reverb | 8080 (interno) | WebSocket server |
| queue-worker | — | Worker de colas Redis |
| scheduler | — | Tareas programadas |
| frontend | 3000 | Next.js dev server |
| matching | 8000 (desarrollo) | FastAPI matching service |
| postgres | 5432 (desarrollo) | PostgreSQL 16 |
| redis | 6379 (desarrollo) | Redis 7 |
| meilisearch | 7700 (desarrollo) | Motor de búsqueda |
| mailpit | 1025, 8025 (desarrollo) | Servidor SMTP de prueba |

## Variables de Entorno

Las variables de entorno se definen en archivos `.env.*` para cada servicio. `.env.example` contiene únicamente la plantilla de Compose; los archivos sin sufijo `.example` están ignorados por Git.

- `.env.backend` — Configuración de Laravel (DB, Redis, OAuth, IPFS, Blockchain, etc.)
- `.env.frontend` — URLs del API y dominio público
- `.env.matching` — API key y modo debug del matching service

`setup.sh` genera los valores locales marcados como `__GENERATE_LOCAL_SECRET__` y nunca reemplaza un valor existente. Para producción se deben crear archivos separados, no versionados, usando `.env.production.example` como guía.

### Servicios Externos (requieren configuración)

| Servicio | Variables | Uso |
|----------|-----------|-----|
| Google OAuth | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Login + Google Meet |
| LinkedIn OAuth | `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET` | Login |
| GitHub OAuth | `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` | Login |
| Pinata (IPFS) | `PINATA_API_KEY`, `PINATA_SECRET_API_KEY` | Almacenamiento de certificados |
| Polygon | `POLYGON_PRIVATE_KEY`, `POLYGON_RPC_URL`, `POLYGON_CONTRACT_ADDRESS` | Certificados on-chain |
| Stripe | `STRIPE_KEY`, `STRIPE_SECRET` | Pagos |
| MercadoPago | `MERCADOPAGO_ACCESS_TOKEN` | Pagos |

## API REST

Todos los endpoints están bajo `/api/v1/`.

### Públicos

- `POST /auth/register` — Registro
- `POST /auth/login` — Login
- `POST /auth/refresh` — Renovar token
- `GET /auth/{provider}/redirect` — OAuth redirect
- `GET /auth/{provider}/callback` — OAuth callback
- `GET /mentors` — Listar mentores
- `GET /plans` — Listar planes

### Autenticados (requieren token)

- **Perfil**: `GET|PUT /profile`, `PUT /profile/avatar`
- **Personalidad**: `POST /personality/start-test`, `POST /submit-answers`, `POST /calculate`
- **Relaciones**: CRUD `/relationships`, `PUT /{id}/status`
- **Sesiones**: CRUD `/sessions`, `POST /{id}/meet-link`
- **Mensajes**: `GET|POST /messages`, `PUT /{id}/read`
- **Certificados**: `GET /certificates`, `POST /certificates/issue`, `GET /{id}/verify`
- **Matching**: `POST /matching/suggestions`, `POST /matching/calculate` (el servicio devuelve `501` hasta disponer de datos de perfiles)

### Admin (requiere rol super_admin o admin)

- CRUD: Users, Mentors, Sessions, Assessments, Plans
- `GET /admin/reports`

## Matching Service API

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/v1/health` | GET | Health check |
| `/api/v1/personality/score` | POST | Scoring IPIP-NEO |
| `/api/v1/personality/batch-score` | POST | Scoring en lote |
| `/api/v1/personality/compare/{id1}/{id2}` | GET | Comparar perfiles |
| `/api/v1/matching/calculate` | POST | Reservado; devuelve `501` sin datos de Laravel |
| `/api/v1/matching/suggestions` | POST | Reservado; devuelve `501` sin datos de Laravel |
| `/api/v1/matching/analyze-profile` | POST | Analizar perfil |

### Pesos de Matching por Tipo de Cliente

| Dimensión | personal | familiar | grupal | empresa |
|-----------|----------|----------|--------|---------|
| Personalidad | 0.30 | 0.20 | 0.15 | 0.15 |
| Habilidades | 0.25 | 0.20 | 0.20 | 0.30 |
| Intereses | 0.20 | 0.25 | 0.15 | 0.15 |
| Disponibilidad | 0.15 | 0.20 | 0.30 | 0.20 |
| Historial | 0.10 | 0.15 | 0.20 | 0.20 |

El servicio matching exige `X-Internal-API-Key` fuera de `/api/v1/health`, limita el tamaño de las peticiones y aplica un rate limit por IP. Se ejecuta con un único worker porque el rate limiter es deliberadamente acotado al proceso; `MATCHING_WORKERS` no debe superar `1`.

## Producción

El archivo `docker-compose.production.yml` no construye ni monta código fuente. Requiere imágenes de release referenciadas por digest en `.env.production` y archivos de entorno de producción no versionados:

```bash
cp .env.production.example .env.production
cp .env.backend.example .env.backend.production
cp .env.frontend.example .env.frontend.production
cp .env.matching.example .env.matching.production
# Completar los digests, secretos y rutas de env de producción fuera de Git.
docker compose --env-file .env.production -f docker-compose.production.yml config --quiet
docker compose --env-file .env.production -f docker-compose.production.yml up -d --wait
```

Las imágenes de aplicación se pueden construir desde la raíz con `docker/php/Dockerfile.production`, `docker/python/Dockerfile.production` y `docker/nginx/Dockerfile.production`; la imagen Node debe construirse con `--build-arg NEXT_BUILD=true` y `docker/node/Dockerfile` usando `frontend` como contexto. El pipeline debe publicar los artefactos y sustituir los digests del ejemplo.

La terminación TLS debe realizarse en un ingress o proxy externo que reenvíe `Host`, `X-Forwarded-For` y `X-Forwarded-Proto`. Nginx escucha internamente en `8080` en producción y no publica puertos directamente. Activa HSTS únicamente cuando todo el dominio esté servido exclusivamente por HTTPS. `APP_DEBUG=false`, los headers de seguridad y la denegación de archivos ocultos ya están incluidos en la configuración de producción.

## Operación

Compose limita los logs `json-file` a 10 MiB por archivo y conserva cinco archivos en desarrollo, diez en producción. Para inspeccionar eventos recientes:

```bash
docker compose logs --since=1h
docker compose ps
```

Realiza backups de PostgreSQL en almacenamiento cifrado y fuera del volumen Docker. Ejemplo de backup y restauración usando el entorno de producción:

```bash
mkdir -p backups
docker compose --env-file .env.production -f docker-compose.production.yml exec -T postgres \
  sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB"' > backups/mis-$(date +%Y%m%d-%H%M%S).sql

cat backups/mis.sql | docker compose --env-file .env.production -f docker-compose.production.yml exec -T postgres \
  sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
```

Prueba periódicamente la restauración, conserva las claves de aplicación y de base de datos en un gestor de secretos, y no incluyas los dumps en Git.

## Blockchain — CertificateRegistry

Smart contract en Solidity para emisión y verificación de certificados on-chain en Polygon.

```solidity
// Funciones principales
issue(bytes32 hash)     // Emitir certificado (solo issuers autorizados)
verify(bytes32 hash)    // Verificar validez
revoke(bytes32 hash)    // Revocar certificado
```

## Testing

```bash
# Backend — Pest (PHPUnit)
make test-backend

# Frontend — Vitest
make test-frontend

# Matching — pytest
make test-matching
```

## Tareas Programadas

| Frecuencia | Comando |
|------------|---------|
| Cada minuto | `app:send-session-reminders` |
| Diaria | `app:cleanup-expired-subscriptions` |
| Diaria | `scout:flush` + `scout:import` (User) |
| Horaria | `app:sync-pending-match-scores` |

## Conveniones

- **UUID**: Todos los modelos usan `HasUuids` como PK
- **Polimorfismo**: `MentorshipRelationship.source` morfa a User, FamilyGroup, Cohort o Company
- **Contenido multi-idioma**: columnas JSONB `{"es": "...", "en": "...", "pt": "..."}`
- **API**: todos los endpoints bajo `/api/v1/`
- **Validación**: Form Request classes (nunca `$request->validate()` inline)
- **Respuestas**: API Resource transformers para JSON consistente
- **Lógica de negocio**: Action classes (controllers delgados)
- **Forms**: react-hook-form + Zod (nunca useState manual)
- **Data fetching**: @tanstack/react-query
- **UI**: Componentes reutilizables en `src/components/ui/`

## Licencia

Por definir.
