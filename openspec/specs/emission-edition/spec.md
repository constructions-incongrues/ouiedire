# emission-edition Specification

## Purpose
Définit ce qu'un mainteneur peut faire depuis l'outil d'édition en ligne des émissions : ce qui est éditable, ce qui reste hors de sa portée, comment il s'authentifie, et quels garde-fous empêchent de publier une émission incomplète.

## Requirements

### Requirement: Édition d'une émission existante

L'outil d'édition MUST permettre de modifier l'ensemble des champs éditoriaux d'une émission existante : titre, auteurices, date de diffusion, type, visibilité publique, description et playlist.

L'outil MUST exposer la playlist entrée par entrée, chaque entrée présentant séparément son repère temporel, son artiste et son titre. Un mainteneur MUST NOT avoir à saisir de balisage pour ajouter, retirer ou réordonner une entrée.

Le système MUST enregistrer toute modification dans l'historique de version du dépôt, attribuée à la personne qui l'a effectuée.

#### Scenario: Correction d'un titre
- **WHEN** un mainteneur corrige le titre d'une émission publiée et enregistre
- **THEN** la modification est écrite dans le dépôt sous son identité, et la page publique reflète le nouveau titre

#### Scenario: Ajout d'une entrée de playlist
- **WHEN** un mainteneur ajoute une entrée en renseignant un repère temporel, un artiste et un titre
- **THEN** l'entrée apparaît à sa place dans la playlist publiée, sans qu'aucun balisage n'ait été saisi

#### Scenario: Réordonnancement d'entrées
- **WHEN** un mainteneur déplace une entrée dans la playlist
- **THEN** l'ordre publié reflète le nouvel ordre

#### Scenario: Basculement de visibilité
- **WHEN** un mainteneur bascule la visibilité d'une émission dont l'audio est disponible
- **THEN** l'émission apparaît ou disparaît des listes publiques en conséquence

### Requirement: Création d'une émission depuis l'outil d'édition

L'outil d'édition MUST permettre de créer une émission, et cette création MUST produire une émission dont le contenu éditorial est complet.

L'identifiant de l'émission créée MUST respecter la forme `<type>-<numéro>`, et MUST être saisi explicitement : l'outil MUST NOT le dériver du titre ni d'aucune autre valeur libre.

L'outil ne peut pas valider cette forme par configuration, faute de quoi il faudrait dupliquer l'identité de l'émission dans son contenu. Une émission dont l'identifiant ne respecte pas la forme MUST donc rester invisible du public plutôt que de s'afficher incorrectement.

Le circuit de création existant, qui produit le squelette complet d'une émission, MUST rester disponible : la création depuis l'outil d'édition en est un second chemin, non un remplacement.

#### Scenario: Création conforme
- **WHEN** un mainteneur crée une émission de type `Ailleurs` portant le numéro `332`
- **THEN** l'émission est enregistrée sous l'identifiant `ailleurs-332` et devient résolvable

#### Scenario: Identifiant saisi explicitement
- **WHEN** un mainteneur crée une émission
- **THEN** un champ d'identifiant obligatoire lui est présenté, et l'identifiant n'est à aucun moment déduit du titre

#### Scenario: Identifiant non conforme
- **WHEN** une émission est enregistrée avec un identifiant ne respectant pas la forme `<type>-<numéro>`
- **THEN** elle n'est résolvable par aucune page et n'apparaît dans aucune liste publique, jusqu'à ce que son dossier soit renommé

#### Scenario: Émission créée sans audio
- **WHEN** une émission vient d'être créée depuis l'outil d'édition et qu'aucun audio n'a encore été déposé
- **THEN** elle reste invisible du public, même si sa visibilité a été mise à vrai

### Requirement: Dépôt d'une image de couverture

L'outil d'édition MUST permettre de déposer une image de couverture sans contrainte de nommage.

Le dépôt d'une image supplémentaire MUST NOT modifier la vignette de partage d'une émission qui possède déjà une couverture conforme à la convention historique.

#### Scenario: Dépôt sur une émission sans couverture
- **WHEN** un mainteneur dépose une image sur une émission qui n'en avait aucune
- **THEN** cette image devient la couverture affichée et la vignette de partage

#### Scenario: Dépôt sur une émission déjà pourvue
- **WHEN** un mainteneur dépose une image sur une émission possédant déjà une couverture conforme à la convention historique
- **THEN** l'image s'ajoute à l'affichage et la vignette de partage reste inchangée

### Requirement: L'hébergement audio reste hors de l'outil d'édition

L'outil d'édition MUST NOT gérer le dépôt des fichiers audio, qui restent déposés séparément sur leur hébergement dédié.

L'absence d'audio MUST NOT empêcher la création ni l'édition du contenu éditorial d'une émission.

#### Scenario: Édition d'une émission sans audio
- **WHEN** un mainteneur édite la description et la playlist d'une émission dont l'audio n'a pas encore été déposé
- **THEN** ses modifications sont enregistrées normalement, l'émission restant invisible du public

### Requirement: Authentification sans service tiers

L'accès à l'outil d'édition MUST être authentifié par les identifiants du mainteneur sur la forge hébergeant le dépôt.

L'authentification MUST NOT exiger de service supplémentaire à héberger ou à maintenir.

Un visiteur non authentifié MUST NOT pouvoir lire l'outil d'édition ni écrire dans le dépôt.

#### Scenario: Accès authentifié
- **WHEN** un mainteneur s'authentifie avec ses identifiants de forge
- **THEN** il accède à la liste des émissions et peut les éditer

#### Scenario: Accès non authentifié
- **WHEN** un visiteur ouvre l'adresse de l'outil d'édition sans s'authentifier
- **THEN** aucun contenu du dépôt ne lui est accessible et aucune écriture n'est possible

### Requirement: Une seule configuration d'outil d'édition

Le dépôt MUST NOT contenir plus d'une configuration d'outil d'édition de contenu. Toute configuration antérieure devenue inactive MUST être supprimée.

#### Scenario: Absence de configuration résiduelle
- **WHEN** on inspecte le dépôt après la mise en place de l'outil d'édition
- **THEN** aucune configuration d'un outil d'édition antérieur n'y subsiste

### Requirement: Accès à l'édition depuis la page d'émission

Chaque page d'émission MUST proposer un accès direct à l'édition de cette émission dans l'outil d'édition, et MUST NOT renvoyer vers l'édition de fichier brut sur la forge.

#### Scenario: Lien d'édition
- **WHEN** un mainteneur suit le lien d'édition depuis la page d'une émission
- **THEN** l'outil d'édition s'ouvre sur cette émission
