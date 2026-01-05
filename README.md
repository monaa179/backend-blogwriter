# Backend BlogWriter - Symfony REST API

API REST pour la plateforme interne de rédaction d'articles avec intégration Make.com.

## Configuration

### Prérequis
- PHP 8.4+
- MySQL 8.0+
- Composer

### Installation

```bash
# Installer les dépendances
composer install

# Copier la configuration
cp .env.example .env.local

# Éditer .env.local avec vos valeurs
# - DATABASE_URL
# - JWT_PASSPHRASE
# - MAKE_WEBHOOK_URL
# - MAKE_WEBHOOK_SECRET

# Créer la base de données
php bin/console doctrine:database:create

# Exécuter les migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Charger les fixtures (user admin + modules)
php bin/console doctrine:fixtures:load --no-interaction

# Lancer le serveur de développement
symfony server:start
# ou
php -S localhost:8000 -t public
```

## Authentification

L'API utilise JWT (JSON Web Tokens). Tous les endpoints sauf `/auth/login` nécessitent un token Bearer.

### Credentials par défaut (fixtures)
- **Email**: `admin@digichef.fr`
- **Password**: `admin123`

---

## Exemples cURL

### 1. Login

```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@digichef.fr","password":"admin123"}'
```

**Réponse:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "user": {
    "id": 1,
    "email": "admin@digichef.fr",
    "roles": ["ROLE_ADMIN", "ROLE_USER"],
    "firstName": "Admin",
    "lastName": "Digichef"
  }
}
```

### 2. Créer un article

```bash
curl -X POST http://localhost:8000/articles \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "source_url": "https://example.com/article-source",
    "original_title": "Titre original de l article",
    "original_description": "Description originale de l article à rédiger",
    "modules": [1, 2]
  }'
```

### 3. Lister les articles

```bash
# Liste simple
curl http://localhost:8000/articles \
  -H "Authorization: Bearer YOUR_TOKEN"

# Avec pagination et filtres
curl "http://localhost:8000/articles?page=1&limit=10&status=proposed&q=recherche" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 4. Récupérer un article

```bash
curl http://localhost:8000/articles/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 5. Supprimer un article

```bash
curl -X DELETE http://localhost:8000/articles/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 6. Déclencher la rédaction (webhook Make.com)

```bash
curl -X POST http://localhost:8000/articles/1/write \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Réponse (202 Accepted):**
```json
{
  "message": "writing_started",
  "article_id": 1
}
```

### 7. Callback webhook (appelé par Make.com)

```bash
curl -X POST http://localhost:8000/articles/1/write/callback \
  -H "X-WEBHOOK-SECRET: your-webhook-secret-key" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "<h1>Article rédigé</h1><p>Contenu de l article...</p>",
    "suggested_title": "Nouveau titre suggéré",
    "suggested_description": "Nouvelle description suggérée",
    "score": 85
  }'
```

### 8. Valider un article

```bash
curl -X POST http://localhost:8000/articles/1/validate \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 9. Publier un article

```bash
curl -X POST http://localhost:8000/articles/1/published \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 10. Créer un module

```bash
curl -X POST http://localhost:8000/modules \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Google Reviews",
    "slug": "google-reviews",
    "active": true
  }'
```

### 11. Modifier un module

```bash
curl -X PUT http://localhost:8000/modules/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Nouveau nom",
    "active": false
  }'
```

### 12. Articles d'un module

```bash
curl "http://localhost:8000/modules/1/articles?page=1&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Status des articles

| Status | Description |
|--------|-------------|
| `proposed` | Article créé, en attente de rédaction |
| `writing` | Rédaction en cours (Make.com) |
| `written` | Article rédigé, en attente de validation |
| `validated` | Article validé, prêt à publier |
| `published` | Article publié |

## Format des erreurs

Toutes les erreurs suivent le format:

```json
{
  "error": "validation_error",
  "details": {
    "field": "Message d'erreur"
  }
}
```

**Codes d'erreur:**
- `invalid_json` - Corps JSON invalide
- `validation_error` - Erreur de validation (422)
- `not_found` - Ressource non trouvée (404)
- `conflict` - Conflit (slug dupliqué, etc.) (409)
- `unauthorized` - Token invalide ou absent (401)
