# SterPlatform — Documentation technique

> Version : 0.5
> Auteur : Alan
> Date : Mai 2026
> Statut : **Phase 2 terminée — Multi-tenancy opérationnel**

---

## Historique des versions

| Version | Date | Modifications |
|---|---|---|
| 0.1 | Mai 2026 | Phase 0 — Scaffold Symfony 8, Docker (PHP 8.4 + PostgreSQL 16 + Nginx + Mercure + Mailpit), API Platform 4, LexikJWT, JWT keypair générée, socle opérationnel |
| 0.2 | Mai 2026 | Phase 1 — Auth générique complète : entité User (UUID), migration, firewall JWT, endpoints register/verify/login/forgot-password/reset-password/me, MailerService + templates Twig, 17 tests PHPUnit passants |
| 0.3 | Mai 2026 | Upgrade PostgreSQL 16 → 18 (tous les environnements), correction du point de montage volume PG18 (`/var/lib/postgresql` au lieu de `/var/lib/postgresql/data`), Dockerfile production multi-stage (PHP-FPM + Nginx + Supervisor), CI GitHub Actions, deploy webhook Coolify |
| 0.4 | Mai 2026 | Phase 1b — JWT refresh token (`POST /api/auth/refresh`), logout avec révocation (`POST /api/auth/logout`), entité `RefreshToken` + migration, CI GitHub Actions opérationnel (tests + lint sur `develop` + `main`), 23 tests PHPUnit passants |
| 0.5 | Mai 2026 | Phase 2 — Multi-tenancy : entités `Organization` + `OrganizationMember`, 5 endpoints REST, `TenantFilter` Doctrine (header `X-Organization-Slug`), `OrganizationVoter` (OWNER/ADMIN/MEMBER), 34/34 tests passants |

---

## 1. Présentation du projet

### Contexte

SterPlatform est un backend Symfony auto-hébergé, générique et multi-projets. Il remplace Supabase (25 €/mois par projet sur le plan Pro) pour les projets en phase de démarrage ou quasi-gratuits.

Un seul backend sert tous les projets (DartsOpen, FestManager, futurs projets) sur le VPS Hetzner CX23 déjà payé (~5 €/mois). Zéro coût additionnel.

### Correspondance Supabase → SterPlatform

| Supabase | SterPlatform |
|---|---|
| PostgreSQL | PostgreSQL 18 + Doctrine ORM |
| Auth JWT + email | LexikJWT + Symfony Security + Mailer |
| API REST auto-générée | API Platform 4 (OpenAPI / JSON-LD) |
| Row Level Security (RLS) | Symfony Voters + Doctrine Filters |
| Realtime (WebSockets) | Mercure (SSE pub/sub) |
| Email templates | Twig + Symfony Mailer |
| Dashboard admin | EasyAdmin 4 *(Phase 4)* |

---

## 2. Stack technique

| Couche | Technologie | Justification |
|---|---|---|
| Framework | Symfony 8.0 (PHP 8.4) | Mature, performant, écosystème riche — proche de Spring Boot |
| API | API Platform 4 | Génère REST + OpenAPI depuis les entités Doctrine |
| ORM | Doctrine ORM 3 | Migrations, repositories, relations — équivalent Hibernate |
| Auth | LexikJWTAuthenticationBundle 3 + gesdinet/jwt-refresh-token-bundle 2 | JWT access token + refresh token persisté en base, standard industrie |
| Temps réel | Mercure (hub dunglas) | SSE pub/sub, inventé par l'équipe Symfony |
| Email | Symfony Mailer + Twig | Templates HTML, SMTP / Mailpit en dev |
| Admin | EasyAdmin 4 | Dashboard rapide à générer depuis les entités *(Phase 4)* |
| Tests | PHPUnit + ApiTestCase | Tests unitaires + intégration API |
| Base de données | PostgreSQL 18 | Version LTS la plus récente — même famille que Supabase |
| Containerisation | Docker + Docker Compose | Déploiement identique aux autres projets |
| Déploiement | Coolify v4 sur Hetzner CX23 | Infrastructure partagée |

---

## 3. Architecture cible

```
┌─────────────────────────────────────────────────────┐
│                   SterPlatform                      │
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

### Multi-tenancy *(Phase 2)*

Chaque projet est un **tenant** isolé :
- Table `organizations` (un enregistrement par projet)
- `Doctrine Extension` qui filtre automatiquement les données par `organization_id`
- Symfony Voters pour les droits fins (OWNER, ADMIN, MEMBER)

---

## 4. Modèle de données générique *(cible Phase 1-2)*

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

---

## 5. Structure du projet

```
SterPlatform/
├── config/
│   ├── packages/          # Config API Platform, LexikJWT, Doctrine, Security…
│   ├── routes/
│   └── jwt/               # Clés RSA JWT (private.pem, public.pem — non commitées)
├── src/
│   ├── Entity/            # User, Organization, OrganizationMember (Phase 1-2)
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
│   ├── php/
│   │   ├── Dockerfile     # PHP 8.4-fpm + extensions
│   │   └── php.ini        # OPcache, memory_limit, upload
│   └── nginx/
│       └── default.conf   # Reverse proxy → php:9000
├── docker-compose.yml     # Services : php, nginx, db, mercure
├── compose.override.yaml  # Dev only : mailer (Mailpit)
├── docs/
│   ├── SterPlatform_Documentation.md
│   └── SterPlatform_Roadmap.md
├── .env                   # Variables d'environnement (sans secrets)
├── .env.example           # Template à documenter
└── README.md
```

---

## 6. Docker — Services et ports

| Service | Image | Port local | Description |
|---|---|---|---|
| php | Dockerfile (PHP 8.4-fpm) | — | PHP-FPM — traite les requêtes Symfony |
| nginx | nginx:1.27-alpine | 8080 → 80 | Reverse proxy vers php:9000 |
| db | postgres:18-alpine | 5432 | Base de données PostgreSQL |
| mercure | dunglas/mercure | 9090 → 80 | Hub SSE temps réel |
| mailer | axllent/mailpit | 8025 (UI), 1025 (SMTP) | Intercepteur email dev *(compose.override.yaml)* |

### Volumes Docker

| Volume | Contenu |
|---|---|
| `db_data` | Données PostgreSQL persistantes |
| `vendor_data` | Dépendances Composer (nommé — persistant entre runs) |

### Commandes essentielles

```bash
# Démarrer tous les services
docker compose up -d

# Installer les dépendances (première fois ou après composer.json modifié)
docker compose run --rm php composer install

# Générer les clés JWT
docker compose run --rm php php bin/console lexik:jwt:generate-keypair --overwrite

# Créer et lancer les migrations
docker compose run --rm php php bin/console doctrine:migrations:diff
docker compose run --rm php php bin/console doctrine:migrations:migrate --no-interaction

# Lancer les tests
docker compose run --rm php php bin/phpunit

# Arrêter et supprimer les volumes (reset complet)
docker compose down -v
```

---

## 7. Erreurs à ne pas reproduire

> Cette section documente les erreurs techniques rencontrées pendant le développement, pour ne pas les reproduire.

| # | Contexte | Erreur | Solution |
|---|---|---|---|
| 1 | Dockerfile — version PHP | `FROM php:8.3-fpm` génère une erreur Composer : "symfony/framework-bundle v8.0.* requires php >=8.4" → installation bloquée | Utiliser **`FROM php:8.4-fpm`**. Symfony 8 exige PHP >=8.4. |
| 2 | Composer — alias Symfony Flex | `composer require api` échoue avec "Package api not found" car `api` est un alias Symfony Flex non reconnu en `composer require` standalone | Utiliser le nom complet du package : **`composer require api-platform/core`** |
| 3 | Symfony Flex — docker-compose.yml modifié automatiquement | La recette Doctrine de Symfony Flex a ajouté un service `database` dans `docker-compose.yml` alors qu'un service `db` existait déjà → conflit de noms → Docker Compose invalide | Symfony Flex modifie automatiquement `docker-compose.yml` lors des `composer require`. Toujours vérifier le fichier après une installation. Réécrire manuellement `docker-compose.yml` si Flex a introduit des incohérences. |
| 4 | `compose.override.yaml` — service sans image | Flex a créé un `compose.override.yaml` contenant une section `database:` (ports override) qui référence un service `database` inexistant dans le compose principal → `service "database" has neither an image nor a build context` | Supprimer la section `database` dans `compose.override.yaml`. Ne garder que les sections utiles au dev (ex. `mailer` Mailpit). |
| 5 | Volume PostgreSQL — incompatibilité de version | Le volume `db_data` contenait des données initialisées par PostgreSQL 15. En passant à l'image `postgres:16-alpine`, le container refusait de démarrer : "The data directory was initialized by PostgreSQL version 15, which is not compatible with this version 16" | Supprimer le volume et le recréer : **`docker compose down -v`** puis relancer. En dev, pas de données à préserver — ne pas hésiter à resetter. |
| 6 | `symfony/orm-pack` — absent du composer.lock | `symfony/orm-pack` est un "virtual pack" Symfony Flex : il est décomposé en ses packages individuels lors de l'installation. Le `composer.lock` ne contient pas le pack lui-même. Si les packages individuels sont déjà listés dans `composer.json`, `composer install` échoue avec "Required package symfony/orm-pack is not present in the lock file" | Retirer `symfony/orm-pack` de `composer.json` et lister ses packages directement (`doctrine/doctrine-bundle`, `doctrine/orm`, `doctrine/doctrine-migrations-bundle`). |
| 7 | Volume vendor anonyme — vide à chaque `docker compose run` | La configuration initiale utilisait un volume anonyme pour `/var/www/html/vendor` : `- /var/www/html/vendor`. Chaque `docker compose run` crée un nouveau container avec un vendor vide → `Fatal error: Symfony Runtime is missing` même après un `composer install` dans une session précédente | Utiliser un **volume nommé** : `- vendor_data:/var/www/html/vendor` et ajouter `vendor_data:` dans `volumes:`. Le vendor persiste entre les runs. |
| 8 | `bin/console` — "Symfony Runtime is missing" | `php bin/console` échoue avec `Fatal error: Uncaught LogicException: Symfony Runtime is missing` même si `symfony/runtime` est dans `composer.json` | Cause : le vendor directory est vide (voir erreur #7). Solution : s'assurer que `composer install` a bien tournéavec le volume nommé `vendor_data`, puis relancer la commande. |
| 9 | PHPUnit — "test.service_container not found" | `getContainer()` dans `setUp()` échoue avec "Could not find service test.service_container" alors que `framework.test: true` est configuré | Appeler `createClient()` **avant** `getContainer()` dans `setUp()`. `createClient()` est ce qui booste le kernel en mode test — `getContainer()` utilisé en premier ne peut pas trouver le service container de test. |
| 10 | PHPUnit — "Booting the kernel before createClient() is not supported" | Appeler `static::createClient()` depuis une méthode helper (ex. `getJwtToken()`) échoue si le kernel est déjà booté depuis `setUp()` | Stocker le client dans `$this->client` lors du `setUp()` et réutiliser la même instance dans tous les helpers et tests. Ne jamais appeler `createClient()` plusieurs fois dans le même test. |
| 11 | `access_control` — `/api/auth/me` public par héritage de règle | La règle `{ path: ^/api/auth, roles: PUBLIC_ACCESS }` couvre `/api/auth/me`. Un appel sans token atteint le contrôleur avec `getUser() === null` → crash 500 au lieu de 401. | Ajouter une règle plus spécifique **avant** la règle générale : `{ path: ^/api/auth/me$, roles: IS_AUTHENTICATED_FULLY }`. Dans `access_control`, la première règle qui matche gagne. |
| 12 | Docker — PostgreSQL 18 — container crash au démarrage | Après upgrade PG16 → PG18, le container `db` crashe avec : `"there appears to be PostgreSQL data in /var/lib/postgresql/data (unused mount/volume)"`. PG18 change la structure interne : les données sont désormais stockées dans un sous-répertoire versionné (ex. `/var/lib/postgresql/18/main`) au lieu de `/var/lib/postgresql/data` directement. | Changer le point de montage du volume dans `docker-compose.yml` (et `.prod.yml`) : **`- db_data:/var/lib/postgresql`** (sans `/data`). Puis `docker compose down -v` + `docker compose up -d` pour recréer le volume avec la bonne structure. |
| 13 | CI GitHub Actions — `serverVersion` incohérent avec image PG | Le `DATABASE_URL` en CI pointait vers `serverVersion=16` alors que l'image PostgreSQL était déjà `postgres:18-alpine` → Doctrine générait des requêtes avec hints PG16, potentiellement incompatibles avec PG18. | Mettre à jour simultanément l'image (`postgres:18-alpine`) et le `serverVersion` dans `DATABASE_URL` (`serverVersion=18`) dans `ci.yml`. Les deux doivent toujours être synchronisés. |

---

## 8. Actions techniques réalisées

> Cette section trace les décisions et actions techniques importantes.

| # | Date | Action | Détail |
|---|---|---|---|
| 1 | Mai 2026 | Scaffold Symfony 8 sans PHP local | `docker run --rm -v "$(pwd):/app" -w /app composer:2 create-project symfony/skeleton sterplatform_tmp` — scaffold réalisé entièrement via Docker, sans installer PHP sur la machine Windows |
| 2 | Mai 2026 | Installation des packages | `api-platform/core`, `doctrine/doctrine-bundle`, `doctrine/doctrine-migrations-bundle`, `doctrine/orm`, `lexik/jwt-authentication-bundle`, `symfony/security-bundle`, `symfony/mailer`, `twig/extra-bundle`, `symfony/twig-bundle` |
| 3 | Mai 2026 | Docker Compose configuré | Services : php (PHP 8.4-fpm + extensions pdo_pgsql, intl, zip, opcache, apcu), nginx (1.27-alpine), db (postgres:16-alpine + healthcheck), mercure (dunglas/mercure), mailer (axllent/mailpit dans compose.override.yaml) |
| 4 | Mai 2026 | Volume vendor nommé | Remplacement du volume anonyme `/var/www/html/vendor` par un volume nommé `vendor_data` pour que les dépendances persistent entre les `docker compose run` |
| 5 | Mai 2026 | Nettoyage recettes Symfony Flex | Suppression du service `database` ajouté par Flex dans `docker-compose.yml` (recette Doctrine), suppression de la section `database` dans `compose.override.yaml`. Conservation de la section `mailer` (Mailpit) dans `compose.override.yaml` |
| 6 | Mai 2026 | Génération JWT keypair | `docker compose run --rm php php bin/console lexik:jwt:generate-keypair --overwrite` — clés RSA générées dans `config/jwt/private.pem` et `config/jwt/public.pem` (non commitées, dans `.gitignore`) |
| 7 | Mai 2026 | Vérification API Platform | `GET http://localhost:8080/api` retourne `{"@context":"/api/contexts/Entrypoint","@id":"/api","@type":"Entrypoint"}` — API Platform 4 opérationnel |
| 8 | Mai 2026 | Git init + remote GitHub | Repo créé sur `naviss29/SterPlatform`, branches `main` + `develop`, `.gitignore` configuré (vendor, config/jwt/*.pem, .env.local) |
| 9 | Mai 2026 | Entité User + migration | `User` : UUID (symfony/uid), email unique, password hashed, roles JSON, isVerified, verificationToken + expiry, resetToken + expiry, createdAt. Table `users` (évite le mot réservé PostgreSQL `user`). Migration `Version20260513095035` générée et exécutée. |
| 10 | Mai 2026 | Firewall JWT configuré | `security.yaml` : provider `app_user_provider` (entity User, property email), firewall `login` (json_login → LexikJWT), firewall `api` (jwt stateless). `access_control` : `/api/auth/me` protégé, endpoints auth publics, reste de l'API protégé. |
| 11 | Mai 2026 | AuthController — 5 endpoints | `POST /api/auth/register` (email+password → user créé + email envoyé), `GET /api/auth/verify?token=` (activation compte), `POST /api/auth/login` (géré par firewall json_login → JWT), `POST /api/auth/forgot-password` (envoi email reset), `POST /api/auth/reset-password` (nouveau mdp via token), `GET /api/auth/me` (profil JWT). Réponses génériques sur register/forgot-password pour éviter l'énumération d'emails. |
| 12 | Mai 2026 | MailerService + templates Twig | `MailerService` injecte `MailerInterface`, `Environment` (Twig), `$appUrl`, `$fromEmail`. Templates HTML responsive `emails/verification.html.twig` et `emails/reset_password.html.twig`. Variables d'env `APP_URL` et `APP_FROM_EMAIL` ajoutées. MAILER_DSN=`smtp://mailer:1025` (Mailpit) en dev, `null://null` en test. |
| 13 | Mai 2026 | PHPUnit — 17 tests passants | `tests/Controller/AuthControllerTest.php` : 17 tests couvrant register (succès, email invalide, mdp court, email existant→201), login (succès→JWT, mauvais mdp→401, email inconnu→401), verify (succès, token invalide, token expiré), forgot-password (succès, email inconnu→200), reset-password (succès, token expiré, mdp court), me (JWT valide→200, pas de token→401). Base de test `sterplatform_test` créée et migrée. |
| 14 | Mai 2026 | Dockerfile production multi-stage | `Dockerfile` à la racine : stage 1 = `composer:2` installe les dépendances sans `--dev` ; stage 2 = `php:8.4-fpm` + nginx + supervisor. Un seul container expose le port 80. Compatible Coolify, Jenkins, tout CI/CD standard. `docker/supervisor/supervisord.conf` orchestre php-fpm et nginx. `docker/php/entrypoint.prod.sh` : cache:clear + migrations + exec supervisord. |
| 15 | Mai 2026 | CI GitHub Actions | `.github/workflows/ci.yml` : déclenché sur push/PR vers `develop` et `main`. Service PostgreSQL 18, PHP 8.4, cache Composer, génération JWT keypair, migration, phpunit. `.github/workflows/deploy.yml` : déclenché sur push `main`, appelle le webhook Coolify via secrets `COOLIFY_TOKEN` et `COOLIFY_WEBHOOK_URL`. |
| 16 | Mai 2026 | Upgrade PostgreSQL 16 → 18 | Mise à jour de `docker-compose.yml`, `docker-compose.prod.yml`, `.env`, `.env.example`, `ci.yml` : image `postgres:18-alpine`, `serverVersion=18` dans `DATABASE_URL`. Correction du point de montage volume (`/var/lib/postgresql` sans `/data` — breaking change PG18). Reset des volumes locaux (`docker compose down -v` + relance). |
| 17 | Mai 2026 | Phase 1b — JWT Refresh Token + Logout | Installation `gesdinet/jwt-refresh-token-bundle` v2.0.0. Entité `RefreshToken` étend le `mapped-superclass` du bundle, table `refresh_tokens`. `POST /api/auth/refresh` géré par le firewall `refresh_jwt` (stub route nécessaire). `POST /api/auth/logout` révoque le refresh token via `RefreshTokenManagerInterface` (JWT requis). Login retourne désormais `token` + `refresh_token`. 6 tests ajoutés (23/23 passants). Bundle enregistré manuellement dans `bundles.php` (recipe Flex ignorée). |
| 18 | Mai 2026 | CI GitHub Actions — workflow tests.yml | `.github/workflows/tests.yml` : déclenché sur push/PR vers `develop` et `main`. Service `postgres:18-alpine`, PHP 8.4 + extensions, cache Composer, génération JWT keypair, migration test, phpunit. `DATABASE_URL` injecté via env dans le workflow (pas de secrets requis pour les tests). |
| 19 | Mai 2026 | Phase 2 — Multi-tenancy | Entités `Organization` (id UUID, name, slug unique, createdAt) + `OrganizationMember` (user FK, organization FK, role enum OWNER/ADMIN/MEMBER, joinedAt). 5 endpoints : `POST/GET /api/organizations`, `GET /api/organizations/{slug}`, `POST/GET /api/organizations/{slug}/members`. `TenantContext` service + `TenantSubscriber` (kernel.request, priorité 5) lit le header `X-Organization-Slug`, active le filtre Doctrine `tenant_filter`. `TenantFilter` filtre les entités ayant une association `organization`. `OrganizationVoter` avec 3 attributs : `ORGANIZATION_VIEW` (tout membre), `ORGANIZATION_MANAGE_MEMBERS` (OWNER+ADMIN), `ORGANIZATION_OWNER`. Signature Voter Symfony 8 inclut `?Vote $vote = null`. |

---

## 9. Roadmap

- [x] Phase 0 — Socle technique (Symfony 8, Docker, API Platform 4, PostgreSQL 18, JWT, CI/CD GitHub Actions, Coolify)
- [x] Phase 1 — Auth générique (User entity, register, verify, login, forgot-password, reset-password, me — 17 tests)
- [x] Phase 1b — JWT refresh token + logout + CI GitHub Actions (23 tests)
- [x] Phase 2 — Multi-tenancy (Organization, TenantFilter, OrganizationVoter — 34 tests)
- [ ] Phase 3 — Mercure Real-time
- [ ] Phase 3 — Mercure Real-time (MercurePublisher, EventSubscriber, exemples Next.js + Angular)
- [ ] Phase 4 — Admin & Observabilité (EasyAdmin 4, /health, logs structurés)
- [ ] Phase 5 — Migration DartsOpen (entités miroir Supabase, migration données, refactor frontend)
- [ ] Phase 6 — Production multi-projets (guide intégration, versioning API, OpenAPI publique)
