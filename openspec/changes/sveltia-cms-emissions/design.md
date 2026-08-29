# Design — Édition des émissions via Sveltia CMS

## Context

Voir [proposal.md](proposal.md) pour la motivation.

Contraintes qui façonnent l'approche :

- **Sveltia impose une entrée = un fichier.** Confirmé par la documentation : *« Each entry in the collection is represented by a separate file. »* Le motif « page bundle » (`path: '{{slug}}/index'` + `media_folder: ''`) est le seul moyen documenté d'avoir un dossier par entrée.
- **Le stack est figé.** PHP 7.4, Silex (mort depuis 2018), `zendframework/*` (abandonné), aucun test. Toute modification doit être vérifiable par comparaison de sortie, pas par une suite de tests.
- **`bootstrap.php` renvoie 404 si l'un des trois fichiers manque** ([:164-167](../../../src/src/bootstrap.php:164)). Un dossier à moitié créé est une page cassée silencieuse.
- **Le dépôt est lourd** : `.git` à 682 Mo, 143 Mo de covers versionnées, des PNG à 3,2 Mo.

## Goals / Non-Goals

**Goals :**

- Une émission = un fichier éditable en un seul écran.
- La playlist devient une donnée structurée, plus du HTML tapé à la main.
- Sortie HTML publique inchangée : la migration ne doit rien modifier de visible.
- Zéro dépendance ajoutée, zéro service à héberger.
- Une émission peut être créée depuis le CMS (`index.json` + cover) ; le MP3 reste déposé séparément.

**Non-Goals :**

- Corriger la dette sémantique artiste/titre. La migration la reproduit fidèlement.
- Remplacer intégralement le circuit de publication : la GitHub Action + cookiecutter reste disponible, la création via CMS est un chemin additionnel pour le contenu et la cover.
- Gérer l'upload du MP3 depuis le CMS — reste externe (Nextcloud).
- Alléger le dépôt. Problème réel mais orthogonal.

## Decisions

### 1. `index.json`, pas `index.md` à frontmatter

`json_decode` est déjà utilisé ([:170](../../../src/src/bootstrap.php:170)). Aucune dépendance, aucun format nouveau à apprendre, aucun piège de sérialisation sur des chaînes contenant du HTML.

*Alternative écartée :* `index.md` + frontmatter YAML. `symfony/yaml` est présent en transitif, donc techniquement disponible — mais le frontmatter n'apporte un gain que si le corps est du Markdown, ce qu'il n'est pas (voir décision 2). Il ajouterait un format sans rien simplifier.

Chemin : `folder: src/public/assets/emission`, `path: '{{slug}}/index'`, `format: json`, `extension: json`. Le dernier segment du gabarit devient le nom de fichier ; le slug (`ailleurs-331`) devient le dossier.

### 2. La description reste du HTML brut, en champ `text`

Elle est rendue en `|raw` ([emission.html.twig:91](../../../src/views/emission.html.twig:91)) et injectée telle quelle dans le flux RSS ([:511](../../../src/src/bootstrap.php:511)). La stocker en chaîne JSON la préserve à l'octet près.

*Alternative écartée :* convertir 367 descriptions en Markdown. Cela demanderait une conversion HTML → Markdown non réversible **et** l'ajout d'un parseur Markdown en PHP, pour un contenu que personne ne réécrit.

### 3. Playlist : deux formes de stockage, et une échappatoire par entrée

Mesuré sur les 367 émissions : **la playlist n'est pas toujours une liste de morceaux**. Trente-cinq émissions contiennent autre chose — un message expliquant que le DJ n'a pas transmis sa tracklist, de l'ASCII art, des crédits, une rampe de `font-size` volontaire, une numérotation délibérée (`<ol class="number">`), ou rien du tout.

Le champ `playlist` prend donc deux formes :

| forme | émissions | contenu |
|---|---:|---|
| liste d'entrées structurées | 332 | 7052 entrées, dont 294 préservées telles quelles (4,2 %) |
| fragment HTML verbatim | 35 | conteneur non standard, message, mise en forme délibérée, ou aucune liste |

Une émission bascule en verbatim si, et seulement si, son conteneur n'est pas `<ol class="mejs-smartplaylist-playlist">`, si elle ne contient aucune ligne, ou si ses `<li>` portent des attributs.

Au sein d'une playlist structurée, chaque entrée relève de :

| niveau | condition | résultat |
|---|---|---|
| **A** | repère + `<span>` adjacent + ` - ` + titre | `{time, artist, title}` |
| **B** | repère + `<span>` adjacent, rien après | `{time, artist, title: null}` |
| **C** | tout le reste | `{raw: "<contenu du li tel quel>"}` |

`title` est stocké comme **fragment HTML** : les titres décorés d'une emphase conservent leur balisage et sont rendus en `|raw`. `artist` et `time` sont stockés en texte et échappés au rendu, ce qui reproduit les entités du source (`&amp;`, `&gt;` de `Beak>`).

**Pourquoi deux échappatoires et pas une.** L'échappatoire par émission seule ferait basculer 83 émissions en verbatim, dont une trentaine pour une seule ligne fautive. L'échappatoire par entrée seule obligerait à stocker aussi le conteneur de chaque émission, et ne saurait pas quoi faire des huit playlists sans liste. Chacune est placée là où la mesure la justifie.

**Normalisation préalable.** 701 lignes dans 174 émissions ont été normalisées dans le HTML source — séparateur absent, balise orpheline, tiret collé au titre, attributs superflus sur le lien de temps — pour que l'aller-retour soit exact sur les 7052 entrées structurées. Vérifié : 100 %.

*Alternative écartée :* champ unique `{time, label}` sans séparer l'artiste. Deux fois moins risqué à migrer, mais supprime `/artists` — écarté sur décision explicite.

### 4. La cover est éditable depuis le CMS, via un glob assoupli

Sveltia ne sait pas nommer un fichier téléversé selon un gabarit : un fichier déposé via son champ image garde son nom d'origine. `getShow()` trouve aujourd'hui les covers par convention de nom (`*_cover-*.*`, [:242](../../../src/src/bootstrap.php:242)) — ce glob doit s'assouplir pour accepter toute image du dossier, faute de quoi une cover téléversée par le CMS resterait invisible.

Assouplir le glob fait remonter le bruit déjà présent dans les dossiers : `unnamed.jpg`, `buveete.jpeg`, `carton tendron.jpg`, `eat_cheering.gif`, `A_EFFACER`, quatre `.DS_Store`, et une famille `*_cover_hd-*` qui ne suit pas la convention (dont `ailleurs-222/` qui contient des covers numérotées `ailleurs-221`). **Décision assumée : la migration nettoie exhaustivement le bruit sur les 367 dossiers**, en préalable à l'assouplissement.

Le nettoyage ne peut pas aller jusqu'à « une seule image par dossier » : [emission.html.twig:87](../../../src/views/emission.html.twig:87) rend **toutes** les covers (`{% for urlCover in show.covers %}`), et sept émissions en affichent légitimement deux ou trois — `ailleurs-186`, `-221`, `-222`, `-80`, `-97`, `bagage-7`, `ouiedire-3`. Les réduire à une seule image retirerait du contenu visible.

L'ordre doit donc être décidé, pas subi. `covers[0]` alimente `og:image` ([emission.html.twig:20](../../../src/views/emission.html.twig:20)) et le flux RSS ([:511](../../../src/src/bootstrap.php:511), [:635](../../../src/src/bootstrap.php:635)) **sans aucune garde**, et le `Finder` ne trie pas : sans règle, l'image de partage d'une émission à plusieurs covers est tirée au sort par le système de fichiers. La règle retenue :

1. Filtrage par extension d'image (`png`, `jpg`, `jpeg`, `gif`, `webp`).
2. Tri déterministe : les fichiers suivant la convention `*_cover-*` d'abord, le reste ensuite, par ordre alphabétique dans chaque groupe. La cover historique reste ainsi `covers[0]` partout où elle existe, et une image téléversée depuis le CMS s'ajoute derrière sans voler la vignette de partage.
3. `covers[0]` garanti non vide par une image de repli.

Le point 3 corrige un bug latent aujourd'hui actif : `ailleurs-188/` et `ailleurs-42/` sont `isPublic: true` sans aucune cover conforme, donc `covers[0]` y est un index indéfini dans le flux RSS.

**Conséquence** : cette décision retire la garantie de la version précédente, qui maintenait `bootstrap.php` au strict périmètre de la playlist — `getShow()` (`:242`) entre désormais dans le périmètre du change.

*Alternative écartée :* garder la cover hors CMS (version précédente de cette décision). Cohérente et minimale, mais contredit l'exigence de pouvoir créer une émission complète depuis le CMS.

### 5. `create: true` sur la collection — MP3 excepté

Une émission créée depuis le CMS obtient un `index.json` (décision 1) et une cover (décision 4 révisée). Le MP3 reste hors du CMS : Sveltia n'a pas vocation à gérer l'hébergement audio, qui reste sur Nextcloud et doit être déposé séparément par le mainteneur.

**Le garde-fou existe déjà dans le code :** sans MP3 ni FLAC, `isPublic` est forcé à faux ([:232-234](../../../src/src/bootstrap.php:232)). Entre la création de l'entrée et le dépôt de l'audio, l'émission existe mais reste invisible du public, quelle que soit la valeur d'`isPublic` saisie dans le CMS. **Le CMS ne peut donc pas publier une émission sans audio.** C'est ce qui rend `create: true` sûr, et cela ne demande aucun code nouveau.

**Contrainte de nommage :** `getShow()` dérive le type et le numéro du nom de dossier par `explode('-', $id)` ([:145-154](../../../src/src/bootstrap.php:145)). Le slug produit par le CMS doit donc être exactement `<typeslug>-<numéro>`. Un slug libre — dérivé du titre, par exemple — casserait la résolution de l'émission.

La GitHub Action `emission.yml` + cookiecutter reste disponible en parallèle : le CMS n'est pas le seul chemin de création, il en devient un second, plus léger, pour le contenu et la cover.

### 6. Authentification par jeton personnel

Sveltia propose « Sign In with Token » : l'utilisateur colle un PAT GitHub dans une boîte de dialogue. Aucun serveur.

*Alternatives écartées :* le worker Cloudflare `sveltia-cms-auth` (héberger un service pour faciliter la maintenance est contre-productif à deux mainteneurs) ; le PKCE, non implémenté — la documentation indique attendre que GitHub le supporte pour les SPA, chantier mis en pause après une cible Q4 2025.

À reconsidérer uniquement si l'édition s'ouvre à des personnes à qui l'on ne veut pas expliquer ce qu'est un jeton.

### 7. Le rendu passe dans un partiel Twig, avec sortie identique pour contrat

Le `<ol>` est reconstruit par un partiel unique, inclus par [emission.html.twig:82](../../../src/views/emission.html.twig:82), [embed.html.twig:28](../../../src/views/embed.html.twig:28) et le flux RSS ([:511](../../../src/src/bootstrap.php:511)).

**Sur les 35 playlists verbatim, `getArtists()` continue de parser le DOM** : sans quoi leurs artistes disparaîtraient de `/artists`. La dépendance à `symfony/dom-crawler` subsiste donc, sur 10 % du catalogue.

**Contrat de recette :** pour les 367 émissions, le `<ol>` rendu après migration doit être identique à celui rendu avant, à la normalisation des espaces près. C'est la vérification qui remplace la suite de tests absente. L'invariant des 5462 artistes distincts en découle, mais reste utile parce qu'il se lit d'un coup d'œil. Il tolère exactement deux corrections, énumérées dans la spec, issues de balisage malformé corrigé en amont de la migration.

### 8. Un seul commit pour le contenu et le code

La migration des 367 fichiers et la modification de `bootstrap.php` doivent atterrir dans le même commit. Séparés, l'état intermédiaire est un site en 404 sur toutes les émissions. Corollaire : le rollback est un `git revert` unique.

## Risks / Trade-offs

**La dette artiste/titre devient visible et attribuée** → La migration la reproduit sans l'aggraver. Le champ `artist` la rendra lisible à l'édition, donc corrigeable au fil de l'eau. Aucune correction automatique n'est tentée : distinguer « Concheperla » (artiste) de « Marinera Norteña » (titre) demande de connaître la musique.

**Le classement A/B/C peut mal ranger des lignes** → Le script produit d'abord un rapport en lecture seule (compte par niveau et par émission) à relire avant d'écrire quoi que ce soit. Les totaux attendus sont connus : 6349 / 253 / 156 / 820.

**Sveltia peut être lent sur 367 entrées via l'API GitHub**, dans une arborescence chargée de 143 Mo d'images → Testable en une demi-heure avec un `config.yml` et un jeton, **avant** d'engager la migration. Si c'est inutilisable, la migration perd son débouché et le change s'arrête là — d'où sa place en première tâche.

**Trois dossiers déviants** (`ailleurs-16/` vide, `ailleurs-188/` et `ailleurs-42/` sans cover conforme) → À traiter avant migration pour que les totaux tombent juste, sinon le rapport de classement devient illisible.

**Le `.pages.yml` en racine** est un vestige d'une tentative PagesCMS abandonnée, pointant sur `src/_shows` qui est vide → À supprimer avec ce change, sous peine de laisser deux configurations de CMS contradictoires dans le dépôt.

**Le glob assoupli de `getShow()` peut remonter une image oubliée par le nettoyage** → Le nettoyage vise l'exhaustivité sur les 367 dossiers avant bascule. Le tri de la décision 4 borne le dégât : une image oubliée arrive après les `*_cover-*`, donc elle ne peut pas voler `covers[0]` — ni la vignette de partage, ni l'image du flux. Elle s'affiche en trop sur la page, ce qui se voit et se corrige.

**Une émission créée depuis le CMS reste sans MP3 le temps que le mainteneur le dépose** → Sans conséquence : `isPublic` est forcé à faux tant qu'aucun audio n'est lisible ([:232-234](../../../src/src/bootstrap.php:232)). L'émission est invisible du public jusqu'au dépôt. Vérifié dans le code, pas reporté à l'implémentation.

## Migration Plan

1. **Valider Sveltia d'abord.** `config.yml` + jeton, sur le contenu actuel non migré, en collection `manifest.json` seule. Vérifier que 367 entrées se listent et s'éditent à une vitesse acceptable. Si non, arrêter ici.
2. Nettoyer les trois dossiers déviants et, sur l'ensemble des 367 dossiers, le bruit d'images non conforme (`unnamed.jpg`, `.DS_Store`, `A_EFFACER`, doublons `*_cover_hd-*`, etc.) — préalable à l'assouplissement du glob de `getShow()` (décision 4). Les sept émissions à plusieurs covers légitimes sont conservées telles quelles : c'est le tri, pas le nettoyage, qui garantit `covers[0]`.
3. Capturer l'état de référence : les 367 `<ol>` rendus et la liste des 5462 artistes.
4. Exécuter le script de migration en lecture seule ; relire le rapport de classement A/B/C.
5. Migrer, modifier `bootstrap.php`, le partiel Twig et `getArtists()` — **un seul commit**.
6. Rejouer les deux vérifications. Le diff doit être vide.
7. Poser `src/public/admin/`, basculer le lien « EDIT » vers le CMS, supprimer `.pages.yml`.

**Rollback :** `git revert` du commit de migration. Aucune donnée hors dépôt n'est touchée — les MP3 sur Nextcloud et les covers ne bougent pas.

## Open Questions

- Faut-il conserver `bin/extract-artists` ? Il ne sert qu'au débogage et fonctionnera toujours après migration. Décidable à l'implémentation.
- Le champ `type` en widget `select` (Ailleurs / Bagage / Bureau / Ouïedire) ou en texte libre ? Cosmétique, sans effet sur le format stocké.
- Une passe de correction de la dette artiste/titre mérite-t-elle son propre change plus tard ? À trancher une fois qu'on aura vu l'ampleur réelle à l'usage.
