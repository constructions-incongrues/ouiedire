# Review

## Grilles retenues

**DevEx**, seule grille retenue. Le livrable se consomme : un mainteneur — demain
un contributeur — dépose un fichier dans un dossier et attend que le site le
serve. Rien ne se regarde (le change retire une commande d'interface, il n'en
ajoute aucune) et rien ne refuse (il supprime précisément un refus).

**Revue d'ingénierie non convoquée.** Aucune décision ne porte le cran
irréversible : le rollback est un `git revert`, aucun fichier n'est déplacé ni
renommé sur le serveur, aucune donnée n'est transformée. Le plan tient en une
merge request.

## Findings et traitement

| # | Grille · dimension | Finding | Sévérité | Traitement |
| --- | --- | --- | --- | --- |
| 1 | DevEx · récupération d'erreur | Le change échange un échec silencieux contre un autre. Avant : un fichier mal nommé rendait l'émission invisible. Après : un fichier **erroné** déposé dans un dossier devient l'audio de cette émission, sans que rien ne le signale. Le nom strict jouait accidentellement le rôle de vérification. | Moyenne | Accepté, et borné par l'ordre retenu : là où une couverture conforme existe, un fichier étranger arrive après elle et ne peut pas la remplacer. Le risque résiduel porte sur les dossiers sans fichier conforme. Consigné ici plutôt que découvert à l'usage. |
| 2 | DevEx · boucle de retour | Rien n'indique à un mainteneur qu'une émission est dépubliée faute d'audio. C'est vrai avant comme après le change ; celui-ci en supprime la cause la plus fréquente sans rendre la situation observable. | Moyenne | Hors périmètre. Les cinq émissions mesurées non servies en production sont le symptôme de cette absence. Mérite son propre change — rendre visible aux mainteneurs ce que le public ne voit pas. |
| 3 | DevEx · coût d'entrée | Le change pose PHPUnit, première suite du dépôt. Un contributeur devra désormais savoir lancer les tests, alors qu'aucune commande n'existe aujourd'hui. | Faible | Traité par le plan de migration : `sdr-004` impose de déclarer runner et commande dans la section `## Testing` du contexte agent. Sans cette déclaration, le seuil de 90 % n'est pas vérifiable et le coût retombe sur chaque personne qui découvre le dépôt. |
| 4 | DevEx · cohérence | Le bouton « Copier le nom de fichier attendu » disparaît. Toute documentation ou habitude qui y renvoie devient fausse le jour du déploiement. | Faible | Le `README` a été réécrit par le change précédent et ne mentionne pas ce bouton. Reste à vérifier qu'aucune consigne transmise de vive voix ne survit — hors de portée d'une revue de code. |
| 5 | DevEx · promesse tenue | Le change est présenté comme un prérequis à l'ouverture aux contributeurs, mais il ne les sert pas encore : ils n'ont toujours aucun moyen de déposer un fichier. | Faible | Assumé et écrit dans les Non-Goals du design. Le gain immédiat est pour les deux mainteneurs ; le gain pour les 254 auteurices attend un autre change. |

## Porte

**Passable.** Aucun finding bloquant.

Les deux findings de sévérité moyenne sont des angles morts d'observabilité, pas
des défauts de ce change : le n°1 est un risque que l'ordre retenu borne, le n°2
préexiste et le dépasse. Tous deux sont nommés ici pour qu'ils ne soient pas
redécouverts comme des surprises.
