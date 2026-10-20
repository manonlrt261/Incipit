// Partage la gestion des aperçus de couverture entre les formulaires.
export function creerGestionnaireCouverture({
    apercu,
    fichier,
    champImage
}) {
    let urlApercuLocale = null;

    // Affiche une image locale et efface l’URL distante pour éviter deux sources de couverture.
    function afficherFichier(image) {
        if (!image || !image.type.startsWith('image/')) {
            return;
        }

        nettoyer();

        urlApercuLocale = URL.createObjectURL(image);

        const elementImage = document.createElement('img');
        elementImage.src = urlApercuLocale;
        elementImage.alt = 'Aperçu de la couverture sélectionnée';

        apercu?.replaceChildren(elementImage);

        if (champImage) {
            champImage.value = '';
        }
    }

    // Affiche une couverture distante et efface le fichier local précédemment sélectionné.
    function afficherURL(url, titre = 'Livre') {
        if (!url) return;

        nettoyer();

        const elementImage = document.createElement('img');
        elementImage.src = url;
        elementImage.alt = `Couverture de ${titre}`;

        apercu?.replaceChildren(elementImage);

        if (champImage) {
            champImage.value = url;
        }

        if (fichier) {
            fichier.value = '';
        }
    }

    // Libère l’URL temporaire de l’image pour éviter de conserver des ressources inutiles.
    function nettoyer() {
        if (urlApercuLocale) {
            URL.revokeObjectURL(urlApercuLocale);
            urlApercuLocale = null;
        }
    }

    return {
        afficherFichier,
        afficherURL,
        nettoyer
    };
}
