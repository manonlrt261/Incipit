// Recherche les fiches d’auteurs et change les catégories sans recharger la page.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage(ecouter => {
    const recherche = document.getElementById('auteurs-recherche');
    if (recherche) {
        const cartes = [...document.querySelectorAll('[data-auteur-nom]')];
        // Ignore les accents et les majuscules, comme les autres recherches de livres.
        const normaliser = texte => texte.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr').trim();
        function filtrer() {
            let nombre = 0;
            cartes.forEach(carte => {
                carte.hidden = !normaliser(carte.dataset.auteurNom).includes(normaliser(recherche.value));
                if (!carte.hidden) nombre++;
            });
            document.getElementById('auteurs-compteur').textContent = `${nombre} auteur${nombre > 1 ? 's' : ''}`;
            document.getElementById('auteurs-vide').hidden = nombre !== 0;
        }
        ecouter(recherche, 'input', filtrer);
        ecouter(document.getElementById('auteurs-recherche-form'), 'submit', event => {
            event.preventDefault();
            filtrer();
        });
    }
    const fiche = document.getElementById('page-auteur-fiche');
    if (!fiche) return;
    const onglets = [...fiche.querySelectorAll('[role="tab"]')];
    // Garde un seul panneau visible et conserve les repères accessibles des quatre catégories.
    function choisir(choix) {
        onglets.forEach(onglet => {
            const actif = onglet === choix;
            onglet.setAttribute('aria-selected', String(actif));
            onglet.tabIndex = actif ? 0 : -1;
            document.getElementById(onglet.getAttribute('aria-controls')).hidden = !actif;
        });
    }
    onglets.forEach((onglet, index) => {
        ecouter(onglet, 'click', () => choisir(onglet));
        ecouter(onglet, 'keydown', event => {
            let suivant;
            if (event.key === 'ArrowRight') suivant = (index + 1) % onglets.length;
            if (event.key === 'ArrowLeft') suivant = (index + onglets.length - 1) % onglets.length;
            if (event.key === 'Home') suivant = 0;
            if (event.key === 'End') suivant = onglets.length - 1;
            if (suivant === undefined) return;
            event.preventDefault();
            choisir(onglets[suivant]);
            onglets[suivant].focus();
        });
    });
});
