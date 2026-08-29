# Brainstorm

## Contexte

`getShow()` ne cherche pas le fichier audio d'une émission : il **calcule** le nom
qu'il devrait avoir, puis teste son existence.

```php
// bootstrap.php:227
$fileMp3 = new SplFileInfo(sprintf('%s/ouiedire_%s-%s_%s_%s.mp3',
    $pathPublicEmission, slugify($show['type']), $show['number'],
    slugify($show['authors']), slugify($show['title'])));
// :244
if ($fileMp3->isReadable()) { $show['urlDownloadMp3'] = …; }
// :232 — sans audio lisible, l'émission est dépubliée
```

Deux conséquences, la seconde découverte en explorant :

**Le nom doit être reproduit à la main.** Le dépôt a fini par gagner une
fonctionnalité de contournement : un troisième bouton de téléchargement, titré
« Copier le nom de fichier attendu » ([emission.html.twig:63]). Une contrainte
que le code s'impose à lui-même, compensée par de l'interface.

**Corriger un titre dépublie l'émission.** Mesuré :

```
fichier présent : ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3.mp3

titre « La Pompa Chalor Vol 3 »   isPublic: oui   mp3: trouvé
titre « La Pompa Calor Vol 3  »   isPublic: NON   mp3: INTROUVABLE
titre « Pompa Chalor 3        »   isPublic: NON   mp3: INTROUVABLE
```

Le fichier ne bouge pas ; le nom attendu change. Idem en corrigeant une auteurice.
Le piège préexiste, mais il fallait éditer `manifest.json` à la main sur GitHub
pour le déclencher. Le CMS livré par `sveltia-cms-emissions` le met à deux clics,
sur le champ le plus naturellement corrigé qui soit. Le cache HTTP différera la
disparition, ce qui rendra le diagnostic plus obscur encore — c'est ce qui a
masqué le premier test.

**Le précédent existe déjà dans ce dépôt.** La décision 4 de `sveltia-cms-emissions`
a fait exactement cette bascule pour les couvertures : cesser de dériver le nom
d'une convention, balayer le dossier à la place, avec un ordre déterministe.
Livré et vérifié.

## Chaîne de décision

### Q1 — Quand plusieurs audios cohabitent, lequel est l'émission ?

Le cas est exceptionnel mais réel (un MP3 et un FLAC coexistent déjà ; deux MP3
restent possibles).

**Retenu : convention d'abord, puis alphabétique.** La même règle que les
couvertures. Elle préserve le comportement actuel sur les 367 émissions déjà
conformes — aucune ne change de fichier servi — et reste lisible depuis le
dossier, sans dépendre du contenu des fichiers.

*Écarté :* le plus gros fichier — choisit bien dans le cas décrit, mais l'ordre
dépend alors du contenu et n'est plus prévisible à la lecture du dossier.

*Écarté :* un champ dans `index.json` désignant le fichier — sans ambiguïté, mais
réintroduit un nom de fichier dans le contenu, donc la possibilité qu'il diverge
du fichier réel. C'est précisément ce qu'on cherche à supprimer.

### Q2 — Quels formats le balayage accepte-t-il ?

**Non tranchée.** Question posée, restée sans réponse. Elle ne conditionne pas la
conception du balayage : la règle de découverte est la même quel que soit
l'ensemble d'extensions.

**Hypothèse retenue faute de réponse : MP3 et FLAC, périmètre inchangé.** C'est le
choix minimal, et la question des formats appartient au projet « soumission par
les contributeurs » plutôt qu'à celui-ci.

## Alternatives pesées

| approche | ce qu'elle règle | ce qu'elle coûte |
|---|---|---|
| **Balayage du dossier** *(retenue)* | le nom cesse d'être une donnée ; corriger un titre ne dépublie plus ; le contributeur dépose sans consigne | l'ordre doit être décidé ; le nom de téléchargement doit être préservé autrement |
| Recalculer le nom à l'édition, et renommer le fichier | garde la convention intacte | il faut écrire sur le disque du serveur depuis l'application — nouveau pouvoir, nouveaux droits, et un renommage qui casse les liens déjà partagés |
| Laisser en l'état, documenter le piège | rien à écrire | la documentation ne protège pas d'un champ éditable en deux clics ; c'est ce qu'on fait aujourd'hui, et le bouton de contournement en est la trace |

## Compromis de conception

**Le nom canonique reste important au téléchargement.** Aujourd'hui le nom du
fichier sur le disque *est* le nom canonique. Une fois découplés, le fichier peut
s'appeler n'importe comment et le public doit quand même recevoir
`ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3.mp3`.

L'attribut `download` du lien HTML impose le nom d'enregistrement sans contraindre
celui du fichier. Le nom canonique cesse d'être une contrainte de stockage pour
devenir une étiquette d'affichage — ce qu'il aurait dû être depuis le début. Il
reste calculé à partir du titre et des auteurices, donc il suit leurs corrections
au lieu de s'y casser.

**Du code disparaît.** `slugDownload` ([bootstrap.php:242]) et son bouton
([emission.html.twig:63]) n'ont plus d'objet une fois le nom devenu libre.

**Aucune migration de données.** Les 367 émissions ont des fichiers conformes : la
règle « convention d'abord » les sert à l'identique. Le change modifie du code, pas
du contenu.

## Ce qui reste ouvert

- **Q2, les formats acceptés** — hypothèse MP3 + FLAC, à confirmer ou corriger.
- **Le sort du bouton « copier le nom attendu »** — le supprimer semble acquis
  puisqu'il perd son objet, mais ça reste à valider explicitement.
- **Les émissions déjà cassées en production — mesuré, borné, non élucidé.**

  ```
  dépôt, isPublic: true ........ 365
  servies en production ........ 361
  publiques non servies ........   5
  ```

  Les cinq : `ailleurs-97`, `-115`, `-234`, `-304`, `-316`. Pour chacune, le nom
  canonique calculé par l'application renvoie 404 en production, en `.mp3` comme
  en `.flac`. Méthode validée sur un témoin servi (`ailleurs-300` → 200).

  **La cause n'est pas déterminable de l'extérieur** : l'indexation de dossier est
  désactivée, donc « audio jamais déposé » et « audio présent sous un autre nom »
  sont indiscernables. Cinq est donc un majorant, pas un décompte. Lister ces cinq
  dossiers sur le serveur trancherait en une minute.

  Un exemple de ce que le nom canonique exige : `ailleurs-97` attend
  `ouiedire_ailleurs-97_sammy-stein_un-nouveau-dpart.mp3`. Le `slugify` **supprime
  le « é » au lieu de le translittérer** — « départ » devient « dpart ». Aucun
  humain ne devinerait ce nom, et le bouton « copier le nom attendu » existe
  précisément parce qu'il est indevinable.

- **La production n'est pas décrite par le dépôt.** `ailleurs-321` (« Diazépam
  racolage », par Jean et Johan) est servie en production et **absente du dépôt**,
  y compris de `main` — le dépôt passe de 320 à 322. C'est le seul cas.

  Hors périmètre de ce change, mais il le concerne : le CMS édite git, donc il ne
  verra jamais cette émission. Et si un déploiement synchronisait un jour le
  dossier d'assets depuis git, elle disparaîtrait.
