# Décisions de kick-off

## ADR trouvés

Dix ADR hérités portaient `status: proposed`, `date` nulle et `authors: []` —
aucun n'avait été signé. Tous les dix se sont posés dans le périmètre de cette
cérémonie :

- `docs/architecture/adr/sdr-001-languages.md`
- `docs/architecture/adr/sdr-002-architecture.md`
- `docs/architecture/adr/sdr-003-localization.md`
- `docs/architecture/adr/sdr-004-tests.md`
- `docs/architecture/adr/sdr-005-commits.md`
- `docs/architecture/adr/sdr-006-documentation.md`
- `docs/architecture/adr/sdr-007-forge-and-ci.md`
- `docs/architecture/adr/sdr-008-branching.md`
- `docs/architecture/adr/sdr-009-delivery.md`
- `docs/architecture/adr/sdr-010-validation.md`

Chacun a été tranché `accepted` — texte final déjà écrit dans son propre
fichier, résumé ci-dessous. Toutes les décisions s'appuient sur l'état réel du
dépôt (code, remotes git, workflows GitHub, protections de branche) et sur les
objectifs qualité ordonnés de `discovery.md` (fiabilité des données >
simplicité opérationnelle > accessibilité de la contribution >
découvrabilité).

## Pour chaque ADR — le texte final

Le texte final de chaque décision vit dans son propre fichier (ils sont
`accepted` et immuables au sens de la méthode) — cette section en donne le
résumé et le verdict.

### sdr-001 — Languages

**Verdict : `accepted`.** `languages.main.name: PHP`. Écart par rapport à la
liste proposée (Python/Go/TypeScript) : le dépôt est déjà entièrement écrit en
PHP (`src/src/bootstrap.php`, Twig, Composer) ; en choisir un autre aurait
signifié réécrire l'application.

### sdr-002 — Software Architecture

**Verdict : `accepted`.** `architecture.pattern: monolithic`. Écart par
rapport à la proposition (`hexagonal`) : un point d'entrée procédural unique
sans couche domaine isolable de l'I/O, cohérent avec l'objectif « simplicité
opérationnelle ».

### sdr-003 — Localization

**Verdict : `accepted`.** `default: français`, `code: anglais` (les trois
autres surcharges héritent du défaut). Adopte la proposition de la méthode
telle quelle — déjà la pratique observée dans le code et la documentation.

### sdr-004 — Tests

**Verdict : `accepted`.** `tests.coverage_target: 90`. Adopte la proposition
telle quelle malgré un départ à zéro test, en s'appuyant sur le TDD déjà
mandaté par `CLAUDE.md` et sur l'objectif « fiabilité des données ».

### sdr-005 — Commits

**Verdict : `accepted`.** `commits.scopes: []`, `commits.tool: aucun`. Écart :
aucune chaîne de qualité n'existe pour l'appliquer (`sdr-007`), et l'historique
observé (commits automatisés hors Conventional Commits) ne suit déjà pas la
convention.

### sdr-006 — Documentation

**Verdict : `accepted`.** `docs.tool: docusaurus`, `docs.root: docs/`,
`docs.audience: interne`, `docs.quadrants: [guides, reference]`. Les deux
premières clés reprennent ce que `CLAUDE.md` déclarait déjà ; les deux
dernières sont tranchées ici pour la première fois.

### sdr-007 — Forge and continuous integration

**Verdict : `accepted`.** `forge.host: github`, `ci.tool: github-actions`,
`ci.gate: aucune`. Écart par rapport à la proposition (`bloquante`) : vérifié
par API GitHub, `main` n'a aucune protection de branche, et aucune chaîne de
qualité n'existe à rendre bloquante.

### sdr-008 — Branching

**Verdict : `accepted`.** `branching.model: github-flow`,
`branching.merge: squash`, `branching.prefixes: [feat, fix, docs]`. Adopte la
proposition telle quelle — confirmée par l'historique (branches courtes par
émission, commits `main` portant `(#108)`, signature d'un squash-merge).

### sdr-009 — Delivery

**Verdict : `accepted`.** `deploy.trigger: manuel`,
`deploy.environments: [production]`, `release.tool: aucun`. Écart par rapport
à la proposition (`tag` / `release-please`) : vérifié par les remotes git, le
déploiement est un `git push dokku main` fait à la main, sans tag ni version
dans l'historique.

### sdr-010 — Validation

**Verdict : `accepted`.** `validation.boundary: opis/json-schema`,
`validation.data_files: json-schema`. La méthode n'avait pesé ni proposé
d'outil pour PHP ; une seule bibliothèque JSON Schema sert les deux clés,
directement motivée par le format `index.json` à venir (story
`emission-format-et-cms` de `openspec/discovery.md`) et l'objectif « fiabilité
des données ».

## Ce que la porte a refusé

Rien. Les dix ADR portent chacun `## Options envisagées` sous une forme
tabulaire (`| Clé | Valeur | Motif |`) avec au moins une option écartée
justifiée dans le corps de chaque fichier — la porte ne refuse aucun des dix
verdicts `accepted`.
