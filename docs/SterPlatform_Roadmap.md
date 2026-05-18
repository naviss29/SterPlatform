# SterPlatform — Roadmap & Plan technique

> Version : 2.1 — Phase 5 terminée
> Auteur : Alan
> Date : Mai 2026
> Statut : **Production en ligne — https://sterplatform.bichetapps.com — Phase 6 (Intégration DartsOpen email) à démarrer**

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

**SterPlatform** — un backend Symfony auto-hébergé, générique et multi-projets, qui remplace les fonctionnalités d'infrastructure de Supabase :

| Supabase | SterPlatform |
|---|---|
| Auth JWT + email vérification | LexikJWT + Symfony Security + Mailer |
| Reset password | Symfony Mailer + token Doctrine |
| Realtime (WebSockets/SSE) | Mercure (SSE pub/sub) |
| Email templates | Twig + EasyAdmin CRUD + Symfony Mailer |
| Dashboard admin | EasyAdmin 5 |

> **Ce que SterPlatform ne fait PAS :** il ne porte pas les données métier des applications (tournois, bénévoles, etc.). Chaque application gère son propre schéma via son propre ORM (Prisma pour DartsOpen, JPA pour FestManager). Voir [Architecture_Ecosysteme.md](../../docs/Architecture_Ecosysteme.md).

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
| Admin | EasyAdmin 5 | Dashboard `/admin` CRUD User + Organization, login session-based |
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

## 4. Modèle de données

### Entités partagées (socle générique)

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

EmailTemplate
├── id (uuid)
├── slug (unique — ex. "dartsopen_inscription_confirmation")
├── project (étiquette — ex. "dartsopen", "festmanager", "global")
├── subject (ligne objet, peut contenir des variables Twig)
├── html_body (template Twig complet)
├── description (liste des variables disponibles, à titre informatif)
└── created_at / updated_at
```

> **Règle :** SterPlatform ne contient que ces entités. Les entités métier (tournois, bénévoles, etc.) appartiennent aux applications concernées.

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

### Phase 3b — Refacto & Élimination N+1 ✅ *(terminée)*

**Objectif :** Assainir les requêtes, éliminer les problèmes N+1, et améliorer la lisibilité du code.

- [x] Audit N+1 : `list()` (N×`findMembership`) + `listMembers()` (N×lazy `getUser()`) + `TenantSubscriber` (2 requêtes séquentielles) ✅
- [x] `OrganizationMemberRepository::findByUserWithOrganization()` — JOIN FETCH org, remplace `findByUser + N×findMembership` dans `list()` ✅
- [x] `OrganizationMemberRepository::findByOrganizationWithUser()` — JOIN FETCH user, remplace `$org->getMembers() + N×getUser()` dans `listMembers()` ✅
- [x] `OrganizationMemberRepository::findMembershipByOrgSlug()` — JOIN org+membership en 1 requête, remplace `findBySlug + hasRole` dans `TenantSubscriber` ✅
- [x] `TenantSubscriber` simplifié — injection `OrganizationRepository` supprimée ✅
- [x] Index confirmés : `slug` unique, FK `user_id` + `organization_id`, unique constraint `(user_id, organization_id)` — tout couvert ✅
- [x] `EXPLAIN ANALYZE` validé : Index Scan sur slug + Bitmap Index sur user_id — aucun Seq Scan sur les tables critiques ✅
- [x] 40/40 tests passants après refacto ✅

**Résultat :**
| Endpoint | Avant | Après |
|---|---|---|
| `GET /api/organizations` | 1 + N requêtes | **1 requête** (JOIN FETCH) |
| `GET /api/organizations/{slug}/members` | 1 + N requêtes | **1 requête** (JOIN FETCH) |
| `X-Organization-Slug` header (par request) | 2 requêtes | **1 requête** (JOIN combiné) |

---

### Phase 4 — Admin & Observabilité ✅ *(terminée)*

**Objectif :** Dashboard admin et monitoring.

- [x] EasyAdmin v5 — CRUD User, Organization — `/admin` (ROLE_ADMIN) ✅
- [x] `app:user:promote <email>` — commande console pour attribuer ROLE_ADMIN ✅
- [x] Health check `GET /health` — vérifie DB (fatal) + Mercure (non-bloquant) ✅
- [x] Logs structurés JSON — MonologBundle + `monolog.formatter.json` sur `php://stderr` ✅
- [x] Métriques basiques — `RequestMetricsSubscriber` (method/path/status/duration_ms par requête) ✅
- [x] `wget` ajouté au Dockerfile (Coolify health check) ✅
- [x] `assets:install` ajouté à l'entrypoint (CSS EasyAdmin) ✅
- [x] DNS Cloudflare configuré DNS-only — certificats Let's Encrypt générés via Traefik ✅
- [x] Compte admin `yvenou.alan@gmail.com` créé en production ✅

**Livrable :** Dashboard admin sur `https://sterplatform.bichetapps.com/admin`, health check sur `/health`, métriques dans les logs Coolify, SSL Let's Encrypt valide. ✅

---

### Phase 5 — Email + Gestion des templates ✅ *(terminée)*

**Objectif :** Faire de SterPlatform la plateforme d'envoi d'emails centralisée pour toutes les applications.

#### 5a — Infrastructure email
- [x] Configurer Brevo comme provider SMTP (`MAILER_DSN=smtp://...@smtp-relay.brevo.com:587`)
- [x] Configurer `MAILER_DSN` dans Coolify (staging + prod)
- [x] Valider l'envoi depuis les templates existants (verification, reset_password)

#### 5b — Entité EmailTemplate
- [x] Entity `EmailTemplate` (slug, project, subject, html_body, description, timestamps) + migration
- [x] EasyAdmin CRUD pour `EmailTemplate` (avec champ texte long pour html_body)

#### 5c — Endpoint d'envoi
- [x] `POST /api/email/send` — prend `template` (slug), `to`, `variables` (JSON)
- [x] Authentification par token applicatif (header `X-App-Token`) — différent du JWT utilisateur
- [x] Rendu Twig à la volée depuis le html_body stocké en base via `ArrayLoader` (sans cache filesystem)
- [x] Gestion des erreurs (template introuvable, email invalide, token invalide)
- [x] Firewall `email_api` dédié (`security: false`) pour bypasser le JWT sur `/api/email`
- [x] Tests PHPUnit : token absent, token invalide, champ manquant, email invalide, template introuvable, envoi OK — 52/52 passants

**Livrable :** `POST /api/email/send` opérationnel en staging et production. Brevo configuré. CRUD templates dans EasyAdmin. 52/52 tests passants. ✅

---

### Phase 5-cleanup — Suppression entités métier DartsOpen + refacto ✅ *(terminée)*

**Contexte :** Des entités Doctrine miroir du schéma DartsOpen avaient été créées dans SterPlatform (`Tournament`, `Round`, `Registration`, `Pool`, `PoolPlayer`, `DartsMatch`, `MatchSet`). Cette décision a été abandonnée — DartsOpen utilise désormais Prisma direct sur sa propre base.

**Objectif :** Nettoyer SterPlatform des entités qui ne lui appartiennent pas et assainir le code.

- [x] Supprimer les entités DartsOpen + 8 enums + 7 repositories associés
- [x] Simplifier `TenantFilter` — suppression du code mort (`hasField('organizationId')`)
- [x] Mise à jour README (badge tests 52, phases, env vars, commandes)
- [x] Mise à jour Roadmap (statuts, structure projet, modèle de données)
- [x] 52/52 tests passants — aucune régression

**Livrable :** SterPlatform ne contient que : User, Organization, OrganizationMember, RefreshToken, EmailTemplate. ✅

---

### Phase 6 — Intégration DartsOpen *(1-2 sessions)*

**Contexte :** DartsOpen utilise déjà SterPlatform pour l'auth JWT (login, register, refresh). Il reste à câbler l'envoi d'emails.

**Objectif :** Brancher DartsOpen sur l'API email SterPlatform.

- [ ] Identifier tous les emails à envoyer depuis DartsOpen (inscription, confirmation paiement, invitation tournoi, résultats ?)
- [ ] Créer les templates correspondants dans SterPlatform (via EasyAdmin)
- [ ] Ajouter un token applicatif DartsOpen dans SterPlatform (table `AppToken` ou secret partagé)
- [ ] Créer `lib/api/sterplatform-email.ts` dans DartsOpen — client vers `POST /api/email/send`
- [ ] Appeler ce client depuis les Server Actions concernées
- [ ] Tests

**Livrable :** DartsOpen envoie ses emails via SterPlatform. Aucun SMTP configuré côté DartsOpen.

---

### Phase 7 — Intégration FestManager *(2-3 sessions)*

**Contexte :** FestManager a sa propre auth Spring Boot + JWT (jjwt) et son propre SMTP. Il faut migrer vers SterPlatform.

**Objectif :** FestManager délègue auth et email à SterPlatform.

#### 7a — Migration auth
- [ ] Remplacer Spring Security + jjwt par des appels HTTP vers `/api/auth/*` de SterPlatform
- [ ] Adapter le `JwtFilter` Spring Boot pour valider les tokens SterPlatform (clé publique LexikJWT)
- [ ] Ou : consommer l'API SterPlatform comme proxy (login → appelle SterPlatform, retourne le JWT)
- [ ] Adapter l'AuthService Angular pour appeler les routes SterPlatform
- [ ] Gérer le refresh token dans Angular (intercepteur HTTP)
- [ ] Tests

#### 7b — Migration email
- [ ] Remplacer `JavaMailSender` + templates Thymeleaf par des appels vers `POST /api/email/send`
- [ ] Créer les templates FestManager dans SterPlatform (confirmation affectation, invitation, rappel)
- [ ] Supprimer la config SMTP de FestManager
- [ ] Tests

**Livrable :** FestManager sans auth propre ni SMTP. Tout passe par SterPlatform.

---

### Phase 8 — Hardening & Documentation *(1 session)*

**Objectif :** Solidifier la plateforme pour un usage multi-projets durable.

- [ ] Rate limiting sur `/api/auth/*` (symfony/rate-limiter)
- [ ] Monitoring UptimeRobot (health check toutes les 5 min, alerte email si down)
- [ ] Backup PostgreSQL quotidien (pg_dump → Hetzner Object Storage ou cron local)
- [ ] Guide "Intégrer un nouveau projet dans SterPlatform" (doc dans `docs/`)
- [ ] Versioning API `/api/v1/` *(optionnel selon besoin)*
- [ ] Documentation OpenAPI publique

**Livrable :** SterPlatform résiste à la montée en charge, se sauvegarde, et peut accueillir un nouveau projet en moins d'une journée.

---

## 6. Ordre de priorité & estimation

| Phase | Durée estimée | Priorité | Statut |
|---|---|---|---|
| Phase 0 — Socle | 2 sessions | ~~🔴 Critique~~ | ✅ |
| Phase 1 — Auth | 1 session | ~~🔴 Critique~~ | ✅ |
| Phase 1b — JWT Refresh | 1 session | ~~🔴 Critique~~ | ✅ |
| Phase 2 — Multi-tenancy | 1 session | ~~🟠 Haute~~ | ✅ |
| Phase 3 — Mercure | 1 session | ~~🟠 Haute~~ | ✅ |
| Phase 3b — Refacto + N+1 | 1 session | ~~🟠 Haute~~ | ✅ |
| Phase 4 — Admin + Mise en prod | 2 sessions | ~~🟡 Moyenne~~ | ✅ |
| Phase 5 — Email + Templates | 2-3 sessions | ~~🔴 Critique~~ | ✅ |
| Phase 5-cleanup — Suppression entités DartsOpen + refacto | 1 session | ~~🟠 Haute~~ | ✅ |
| Phase 6 — Intégration DartsOpen (email) | 1-2 sessions | 🟠 Haute | ❌ À faire |
| Phase 7 — Intégration FestManager (auth + email) | 2-3 sessions | 🟡 Moyenne | ❌ À faire |
| Phase 8 — Hardening & Documentation | 1 session | 🟢 Basse | ❌ À faire |

**Total restant estimé : 7-10 sessions**

---

## 7. Risques & décisions à prendre

| Risque | Mitigation |
|---|---|
| SterPlatform down = toutes les apps down | Restart auto Coolify + UptimeRobot + backup DB quotidien |
| Mercure nécessite un serveur SSE dédié | Inclus dans Docker Compose — pas de coût additionnel |
| FestManager auth migration : impact Angular | Adapter l'intercepteur HTTP Angular + gérer refresh token |
| Charge SMTP sur volume d'emails | Brevo 20 000 mails/mois à 9 €/mois — largement suffisant |
| Token applicatif exposé côté DartsOpen/FestManager | Stocker en variable d'env Coolify, jamais committé |

---

## 8. Structure du projet (cible)

```
SterPlatform/
├── config/
│   ├── packages/          # Config API Platform, LexikJWT, Mercure, Mailer…
│   └── routes/
├── src/
│   ├── Entity/            # User, Organization, OrganizationMember, EmailTemplate
│   ├── Repository/        # UserRepository, OrganizationRepository…
│   ├── Security/          # OrganizationVoter
│   ├── Controller/        # Auth, Organization, Email, Mercure, Health + Admin/
│   ├── Service/           # MailerService, MercurePublisher, TenantContext
│   ├── EventSubscriber/   # TenantSubscriber, DoctrinePublishSubscriber, RequestMetricsSubscriber
│   ├── Doctrine/          # TenantFilter (SQLFilter multi-tenancy)
│   ├── Enum/              # OrganizationRole
│   └── Command/           # app:user:promote
├── templates/
│   └── emails/            # Twig email templates statiques (verification, reset_password)
├── migrations/
├── tests/
│   └── Controller/        # WebTestCase pour Auth, Organization, Email, Mercure
├── docker/
│   ├── php/Dockerfile
│   └── nginx/nginx.conf
├── docker-compose.yml
├── docs/
│   └── SterPlatform_Roadmap.md
└── .env.example
```

---

## 9. Prochaine session — Phase 6

Actions concrètes pour démarrer :

1. Identifier les emails à envoyer depuis DartsOpen (inscription, confirmation paiement, invitation, résultats)
2. Créer les templates correspondants dans SterPlatform via EasyAdmin
3. Créer `lib/api/sterplatform-email.ts` dans DartsOpen — client HTTP vers `POST /api/email/send`
4. Appeler ce client depuis les Server Actions concernées
5. Configurer `APP_TOKEN` DartsOpen dans Coolify (variable d'env côté DartsOpen)
