# Design — Découverte du fichier audio par balayage

Aucune section arc42 touchée : ce change ne déplace ni bloc de construction, ni
conteneur, ni frontière de déploiement. Il modifie la façon dont une fonction
existante trouve un fichier déjà servi depuis le même endroit.

## Context

Voir [proposal.md](proposal.md) pour la motivation et la mesure.

Contraintes en vigueur, lues avant d'écrire — les dix SDR de
`docs/architecture/adr/` sont tous `accepted`, aucun n'est remplacé :

| SDR | Ce qu'il impose ici |
|---|---|
| `sdr-001` | PHP est le langage principal. Le code de ce change est du PHP. |
| `sdr-002` | `architecture.pattern = monolithic`. Pas de port ni d'adaptateur à extraire : le fichier qui charge une émission est celui qui la rend, et c'est assumé. |
| `sdr-004` | TDD sans exception, `tests.coverage_target = 90` sur le code touché. Le dépôt n'a **aucune suite** : PHPUnit et un pilote de couverture restent à poser. |
| `sdr-010` | `opis/json-schema` aux frontières, un schéma JSON pour `index.json`. |

État de départ : `getShow()` calcule le nom attendu du fichier audio puis teste
son existence. Les 367 émissions du dépôt portent des fichiers conformes ; cinq
émissions marquées publiques ne sont pas servies en production.

## Goals / Non-Goals

**Goals :**

- Le nom du fichier audio cesse d'être une donnée dont dépend la publication.
- Corriger un titre ou une auteurice ne dépublie plus l'émission.
- Un contributeur peut déposer un fichier sans consigne de nommage.
- Le nom canonique reste ce que le public télécharge.
- Le code touché est couvert par des tests, conformément à `sdr-004`.

**Non-Goals :**

- Élargir les formats servis au-delà de MP3 et FLAC.
- Gérer le dépôt du fichier — transport, permissions, contributeurs.
- Retester `bootstrap.php` dans son ensemble : `sdr-004` borne le 90 % au code
  touché tant que le reste n'est pas repris.
- Poser le schéma JSON d'`index.json` (voir Open Questions).

## Decisions

### 1. Balayage du dossier, avec un ordre décidé

`getShow()` cesse de calculer un nom et cherche les fichiers par extension dans
le dossier de l'émission. L'ordre reprend celui des couvertures, livré et vérifié
par `sveltia-cms-emissions` : **le fichier suivant la convention historique
d'abord, puis l'ordre alphabétique.**

Cette règle préserve le comportement actuel sur les 367 émissions conformes —
aucune ne change de fichier servi — et reste lisible depuis le dossier, sans
dépendre du contenu des fichiers.

MP3 et FLAC sont cherchés séparément : ce sont deux liens de téléchargement
distincts dans l'interface, pas deux candidats pour un même rôle.

*Écarté :* le plus gros fichier gagne. Choisit bien dans le cas décrit comme
exceptionnel, mais l'ordre dépend alors du contenu et cesse d'être prévisible à
la lecture du dossier.

*Écarté :* un champ dans `index.json` désignant le fichier. Sans ambiguïté, mais
réintroduit un nom de fichier dans le contenu — donc la possibilité qu'il diverge
du fichier réel, ce que ce change existe précisément pour supprimer.

### 2. Le nom canonique devient une étiquette, pas un emplacement

Aujourd'hui le nom du fichier sur le disque **est** le nom canonique. Une fois
découplés, le fichier peut porter n'importe quel nom et le public doit quand même
recevoir `ouiedire_ailleurs-331_rachitik-data_la-pompa-chalor-vol-3.mp3`.

L'attribut `download` du lien impose le nom d'enregistrement sans contraindre
celui du fichier. Le nom canonique reste calculé depuis le titre et les
auteurices — donc il **suit** leurs corrections au lieu de s'y casser.

*Écarté :* un en-tête `Content-Disposition`. Même effet, mais il faudrait le
configurer sur le Plesk, hors du dépôt et hors de portée d'une revue.

### 3. Du code disparaît

`slugDownload` ([bootstrap.php:242](../../../src/src/bootstrap.php:242)) et le
bouton « Copier le nom de fichier attendu » qu'il alimente
([emission.html.twig:63](../../../src/views/emission.html.twig:63)) perdent leur
objet. Ce bouton est la trace d'une contrainte que le code s'imposait à lui-même
et compensait par de l'interface ; il part avec elle.

### 4. Ce change pose la première suite de tests du dépôt

`sdr-004` impose le TDD sans exception et un seuil de 90 % sur le code touché. Le
dépôt n'a aucune suite : PHPUnit et un pilote de couverture sont donc à poser
**dans ce change**, puisque c'est lui qui touche le code.

Le périmètre reste borné à ce que ce change modifie : la découverte des fichiers
audio et l'ordre retenu. C'est une fonction pure de l'état d'un dossier — le
genre de chose qui se teste sans monter l'application, avec des dossiers
temporaires en fixture.

**Conséquence assumée :** un change de trois règles porte l'installation d'une
infrastructure de test. Le coût est réel, mais il est dû, et il ne diminuera pas
en attendant. Le premier change qui touche du code après `sdr-004` le paie ; ce
sera celui-ci ou le suivant.

*Écarté :* reproduire la méthode de `sveltia-cms-emissions` — un invariant
avant/après mesuré contre le rendu réel, sans suite de tests. Elle a prouvé sa
valeur sur une migration de données, où elle a rattrapé ce qu'aucun test unitaire
n'aurait vu. Mais elle ne se rejoue pas : elle vérifiait qu'une transformation
ponctuelle n'avait rien changé, pas qu'une règle reste vraie dans le temps.

### 5. Rester procédural

`sdr-002` fixe `monolithic` et écarte explicitement l'hexagonal pour ce dépôt. La
découverte des fichiers reste donc une fonction dans `bootstrap.php`, à côté de
celle des couvertures, sans indirection nouvelle.

## Risks / Trade-offs

**Un fichier indésirable dans le dossier devient l'audio de l'émission** → Le
risque existe déjà pour les couvertures et a été borné de la même façon : le tri
place la convention historique en tête, donc un fichier étranger arrive après et
ne peut pas voler la première place là où une conformité existe. Là où aucune ne
existe, il n'y avait de toute façon rien à voler.

**Les cinq émissions non servies ne réapparaîtront peut-être pas** → Si leur audio
n'a jamais été déposé, ce change n'y peut rien. Le décompte réel se lira après
bascule : c'est la mesure, pas une promesse.

**Poser PHPUnit sur PHP 7.4** → La version de PHPUnit compatible 7.4 est ancienne.
À vérifier à l'implémentation ; si le pilote de couverture pose problème, le
signaler plutôt que de baisser le seuil en silence.

**Le nom canonique dérive du titre, qui est éditable** → C'est voulu : l'étiquette
suit la correction. Un fichier téléchargé avant et après une correction portera
deux noms différents. Sans conséquence — ce sont des noms d'enregistrement, pas
des identifiants.

## Migration Plan

Aucune migration de données. Les 367 émissions portent des fichiers conformes que
la règle « convention d'abord » sert à l'identique.

1. Poser PHPUnit et le pilote de couverture ; déclarer le runner dans la section
   `## Testing` du contexte agent, comme `sdr-004` le demande.
2. Écrire les tests de la découverte et de l'ordre, avant le code.
3. Remplacer le calcul par le balayage ; supprimer `slugDownload` et son bouton ;
   poser l'attribut `download` sur les deux liens restants.
4. Vérifier que les 367 émissions servent le même fichier qu'avant — le harnais de
   capture de `sveltia-cms-emissions` fournit la méthode, à défaut de l'outil.
5. Après bascule en production, relever combien des cinq émissions réapparaissent.

**Rollback :** `git revert`. Aucun fichier n'est déplacé ni renommé sur le
serveur, donc rien à défaire côté données.

## Open Questions

- **Les formats acceptés.** Hypothèse retenue faute de réponse : MP3 et FLAC,
  périmètre inchangé. Ouvrir à WAV ou M4A appartient au projet de soumission par
  les contributeurs.

- **Le sort du bouton « copier le nom attendu ».** La décision 3 le supprime ;
  ça n'a pas été validé explicitement.

- **`sveltia-cms-emissions` porte une dette envers deux SDR en vigueur, et cette
  conception ne la règle pas.** `sdr-010` nomme `index.json` comme « exactement la
  frontière que cet ADR doit couvrir » et impose `opis/json-schema` avec un schéma
  JSON ; le change a livré `index.json` sans schéma ni dépendance. `sdr-004`
  impose le TDD sans exception ; le change a livré sans suite de tests, en
  s'appuyant sur des invariants avant/après.

  Ce n'est pas à ce change de la solder — il ne touche pas au chargement
  d'`index.json`. Mais elle est réelle, elle est sur du code déjà archivé, et elle
  mérite son propre change plutôt que de rester tacite.
