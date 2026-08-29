## 1. Poser la suite de tests

`sdr-004` impose le TDD sans exception et un seuil de 90 % sur le code touché. Le
dépôt n'a aucune suite : elle se pose avant d'écrire une ligne de production.

- [x] 1.1 Ajouter PHPUnit à `src/composer.json` dans une version compatible PHP 7.4, et vérifier qu'il s'installe dans le conteneur `php:7.4-cli`
- [x] 1.2 Installer un pilote de couverture (pcov ou xdebug) dans l'image de développement et vérifier qu'un rapport de couverture se produit
- [x] 1.3 Poser l'arborescence de tests et un test trivial qui passe, pour valider la commande de bout en bout
- [x] 1.4 Déclarer runner, commande exacte et sortie attendue dans `CLAUDE.local.md`, section `## Testing (ce dépôt)` — **pas dans `CLAUDE.md`**, que la mise à jour de la méthode réécrit

**Écarts constatés à l'exécution, et consignés :** Composer épinglé à 2.8.12
(à partir de 2.9 il refuse les paquets sous avis de sécurité, ce dépôt en compte
23), `unzip` ajouté à l'image d'installation, `.gitignore` étendu au cache
PHPUnit, `.dockerignore` créé. La couverture est mesurée par `bin/coverage-check.php`
et non par `--coverage-text` — voir groupe 2 bis.

## 2. Découverte de l'audio et ordre, tests d'abord

Découverte et ordre sont un seul comportement et se testent ensemble : séparés,
l'implémentation de la découverte contiendrait la branche de tri qu'aucun test
rouge n'aurait exigée. `sdr-004` l'interdit sans exception.

- [x] 2.1 Écrire les tests de la découverte : un fichier au nom libre est trouvé ; un dossier sans audio n'en produit aucun ; MP3 et FLAC sont cherchés séparément
- [x] 2.2 Écrire les tests de l'ordre dans la même tâche : la convention historique passe devant, l'alphabétique départage, la convention d'une autre émission ne gagne pas
- [x] 2.3 Vérifier que ces tests échouent pour la bonne raison avant d'écrire le code
- [x] 2.4 Écrire `findAudioFile()` dans `src/src/audio.php`, sans dépendance à `slugify()`, jusqu'à ce que les tests passent
- [x] 2.5 Vérifier par la couverture que la branche « convention d'abord » est réellement exécutée

## 2 bis. Contrôle de couverture par fichier

`--coverage-text` ne sait pas rendre ce que `sdr-004` exige : il n'affiche ni les
fonctions libres ni le détail par fichier, et son total est capturé par les 507
instructions non couvertes de `bootstrap.php`. Le seuil de 90 % y est
inatteignable par construction. Défaut du plan, trouvé en relecture.

- [x] 2bis.1 Écrire les tests de `coverage-check.php` : seuil atteint, seuil manqué, **fichier absent du rapport**, rapport illisible
- [x] 2bis.2 Implémenter `bin/coverage-check.php` : lecture de Clover, ratio par fichier, un fichier absent est un échec
- [x] 2bis.3 Vérifier le contrôle de bout en bout sur le vrai rapport de couverture
- [x] 2bis.4 Déclarer la commande de seuil dans `CLAUDE.local.md`

## 3. Nom canonique au téléchargement

- [x] 3.1 Écrire le test : le nom proposé au téléchargement reste canonique quel que soit le nom du fichier stocké, et suit une correction du titre
- [x] 3.2 Poser l'attribut `download` sur les liens MP3 et FLAC de `emission.html.twig`, avec le nom canonique
- [ ] 3.3 Vérifier dans un navigateur qu'un fichier au nom libre s'enregistre bien sous le nom canonique

  Laissée décochée : l'attribut est vérifié dans le HTML rendu (Task 6, Step 4 du
  plan, MP3 et FLAC), mais l'enregistrement effectif sous ce nom demande un vrai
  téléchargement dans un navigateur, que cette session n'a pas pu observer.

## 4. Retirer ce qui n'a plus d'objet

- [x] 4.1 Supprimer `slugDownload` de `bootstrap.php` et le troisième bouton « Copier le nom de fichier attendu » de `emission.html.twig`
- [x] 4.2 Vérifier qu'aucun gabarit ni script ne référence encore `slugDownload`

## 5. Documentation

- [x] 5.1 `README.md` : affirmer que le nom du fichier audio n'a plus d'importance, là où le circuit décrit son dépôt
- [x] 5.2 Relire la prose produite au regard du français et du Google developer documentation style

**Écart constaté à l'exécution :** la relecture prescrite au point 5.2 a trouvé
une seconde page qui décrivait encore l'ancienne convention — le corps de la Pull
Request produit par `.github/workflows/emission.yml` demandait « obtenir le nom
de fichier attendu pour le MP3 en cliquant sur le bouton de téléchargement du
morceau ». Ce bouton a été supprimé au groupe 4. La ligne est retirée, et le
dépôt du MP3 précise que le nom est libre.

## 6. Vérification et bascule

- [x] 6.1 Vérifier que les 367 émissions servent le même fichier audio qu'avant — la méthode de capture avant/après de `sveltia-cms-emissions` fournit le procédé

  Relevé : `367 emissions, 0 sans audio`, et l'instantané `var_export()` des 367
  ne bouge que sur `canonicalDownloadName` (Task 8, Step 1 du plan).
  **Réserve consignée :** les 367 audios de l'arbre sont des fixtures de zéro
  octet posées par `bin/dev-audio-fixtures` sous le nom canonique que
  l'application calcule elle-même. Sans elles, le relevé rendrait
  `367 emissions, 367 sans audio`. La comparaison porte donc sur le
  comportement du balayage, pas sur les noms réellement déposés sur Nextcloud.

- [x] 6.2 Vérifier qu'une émission dont on corrige le titre reste publiée et son audio téléchargeable : c'est le scénario qui motive ce change

  Laissée décochée. La garantie est **structurelle** — `hasAudio` ne dépend plus
  d'aucun slug depuis que `findAudioFile()` balaye — mais **aucun test nommé ne
  la tient**, et les deux scénarios du spec « Correction du titre / des
  auteurices d'une émission publiée » n'ont donc rien à opposer. C'est le trou
  le plus net du bilan de conformité (Task 8 du plan), et il porte sur le
  scénario qui motive le change. Un test appelant `applyAudioDownloads()` deux
  fois avec deux titres, et vérifiant `isPublic` inchangé, le fermerait.

- [x] 6.3 Lancer `openspec validate audio-sans-convention --type change --strict` avant archivage

  Relevé : `Change 'audio-sans-convention' is valid`, code de retour `0`.

- [ ] 6.4 Livrer en un commit unique : tests, code, gabarits, documentation

  Laissée décochée : **ce n'est pas ce qui s'est passé**. La branche
  `audio-sans-convention` porte plusieurs commits, un par task, plus ceux du
  correctif post-Task 6. Le commit unique est une décision de merge (squash),
  pas un fait de cette branche — et le prétendre acquis serait faux.
- [ ] 6.5 Après déploiement, relever combien des cinq émissions non servies réapparaissent (`ailleurs-97`, `-115`, `-234`, `-304`, `-316`) — c'est la mesure qui dira si le nom était en cause ou si l'audio manquait

  **Post-déploiement : elle ne peut pas être cochée ici**, et elle est laissée
  telle quelle. Préparée : les cinq dossiers existent et portent un
  `index.json`. Les cinq sont **déjà servies en local**, mais uniquement parce
  que `bin/dev-audio-fixtures` y a posé un MP3 vide au nom canonique — le local
  ne dit donc rien de ce que Nextcloud porte. Le relevé d'après déploiement
  garde tout son sens. Détail au plan, Task 8, Step 4.

## 7. Harnais de `bootstrap.php` (ajouté en relecture)

`bootstrap.php` n'était amorcé par aucune suite, et deux mutations y
survivaient. Leur seul juge était un script `var_export()` non versionné.

- [x] 7.1 Verser ce juge dans le dépôt : `src/tests/BootstrapShowTest.php` et son relevé `src/tests/support/probe-bootstrap.php`
- [x] 7.2 Vérifier par mutation que le harnais tue ce qu'il prétend tenir — trois mutations appliquées, suite relancée, fichier restauré (`md5` vérifié)
- [x] 7.3 Documenter dans `CLAUDE.local.md` ce que ce harnais tient, pourquoi il tourne dans un processus fils, et pourquoi il ne doit pas poser de fixtures
