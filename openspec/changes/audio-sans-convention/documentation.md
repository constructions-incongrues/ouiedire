# Plan de documentation

| Chemin | Quadrant | Destinataire | Geste |
| --- | --- | --- | --- |
| `CLAUDE.local.md` | — | l'agent ou la personne qui lance les tests | modifiée |
| `README.md` | — | le mainteneur qui publie une émission | modifiée |

## Ce que chaque page devra porter

**`CLAUDE.local.md`** — une section `## Testing (ce dépôt)` déclarant le runner
PHPUnit, la commande exacte et la sortie attendue. `sdr-004` fixe le seuil de
couverture mais énonce explicitement que la clé « ne couvre ni runner, ni commande
de test, ni sortie attendue » ; ce change pose la première suite du dépôt, c'est
donc lui qui doit la déclarer.

**L'emplacement n'est pas interchangeable.** `CLAUDE.md` porte l'instruction et
son propre avertissement : il arrive entier à chaque mise à jour de la méthode, et
ce qu'on y ajoute disparaît sans un mot. La déclaration va dans `CLAUDE.local.md`,
importé à la fin et jamais réécrit.

*Observation, hors périmètre :* `CLAUDE.local.md` n'est pas suivi par git. Une
commande de test déclarée là ne sera lue que sur cette machine. Le dépôt en a
décidé ainsi ; le noter suffit ici.

**`README.md`** — le circuit de publication doit dire que le nom du fichier audio
n'a plus d'importance. La page décrit aujourd'hui le dépôt du MP3 sans consigne de
nommage, parce que le change précédent l'avait déjà retirée ; il faut désormais
l'affirmer, plutôt que de la taire. C'est la seule conséquence de ce change qu'un
mainteneur perçoit avant d'ouvrir le code.

## Corpus : aucune page due

Aucune page ne naît ni ne se déplace sous `docs/`. `sdr-006` fixe le corpus à
`docs/`, audience interne, quadrants `[guides, reference]` ; il ne contient
aujourd'hui que l'arc42 et les ADR.

Un guide « déposer l'audio d'une émission » serait tentant, mais le circuit
qu'il décrirait est celui du `README`, et le dupliquer créerait deux vérités pour
un même geste. Ce guide aura sa place quand les contributeurs déposeront
eux-mêmes — dans le change qui ouvrira cette porte, pas dans celui-ci.

**En conséquence, la skill `diataxis` n'a pas été invoquée** : sa condition de
déclenchement est qu'une page naisse ou se déplace sous le corpus, et aucune ne
le fait. Les deux pages dues sont hors corpus, donc sans quadrant.
