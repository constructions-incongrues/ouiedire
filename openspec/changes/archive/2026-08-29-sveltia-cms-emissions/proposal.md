# Édition des émissions via Sveltia CMS

## Why

Maintenir une émission publiée demande aujourd'hui d'éditer à la main, dans l'interface web de GitHub, trois fichiers de deux formats différents répartis dans un dossier : `manifest.json`, `description.html` et `playlist.html`. Le lien « EDIT » en bas de chaque page d'émission ([emission.html.twig:33](src/views/emission.html.twig:33)) ouvre du JSON brut.

La `playlist.html` est le point douloureux : c'est du HTML tapé à la main, avec une structure imposée que rien ne valide. La checklist de la PR de publication comporte une case « la liste de lecture est à jour et **bien formée** », ce qui dit assez que ça casse régulièrement.

Un CMS git-backed (Sveltia) résout l'édition, mais impose une contrainte : **une entrée = un fichier**. Le modèle de contenu actuel, à trois fichiers par émission, est donc l'obstacle réel — pas l'outil.

## What Changes

- **BREAKING** — Le contenu d'une émission passe de trois fichiers à un seul `index.json` par dossier. `manifest.json`, `description.html` et `playlist.html` disparaissent.
- La playlist de 332 émissions devient une liste structurée (`time`, `artist`, `title`) ; le `<ol>` est produit par un partiel Twig, plus par le fichier de contenu. Les 35 émissions dont la playlist n'est pas une liste de morceaux — message, mise en forme délibérée, absence de liste — la conservent en HTML verbatim.
- `getArtists()` lit directement le tableau pour les 332 émissions structurées, et continue de parser le DOM ([bootstrap.php:66-85](src/src/bootstrap.php:66)) pour les 35 dont la playlist est conservée verbatim. La dépendance à `symfony/dom-crawler` subsiste donc, sur 10 % du catalogue.
- Ajout de Sveltia CMS servi en statique depuis `src/public/admin/`, authentifié par jeton personnel GitHub — **aucun serveur, aucun proxy OAuth, aucune infrastructure ajoutée**.
- Migration ponctuelle des 367 émissions existantes par un script jetable.
- Suppression de `.pages.yml`, vestige d'une tentative PagesCMS abandonnée pointant sur `src/_shows` (vide) — deux configurations de CMS contradictoires ne doivent pas coexister.
- La création d'une émission devient possible depuis le CMS : `index.json` et cover peuvent y être posés directement (voir Design, décisions 4 et 5). La GitHub Action `emission.yml` + cookiecutter reste disponible en parallèle.
- **Hors périmètre** : l'upload du MP3 reste sur Nextcloud, déposé séparément par le mainteneur — le CMS ne gère pas l'hébergement audio.

## Capabilities

### New Capabilities

- `emission-contenu` : format de stockage d'une émission (fichier unique, champs obligatoires, playlist structurée), règles de rendu associées, et invariant de préservation des artistes lors de la migration.
- `emission-edition` : périmètre et garde-fous de l'édition d'une émission via CMS — ce qui est éditable, ce qui ne l'est pas, et comment la création s'y intègre.

### Modified Capabilities

Aucune : le dépôt ne contient pas encore de spécifications sous `openspec/specs/`.

## Impact

**Données** — 367 dossiers sous `src/public/assets/emission/`, soit 1101 fichiers fusionnés en 367. Un dossier incomplet connu (`ailleurs-16/`, aucun des trois fichiers) et deux sans cover conforme (`ailleurs-188/`, `ailleurs-42/`) à traiter avant migration. Le glob de cover assoupli (voir Design, décision 4) impose en plus un nettoyage exhaustif du bruit d'images non conforme sur l'ensemble des 367 dossiers.

**Playlists** — 7578 lignes `<li>` mesurées sur les 367 émissions :

| | émissions | entrées | |
|---|---:|---:|---|
| Playlist structurée | 332 | 7052 | dont 294 entrées préservées telles quelles (4,2 %) |
| Playlist conservée verbatim | 35 | — | conteneur non standard, message tenant lieu de playlist, ou aucune liste |

Une normalisation préalable du HTML source (701 lignes dans 174 émissions) rend l'aller-retour exact pour toutes les entrées structurées.

**Invariant de recette** : les **5462 artistes distincts** listés par `/artists` doivent être identiques avant et après migration, à deux corrections près — `fred finger - petit perroquet` et `zutzut - jadea`, deux entrées où un `<span>` non refermé faisait entrer le titre du morceau dans le nom de l'artiste. Elles sont énumérées dans la spec ; toute autre divergence fait échouer la migration. Les 820 lignes muettes ne produisent aucun artiste aujourd'hui et peuvent donc être dégradées sans perte.

**Code** — [bootstrap.php](src/src/bootstrap.php) : chargement (`:164-174`), playlist (`:252`), description (`:255`), `getArtists()` (`:66-85`) et ses trois appelants (`:411`, `:671`, `:719`), `getShow()` (`:242`, glob de cover assoupli), flux RSS (`:511`). Templates : [emission.html.twig:82](src/views/emission.html.twig:82) et [embed.html.twig:28](src/views/embed.html.twig:28).

**Dépendances** — Aucune ajoutée. `json_decode` est déjà en usage ([bootstrap.php:170](src/src/bootstrap.php:170)), Sveltia est un script chargé depuis un CDN.

**Risque principal** — Le champ `<span>` est traité comme l'artiste par `getArtists()`, mais la convention est inversée dans une part inconnue des lignes (dans la seule `ailleurs-331` : « Marinera Norteña », « Yo Perdí el Corazón » et « Piano Caliente A La Casa De Pepere » sont des titres classés comme artistes). La migration reproduit fidèlement cette dette sans l'aggraver ni la corriger ; elle la rend visible à l'édition, ce qui permettra de la résorber au fil de l'eau. Décision assumée : `/artists` est conservée telle quelle.
