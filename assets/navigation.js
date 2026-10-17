// Garde l’onglet actif visible dans la barre de navigation sur téléphone.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage((ecouter) => {
    // Évite le contour laissé par le retour du focus après une fiche ouverte à la souris ou au toucher.
    ecouter(document, 'pointerdown', event => {
        if (!(event.target instanceof Element)) return;
        const carte = event.target.closest('.bibliotheque-livre, .bibliotheque-livre-retire, .lectures-carte, .wishlist-carte, .accueil-carte-livre, .dashboard-livre');
        if (carte) carte.classList.add('carte-focus-souris');
    });

    // Rétablit immédiatement le repère quand la personne utilise le clavier.
    ecouter(document, 'keydown', () => {
        document.querySelectorAll('.carte-focus-souris').forEach(carte => {
            carte.classList.remove('carte-focus-souris');
        });
    });

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
