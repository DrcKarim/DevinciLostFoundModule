# DescriptionWithAI - Guide de Démarrage

## Prérequis

1. **Serveur Web** : Apache/XAMPP/MAMP avec PHP 8.0+
2. **Base de données** : MySQL
3. **Omeka-S** : Version 4.1.1 installé à `http://localhost/omk_thyp_25-26_clone/`
4. **Ollama** : AI runtime pour le matching intelligent
5. **Python 3** : Pour servir le frontend

## Installation

### 1. Configuration d'Omeka-S

```bash
# Omeka-S doit être accessible à
http://localhost/omk_thyp_25-26_clone/
```

**Important:** Si votre Omeka-S est à un chemin différent, mettez à jour :
- `DevinciLostFound/modules/authParams.js` → modifier `apiOmk`
- `DevinciLostFound/start.sh` → modifier le chemin du module

### 2. Installation du Module DescriptionWithAI

Le module se trouve dans : `/omk_thyp_25-26_clone/modules/DescriptionWithAI/`

**Activer le module :**
1. Aller sur http://localhost/omk_thyp_25-26_clone/admin
2. Onglet "Modules"
3. Cliquer sur "Install" puis "Activate" pour `DescriptionWithAI`

### 3. Installation d'Ollama

```bash
# macOS
brew install ollama

# Ou télécharger depuis https://ollama.ai

# Démarrer Ollama
ollama serve

# Installer le modèle llama2 (dans un autre terminal)
ollama pull llama2
```

Vérifier qu'Ollama fonctionne :
```bash
curl http://localhost:11434/api/tags
```

## ▶Lancement du Projet (Méthode Simple)

### Option 1 : Script Automatique (Recommandé)

```bash
cd DevinciLostFound
./start.sh
```

Le script va :
-  Vérifier qu'Ollama est actif
-  Vérifier que le modèle llama2 est installé
-  Vérifier qu'Omeka-S est accessible
-  Démarrer l'API de recherche (port 8083)
-  Démarrer le frontend (port 8085)

**Arrêter les serveurs :** Appuyez sur `Ctrl+C`

### Option 2 : Lancement Manuel

**Terminal 1 - API de recherche :**
```bash
cd omk_thyp_25-26_clone/modules/DescriptionWithAI
php -S localhost:8083 api.php
```

**Terminal 2 - Frontend :**
```bash
cd DevinciLostFound
python3 -m http.server 8085
```

**Terminal 3 - Ollama (si pas déjà lancé) :**
```bash
ollama serve
```

### Accéder à l'Application

Ouvrir dans le navigateur :
```
http://localhost:8085/apiOmk.html
```

** Important :** Si la page ne charge pas, faites un hard refresh : `Cmd + Shift + R` (Mac) ou `Ctrl + Shift + R` (Windows)

## Utilisation

### Panel Gauche - Déclarer un Objet Trouvé

1. **Télécharger une photo** (optionnel) : Drag & drop ou clic
2. **Titre de l'objet** : Ex: "Portefeuille noir"
3. **Description détaillée** :
   ```
   Description: Portefeuille en cuir noir avec cartes bancaires
   Telephone: 06 12 34 56 78
   Trouve par: Jean Dupont
   Lieu: Salle A200
   ```
4. Cliquer sur **"Envoyer"**
5. L'objet est créé dans Omeka-S
6. L'IA génère automatiquement une description résumée

### Panel Droit - Rechercher un Objet Perdu

1. **Titre** : Ex: "Portefeuille"
2. **Description** : Ex: "J'ai perdu mon portefeuille noir en cuir"
3. Cliquer sur **"🔍 Rechercher"**
4. **Attendre 30-60 secondes** (l'IA compare avec tous les objets)
5. Résultats possibles :
   -  **Match trouvé** : Score de similarité + coordonnées du trouveur
   -  **Suggestion aléatoire** : Si aucune correspondance exacte (fond jaune)

##  Architecture du Système

### Composants Principaux

```
ProjetNewDepart/
├── omk_thyp_25-26_clone/                 # Backend Omeka-S
│   └── modules/
│       └── DescriptionWithAI/             # MODULE PRINCIPAL
│           ├── Module.php                 # Écouteur d'événements
│           ├── api.php                    # API standalone de recherche (port 8083)
│           ├── config/
│           │   └── module.config.php      # Routes et services
│           └── src/
│               ├── Controller/
│               │   └── ApiController.php  # API de recherche (alternative)
│               └── Service/
│                   ├── TextAiService.php      # Communication Ollama
│                   └── MatchingService.php    # Logique de matching IA
│
└── DevinciLostFound/                     # Frontend
    ├── apiOmk.html                        # Interface utilisateur
    ├── start.sh                           # Script de lancement automatique
    ├── modules/
    │   ├── omk.js                         # Client API Omeka-S
    │   └── authParams.js                  # Configuration API (URL Omeka)
    └── assets/                            # CSS & images
```

### Flux de Données

#### Création d'Objet (Bouton Gauche "Envoyer à Omeka-S")
```
1. Frontend (port 8085) → Omeka-S API (http://localhost/omk_thyp_25-26_clone/api/items)
2. Item créé dans DB
3. Module DescriptionWithAI écoute l'événement api.create.post
4. TextAiService → Ollama IA (localhost:11434)
5. Résumé IA généré et sauvegardé dans item.o:data
```

#### Recherche d'Objet (Bouton Droit "Rechercher")
```
1. Frontend (port 8085) → Module API standalone (http://localhost:8083)
2. api.php bootstrap Omeka-S et appelle MatchingService
3. MatchingService récupère tous les objets trouvés (max 10)
4. Pour chaque objet : TextAiService → Ollama IA pour comparaison
5. Calcul de score de similarité (0-100)
6. Retour du meilleur match (>50%) ou suggestion aléatoire
7. Frontend affiche résultat avec coordonnées du trouveur
```

### Pourquoi api.php Standalone ?

Le fichier `api.php` dans le module contourne les restrictions de routing d'Omeka-S :
- **Problème** : `.htaccess` d'Omeka bloque l'accès direct aux routes du module
- **Solution** : API standalone qui bootstrap Omeka et appelle les services du module
- **Avantage** : Module reste portable pour le professeur
- **Fonctionnement** : PHP built-in server (`php -S localhost:8083 api.php`)

## 🛠️ APIs Disponibles

### 1. Recherche Intelligente (Module API)
```bash
POST http://localhost:8083
Content-Type: application/json

{
  "title": "Portefeuille",
  "description": "Portefeuille noir en cuir avec cartes"
}
```

**Réponse :**
```json
{
  "matchFound": true,
  "itemId": 105,
  "score": 85,
  "explanation": "Match trouvé par analyse IA",
  "isRandomSuggestion": false,
  "title": "Portefeuille Test",
  "description": "...",
  "finderPhone": "06 12 34 56 78",
  "finderName": "Jean Dupont",
  "placeFound": "Salle A200",
  "dateFound": "2025-12-08 14:30:00"
}
```

**Note :** Cette API fonctionne uniquement avec des requêtes POST. Un GET direct retourne `{"error": "Method not allowed"}`.

### 2. Création d'Objet (Omeka-S Standard)
```bash
POST http://localhost/omk_thyp_25-26_clone/api/items
Content-Type: application/json
Key-Identity: gWaqHYnwYbVmwFToXWXTaVXCKPdT3lnp
Key-Credential: rZDAzH9MAAH3XjZE17xUxHu7rKQyOpSA

{
  "dcterms:title": [{"type": "literal", "@value": "Mon objet"}],
  "dcterms:description": [{"type": "literal", "@value": "Description..."}]
}
```

### 3. Liste des Propriétés Omeka
```bash
GET http://localhost/omk_thyp_25-26_clone/api/properties?vocabulary_prefix=dcterms
```

## Configuration

### Clés API Omeka-S

Fichier : `/DevinciLostFound/modules/authParams.js`
```javascript
export const pa = {
    apiOmk: 'http://localhost/omk_thyp_25-26_clone/api/',  //  Chemin Omeka-S
    ident: 'gWaqHYnwYbVmwFToXWXTaVXCKPdT3lnp',
    key: 'rZDAzH9MAAH3XjZE17xUxHu7rKQyOpSA'
};
```

** Important :** Si vous déplacez Omeka-S vers un autre chemin, mettez à jour `apiOmk` ici.

** Important :** Si vous changez le nom du dossier Omeka-S, mettez à jour aussi `start.sh`.

### Configuration Ollama

Fichier : `/omk_thyp_25-26_clone/modules/DescriptionWithAI/src/Service/TextAiService.php`
```php
private $ollamaUrl = "http://localhost:11434/api/generate";
private $model = "llama2";  // Modèle utilisé
```

Paramètres optimisés pour la vitesse :
- `temperature`: 0.1 (plus cohérent)
- `num_predict`: 50 (réponses courtes)
- `num_ctx`: 512 (contexte réduit)
- `timeout`: 60 secondes

## Dépannage

### Problème : "Cross-Origin Request Blocked" ou "NetworkError"
**Cause :** Les serveurs ne sont pas démarrés ou sur les mauvais ports  
**Solution :**
```bash
# Vérifier les serveurs
lsof -i :8083  # API doit être actif
lsof -i :8085  # Frontend doit être actif

# Redémarrer proprement
pkill -f "php -S localhost:8083"
pkill -f "python3 -m http.server 8085"
cd DevinciLostFound
./start.sh
```

### Problème : "Error response 404" sur http://localhost:8085/apiOmk.html
**Cause :** Le serveur Python n'est pas dans le bon dossier  
**Solution :** Vérifier que `start.sh` utilise `$SCRIPT_DIR` correctement (ligne 41-42)

### Problème : Boutons non cliquables, console montre erreur Omeka API
**Cause :** Mauvais chemin vers Omeka-S dans authParams.js  
**Solution :**
```bash
# Éditer le fichier
nano DevinciLostFound/modules/authParams.js
# Vérifier que apiOmk correspond à votre installation
apiOmk: 'http://localhost/omk_thyp_25-26_clone/api/'

# Puis faire hard refresh dans le navigateur : Cmd+Shift+R
```

### Problème : "Erreur serveur: JSON.parse"
**Solution :** Vérifier qu'Ollama est démarré
```bash
ollama serve
```

### Problème : "AI service unavailable"
**Solution :** Vérifier que le modèle llama2 est installé
```bash
ollama list
ollama pull llama2
```

### Problème : Recherche très lente (>2 minutes)
**Cause :** Trop d'objets dans la base de données  
**Solution :** Le module limite automatiquement à 10 objets récents

### Problème : Toujours des suggestions aléatoires (fond jaune)
**Cause :** Ollama ne répond pas ou scores trop bas  
**Solution :** 
1. Vérifier les logs de l'API :
```bash
tail -f /tmp/search-api.log
```
2. Tester Ollama manuellement :
```bash
curl -X POST http://localhost:11434/api/generate -d '{
  "model": "llama2",
  "prompt": "Say only: OK",
  "stream": false
}'
```

### Problème : Module DescriptionWithAI pas visible dans Omeka
**Solution :**
1. Vérifier les permissions du dossier module
2. Vider le cache Omeka-S
3. Dans admin : Modules → Refresh
4. Vérifier les logs : `tail -f omk_thyp_25-26_clone/logs/application.log`

## Monitoring

### Logs Module
```bash
# Logs Omeka-S
tail -f /omeka-s/logs/application.log
```

### Logs Ollama
```bash
# Voir les requêtes en temps réel
ps aux | grep ollama
```

## Optimisations

### Performance IA
-  Limite de 10 objets maximum par recherche
-  Comparaison one-by-one au lieu de batch
-  Timeout réduit à 60s (échec rapide)
-  Prompts simplifiés
-  Early exit si score >90%

### Fallback Intelligence
- Si IA échoue → Suggestion aléatoire
- Si aucun match >50% → Suggestion aléatoire
- Interface affiche clairement les suggestions (fond jaune)

## Exemple de Workflow Complet

### Étape 1 : Préparation
```bash
# Terminal 1 : Démarrer Ollama
ollama serve

# Terminal 2 : Vérifier qu'Omeka-S est accessible
curl -I http://localhost/omk_thyp_25-26_clone/

# Terminal 3 : Démarrer les serveurs
cd DevinciLostFound
./start.sh
```

### Étape 2 : Ajouter un Objet Trouvé
1. Ouvrir http://localhost:8085/apiOmk.html
2. Panel gauche, remplir :
   - **Titre** : Portefeuille noir
   - **Description** :
     ```
     Description: Portefeuille en cuir noir avec carte VISA
     Telephone: 06 12 34 56 78
     Trouve par: Marie Dupont
     Lieu: Cafétéria - Table 5
     ```
3. Cliquer **"Envoyer à Omeka-S"**
4.  Objet créé avec ID 107 (par exemple)
5.  IA génère automatiquement un résumé

### Étape 3 : Rechercher un Objet Perdu
1. Panel droit, remplir :
   - **Titre** : Mon portefeuille
   - **Description** : J'ai perdu mon portefeuille noir hier à la cafétéria
2. Cliquer **"🔍 Rechercher"**
3.  Attendre 30-90 secondes (animation de chargement)
4.  Résultat affiché :
   - Score : 85%
   - Contact : Marie Dupont - 06 12 34 56 78
   - Lieu : Cafétéria - Table 5

### Étape 4 : Tester le Fallback
1. Rechercher quelque chose qui n'existe pas :
   - **Titre** : Licorne magique
   - **Description** : Une licorne rose avec des paillettes
2. Suggestion aléatoire affichée (fond jaune)
3. Message : "Aucune correspondance exacte trouvée. Voici une suggestion..."

## Déploiement pour le Professeur

### Fichiers à Fournir
```
1. Module : omk_thyp_25-26_clone/modules/DescriptionWithAI/
2. Frontend : DevinciLostFound/
3. Base de données : Export SQL avec objets tests
4. Documentation : Ce fichier HOW_TO_RUN.md
```

### Instructions pour le Professeur
```bash
# 1. Installer le module dans son Omeka-S
cp -r DescriptionWithAI /chemin/vers/son/omeka/modules/

# 2. Activer le module dans l'interface admin

# 3. Mettre à jour authParams.js avec son URL Omeka
nano DevinciLostFound/modules/authParams.js
# Changer apiOmk vers son installation

# 4. Mettre à jour start.sh avec le chemin vers son Omeka
nano DevinciLostFound/start.sh
# Ligne 36 : modifier le chemin

# 5. Lancer l'application
cd DevinciLostFound
./start.sh

# 6. Tester à http://localhost:8085/apiOmk.html
```

## Crédits

**Projet réalisé par :** [Votre nom]  
**Module Omeka-S :** DescriptionWithAI  
**Technologies :** Omeka-S 4.1.1, Ollama AI (llama2), PHP 8+, Python 3, JavaScript ES6  
**Date :** Décembre 2025

---

## Support

Pour toute question ou problème :
1. Vérifier cette documentation
2. Consulter les logs : `/tmp/search-api.log`
3. Tester Ollama : `curl http://localhost:11434/api/tags`
ollama serve

# Terminal 2 : Démarrer Frontend
cd /Users/karim/Desktop/ProjetNewDepart/DevinciLostFound
python3 -m http.server 8085

# Navigateur
open http://localhost:8085/apiOmk.html

# Test rapide
# Gauche : Créer un objet "Portefeuille noir"
# Droite : Chercher "portefeuille noir en cuir"
# Résultat : Match trouvé avec score ~85%
```

## Structure du Code

### Module DescriptionWithAI

**Module.php** :
- Écoute l'événement `api.create.post` sur les items
- Déclenche l'analyse IA automatique

**TextAiService** :
- `summarizeText()` : Génère résumé d'objet trouvé
- `queryAI()` : Requêtes IA génériques avec Ollama

**MatchingService** :
- `findMatchingObjects()` : Recherche et compare objets
- `compareItemWithAI()` : Compare 2 descriptions (0-100)
- `formatMatchResult()` : Formate réponse API
- `extractContactInfo()` : Extrait tel/nom/lieu

**ApiController** :
- `matchLostObjectAction()` : Route `/api/match-lost-object`
- `foundObjectsAction()` : Route `/api/found-objects`

---

## Fonctionnalités

-  Création d'objets trouvés avec photos
-  Génération automatique de descriptions IA
-  Recherche sémantique avec Ollama
-  Comparaison intelligente one-by-one
-  Fallback vers suggestions aléatoires
-  Extraction automatique des contacts
-  Interface bilingue (FR)
-  Animations de chargement
-  Responsive design

---

**Créé par Karim - DevInci Lost & Found 2025** 🎓
