# Document d'architecture — texte des sections

## Requirements Overview

ouiedire est l'archive git-native de plus de 368 émissions de radio
communautaire du collectif Constructions Incongrues, publiée sur
ouiedire.net. Le projet doit permettre à n'importe quel membre du collectif
de créer une nouvelle émission ou de corriger une émission déjà publiée sans
éditer à la main du JSON ou du HTML — aujourd'hui, les deux passent par trois
fichiers désynchronisables (`manifest.json`, `description.html`,
`playlist.html`) édités dans l'interface web brute de GitHub, et la playlist
casse régulièrement faute de validation de structure. Le premier chantier
concret est déjà engagé (change `sveltia-cms-emissions` : format
single-file par émission, playlist structurée, CMS git-backed), sans ajouter
d'infrastructure serveur.

## Quality Goals

1. **Fiabilité des données** — aucune émission existante ne perd ou ne
   déforme son contenu. Concrètement : les 5354 artistes distincts de
   `/artists` doivent rester rigoureusement identiques avant et après toute
   migration. Prime sur tout le reste : une fonctionnalité qui livre vite
   mais risque l'archive de 368 émissions n'est pas acceptable.
2. **Simplicité opérationnelle** — aucune infrastructure serveur ajoutée
   (pas de proxy, pas de backend, pas de service à surveiller). Le dépôt
   reste maintenable par un collectif bénévole sans capacité d'exploitation
   dédiée. Passe avant l'accessibilité de contribution : une UX plus riche
   qui exigerait un serveur est refusée.
3. **Accessibilité de la contribution** — n'importe quel membre du collectif,
   technique ou non, peut créer ou corriger une émission sans éditer de JSON
   ou de HTML à la main. C'est l'objectif moteur du travail à venir, mais il
   cède devant les deux précédents : pas de raccourci qui compromettrait la
   fiabilité des données ou ajouterait de l'infrastructure.
4. **Découvrabilité** — recherche et navigation croisée par artiste ou genre
   à travers les 368+ émissions. Reconnu comme valeur à long terme, mais
   explicitement le dernier arbitré : différé de cette découverte (MoSCoW
   Won't), aucune décision d'architecture ne doit lui être sacrifiée avant
   que les trois premiers objectifs soient satisfaits.

## Stakeholders

| Rôle | Contact | Attentes |
| --- | --- | --- |
| Membre du collectif qui publie une émission | collectif Constructions Incongrues | Créer ou corriger une émission sans éditer de JSON/HTML à la main ; publier en confiance |
| Relecteur·se de Pull Request | collectif Constructions Incongrues | La forme (playlist, manifeste) est garantie correcte par construction avant même la relecture |
| Collectif Constructions Incongrues (commanditaire) | — | Arbitre la roadmap et les ADR de ce kick-off ; attend que l'archive (368+ émissions) ne soit jamais corrompue par un changement de format |
| Exploitant technique (déploiement) | remote `dokku` (`lehavre.constructions-incongrues.net`) | Le déploiement reste un geste manuel simple (`git push dokku main`, `sdr-009`), sans infrastructure supplémentaire à surveiller |

## Architecture Decisions

| ADR | Titre | Verdict |
| --- | --- | --- |
| `sdr-001` | Languages | `accepted` — PHP |
| `sdr-002` | Software Architecture | `accepted` — monolithic |
| `sdr-003` | Localization | `accepted` — français par défaut, anglais pour le code |
| `sdr-004` | Tests | `accepted` — seuil de couverture 90 % |
| `sdr-005` | Commits | `accepted` — aucune portée, aucun outil |
| `sdr-006` | Documentation | `accepted` — Docusaurus, `docs/`, audience interne, quadrants guides + reference |
| `sdr-007` | Forge and continuous integration | `accepted` — GitHub, GitHub Actions, aucune porte bloquante |
| `sdr-008` | Branching | `accepted` — github-flow, squash, préfixes feat/fix/docs |
| `sdr-009` | Delivery | `accepted` — déploiement manuel, environnement production, aucun outil de release |
| `sdr-010` | Validation | `accepted` — opis/json-schema aux deux frontières (exécution et fichiers de données) |

## Ce qui n'a pas été rempli

**Toutes les autres sections du gabarit arc42** (Constraints, Solution
Strategy, Building Block View, Runtime View, Deployment View, Cross-cutting
Concepts, Design Decisions au-delà de la liste d'ADR ci-dessus, Quality
Requirements sous forme de scénarios, Risks and Technical Debt, Glossary)
restent vides. Les remplir est leur propre change — plusieurs skills du
skill-pack interne du dépôt les couvrent déjà à l'état « ouvert »
(`arc42-spec`, `c4-diagram`), et les skills `arc42-section-NN` amont peuvent
les produire quand ce travail sera engagé.

**Rédaction à la main, pas par skill.** Les skills `arc42-section-01` à
`arc42-section-12` sont disponibles mais n'ont pas été employés : les quatre
sections ci-dessus suivent un gabarit propre à cette cérémonie de kick-off
(pas le gabarit complet arc42 §1/§9), et les rédiger directement depuis
`discovery.md` et `decisions.md` gardait un contrôle plus direct sur la
fidélité aux deux sources.

**Adaptation de source pour *Requirements Overview*.** L'instruction attendait
une section `## Périmètre` dans `openspec/discovery.md` ; le skill `discovery`
réellement installé dans ce dépôt (agent-skills, pas le gabarit attendu par
cette cérémonie) produit à la place `Sources`, `Personas`, `Journey Map`,
`MoSCoW`, `Stories` — sans section `Périmètre` isolée. *Requirements Overview*
est donc composé à partir de `Sources` et du bucket `Must` du `MoSCoW`, pas
recopié d'une section unique.

**Lint non passé.** `CLAUDE.local.md` ne déclare aucun instrument sous une
section `## Linting` — ce tableau est lui-même un geste du kick-off qui n'a
pas encore été fait ailleurs dans ce dépôt. Rien n'a donc été inventé pour ce
seul fichier ; le lint de `arc42.md` reste à faire une fois l'instrument
déclaré.
