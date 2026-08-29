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
- [ ] 3.2 Poser l'attribut `download` sur les liens MP3 et FLAC de `emission.html.twig`, avec le nom canonique
- [ ] 3.3 Vérifier dans un navigateur qu'un fichier au nom libre s'enregistre bien sous le nom canonique

## 4. Retirer ce qui n'a plus d'objet

- [ ] 4.1 Supprimer `slugDownload` de `bootstrap.php` et le troisième bouton « Copier le nom de fichier attendu » de `emission.html.twig`
- [ ] 4.2 Vérifier qu'aucun gabarit ni script ne référence encore `slugDownload`

## 5. Documentation

- [ ] 5.1 `README.md` : affirmer que le nom du fichier audio n'a plus d'importance, là où le circuit décrit son dépôt
- [ ] 5.2 Relire la prose produite au regard du français et du Google developer documentation style

## 6. Vérification et bascule

- [ ] 6.1 Vérifier que les 367 émissions servent le même fichier audio qu'avant — la méthode de capture avant/après de `sveltia-cms-emissions` fournit le procédé
- [ ] 6.2 Vérifier qu'une émission dont on corrige le titre reste publiée et son audio téléchargeable : c'est le scénario qui motive ce change
- [ ] 6.3 Lancer `openspec validate audio-sans-convention --type change --strict` avant archivage
- [ ] 6.4 Livrer en un commit unique : tests, code, gabarits, documentation
- [ ] 6.5 Après déploiement, relever combien des cinq émissions non servies réapparaissent (`ailleurs-97`, `-115`, `-234`, `-304`, `-316`) — c'est la mesure qui dira si le nom était en cause ou si l'audio manquait
