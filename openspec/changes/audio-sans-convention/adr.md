# ADR Review Manifest

- Status: completed
- Review date: 2026-08-29

## Review Summary

ADR review completed for this change.

Les décisions de `design.md` ont été pesées une à une contre la barre : engagement
architectural durable, portée au-delà de ce change, non déjà couvert par un ADR en
vigueur. **Aucune ne la franchit** — le détail par décision figure plus bas.

**Divergence d'emplacement, signalée et non tranchée.** L'instruction attend les
ADR sous `<repo>/adr/`. Ce dépôt les tient sous `docs/architecture/adr/`, avec un
`.mdschema.yml` qui les valide et une série `sdr-NNN-slug.md`. C'est cette
convention qui a été lue pour la revue ; aucun dossier `adr/` concurrent n'a été
créé, un second emplacement fragmenterait le graphe de supersession que
l'instruction cherche précisément à rendre parcourable.

## In-Force ADRs Reviewed

Dix ADR, tous `accepted`, aucun `supersedes` renseigné — le graphe de supersession
est plat, les dix sont donc en vigueur.

| ADR | Ce qu'il impose à ce change |
| --- | --- |
| `sdr-001-languages` | PHP est le langage principal. Le code de ce change est du PHP. |
| `sdr-002-architecture` | `architecture.pattern = monolithic`, l'hexagonal explicitement écarté pour ce dépôt. La découverte des fichiers reste une fonction dans `bootstrap.php`, sans port ni adaptateur. |
| `sdr-003-localization` | Langue de la prose et des commits. |
| `sdr-004-tests` | TDD sans exception, `tests.coverage_target = 90` sur le code touché. Impose que ce change pose PHPUnit — le dépôt n'a aucune suite. |
| `sdr-005-commits` | Forme des messages de commit. |
| `sdr-006-documentation` | Corpus et quadrants documentaires. |
| `sdr-007-forge-and-ci` | Forge et nature de la porte d'intégration. |
| `sdr-008-branching` | Modèle de branche et mode de fusion. |
| `sdr-009-delivery` | Circuit de livraison. |
| `sdr-010-validation` | `opis/json-schema` aux frontières, un schéma JSON pour `index.json`. Ne lie pas ce change, qui ne touche pas au chargement d'`index.json`. |

## New Durable ADRs Created

**Aucun.** Aucune décision de ce change n'introduit d'engagement architectural
durable au sens de la barre.

Le détail, pour que la conclusion soit vérifiable :

- **Balayage du dossier au lieu d'un nom calculé.** Porte bien un principe qui
  dépasse ce change — l'identité d'un fichier d'asset ne se dérive pas du contenu
  éditorial. Mais c'est la **seconde application** d'un motif déjà livré par
  `sveltia-cms-emissions` pour les couvertures, et il est intégralement motivé dans
  le `design.md` de ce change, qui sera archivé. Le graver en ADR ajouterait une
  troisième copie d'un raisonnement déjà écrit deux fois.

- **PHPUnit comme suite de tests.** C'est un choix de technologie durable, mais
  `sdr-004` le retire explicitement du champ des ADR : sa section *More
  Information* énonce que la clé « ne couvre ni runner, ni commande de test, ni
  sortie attendue, ni instrument de couverture » et renvoie à la section
  `## Testing` du contexte agent. Le déclarer en ADR contredirait un ADR en vigueur.

- **Attribut `download` plutôt qu'en-tête `Content-Disposition`.** Détail tactique
  d'implémentation, retenu parce qu'il vit dans le dépôt là où l'en-tête vivrait
  dans la configuration du serveur. Aucune portée au-delà de ce change.

**Question laissée ouverte pour l'étape suivante** : si le principe « l'identité
d'un asset ne se dérive pas du contenu éditorial » mérite un ADR de dépôt, il
faudra d'abord fixer la convention de nommage des ADR propres à ce dépôt. Les dix
existants forment une série de kick-off répondant à un questionnaire fixe, chacun
portant une clé `traits` ; un ADR maison n'en porterait aucune — le schéma le
prévoit — mais sa place dans la numérotation `sdr-NNN` n'est pas établie. Écrire
d'abord et corriger ensuite n'est pas possible : les ADR sont immuables une fois
acceptés.
