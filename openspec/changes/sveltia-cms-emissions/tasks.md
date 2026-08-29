## 1. Validation préalable — go / no-go

Cette étape décide si le change continue. Elle se fait sur le contenu actuel, non migré.

- [x] 1.1 Poser un `src/public/admin/` provisoire : page d'accueil Sveltia + `config.yml` minimal, une seule collection pointant sur `manifest.json` (`folder: src/public/assets/emission`, `path: '{{slug}}/manifest'`, `format: json`)
- [x] 1.2 S'authentifier par jeton personnel GitHub et vérifier que les 367 entrées se listent, s'ouvrent et s'enregistrent à une vitesse acceptable
- [x] 1.3 Consigner la décision go / no-go. En cas de no-go, arrêter le change ici : la migration perd son débouché

## 2. Nettoyage du contenu existant

Préalable à l'assouplissement du glob de couvertures (design, décision 4). Sans lui, du bruit remonterait en image d'émission.

- [x] 2.1 Traiter `ailleurs-16/` : dossier sans aucun des trois fichiers, déjà en 404 — compléter ou supprimer
- [x] 2.2 Inventorier sur les 367 dossiers tout fichier qui n'est ni `manifest.json`, ni `description.html`, ni `playlist.html`, ni une image conforme `*_cover-*.*`, ni un audio
- [x] 2.3 Supprimer le bruit non-image relevé : quatre `.DS_Store`, `ailleurs-42/A_EFFACER`, les `*_TITLESLUG.mp3.txt`
- [x] 2.4 Trancher au cas par cas les images hors convention (`unnamed.jpg`, `buveete.jpeg`, `carton tendron.jpg`, `eat_cheering.gif`, `ouiedire.jpeg`, famille `*_cover_hd-*`) : renommer selon la convention si l'image est légitime, supprimer sinon
- [x] 2.5 Corriger `ailleurs-222/`, dont les couvertures sont numérotées `ailleurs-221` — sans objet après 2.4 : les fichiers fautifs étaient les `*_cover_hd-*`, supprimés ; les deux couvertures conformes de l'émission sont correctement numérotées
- [x] 2.6 Doter `ailleurs-188/` et `ailleurs-42/` d'une couverture conforme, ou acter qu'elles s'appuieront sur l'image de repli — **acté : repli** (aucune image disponible pour ces deux émissions)
- [x] 2.7 Vérifier que les sept émissions à couvertures multiples sont intactes : `ailleurs-186`, `-221`, `-222`, `-80`, `-97`, `bagage-7`, `ouiedire-3`

## 3. Capture de l'état de référence

Ces deux captures sont le seul filet de sécurité du change : le stack n'a pas de tests.

- [x] 3.1 Script capturant, pour les 367 émissions, le `<ol>` de playlist restitué — enregistrer comme référence
- [x] 3.2 Script capturant l'ensemble des artistes distincts — enregistrer comme référence et vérifier le total de 5462

## 4. Script de migration

- [x] 4.1 Implémenter le classement d'une ligne `<li>` selon les trois formes de la spec (repère + artiste + titre / repère + artiste / entrée préservée)
- [x] 4.2 Mode rapport en lecture seule : décompte par forme, global et par émission
- [x] 4.2b Normaliser le HTML source des lignes qui ne peuvent pas faire l'aller-retour (séparateur absent, balise orpheline, tiret collé, attributs en trop) — 701 lignes dans 174 émissions, vérifié à 100 % d'aller-retour exact
- [x] 4.3 Confronter le rapport aux totaux attendus (6349 / 253 / 156 / 820) et corriger le classement tant que les écarts ne sont pas expliqués
- [x] 4.4 Mode écriture : produire un `index.json` par émission portant `title`, `authors`, `releasedAt`, `type`, `isPublic`, `description`, `playlist`
- [x] 4.5 Vérifier sur un échantillon contrasté (`ailleurs-331`, `ouiedire-3`, `ailleurs-42`, une émission à entrées préservées) que le JSON est bien formé et que la description est préservée à l'octet près

## 5. Adaptation du site

- [x] 5.1 `getShow()` : lire `index.json` au lieu des trois fichiers, en conservant la forme du tableau `$show` exposée aux vues
- [x] 5.2 Écrire le partiel Twig de playlist restituant les trois formes, y compris le balisage de mise en forme porté par un titre
- [x] 5.3 Brancher le partiel dans [emission.html.twig:82](../../../src/views/emission.html.twig:82) et [embed.html.twig:28](../../../src/views/embed.html.twig:28)
- [x] 5.4 Produire la playlist du flux de syndication par le même partiel ([bootstrap.php:511](../../../src/src/bootstrap.php:511))
- [x] 5.5 `getArtists()` : lire le tableau de playlist, supprimer le passage par le crawler DOM, et vérifier les trois appelants ([:411](../../../src/src/bootstrap.php:411), [:671](../../../src/src/bootstrap.php:671), [:719](../../../src/src/bootstrap.php:719))
- [x] 5.6 Vérifier qu'aucun autre consommateur ne traite `$show['playlist']` comme une chaîne — `bin/tag` et `bin/extract-artists` passent par `getShow()` et ne touchent que `description`
- [x] 5.7 Couvertures : filtrage par extension d'image, tri convention-d'abord puis alphabétique, et garantie que la première couverture existe toujours
- [x] 5.8 Ajouter l'image de repli utilisée lorsqu'une émission n'a aucune image
- [x] 5.9 Migrer le squelette cookiecutter `src/skel/emission/` vers `index.json` — sans quoi la GitHub Action produirait des émissions à l'ancien format dès la bascule

## 6. Bascule et vérification

- [x] 6.1 Exécuter la migration et livrer contenu migré, code, partiel et squelette cookiecutter dans un **commit unique** (design, décision 8)
- [x] 6.2 Rejouer la capture 3.1 : le diff des 367 playlists restituées doit être vide hors espaces
- [x] 6.3 Rejouer la capture 3.2 : les 5462 artistes distincts doivent être identiques
- [x] 6.4 Contrôle visuel des sept émissions à couvertures multiples, plus `ailleurs-188` et `ailleurs-42` sur l'image de repli
- [x] 6.5 Vérifier qu'une émission dont l'audio est absent reste invisible du public malgré `isPublic` à vrai
- [x] 6.6 Vérifier la page d'intégration et le flux de syndication d'une émission au hasard

## 7. Mise en place de l'outil d'édition

- [x] 7.1 `config.yml` définitif : collection unique, `path: '{{slug}}/index'`, `format: json`, `media_folder: ''`, `public_folder: ''`
- [x] 7.2 Déclarer les champs : `title`, `authors`, `releasedAt` en date, `type` en liste fermée (Ailleurs / Bagage / Bureau / Ouïedire), `isPublic` en booléen, `description` en texte multiligne
- [x] 7.3 Déclarer `playlist` en liste, chaque entrée exposant séparément repère temporel, artiste et titre, et préservant les entrées non interprétées
- [x] 7.4 Activer la création avec un slug contraint à `<type>-<numéro>`, et vérifier qu'un slug dérivé du titre est impossible — `{{fields._slug}}` : saisie explicite obligatoire, donc jamais dérivé du titre. La **forme** n'est pas validée (voir design, décision 5) ; un identifiant non conforme donne un 404 mesuré, sans perte de données
- [x] 7.5 Déclarer le champ image de couverture
- [ ] 7.6 Test bout en bout — création : créer une émission, vérifier le dossier produit, son `index.json`, et son invisibilité publique tant que l'audio manque
- [ ] 7.7 Test bout en bout — édition : ajouter, supprimer et réordonner des entrées de playlist, puis vérifier le rendu publié
- [ ] 7.8 Test bout en bout — couverture : déposer une image sur une émission déjà pourvue et vérifier que la vignette de partage ne change pas

## 8. Finitions

- [x] 8.1 Basculer le lien « EDIT » de [emission.html.twig:33](../../../src/views/emission.html.twig:33) vers l'outil d'édition
- [x] 8.2 Supprimer `.pages.yml`, vestige de la tentative PagesCMS
- [x] 8.3 Mettre à jour le README : circuit de publication et circuit de maintenance
- [x] 8.4 Trancher le sort de `bin/extract-artists` (open question du design) — **conservé** : vérifié fonctionnel après migration, il passe par `getShow()` et `getArtists()`
- [x] 8.5 Retirer le `src/public/admin/` provisoire de l'étape 1 s'il diffère de la configuration définitive
