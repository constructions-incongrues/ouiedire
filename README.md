# ouiedire

[
    ![Ouvrir dans Visual Studio Code](
        https://img.shields.io/static/v1?label=Remote%20Containers&message=Open&color=blue&logo=visualstudiocode
    )
](
    https://vscode.dev/redirect?url=vscode://ms-vscode-remote.remote-containers/cloneInVolume?url=https://github.com/constructions-incongrues/ouiedire
) [
    ![Ouvrir dans Github Codespaces](
        https://img.shields.io/static/v1?label=Codespaces&message=Open&color=green&logo=github
    )
](
    https://github.com/codespaces/new?hide_repo_select=true&ref=main&repo=9930817
)

## Comment faire pour...

### Corriger une émission déjà en ligne

Titre, auteurices, date, description, liste de lecture, visibilité, couverture :
tout se modifie depuis l'interface d'édition, à l'adresse `/admin/`.

- Se rendre sur <https://www.ouiedire.net/admin/>
- Choisir « Sign In with Token » et coller un jeton personnel GitHub ayant accès au dépôt
- Chercher l'émission, la modifier, enregistrer

Le lien « EDIT » en bas de chaque page d'émission y mène directement.

La liste de lecture s'édite morceau par morceau — repère de temps, artiste, titre —
sans avoir à écrire de HTML. Une partie des émissions les plus anciennes conserve
une liste de lecture en HTML brut : leur balisage ne rentrait pas dans le moule sans
altérer la page, il est donc restitué tel quel.

### Publier une nouvelle émission

- Cliquer sur le bouton "Run Workflow" à droite de la page la Github Action appelée ["Nouvelle Émission"](https://github.com/constructions-incongrues/ouiedire/actions/workflows/emission.yml)
- Remplir le formulaire qui apparaît autant que faire se peut (les informations pourront être modifiées plus tard) puis le soumettre
- Il ne reste plus qu'à suivre les instructions détaillées de la [Pull Request](https://github.com/constructions-incongrues/ouiedire/pulls) qui est créée à l'occasion

Une fois la Pull Request fusionnée, le reste se fait depuis `/admin/` : description,
liste de lecture, couverture.

**Le fichier MP3 reste à déposer séparément** dans le dossier de l'émission sur
<https://vip.jeancloude.club/s/gsiYSS78WxFegnt>. Tant qu'aucun audio n'est disponible,
l'émission demeure invisible du public, quelle que soit sa visibilité déclarée.

Le nom du fichier audio est libre : le site retient un fichier par format (MP3,
FLAC) dans le dossier de l'émission, quel que soit son nom. Une faute de frappe
dans le titre ne dépublie plus l'émission.

Le téléchargement, lui, propose toujours un nom canonique, construit à partir du
type, du numéro rempli à trois chiffres, des auteurices et du titre :
`ouiedire_ailleurs-331_rachitik-data_la-pompa-calor-vol-3.mp3`. Ce nom est
recalculé depuis le manifeste à chaque visite : corriger un titre depuis
`/admin/` change le nom proposé sans toucher au fichier déposé. Limite connue,
et défaut à réparer : les caractères accentués y sont supprimés au lieu d'être
translittérés, si bien que « Sans thème » donne `sans-thme`.

Déposer plusieurs fichiers d'un même format dans un dossier reste possible, mais
exceptionnel. Le site retient alors en premier ceux qui suivent la convention
historique `ouiedire_<collection>-<numéro>_`, où le numéro est reconnu sous sa
forme brute (`ailleurs-1`) comme sous sa forme remplie (`ailleurs-001`) ;
l'ordre alphabétique départage à l'intérieur de chaque groupe.

C'est prêt !

## Où vivent les données

Une émission est un dossier `src/public/assets/emission/<collection>-<numéro>/`
contenant un `index.json` — titre, auteurices, date, collection, visibilité,
description et liste de lecture — et ses images de couverture. Le nom du dossier
porte l'identité de l'émission : il doit garder la forme `<collection>-<numéro>`.
