# Outillage du dépôt

Les skills que la méthode distribue sont déjà déclarés dans le champ `skills` de
votre `package.json`, et s'installent par `skills-lock`. **Les plugins ci-dessous
n'arrivent pas par là.**

Aucune commande d'installation ne peut être lancée par le canal : il écrit des
fichiers, il n'exécute rien. Et déclarer un plugin dans `.claude/settings.json`
l'**active** sans l'installer — une session ouverte sur un projet qui déclare un
plugin absent démarre normalement, ne télécharge rien et n'émet aucun
avertissement. Mesuré le 2026-08-18 sur Claude Code 2.1.234.

Posez-les une fois, ici, depuis un terminal à la racine du dépôt.


## superpowers

Apporte le cadrage, les plans, l'exécution TDD, les revues et le debug.

```bash
claude plugin marketplace add obra/superpowers-marketplace#1ab7b8eeef707f21565471f11d3782fac3dd1c61
claude plugin install superpowers@superpowers-marketplace --scope project -y
```

## ponytail

Apporte des hooks, qui rendent le mode actif en tâche de fond.

```bash
claude plugin marketplace add DietrichGebert/ponytail#2ed6c52c9d7e5e56942508591085fd45dea277d3
claude plugin install ponytail@ponytail --scope project -y
```


## Committez ce que ça écrit

`--scope project` écrit `.claude/settings.json`. **Committez-le** : ce que ce
dépôt fait tourner devient relisible en merge request, au lieu de rester un état
de poste.

```bash
git add .claude/settings.json
git commit -m "chore: plugins du projet"
```

## Vérifier

```bash
claude plugin marketplace list
cat .claude/settings.json
```

Les marketplaces déclarés apparaissent, et `enabledPlugins` porte chaque plugin.

## Si vous n'en voulez pas

La liste est prescrite ; la dérogation est la vôtre, à condition d'être écrite.
Ne posez pas le plugin, et consignez-le dans un ADR local — comme pour toute
règle de méthode dont vous déviez.
