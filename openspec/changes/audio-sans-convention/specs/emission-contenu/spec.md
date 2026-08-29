## ADDED Requirements

### Requirement: Découverte du fichier audio

Le système MUST considérer comme audio d'une émission tout fichier au format
attendu présent dans son dossier, quel que soit son nom. Le système MUST NOT
exiger qu'un fichier audio porte un nom dérivé du titre, des auteurices, du type
ou du numéro de l'émission.

Le système MUST chercher séparément chaque format proposé au téléchargement : un
fichier compressé et un fichier sans perte sont deux téléchargements distincts,
pas deux candidats pour un même rôle.

Lorsqu'un dossier comporte plusieurs fichiers d'un même format, le système MUST
retenir le même à chaque lecture, indépendamment de l'ordre de parcours du
système de fichiers : les fichiers suivant la convention de nommage historique
MUST précéder les autres, chaque groupe étant ordonné alphabétiquement.

#### Scenario: Audio déposé sous un nom libre
- **WHEN** un fichier audio est déposé dans le dossier d'une émission sous un nom quelconque
- **THEN** l'émission est proposée au téléchargement dans ce format

#### Scenario: Émission dont le dossier comporte plusieurs fichiers d'un même format
- **WHEN** une émission est consultée alors que son dossier comporte plusieurs fichiers d'un même format, dont un suivant la convention historique
- **THEN** le fichier conforme à la convention est celui qui est proposé au téléchargement

#### Scenario: Même émission servie depuis deux environnements
- **WHEN** la même émission est servie depuis deux environnements dont l'ordre de parcours des fichiers diffère
- **THEN** le fichier proposé au téléchargement est le même dans les deux cas

### Requirement: Nom canonique au téléchargement

Le système MUST proposer le fichier audio sous un nom canonique, dérivé du type,
du numéro, des auteurices et du titre de l'émission, quel que soit le nom que
porte le fichier stocké.

Ce nom MUST refléter les informations courantes de l'émission : lorsqu'un titre ou
une auteurice est corrigé, le nom proposé au téléchargement MUST suivre cette
correction.

#### Scenario: Téléchargement d'un fichier au nom libre
- **WHEN** une personne télécharge l'audio d'une émission dont le fichier stocké porte un nom quelconque
- **THEN** le fichier est enregistré sous le nom canonique de l'émission

#### Scenario: Téléchargement après correction du titre
- **WHEN** une personne télécharge l'audio d'une émission dont le titre vient d'être corrigé
- **THEN** le nom d'enregistrement reflète le titre corrigé

## MODIFIED Requirements

### Requirement: Aucune publication sans audio

Le système MUST NOT rendre visible du public une émission dépourvue de fichier audio téléchargeable, quelle que soit la valeur de son champ `isPublic`.

Une émission est dépourvue d'audio lorsque son dossier n'en contient aucun. Le
système MUST NOT traiter comme dépourvue d'audio une émission dont le dossier
contient un fichier au format attendu.

En conséquence, corriger le titre ou les auteurices d'une émission MUST NOT la
retirer du public.

#### Scenario: Émission complète mais sans audio
- **WHEN** une émission porte `isPublic` à vrai mais qu'aucun fichier audio n'est disponible
- **THEN** elle n'apparaît dans aucune liste publique ni dans le flux de syndication

#### Scenario: Dépôt de l'audio
- **WHEN** le fichier audio d'une émission portant `isPublic` à vrai devient disponible
- **THEN** l'émission devient visible du public sans autre intervention

#### Scenario: Correction du titre d'une émission publiée
- **WHEN** un mainteneur corrige le titre d'une émission publiée dont l'audio est disponible
- **THEN** l'émission reste visible du public et son audio reste téléchargeable

#### Scenario: Correction des auteurices d'une émission publiée
- **WHEN** un mainteneur corrige les auteurices d'une émission publiée dont l'audio est disponible
- **THEN** l'émission reste visible du public et son audio reste téléchargeable
