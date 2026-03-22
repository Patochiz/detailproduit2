# Module Planning Production pour Dolibarr

## Description

Module de gestion de planning de production avec interface hybride timeline et groupement des tâches par semaines, enrichi d'un tableau de gestion des matières premières.

## Fonctionnalités

### Planning de Production
- **Interface hybride** : Combinaison timeline + groupement
- **Drag & Drop** : Déplacement des cartes entre semaines et groupes
- **Cartes non planifiées** : Zone dédiée aux éléments à planifier
- **Édition en ligne** : Modification des cartes via modal
- **Statuts visuels** : Bordures et badges colorés selon les statuts
- **Navigation temporelle** : Navigation par semaine avec filtres
- **Permissions** : Lecture pour tous, écriture avec permission

### 🧱 Gestion des Matières Premières (NOUVEAU)
- **Tableau récapitulatif** : Stocks, commandes en cours et disponibilité
- **Calcul automatique** : Quantités utilisées dans les commandes actives
- **Édition en temps réel** : Modification des stocks avec sauvegarde automatique
- **Alertes visuelles** : Mise en évidence des stocks insuffisants
- **Correspondance intelligente** : Liaison automatique avec les matières des cartes
- **Configuration avancée** : Gestion complète via l'interface d'administration

## Prérequis

- Dolibarr 11.0 ou supérieur
- PHP 5.6 ou supérieur
- Module Commande activé
- Extrafields configurés (voir section Configuration)

## Installation

1. **Copier le module** dans le répertoire `/htdocs/custom/` de Dolibarr :
   ```
   /htdocs/custom/planningproduction/
   ```

2. **Activer le module** via l'interface Dolibarr :
   - Aller dans Configuration → Modules
   - Rechercher "Planning Production"
   - Cliquer sur "Activer"

3. **Vérifier les permissions** :
   - Configuration → Utilisateurs & Groupes → Groupes
   - Attribuer les permissions "Planning Production" aux groupes concernés

## Configuration des Extrafields

Le module nécessite les extrafields suivants (à créer si pas déjà existants) :

### Extrafields sur Commande (`llx_commande`)
- `version` (texte libre) - Version de la commande
- `ref_chantierfp` (texte libre) - Référence chantier
- `delai_liv` (texte libre) - Délai de livraison  
- `statut_ar` (sélection) - Statut AR avec valeurs :
  - `AR VALIDÉ,AR VALIDÉ`
  - `AR NON VALIDÉ,AR NON VALIDÉ`

### Extrafields sur Ligne de Commande (`llx_commandedet`)
- `matiere` (texte libre) - Matière utilisée
- `statut_mp` (sélection) - Statut MP avec valeurs :
  - `MP Ok,MP Ok`
  - `MP en attente,MP en attente`
  - `MP Manquante,MP Manquante`
  - `BL A FAIRE,BL A FAIRE`
  - `PROFORMA A VALIDER,PROFORMA A VALIDER`
  - `MàJ AIRTABLE à Faire,MàJ AIRTABLE à Faire`
- `statut_prod` (sélection) - Statut production avec valeurs :
  - `À PRODUIRE,À PRODUIRE`
  - `EN COURS,EN COURS`
  - `À TERMINER,À TERMINER`
  - `BON POUR EXPÉDITION,BON POUR EXPÉDITION`
- `postlaquage` (sélection) - À peindre avec valeurs :
  - `oui,Oui`
  - `non,Non`

## Configuration des Matières Premières

### Première configuration
1. Aller dans **Configuration** → **Modules** → **Planning Production** → **Paramètres**
2. Défiler jusqu'à la section "**Gestion des Matières Premières**"
3. Ajouter vos codes MP principaux avec leur stock initial
4. Exemple de codes MP :
   - `400 BLANC` - Stock: 815
   - `400 RAL 9003` - Stock: 379
   - `300 RAL 7035` - Stock: 771
   - `11%1,5mm` - Stock: 224

### Import de données d'exemple
Pour importer les données d'exemple basées sur votre fichier Excel :
```sql
-- Exécuter dans phpMyAdmin ou équivalent
source /path/to/dolibarr/htdocs/custom/planningproduction/sql/data_example_matieres.sql
```

## Utilisation

### Accès au module
Le module est accessible via le menu principal "Planning Production" une fois activé.

### Interface principale

#### Zone Non Planifiées (gauche)
- Contient toutes les cartes des commandes validées non expédiées
- Produits manufacturés uniquement (`finished = 1`)
- Panneau réductible/extensible

#### Timeline (droite)
- Affichage par semaines configurables (3, 5 ou 8 semaines)
- Navigation semaine précédente/suivante
- Groupes de production par semaine

#### 🧱 Bouton Matières Premières (nouveau)
- Accès direct depuis l'interface principale
- Tableau récapitulatif avec colonnes :
  - **CODE MP** : Code de la matière première
  - **STOCK** : Quantité disponible (éditable)
  - **CDE EN COURS** : Calculé automatiquement
  - **RESTE** : Stock - Commandes en cours
  - **DATE MàJ** : Dernière modification
  - **ACTIONS** : Bouton "MàJ" pour recalculer

### Fonctionnalités Drag & Drop

#### Déplacer une carte
- **Vers un groupe existant** : Glisser sur le groupe souhaité
- **Créer un nouveau groupe** : Glisser sur "Nouveau Groupe"
- **Planifier dans semaine vide** : Glisser sur zone vide semaine
- **Déplanifier** : Glisser vers zone "Non Planifiées"

#### Supprimer une carte planifiée
- Cliquer sur le bouton 🗑️ de la carte planifiée
- La carte retourne automatiquement dans "Non Planifiées"

### Gestion des Matières Premières

#### Consultation des stocks
1. Cliquer sur le bouton "🧱 Matières Premières" dans l'interface principale
2. Le modal affiche le tableau avec toutes les matières configurées
3. Les stocks insuffisants (reste ≤ 0) sont mis en évidence en rouge

#### Modification des stocks
1. Cliquer dans le champ "STOCK" de la ligne souhaitée
2. Saisir la nouvelle valeur
3. La sauvegarde est automatique lors de la perte de focus
4. Le "RESTE" est recalculé automatiquement

#### Mise à jour des commandes en cours
1. Cliquer sur le bouton "MàJ" de la ligne souhaitée
2. Le système recalcule la somme des quantités des cartes contenant ce code MP
3. Seules les cartes avec statut "À PRODUIRE" et "EN COURS" sont comptabilisées

### Édition des cartes

#### Bouton d'édition ✏️
Permet de modifier :
- **Matière** : Texte libre (code MP + autres infos)
- **Statut MP** : Sélection parmi les valeurs configurées
- **Statut production** : À PRODUIRE, EN COURS, À TERMINER, BON POUR EXPÉDITION
- **À peindre** : Oui/Non (fond jaune fluo si Oui)

#### Statuts visuels
- **Bordure verte** : MP OK ET AR VALIDÉ
- **Bordure rouge** : Autres combinaisons
- **Fond jaune fluo** : Cartes à peindre
- **Badges colorés** : Statuts MP (vert/rouge) et AR (vert/rouge)

### Navigation et filtres

#### Filtres disponibles
- **Année** : Sélection année courante/précédente/suivante
- **Nombre de semaines** : 3, 5 ou 8 semaines
- **Semaine de départ** : Navigation avec boutons ◀▶
- **Client** : Filtre par client (à implémenter)
- **Recherche** : Texte libre (à implémenter)

#### Actions globales
- **⚙️ Configuration** : Accès aux paramètres du module
- **🧱 Matières Premières** : Tableau de gestion des stocks
- **📊 Export Global** : Export de tout le planning
- **🔄 Synchroniser** : Recharge les données depuis Dolibarr
- **Valider semaine** : Valide une semaine de planning
- **Export semaine** : Export d'une semaine spécifique

## Structure des données

### Table principale : `llx_planningproduction_planning`
```sql
- fk_commande (int) : Lien vers commande
- fk_commandedet (int) : Lien vers ligne de commande  
- semaine (int) : Numéro de semaine
- annee (int) : Année
- groupe_nom (varchar) : Nom du groupe
- ordre_groupe (int) : Ordre dans le groupe
- ordre_semaine (int) : Ordre du groupe dans la semaine
```

### 🧱 Nouvelle table : `llx_planningproduction_matieres`
```sql
- rowid (int) : ID technique
- code_mp (varchar) : Code matière première
- stock (decimal) : Stock disponible
- date_creation (datetime) : Date de création
- tms (timestamp) : Dernière modification
- fk_user_creat (int) : Utilisateur créateur
- fk_user_modif (int) : Dernier utilisateur modificateur
- entity (int) : Entité Dolibarr
```

### Logique métier
- Une ligne de commande ne peut être planifiée qu'une seule fois
- Les cartes non planifiées sont issues des commandes validées non expédiées
- Seuls les produits manufacturés sont concernés (`finished = 1`)
- **Nouveau** : Les commandes en cours sont calculées par recherche du code MP dans le champ `matiere`
- **Nouveau** : Les cartes "À TERMINER" et "BON POUR EXPÉDITION" n'apparaissent que dans les onglets

## Permissions

### Droits du module
- **planning read** : Lecture des plannings et matières premières
- **planning write** : Création/modification des plannings et stocks

### Attribution recommandée
- **Tous les utilisateurs** : planning read
- **Responsables production** : planning read + write
- **Administrateurs** : planning read + write

## Développement et personnalisation

### Fichiers principaux
- `planning.php` : Interface principale
- `ajax_planning.php` : Endpoints AJAX planning
- **`ajax_matieres.php`** : Endpoints AJAX matières premières (nouveau)
- `class/planningproduction.class.php` : Classe métier
- **`js/matieres.js`** : JavaScript gestion matières premières (nouveau)

### Structure des endpoints AJAX matières

#### `ajax_matieres.php`
- `action=get_matieres` : Récupérer toutes les matières avec calculs
- `action=update_stock` : Modifier le stock d'une matière
- `action=update_cde_en_cours` : Recalculer les commandes en cours
- `action=create_matiere` : Créer une nouvelle matière
- `action=update_matiere` : Modifier une matière complète
- `action=delete_matiere` : Supprimer une matière

### Personnalisation CSS
Les styles pour les matières premières sont intégrés dans `planning.php`. Pour personnaliser :
1. Modifier les styles dans la section "Styles pour le modal des matières"
2. Ou créer un fichier CSS dédié dans `/css/matieres.css`

### Ajout de fonctionnalités matières premières
- Modifier la classe `PlanningProduction` pour la logique métier
- Ajouter des endpoints dans `ajax_matieres.php` pour les interactions
- Étendre le JavaScript `matieres.js` pour les nouvelles interactions

## Dépannage

### Problèmes courants

#### Les cartes n'apparaissent pas
- Vérifier que les commandes sont validées (`fk_statut = 1`)
- Vérifier que les produits sont manufacturés (`finished = 1`) 
- Vérifier les extrafields configurés

#### Le tableau des matières premières ne se charge pas
- Vérifier que la table `llx_planningproduction_matieres` existe
- Vérifier les permissions sur le fichier `ajax_matieres.php`
- Vérifier les logs PHP/Apache pour les erreurs
- Tester l'endpoint : `/custom/planningproduction/ajax_matieres.php?action=get_matieres`

#### Les calculs de "CDE EN COURS" sont incorrects
- Vérifier que les extrafields `matiere` et `statut_prod` sont correctement configurés
- S'assurer que les codes MP dans le champ `matiere` correspondent exactement
- Les cartes "À TERMINER" et "BON POUR EXPÉDITION" sont exclues du calcul

#### Drag & Drop ne fonctionne pas
- Vérifier la console JavaScript pour les erreurs
- Vérifier les permissions d'écriture
- Vérifier le token CSRF

### Logs et debug
- Logs Dolibarr : `/documents/dolibarr.log`
- Console JavaScript : F12 dans le navigateur
- Logs AJAX matières : Vérifier les requêtes dans l'onglet Réseau des outils développeur
- Logs Apache/Nginx selon configuration serveur

## Changelog

### Version 1.0.0
- Interface hybride timeline + onglets
- Drag & drop des cartes
- Modal d'édition des cartes
- **🧱 Nouveau** : Gestion complète des matières premières
- **🧱 Nouveau** : Calcul automatique des commandes en cours
- **🧱 Nouveau** : Interface d'administration pour configurer les stocks
- **🧱 Nouveau** : Alertes visuelles pour les stocks insuffisants

## Support et contributions

Ce module est fourni en l'état. Pour les améliorations et corrections :

1. Créer une sauvegarde avant modification
2. Tester sur environnement de développement
3. Documenter les modifications apportées

## Licence

GPL v3 ou supérieure - Voir fichier COPYING dans le module.

---

**Module développé par Patrick Delcroix**
*Version 1.0.0 - Avec gestion avancée des matières premières*
