# SterPlatform — Roadmap & Plan technique

> Version : 0.7 — Phase 3 terminée, Mercure Real-time opérationnel
> Auteur : Alan
> Date : Mai 2026
> Statut : **Phase 3 terminée — Phase 3b (Refacto + N+1) à démarrer**

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
| Framework | Symfony 8.0 (PHP 8.4) | Mature, performant, écosystème riche |
| API | API Platform 4 | Génère REST + OpenAPI depuis les entités Doctrine — équivalent Supabase auto-API |
| ORM | Doctrine ORM 3 | Migrations, repositories, relations — équivalent Hibernate |
| Auth | LexikJWTAuthenticationBundle 3 | JWT access + refresh tokens, standard industrie |
| Temps réel | Mercure (hub) | SSE pub/sub, inventé par l'équipe Symfony, facile à déployer |
| Email | Symfony Mailer + Twig | Templates HTML, SMTP / Mailpit en dev |
| Admin | EasyAdmin 4 | Dashboard rapide à générer depuis les entités |
| Tests | PHPUnit + ApiTestCase | Tests unitaires + intégration API |
| Base de données | PostgreSQL 18 | Même DB que Supabase — migration facilitée |
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
│  │         Symfony 8 Core          │                │
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

### Phase 0 — Socle technique ✅ *(terminée)*

**Objectif :** Symfony opérationnel avec Docker, API Platform et PostgreSQL.

- [x] Scaffold Symfony 8 via Docker (sans PHP local) — `composer create-project symfony/skeleton`
- [x] Docker Compose : PHP 8.4-fpm, PostgreSQL 16, Nginx 1.27, Mercure, Mailpit
- [x] API Platform 4 installé et configuré
- [x] Doctrine ORM + LexikJWT + Security + Mailer installés
- [x] JWT keypair générée (`config/jwt/private.pem` + `public.pem`)
- [x] `git init`, remote GitHub `naviss29/SterPlatform`, branches `main` + `develop`
- [ ] CI GitHub Actions (tests + lint sur push) *(reporté Phase 2)*
- [x] Coolify — app créée, déploiement depuis `main`, domaine `sterplatform.bichetapps.com` ✅
- [x] `.env.example` documenté ✅

**Livrable :** `GET /api` renvoie `{"@context":"/api/contexts/Entrypoint","@id":"/api","@type":"Entrypoint"}` ✅

---

### Phase 1 — Auth générique ✅ *(terminée)*

**Objectif :** Système d'authentification complet, réutilisable par tous les projets.

- [x] Entity `User` (UUID, email, password bcrypt, roles, isVerified, verificationToken, resetToken, createdAt) + migration
- [x] `POST /api/auth/register` — inscription + envoi email de confirmation (réponse générique anti-énumération)
- [x] `GET /api/auth/verify?token=` — activation du compte via token 24h
- [x] `POST /api/auth/login` → JWT access token (firewall json_login + LexikJWT)
- [x] `POST /api/auth/forgot-password` — envoi email reset (réponse générique)
- [x] `POST /api/auth/reset-password` — nouveau mot de passe via token 1h
- [x] `GET /api/auth/me` — profil utilisateur connecté (JWT requis)
- [x] Templates email Twig — `verification.html.twig`, `reset_password.html.twig`
- [x] 17 tests PHPUnit passants (register, login, verify, forgot-password, reset-password, me)
- [x] `POST /api/auth/refresh` — renouvellement du JWT *(fait en Phase 1b)*
- [ ] Rate limiting sur les endpoints sensibles *(reporté Phase 4)*

**Livrable :** `POST /api/auth/register` + `POST /api/auth/login` → JWT fonctionnels. 17/17 tests passants ✅

---

### Phase 1b — JWT Refresh Token ✅ *(terminée)*

**Objectif :** Compléter l'auth avec le renouvellement automatique du token.

- [x] Installer `gesdinet/jwt-refresh-token-bundle` v2.0.0
- [x] Entity `RefreshToken` + migration table `refresh_tokens`
- [x] `POST /api/auth/refresh` — échange refresh token → nouvel access token
- [x] `POST /api/auth/logout` — révocation du refresh token (JWT requis)
- [x] Tests PHPUnit : login retourne refresh_token, refresh valide/invalide/expiré, logout + re-refresh refusé — 23/23 tests passants ✅
- [x] CI GitHub Actions — workflow `tests.yml` sur push `develop` et `main` ✅

**Livrable :** Cycle complet login → refresh → logout fonctionnel. CI verte sur GitHub. ✅

---

### Phase 2 — Multi-tenancy ✅ *(terminée)*

**Objectif :** Isolation des données par organisation/projet.

- [x] Entity `Organization` + `OrganizationMember` + migrations
- [x] `POST /api/organizations` — création organisation (créateur devient OWNER)
- [x] `GET /api/organizations` — liste des organisations de l'utilisateur
- [x] `GET /api/organizations/{slug}` — détail organisation (membres seulement)
- [x] `POST /api/organizations/{slug}/members` — invitation membre (OWNER/ADMIN)
- [x] `GET /api/organizations/{slug}/members` — liste des membres
- [x] `TenantFilter` Doctrine — activé via header `X-Organization-Slug`, filtre auto par `organization_id`
- [x] `TenantContext` + `TenantSubscriber` — résolution de l'org courante au `kernel.request`
- [x] `OrganizationVoter` — droits OWNER, ADMIN, MEMBER (`ORGANIZATION_VIEW`, `ORGANIZATION_MANAGE_MEMBERS`, `ORGANIZATION_OWNER`)
- [x] Tests d'isolation : un user ne voit pas les données d'une autre org — 34/34 tests passants ✅

**Livrable :** Deux organisations créées en test — leurs données sont parfaitement isolées. ✅

---

### Phase 3 — Mercure Real-time ✅ *(terminée)*

**Objectif :** Remplacer Supabase Realtime par un hub Mercure intégré.

- [x] `symfony/mercure-bundle` v0.4.2 installé + `config/packages/mercure.yaml` configuré
- [x] Mercure hub dans Docker Compose (service `mercure`, port `9090`) ✅
- [x] `MERCURE_HUB_INTERNAL_URL` + `MERCURE_HUB_PUBLIC_URL` dans `.env` ✅
- [x] `MercurePublisher` service — publie sur `orgs/{slug}` et `orgs/{slug}/{entityType}` ✅
- [x] `DoctrinePublishSubscriber` — publication automatique sur `postPersist/postUpdate/postRemove` pour toute entité avec `getOrganization()` ✅
- [x] `GET /api/mercure/token` — retourne un JWT Mercure subscriber scopé aux orgs de l'utilisateur ✅
- [x] Tests : 6 nouveaux tests (token 401, token JWT valide, topics corrects, user sans org) — 40/40 passants ✅
- [x] `MERCURE_JWT_SECRET` corrigé (min 256 bits) dans `.env`, `.env.test`, `docker-compose.yml` ✅

**Livrable :** Un changement en base sur une entité liée à une org déclenche une mise à jour temps réel. Le client obtient un JWT Mercure via `GET /api/mercure/token` et s'abonne via `EventSource`. ✅

---

### Phase 3b — Refacto & Élimination N+1 *(1 session)*

**Objectif :** Assainir les requêtes, éliminer les problèmes N+1, et améliorer la lisibilité du code.

- [ ] Audit des requêtes N+1 dans `OrganizationController`
  - `list()` — charge chaque membership séparément (N+1 sur `findMembership`)
  - `listMembers()` — lazy load des users de chaque member
- [ ] `OrganizationRepository::findByUserWithRole()` — JOIN FETCH membership + org en une requête
- [ ] `OrganizationMemberRepository::findMembershipsForUser()` — batch fetch pour `list()`
- [ ] Revue de `TenantSubscriber` — vérifier si `hasRole()` génère des requêtes excessives
- [ ] Ajouter des index manquants sur `organization_members` (user_id, organization_id déjà indexés par FK ?)
- [ ] Profiler avec Symfony Profiler / `EXPLAIN ANALYZE` en dev
- [ ] Nettoyer les imports inutilisés, uniformiser les styles de code

**Livrable :** Aucune requête N+1 détectée via profiler. `OrganizationController::list()` résolu en ≤ 2 requêtes SQL.

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
| Phase 0 — Socle | ✅ 2 sessions | ~~🔴 Critique~~ |
| Phase 1 — Auth | ✅ 1 session | ~~🔴 Critique~~ |
| Phase 1b — JWT Refresh | ✅ 1 session | ~~🔴 Critique~~ |
| Phase 2 — Multi-tenancy | ✅ 1 session | ~~🟠 Haute~~ |
| Phase 3 — Mercure | ✅ 1 session | ~~🟠 Haute~~ |
| Phase 3b — Refacto + N+1 | 1 session | 🟠 Haute |
| Phase 4 — Admin | 1-2 sessions | 🟡 Moyenne |
| Phase 5 — Migration DartsOpen | 4-5 sessions | 🟡 Moyenne (après Phase 3b) |
| Phase 6 — Multi-projets | 1-2 sessions | 🟢 Basse |

**Total estimé : 15-22 sessions**

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

## 9. Prochaine session — Phase 3b

Actions concrètes pour démarrer :

1. Profiler `OrganizationController::list()` avec le Symfony Profiler — identifier les requêtes N+1
2. Créer `OrganizationRepository::findByUserWithRole()` en JOIN FETCH pour éliminer le N+1 sur `findMembership`
3. Vérifier les index FK sur `organization_members` (`user_id`, `organization_id`)
4. Profiler `TenantSubscriber::onKernelRequest()` — valider que `hasRole()` ne génère pas de sur-requêtes
5. Nettoyer les imports inutilisés dans tous les fichiers `src/`
6. Lancer `EXPLAIN ANALYZE` sur les requêtes critiques via Doctrine profiler
