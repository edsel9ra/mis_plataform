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

El script `setup.sh` ejecuta automáticamente: build, subir servicios, instalar dependencias, generar APP_KEY, migrar y sembrar la base de datos.

## Instalación Manual

```bash
# 1. Construir y levantar contenedores
make build && make up

# 2. Instalar dependencias
make composer-install
make npm-install

# 3. Configurar Laravel
make key
make storage

# 4. Base de datos
make migrate
make seed

# 5. Verificar
curl http://localhost:8000/api/v1/health
```

## Acceso

| Servicio | URL |
|----------|-----|
| Frontend | http://localhost:3000 |
| Backend API | http://localhost:8000/api/v1 |
| Mailpit (emails) | http://localhost:8025 |
| Meilisearch | http://localhost:7700 |

### Usuario Admin por Defecto

- Email: `admin@mis.com`
- Contraseña: `password`

## Comandos Disponibles

### Infraestructura

| Comando | Descripción |
|---------|-------------|
| `make build` | Construir imágenes Docker |
| `make up` | Iniciar todos los servicios |
| `make down` | Detener todos los servicios |
| `make restart` | Reiniciar todos los servicios |
| `make logs` | Ver logs de todos los servicios |

### Backend (Laravel)

| Comando | Descripción |
|---------|-------------|
| `make shell` | Abrir Tinker |
| `make migrate` | Ejecutar migraciones |
| `make seed` | Sembrar datos |
| `make fresh` | Migrar desde cero + seed |
| `make key` | Generar APP_KEY |
| `make storage` | Crear enlace de storage |
| `make queue` | Iniciar worker de colas |
| `make reverb` | Iniciar servidor WebSocket |
| `make cache` | Optimizar caché de Laravel |
| `make meili` | Indexar usuarios en Meilisearch |
| `make test-backend` | Ejecutar tests (Pest) |
| `make composer-install` | Instalar dependencias PHP |
| `make backend` | Shell del contenedor backend |

### Frontend (Next.js)

| Comando | Descripción |
|---------|-------------|
| `make npm-install` | Instalar dependencias Node |
| `make test-frontend` | Ejecutar tests E2E (Playwright) |
| `make frontend` | Shell del contenedor frontend |

### Matching Service (FastAPI)

| Comando | Descripción |
|---------|-------------|
| `make matching` | Shell del contenedor matching |

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
├── docker/                     # Dockerfiles (PHP, Node, Python)
│   ├── php/Dockerfile
│   ├── node/Dockerfile
│   └── python/Dockerfile
│
├── docker-compose.yml          # 11 servicios
├── Makefile                    # 20+ targets
├── setup.sh                    # Instalación automática
└── AGENTS.md                   # Guía para agentes de código
```

## Servicios Docker

| Servicio | Puerto | Descripción |
|----------|--------|-------------|
| nginx | 80, 443 | Proxy reverso y frontend |
| backend | 9000 (interno) | API Laravel |
| reverb | 8080 (interno) | WebSocket server |
| queue-worker | — | Worker de colas Redis |
| scheduler | — | Tareas programadas |
| frontend | 3000 | Next.js dev server |
| matching | 8000 | FastAPI matching service |
| postgres | 5432 | PostgreSQL 16 |
| redis | 6379 | Redis 7 |
| meilisearch | 7700 | Motor de búsqueda |
| mailpit | 1025, 8025 | Servidor SMTP de prueba |

## Variables de Entorno

Las variables de entorno se definen en archivos `.env.*` para cada servicio:

- `.env.backend` — Configuración de Laravel (DB, Redis, OAuth, IPFS, Blockchain, etc.)
- `.env.frontend` — URLs del API y dominio público
- `.env.matching` — API key y modo debug del matching service

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
- **Matching**: `POST /matching/suggestions`, `POST /matching/calculate`

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
| `/api/v1/matching/calculate` | POST | Calcular match |
| `/api/v1/matching/suggestions` | POST | Sugerir mentores |
| `/api/v1/matching/analyze-profile` | POST | Analizar perfil |

### Pesos de Matching por Tipo de Cliente

| Dimensión | personal | familiar | grupal | empresa |
|-----------|----------|----------|--------|---------|
| Personalidad | 0.30 | 0.20 | 0.15 | 0.15 |
| Habilidades | 0.25 | 0.20 | 0.20 | 0.30 |
| Intereses | 0.20 | 0.25 | 0.15 | 0.15 |
| Disponibilidad | 0.15 | 0.20 | 0.30 | 0.20 |
| Historial | 0.10 | 0.15 | 0.20 | 0.20 |

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

# Frontend — Playwright E2E
make test-frontend
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
