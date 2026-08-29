# Découverte

## Comment elle a été conduite

En deux temps, tous deux avec l'utilisateur en session interactive :

1. **`/office-hours`** (mode Builder, projet open source/communautaire) : six
   questions génératives sur la version la plus intéressante d'ouiedire, à qui
   la montrer, le chemin le plus rapide vers un usage réel, l'analogue le plus
   proche, et la version 10x. Suivi d'un défi de prémisses et de trois
   directions alternatives (CMS-first, Data model + discovery, Community
   contribution layer) tranchées par l'utilisateur. Rapport complet :
   `~/.gstack/projects/constructions-incongrues-ouiedire/tristan-main-design-20260829-012455.md`.
2. **`discovery`** (skill OpenSpec), avec ce rapport en entrée : personas,
   journey map annotée sur le code réel, MoSCoW, et trois stories ordonnées.
   En cours de route, l'utilisateur a corrigé le cadrage : l'objectif
   principal est la **création** d'une émission, pas seulement la correction
   d'une émission déjà publiée — personas et journey map ont été refaits en
   conséquence avant validation.

## Le document de cadrage

`openspec/discovery.md`. Cette découverte y a posé : deux personas (membre du
collectif qui publie une émission — création ou correction ; relecteur·se de
PR), une journey map unifiée sur le code réel (`emission.yml`,
`emission.html.twig`, `bootstrap.php`), un MoSCoW recentré sur la création, et
trois stories ordonnées — dont la première correspond au change déjà proposé
`sveltia-cms-emissions`. Le fichier porte aussi le câblage du backlog dans
`openspec/config.yaml` pour que `/opsx:propose` enchaîne sur la prochaine
story.

## Objectifs qualité

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

## Ce qu'elle n'a pas tranché

Aucune technologie n'est nommée dans cette découverte. Le choix technique
concret pour la story 1 (Sveltia CMS, format `index.json`, etc.) est déjà
arbitré dans `openspec/changes/sveltia-cms-emissions/proposal.md` et
`design.md` — des artefacts antérieurs à ce kick-off, hors de son périmètre.
Restent ouverts : comment les stories 2 et 3 (scaffold de création unifié,
lien EDIT vers le CMS) seront techniquement réalisées — à trancher par leurs
propres ADR/design quand elles seront proposées.
