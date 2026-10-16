// Garde l’onglet actif visible dans la barre de navigation sur téléphone.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage((ecouter) => {
    const navigation = document.querySelector('.sidebar-navigation');
    const actif = navigation?.querySelector('[aria-current="page"]');
    if (!actif) return;
    const mobile = window.matchMedia('(max-width: 700px)');
    // Fait défiler la navigation mobile seulement si l’onglet actif dépasse de la zone visible.
    function montrerOngletActif() {
        if (!mobile.matches) return;
        const cadre = navigation.getBoundingClientRect();
        const lien = actif.getBoundingClientRect();
        if (lien.right > cadre.right) navigation.scrollLeft += lien.right - cadre.right;
        else if (lien.left < cadre.left) navigation.scrollLeft -= cadre.left - lien.left;
    }
    montrerOngletActif();
    ecouter(mobile, 'change', montrerOngletActif);
});
