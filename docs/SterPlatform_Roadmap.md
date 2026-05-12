# SterPlatform — Roadmap & Plan technique

> Version : 0.1 — Document de planification initial
> Auteur : Alan
> Date : Mai 2026
> Statut : **Planification**

---

## 1. Contexte & Motivation

### Problème

Supabase coûte **25 €/mois par projet** sur le plan Pro. Pour des applications quasi-gratuites ou en phase de démarrage (DartsOpen, FestManager, futurs projets), ce coût n'est pas justifiable.

Supabase Free présente des limitations bloquantes :
- 500 MB base de données
- Pause automatique après 1 semaine d'inactivité
- 2 projets maximum
- Realtime limité

### Solution

**SterPlatform** — un backend Symfony auto-hébergé, générique et multi-projets, qui remplace les fonctionnalités Supabase utilisées :

| Supabase | SterPlatform |
|---|---|
| PostgreSQL | PostgreSQL + Doctrine ORM |
| Auth JWT + email | LexikJWT + Symfony Security + Mailer |
| API REST auto-générée | API Platform 3 (OpenAPI) |
| Row Level Security (RLS) | Symfony Voters + Doctrine Filters |
| Realtime (WebSockets) | Mercure (SSE pub/sub) |
| Email templates | Twig + Symfony Mailer |
| Dashboard admin | EasyAdmin 4 |

### Avantages

- **Coût** : VPS Hetzner CX23 déjà payé (~5 €/mois) — zéro coût additionnel
- **Réutilisable** : un seul backend sert DartsOpen, FestManager, et les projets futurs
- **Contrôle total** : pas de vendor lock-in, données sur notre infrastructure
- **Familier** : PHP/Symfony proche de Java/Spring Boot (même paradigme : DI, ORM, annotations)

---

## 2. Stack technique

| Couche | Technologie | Justification |
|---|---|---|
| Framework | Symfony 7.2 (PHP 8.3) | Mature, performant, écosystème riche |
| API | API Platform 3 | Génère REST + OpenAPI depuis les entités Doctrine — équivalent Supabase auto-API |
| ORM | Doctrine ORM 3 | Migrations, repositories, relations — équivalent Hibernate |
| Auth | LexikJWTAuthenticationBundle | JWT access + refresh tokens, standard industrie |
| Temps réel | Mercure (hub) | SSE pub/sub, inventé par l'équipe Symfony, facile à déployer |
| Email | Symfony Mailer + Twig | Templates HTML, SMTP/SendGrid/Mailgun |
| Admin | EasyAdmin 4 | Dashboard rapide à générer depuis les entités |
| Tests | PHPUnit + ApiTestCase | Tests unitaires + intégration API |
| Base de données | PostgreSQL 15 | Même DB que Supabase — migration facilitée |
| Containerisation | Docker + Docker Compose | Déploiement Coolify identique aux autres projets |
| Déploiement | Coolify v4 (Nixpacks ou Dockerfile) | Infrastructure partagée Hetzner CX23 |

---

## 3. Architecture cible

```
┌─────────────────────────────────────────────────────┐
│                   SterPlatform                       │
│                                                     │
│  ┌──────────────┐  ┌──────────────┐                │
│  │  API Platform │  │   Mercure    │                │
│  │  REST/OpenAPI │  │   Hub SSE    │                │
│  └──────┬───────┘  └──────┬───────┘                │
│         │                 │                         │
│  ┌──────▼─────────────────▼───────┐                │
│  │         Symfony 7 Core          │                │
│  │  Security │ Mailer │ Messenger  │                │
│  └──────────────┬─────────────────┘                │
│                 │                                   │
│  ┌──────────────▼─────────────────┐                │
│  │    Doctrine ORM + PostgreSQL    │                │
│  └─────────────────────────────────┘               │
└─────────────────────────────────────────────────────┘
          ↕ JSON/JWT          ↕ SSE/Mercure
┌──────────────────┐   ┌────────────────────┐
│   DartsOpen      │   │   FestManager       │
│   Next.js 16     │   │   Angular 18        │
└──────────────────┘   └────────────────────┘
```

### Multi-tenancy

Chaque projet est un **tenant** isolé :
- Table `organizations` (un enregistrement par projet)
- `Doctrine Extension` qui filtre automatiquement les données par `organization_id`
- Symfony Voters pour les droits fins (owner, member, admin)

---

## 4. Modèle de données générique

```
Organization (tenant)
├── id (uuid)
├── name
├── slug (unique — identifiant URL)
├── created_at
└── users: OrganizationMember[]

User
├── id (uuid)
├── email (unique)
├── password (hashed)
├── roles: string[]
├── is_verified (boolean)
├── reset_token / reset_token_expires_at
├── created_at
└── organizations: OrganizationMember[]

OrganizationMember
├── user_id → User
├── organization_id → Organization
├── role (OWNER | ADMIN | MEMBER)
└── joined_at
```

Chaque projet (DartsOpen, FestManager…) étend ce socle avec ses propres entités liées à `Organization`.

---

## 5. Roadmap

### Phase 0 — Socle technique *(2-3 sessions)*

**Objectif :** Symfony opérationnel avec Docker, API Platform et PostgreSQL.

- [ ] `symfony new SterPlatform --webapp`
- [ ] Docker Compose : PHP 8.3-fpm, PostgreSQL 15, Nginx, Mercure
- [ ] API Platform 3 installé et configuré
- [ ] Doctrine ORM + première migration
- [ ] `git init`, remote GitHub, branches `main` + `develop`
- [ ] CI GitHub Actions (tests + lint sur push)
- [ ] Coolify — app créée, déploiement depuis `main`
- [ ] `.env.example` documenté

**Livrable :** `GET /api` renvoie le JSON-LD de l'API Platform. `GET /api/docs` ouvre le Swagger UI.

---

### Phase 1 — Auth générique *(2-3 sessions)*

**Objectif :** Système d'authentification complet, réutilisable par tous les projets.

- [ ] Entity `User` + migration
- [ ] `POST /api/auth/register` — inscription + envoi email de confirmation
- [ ] `GET /auth/confirm?token=` — activation du compte
- [ ] `POST /api/auth/login` → JWT access token (15 min) + refresh token (7 jours)
- [ ] `POST /api/auth/refresh` — renouvellement du JWT
- [ ] `POST /api/auth/forgot-password` — envoi email reset
- [ ] `POST /api/auth/reset-password` — nouveau mot de passe via token
- [ ] Rate limiting sur les endpoints sensibles (Symfony RateLimiter)
- [ ] Templates email Twig (confirmation, reset) — style identique DartsOpen
- [ ] Tests PHPUnit : register, login, refresh, reset

**Livrable :** Flux auth complet testable via Swagger UI ou Postman.

---

### Phase 2 — Multi-tenancy *(2 sessions)*

**Objectif :** Isolation des données par organisation/projet.

- [ ] Entity `Organization` + `OrganizationMember` + migrations
- [ ] `POST /api/organizations` — création organisation
- [ ] `POST /api/organizations/{slug}/members` — invitation membre
- [ ] Doctrine Extension `TenantFilter` — filtre auto par `organization_id`
- [ ] Symfony Voters : `OrganizationVoter` (OWNER, ADMIN, MEMBER)
- [ ] JWT payload enrichi avec `organization_id` courant
- [ ] Tests d'isolation : un user ne voit pas les données d'une autre org

**Livrable :** Deux organisations créées en test — leurs données sont parfaitement isolées.

---

### Phase 3 — Mercure Real-time *(2 sessions)*

**Objectif :** Remplacer Supabase Realtime par un hub Mercure intégré.

- [ ] Mercure hub dans Docker Compose
- [ ] `MercurePublisher` service générique (publie sur un topic)
- [ ] Docrtine EventSubscriber : publication automatique à chaque `POST/PUT/DELETE`
- [ ] JWT Mercure côté client (token endpoint)
- [ ] Exemple côté Next.js : `EventSource` abonné à un topic
- [ ] Exemple côté Angular : idem avec `EventSourcePolyfill`
- [ ] Tests : publication → réception en temps réel

**Livrable :** Un changement en base déclenche une mise à jour temps réel sur tous les clients abonnés.

---

### Phase 4 — Admin & Observabilité *(1-2 sessions)*

**Objectif :** Dashboard admin et monitoring.

- [ ] EasyAdmin 4 — CRUD User, Organization
- [ ] Health check endpoint `GET /health` (DB, Mercure)
- [ ] Logs structurés (Monolog → JSON)
- [ ] Métriques basiques (nb requêtes, erreurs 5xx)

**Livrable :** Dashboard admin accessible en prod sur `/admin`.

---

### Phase 5 — Migration DartsOpen *(4-5 sessions)*

**Objectif :** Migrer DartsOpen de Supabase vers SterPlatform.

#### 5a — Schéma & Entités
- [ ] Créer toutes les entités Doctrine miroir du schéma Supabase DartsOpen
  - `Tournament`, `Round`, `Registration`, `Pool`, `PoolPlayer`, `Match`, `MatchSet`
- [ ] Migrations Doctrine
- [ ] Scripts de migration des données existantes (Supabase → SterPlatform)

#### 5b — Auth frontend
- [ ] Remplacer `@supabase/ssr` par des appels fetch vers `/api/auth/*`
- [ ] `lib/api/auth.ts` — client HTTP avec gestion JWT + refresh automatique
- [ ] Adapter le middleware Next.js (proxy.ts) pour lire le JWT SterPlatform

#### 5c — API calls
- [ ] Remplacer les appels `supabase.from('table').select(...)` par `fetch('/api/resource')`
- [ ] Client HTTP générique avec intercepteur JWT
- [ ] Adapter toutes les Server Actions

#### 5d — Real-time
- [ ] Remplacer `supabase.channel(...).on(...)` par `EventSource` Mercure
- [ ] Adapter `BracketLive`, `MatchBoard`, `ScoreBoard`

#### 5e — Tests & recette
- [ ] Tests end-to-end du flux complet (inscription → tournoi → phases finales)
- [ ] Comparaison fonctionnelle avec la version Supabase

**Livrable :** DartsOpen tourne à 100% sur SterPlatform sans Supabase.

---

### Phase 6 — Production multi-projets *(1-2 sessions)*

**Objectif :** Documentation et onboarding pour intégrer un nouveau projet.

- [ ] Guide "Intégrer un nouveau projet dans SterPlatform"
- [ ] Variables d'env par projet (`.env.dartsopen`, `.env.festmanager`)
- [ ] Exemple d'intégration FestManager (Spring Boot → quelle cohabitation ?)
- [ ] Versioning de l'API (`/api/v1/`)
- [ ] Documentation OpenAPI publique

**Livrable :** Un nouveau projet peut être intégré en moins d'une journée.

---

## 6. Ordre de priorité & estimation

| Phase | Durée estimée | Priorité |
|---|---|---|
| Phase 0 — Socle | 2-3 sessions | 🔴 Critique |
| Phase 1 — Auth | 2-3 sessions | 🔴 Critique |
| Phase 2 — Multi-tenancy | 2 sessions | 🟠 Haute |
| Phase 3 — Mercure | 2 sessions | 🟠 Haute |
| Phase 4 — Admin | 1-2 sessions | 🟡 Moyenne |
| Phase 5 — Migration DartsOpen | 4-5 sessions | 🟡 Moyenne (après Phase 3) |
| Phase 6 — Multi-projets | 1-2 sessions | 🟢 Basse |

**Total estimé : 14-19 sessions**

---

## 7. Risques & décisions à prendre

| Risque | Mitigation |
|---|---|
| Mercure nécessite un serveur SSE dédié | Inclus dans Docker Compose — pas de coût additionnel |
| Migration DartsOpen : données existantes | Script de migration SQL + validation avant bascule |
| FestManager est Spring Boot (pas Symfony) | SterPlatform est une API REST — Spring Boot consomme la même API |
| PHP vs Java : performances | PHP 8.3 + OPcache + FPM est largement suffisant pour notre charge |
| Doctrine vs Supabase RLS | Les Voters Symfony sont plus expressifs — pas de régression sécurité |

---

## 8. Structure du projet (cible)

```
SterPlatform/
├── config/
│   ├── packages/          # Config API Platform, LexikJWT, Mercure…
│   └── routes/
├── src/
│   ├── Entity/            # User, Organization, OrganizationMember
│   ├── Repository/
│   ├── Security/          # Voters, JWT authenticator
│   ├── Controller/        # Auth endpoints (register, login, reset…)
│   ├── Service/           # MercurePublisher, MailerService, TokenService
│   ├── EventSubscriber/   # Doctrine listener → Mercure publish
│   └── Extension/         # TenantFilter (Doctrine)
├── templates/
│   └── emails/            # Twig email templates
├── migrations/
├── tests/
│   ├── Unit/
│   └── Api/               # ApiTestCase (API Platform)
├── docker/
│   ├── php/Dockerfile
│   └── nginx/nginx.conf
├── docker-compose.yml
├── docker-compose.prod.yml
├── docs/
│   └── SterPlatform_Roadmap.md
└── .env.example
```

---

## 9. Prochaine session — Phase 0

Actions concrètes pour démarrer :

1. `symfony new SterPlatform --webapp` dans `C:\Users\yveno\Documents\Devs\`
2. Installer API Platform : `composer require api`
3. Configurer Docker Compose (PHP, PostgreSQL, Nginx, Mercure)
4. `git init` → remote GitHub `naviss29/SterPlatform`
5. Créer les branches `main` + `develop`
6. Créer l'app dans Coolify (branche `main`)
