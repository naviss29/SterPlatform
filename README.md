# SterPlatform

Backend Symfony générique multi-projets — remplace Supabase pour DartsOpen, FestManager et les projets futurs.

**Stack :** Symfony 8 · PHP 8.4 · API Platform 4 · PostgreSQL 16 · LexikJWT · Mercure · Docker

---

## Démarrage rapide

### Prérequis

- Docker Desktop
- Git

### Installation

```bash
git clone https://github.com/naviss29/SterPlatform.git
cd SterPlatform

# Copier les variables d'environnement
cp .env .env.local
# Éditer .env.local avec vos valeurs (APP_SECRET, JWT_PASSPHRASE...)

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
```

**Swagger UI :** http://localhost:8080/api/docs

---

## Services Docker

| Service | URL locale | Description |
|---|---|---|
| API Symfony | http://localhost:8080 | Application principale |
| API Docs | http://localhost:8080/api/docs | Swagger UI / OpenAPI |
| PostgreSQL | localhost:5432 | Base de données |
| Mercure | http://localhost:9090 | Hub SSE temps réel |
| Mailpit (dev) | http://localhost:8025 | Interface emails de dev |

---

## Commandes utiles

```bash
# Migrations
docker compose run --rm php php bin/console doctrine:migrations:diff
docker compose run --rm php php bin/console doctrine:migrations:migrate --no-interaction

# Cache
docker compose run --rm php php bin/console cache:clear

# Tests
docker compose run --rm php php bin/phpunit

# Console Symfony
docker compose run --rm php php bin/console [commande]

# Logs
docker compose logs -f php
docker compose logs -f nginx

# Reset complet (supprime toutes les données)
docker compose down -v
```

---

## Variables d'environnement

| Variable | Description | Exemple |
|---|---|---|
| `APP_ENV` | Environnement (`dev` / `prod`) | `dev` |
| `APP_SECRET` | Clé secrète Symfony (32 hex) | `generated` |
| `DATABASE_URL` | DSN PostgreSQL | `postgresql://user:pass@db:5432/sterplatform` |
| `JWT_PASSPHRASE` | Passphrase clés JWT RSA | `votre_passphrase` |
| `MERCURE_JWT_SECRET` | Secret JWT Mercure | `votre_secret` |
| `MAILER_DSN` | DSN mailer (`null://null` en dev) | `smtp://mailpit:1025` |

---

## Documentation

- [Documentation technique](docs/SterPlatform_Documentation.md) — Architecture, erreurs, actions réalisées
- [Roadmap](docs/SterPlatform_Roadmap.md) — Plan des phases et backlog

---

## Phases

| Phase | Statut |
|---|---|
| Phase 0 — Socle (Symfony 8, Docker, API Platform, JWT) | ✅ Terminée |
| Phase 1 — Auth générique (User, register, login, reset) | En attente |
| Phase 2 — Multi-tenancy (Organization, TenantFilter, Voters) | En attente |
| Phase 3 — Mercure Real-time | En attente |
| Phase 4 — Admin & Observabilité (EasyAdmin 4) | En attente |
| Phase 5 — Migration DartsOpen | En attente |
| Phase 6 — Production multi-projets | En attente |
