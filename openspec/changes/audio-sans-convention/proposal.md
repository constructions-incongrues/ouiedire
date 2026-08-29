# Découverte du fichier audio par balayage du dossier

## Why

`getShow()` ne cherche pas le fichier audio d'une émission : il **calcule** le nom
qu'il devrait porter — à partir du type, du numéro, des auteurices et du titre —
puis teste son existence ([bootstrap.php:227](../../../src/src/bootstrap.php:227)).
Sans correspondance, `isPublic` est forcé à faux et l'émission disparaît du site,
sans erreur ni trace.

**Corriger une coquille dans un titre dépublie donc l'émission.** Mesuré :

```
fichier présent : ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3.mp3

titre « La Pompa Chalor Vol 3 »   isPublic: oui   mp3: trouvé
titre « La Pompa Calor Vol 3  »   isPublic: NON   mp3: INTROUVABLE
```

Le piège préexiste, mais il fallait éditer `manifest.json` à la main sur GitHub
pour le déclencher. Le change `sveltia-cms-emissions` vient de le mettre à deux
clics, sur le champ le plus naturellement corrigé qui soit. Le cache HTTP diffère
la disparition, ce qui rend le diagnostic plus obscur encore.

Le nom exigé est par ailleurs indevinable : `slugify` supprime les accents au lieu
de les translittérer, si bien qu'`ailleurs-97` attend
`…_un-nouveau-dpart.mp3` pour un titre « Un nouveau départ ». Le dépôt a fini par
gagner une fonctionnalité pour compenser cela — un bouton « Copier le nom de
fichier attendu » ([emission.html.twig:63](../../../src/views/emission.html.twig:63)).

Enfin, l'objectif d'ouvrir le dépôt de l'audio aux contributeurs rend la contrainte
intenable : un DJ invité ne reproduira pas cette chaîne.

## What Changes

- Le fichier audio est **découvert** par balayage du dossier de l'émission, au lieu
  d'être calculé. La règle d'ordre reprend celle des couvertures : un fichier
  suivant la convention historique d'abord, puis l'ordre alphabétique.
- Le nom canonique cesse d'être une contrainte de stockage et devient une étiquette
  de téléchargement, imposée par l'attribut `download` du lien. Il reste calculé
  depuis le titre et les auteurices, donc il **suit** leurs corrections au lieu de
  s'y casser.
- **Suppression** de `slugDownload` ([bootstrap.php:242](../../../src/src/bootstrap.php:242))
  et du bouton « Copier le nom de fichier attendu » qu'il alimente : sans objet une
  fois le nom devenu libre.
- Périmètre de formats inchangé : MP3 et FLAC. Ouvrir à d'autres formats appartient
  au projet de soumission par les contributeurs, pas à celui-ci.

Aucune modification de contenu, aucune migration : les 367 émissions du dépôt
portent des fichiers conformes, que la règle « convention d'abord » sert à
l'identique.

## Capabilities

### New Capabilities

Aucune.

### Modified Capabilities

- `emission-contenu` : la découverte du fichier audio cesse de dépendre d'une
  convention de nommage. L'exigence « Aucune publication sans audio » demeure, mais
  la question qu'elle pose passe de « *ce* fichier existe-t-il ? » à « un audio
  est-il présent ? ». S'y ajoute la garantie qu'éditer le titre ou les auteurices
  d'une émission ne la dépublie pas.

**Dépendance d'ordonnancement** : cette capability n'existe pas encore dans
`openspec/specs/` — elle vit dans le change `sveltia-cms-emissions`, complet mais
non archivé. Le delta doit être écrit après cet archivage, sans quoi il porterait
sur une spec absente.

## Impact

**Code** — [bootstrap.php](../../../src/src/bootstrap.php) : calcul du nom
(`:227`, `:234`), test d'existence (`:244`, `:247`), `slugDownload` (`:242`).
[emission.html.twig](../../../src/views/emission.html.twig) : les trois boutons de
téléchargement (`:59`, `:61`, `:63`), dont un disparaît et deux gagnent l'attribut
`download`.

**Dépendances** — aucune ajoutée. Le balayage réutilise le `Finder` déjà employé
pour les couvertures.

**Effet mesuré attendu** — cinq émissions marquées publiques ne sont pas servies en
production (`ailleurs-97`, `-115`, `-234`, `-304`, `-316`) : leur nom canonique
renvoie 404. La cause n'est pas déterminable de l'extérieur, l'indexation de
dossier étant désactivée — « audio jamais déposé » et « audio sous un autre nom »
sont indiscernables. **Cinq est un majorant, pas un décompte** : celles dont le
fichier existe sous un autre nom réapparaîtront d'elles-mêmes après bascule, et le
compte réel se lira à ce moment-là.

**Hors périmètre, relevé en mesurant** — `ailleurs-321` est servie en production et
absente du dépôt, y compris de `main`. Le CMS édite git : il ne la verra jamais.
