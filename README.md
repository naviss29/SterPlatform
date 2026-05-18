# SterPlatform

> Infrastructure partagée pour l'écosystème Stêr Eo Production — Auth JWT + Email + Templates.

![Status](https://img.shields.io/badge/status-Production-brightgreen)
![Symfony](https://img.shields.io/badge/Symfony-8.0-black)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4)
![Tests](https://img.shields.io/badge/tests-40%20passing-brightgreen)
![Docker](https://img.shields.io/badge/Docker-Compose-blue)

**Production :** https://sterplatform.bichetapps.com  
**Staging :** https://sterplatform.dev.bichetapps.com

---

## Rôle dans l'écosystème

SterPlatform est le backend d'infrastructure partagé par tous les projets. Il centralise ce qui est commun à toutes les applications, sans connaître leur métier.

| SterPlatform gère | Les applications gèrent |
|---|---|
| Auth JWT (login, register, refresh, logout) | Leurs entités métier |
| Vérification email + reset password | Leur base de données |
| Envoi d'emails transactionnels (Brevo) | Leur logique métier |
| Templates email (admin EasyAdmin) | Leur propre ORM |
| Mercure SSE hub (temps réel) | |
| Admin dashboard (users, orgs, templates) | |

> SterPlatform ne contient **aucune entité métier** propre à DartsOpen ou FestManager. Voir [Architecture_Ecosysteme.md](../docs/Architecture_Ecosysteme.md).

---

## Stack

| Couche | Technologie |
|---|---|
| Framework | Symfony 8.0 (PHP 8.4) |
| API | API Platform 4 |
| ORM | Doctrine ORM 3 + PostgreSQL 18 |
| Auth | LexikJWTAuthenticationBundle 3 |
| Refresh token | gesdinet/jwt-refresh-token-bundle 2 |
| Temps réel | Mercure (SSE hub) |
| Email | Symfony Mailer + Twig + Brevo SMTP |
| Admin | EasyAdmin v5 |
| Tests | PHPUnit 13 |
| Containerisation | Docker + Docker Compose |
| Déploiement | Coolify v4 (Hetzner CX23) |
| CI/CD | GitHub Actions |

---

## Démarrage rapide

**Prérequis :** Docker Desktop, Git

```bash
git clone https://github.com/naviss29/SterPlatform.git
cd SterPlatform

# Copier les variables d'environnement
cp .env .env.local
# Éditer .env.local (APP_SECRET, JWT_PASSPHRASE, MAILER_DSN...)

# Installer les dépendances
docker compose run --rm php composer install

# Générer les clés JWT
docker compose run --rm php php bin/console lexik:jwt:generate-keypair

# Démarrer tous les services
docker compose up -d
```

### Vérification

```bash
curl http://localhost:8080/api
# {"@context":"/api/contexts/Entrypoint","@id":"/api","@type":"Entrypoint"}

curl http://localhost:8080/health
# {"status":"ok","database":"ok","mercure":"ok"}
```

---

## Services Docker

| Service | URL locale | Description |
|---|---|---|
| API Symfony | http://localhost:8080 | Application principale |
| Swagger UI | http://localhost:8080/api/docs | Documentation OpenAPI |
| Admin EasyAdmin | http://localhost:8080/admin | Dashboard admin |
| PostgreSQL | localhost:5432 | Base de données |
| Mercure | http://localhost:9090 | Hub SSE temps réel |
| Mailpit | http://localhost:8025 | Interface emails (dev uniquement) |

---

## Endpoints principaux

### Auth

| Méthode | URL | Description |
|---|---|---|
| `POST` | `/api/auth/register` | Inscription + email de confirmation |
| `GET` | `/api/auth/verify?token=` | Activation du compte |
| `POST` | `/api/auth/login` | Login → JWT access + refresh token |
| `POST` | `/api/auth/refresh` | Renouvellement du JWT |
| `POST` | `/api/auth/logout` | Révocation du refresh token |
| `POST` | `/api/auth/forgot-password` | Envoi email reset |
| `POST` | `/api/auth/reset-password` | Nouveau mot de passe via token |
| `GET` | `/api/auth/me` | Profil utilisateur (JWT requis) |
| `GET` | `/api/mercure/token` | JWT Mercure pour s'abonner au SSE |

### Email (à venir — Phase 5)

| Méthode | URL | Description |
|---|---|---|
| `POST` | `/api/email/send` | Envoi d'un email via template slug |

---

## Commandes utiles

```bash
# Migrations
docker compose run --rm php php bin/console doctrine:migrations:diff
docker compose run --rm php php bin/console doctrine:migrations:migrate --no-interaction

# Tests
docker compose run --rm php php bin/phpunit

# Cache
docker compose run --rm php php bin/console cache:clear

# Logs
docker compose logs -f php
```

---

## Variables d'environnement

| Variable | Description | Dev |
|---|---|---|
| `APP_ENV` | Environnement (`dev` / `prod`) | `dev` |
| `APP_SECRET` | Clé secrète Symfony (32 hex) | généré |
| `DATABASE_URL` | DSN PostgreSQL | `postgresql://...` |
| `JWT_PASSPHRASE` | Passphrase clés JWT RSA | `votre_passphrase` |
| `MERCURE_JWT_SECRET` | Secret JWT Mercure (≥ 256 bits) | `votre_secret` |
| `MAILER_DSN` | DSN mailer | `smtp://mailpit:1025` (dev) / Brevo (prod) |

---

## Tests

```bash
docker compose run --rm php php bin/phpunit
# 40/40 tests passants
```

---

## Phases

| Phase | Description | Statut |
|---|---|---|
| 0 | Socle Symfony 8 + Docker + API Platform + JWT + Coolify | ✅ |
| 1 | Auth générique (register, login, verify, reset — 17 tests) | ✅ |
| 1b | JWT Refresh Token + logout + CI GitHub Actions | ✅ |
| 2 | Multi-tenancy (Organization, TenantFilter, Voters — 34 tests) | ✅ |
| 3 | Mercure Real-time (MercurePublisher, token endpoint) | ✅ |
| 3b | Refacto + élimination N+1 (JOIN FETCH) | ✅ |
| 4 | Admin EasyAdmin v5 + /health + métriques + déploiement prod | ✅ |
| 5 | Email + Gestion des templates (Brevo, EmailTemplate, POST /api/email/send) | ❌ |
| 5-cleanup | Suppression entités Doctrine DartsOpen orphelines | ❌ |
| 6 | Intégration DartsOpen (câblage email) | ❌ |
| 7 | Intégration FestManager (migration auth + email) | ❌ |
| 8 | Hardening (rate limiting, backups, monitoring, guide) | ❌ |

---

## Documentation

- [Roadmap détaillée](docs/SterPlatform_Roadmap.md)
- [Documentation technique](docs/SterPlatform_Documentation.md)
- [Architecture écosystème](../docs/Architecture_Ecosysteme.md)

---

## Auteur

**Alan** — Développeur Full Stack (Java / Spring Boot / Angular / Next.js / Symfony)

[![GitHub](https://img.shields.io/badge/GitHub-naviss29-black)](https://github.com/naviss29)
