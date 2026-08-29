## Purpose

Définit comment le contenu d'une émission est stocké, identifié et restitué : un fichier unique par émission, une playlist structurée, des règles de sélection d'images de couverture, et les invariants que la migration du contenu existant doit préserver.

## ADDED Requirements

### Requirement: Stockage d'une émission en fichier unique

Le système MUST stocker le contenu éditorial d'une émission dans un unique fichier `index.json`, placé dans le dossier de l'émission. Les fichiers `manifest.json`, `description.html` et `playlist.html` MUST NOT subsister.

Ce fichier MUST porter les champs suivants : `title`, `authors`, `releasedAt`, `type`, `isPublic`, `description` et `playlist`.

Le système MUST restituer le champ `description` tel quel, sans transformation : il s'agit d'un fragment HTML.

#### Scenario: Lecture d'une émission migrée
- **WHEN** une page d'émission est demandée
- **THEN** son titre, ses auteurices, sa date, son type, sa description et sa playlist sont lus depuis le seul `index.json` de son dossier

#### Scenario: Description contenant du balisage
- **WHEN** le champ `description` contient du HTML (`<p class="justify">`, liens, emphase)
- **THEN** ce balisage est restitué à l'identique sur la page et dans le flux de syndication, sans échappement ni conversion

### Requirement: Identification d'une émission par son dossier

Le système MUST identifier une émission par le nom de son dossier, de la forme `<type>-<numéro>`, et MUST pouvoir en dériver le type et le numéro.

Le système MUST NOT exposer publiquement une émission dont le nom de dossier ne respecte pas cette forme.

#### Scenario: Résolution d'un identifiant
- **WHEN** le dossier d'une émission se nomme `ailleurs-331`
- **THEN** l'émission est résolue avec le type `ailleurs` et le numéro `331`

#### Scenario: Nom de dossier non conforme
- **WHEN** un dossier ne respecte pas la forme `<type>-<numéro>`
- **THEN** l'émission n'est pas résolvable et n'apparaît dans aucune liste publique

### Requirement: Playlist structurée

Le champ `playlist` MUST prendre l'une des deux formes suivantes :

- **une liste ordonnée d'entrées structurées**, lorsque la playlist est une liste de morceaux ;
- **un fragment HTML conservé verbatim**, lorsqu'elle n'en est pas une — message tenant lieu de playlist, mise en forme délibérée, ou absence de liste.

Le système MUST NOT imposer la forme structurée à une playlist qui n'est pas une liste de morceaux.

Lorsque la playlist est structurée, chaque entrée MUST relever de l'une des trois formes suivantes :

- **repère temporel, artiste et titre** — l'entrée porte les trois informations ;
- **repère temporel et artiste** — le titre est absent ou vide ;
- **entrée préservée** — la ligne d'origine est conservée telle quelle, sans interprétation.

La conversion MUST préserver toutes les entrées : toute ligne non interprétable relève de la troisième forme.

Le système MUST restituer tel quel le balisage de mise en forme porté par un titre.

#### Scenario: Playlist qui est une liste de morceaux
- **WHEN** une playlist est une liste de morceaux dans le conteneur habituel
- **THEN** elle est stockée en entrées structurées, éditables séparément

#### Scenario: Playlist qui n'est pas une liste de morceaux
- **WHEN** une playlist contient un message, une mise en forme délibérée, ou aucune liste
- **THEN** son contenu est conservé verbatim et restitué à l'identique, sans tentative de structuration

#### Scenario: Entrée complète
- **WHEN** une entrée porte un repère temporel, un artiste et un titre
- **THEN** les trois informations sont accessibles séparément, et l'artiste alimente la liste des artistes

#### Scenario: Entrée sans titre exploitable
- **WHEN** une entrée porte un repère temporel et un artiste, mais aucun titre exploitable
- **THEN** l'entrée est conservée avec son repère temporel et son artiste, et le titre est vide

#### Scenario: Entrée non interprétable
- **WHEN** une ligne de playlist ne porte ni repère temporel ni artiste identifiable
- **THEN** son contenu d'origine est conservé intégralement et restitué tel quel, sans artiste associé

#### Scenario: Titre portant une mise en forme
- **WHEN** le titre d'une entrée contient une emphase
- **THEN** cette emphase est restituée à l'affichage

### Requirement: Restitution de la playlist inchangée

La conversion de la playlist MUST NOT produire de changement visible. Pour chacune des émissions existantes, le balisage de playlist restitué après migration MUST être identique à celui restitué avant, à la normalisation des espaces près.

Le système MUST restituer la même playlist sur la page d'émission, sur la page d'intégration et dans le flux de syndication.

#### Scenario: Comparaison avant et après migration
- **WHEN** on compare la playlist restituée pour les 367 émissions avant et après migration
- **THEN** la comparaison ne relève aucune différence hors espaces

#### Scenario: Cohérence entre les surfaces
- **WHEN** une même émission est consultée sur sa page, sur sa page d'intégration et dans le flux de syndication
- **THEN** la playlist restituée y est identique

### Requirement: Préservation de la liste des artistes

Le système MUST dériver la liste publique des artistes des entrées de playlist portant un artiste, et, pour une playlist conservée verbatim, des artistes que son balisage désigne — de sorte qu'aucune émission ne disparaisse de la page selon la forme de stockage retenue.

La migration MUST NOT ajouter, retirer ni renommer d'artiste, à deux exceptions près, énumérées ici. Toutes deux proviennent de balisage malformé où le `<span>` d'artiste engloutissait le titre du morceau, faute d'être refermé :

| avant | après |
|---|---|
| `fred finger - petit perroquet` | `fred finger` |
| `zutzut - jadea` | `zutzut` |

Hors ces deux corrections, l'ensemble des artistes distincts MUST être rigoureusement identique avant et après migration, soit 5462 entrées.

Toute autre divergence MUST faire échouer la migration.

La migration MUST NOT tenter de corriger les inversions artiste/titre présentes dans le contenu existant.

#### Scenario: Invariant de migration
- **WHEN** on compare l'ensemble des artistes distincts avant et après migration
- **THEN** les deux ensembles comptent 5462 entrées et ne diffèrent que par les deux corrections énumérées

#### Scenario: Divergence non prévue
- **WHEN** la comparaison révèle un artiste ajouté, retiré ou renommé hors des deux corrections énumérées
- **THEN** la migration est tenue en échec et n'est pas livrée

#### Scenario: Playlist verbatim alimentant les artistes
- **WHEN** une émission dont la playlist est conservée verbatim désigne des artistes dans son balisage
- **THEN** ces artistes figurent dans la liste publique, comme avant migration

#### Scenario: Entrée préservée sans artiste
- **WHEN** une entrée de playlist relève de la forme préservée
- **THEN** elle n'introduit aucun artiste dans la liste publique, comme c'était déjà le cas avant migration

#### Scenario: Inversion artiste/titre existante
- **WHEN** une entrée du contenu existant désigne un titre de morceau à la place de l'artiste
- **THEN** la migration reproduit cette valeur sans la modifier, et l'entrée reste corrigeable manuellement par la suite

### Requirement: Sélection et ordre des images de couverture

Le système MUST considérer comme couverture toute image présente dans le dossier d'une émission, quel que soit son nom, afin qu'une image déposée depuis l'outil d'édition soit prise en compte.

Le système MUST ordonner les couvertures de façon déterministe et indépendante du système de fichiers : les images suivant la convention de nommage historique MUST précéder les autres, chaque groupe étant ordonné alphabétiquement.

Le système MUST garantir l'existence d'une première couverture. Lorsqu'une émission ne comporte aucune image, une image de repli MUST en tenir lieu.

#### Scenario: Émission à plusieurs couvertures
- **WHEN** une émission comporte plusieurs images dont certaines suivent la convention historique
- **THEN** toutes sont affichées, et la première image conforme à la convention est retenue comme vignette de partage et comme image du flux de syndication

#### Scenario: Image déposée depuis l'outil d'édition
- **WHEN** une image est déposée sous un nom quelconque dans le dossier d'une émission qui possède déjà une couverture conforme
- **THEN** elle est affichée en supplément, sans remplacer la vignette de partage existante

#### Scenario: Émission sans aucune image
- **WHEN** une émission publique ne comporte aucune image
- **THEN** l'image de repli est utilisée comme vignette de partage et dans le flux de syndication, sans erreur ni page cassée

#### Scenario: Ordre reproductible
- **WHEN** la même émission est servie depuis deux environnements dont l'ordre de parcours des fichiers diffère
- **THEN** l'ordre des couvertures restitué est identique

### Requirement: Aucune publication sans audio

Le système MUST NOT rendre visible du public une émission dépourvue de fichier audio téléchargeable, quelle que soit la valeur de son champ `isPublic`.

#### Scenario: Émission complète mais sans audio
- **WHEN** une émission porte `isPublic` à vrai mais qu'aucun fichier audio n'est disponible
- **THEN** elle n'apparaît dans aucune liste publique ni dans le flux de syndication

#### Scenario: Dépôt de l'audio
- **WHEN** le fichier audio d'une émission portant `isPublic` à vrai devient disponible
- **THEN** l'émission devient visible du public sans autre intervention
