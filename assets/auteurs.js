// Recherche les fiches d’auteurs et change les catégories sans recharger la page.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage(ecouter => {
    const recherche = document.getElementById('auteurs-recherche');
    if (recherche) {
        const cartes = [...document.querySelectorAll('[data-auteur-nom]')];
        const ordreInitial = [...cartes];
        const prenom = document.getElementById('auteurs-tri-prenom');
        const nom = document.getElementById('auteurs-tri-nom');
        const alphabet = new Intl.Collator('fr', { sensitivity: 'base', numeric: true });
        // Ignore les accents et les majuscules, comme les autres recherches de livres.
        const normaliser = texte => texte.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr').trim();
        function filtrer() {
            let nombre = 0;
            cartes.forEach(carte => {
                carte.hidden = !normaliser(carte.dataset.auteurNom).includes(normaliser(recherche.value));
                if (!carte.hidden) nombre++;
            });
            // Le dernier menu choisi pilote le tri sans effacer la recherche.
            const champ = nom.value ? 'nom' : 'prenom';
            const sens = nom.value || prenom.value;
            if (sens) {
                cartes.sort((a, b) => {
                    const x = a.dataset[champ], y = b.dataset[champ];
                    if (!x && y) return 1;
                    if (x && !y) return -1;
                    return alphabet.compare(x, y) * (sens === 'desc' ? -1 : 1) || alphabet.compare(a.dataset.auteurNom, b.dataset.auteurNom);
                });
                cartes.forEach(carte => document.querySelector('.auteurs-grille').appendChild(carte));
            }
            document.getElementById('auteurs-compteur').textContent = `${nombre} auteur${nombre > 1 ? 's' : ''}`;
            document.getElementById('auteurs-vide').hidden = nombre !== 0;
        }
        // Efface recherche et tris, puis rétablit l’ordre initial des fiches sans navigation.
        ecouter(document.getElementById('auteurs-reinitialiser'), 'click', () => {
            recherche.value = '';
            prenom.value = '';
            nom.value = '';
            cartes.splice(0, cartes.length, ...ordreInitial);
            cartes.forEach(carte => document.querySelector('.auteurs-grille').appendChild(carte));
            filtrer();
        });
        ecouter(recherche, 'input', filtrer);
        ecouter(prenom, 'change', () => { nom.value = ''; filtrer(); });
        ecouter(nom, 'change', () => { prenom.value = ''; filtrer(); });
        ecouter(document.getElementById('auteurs-recherche-form'), 'submit', event => {
            event.preventDefault();
            filtrer();
        });
    }
    const fiche = document.getElementById('page-auteur-fiche');
    if (!fiche) return;
    let numeroFiche = 0;
    let requeteFiche;
    // Charge la fiche complète seulement au clic, sans changer la page ou l’onglet actif.
    async function ouvrirLivre(carte) {
        const numero = ++numeroFiche;
        requeteFiche?.abort();
        requeteFiche = new AbortController();
        const erreur = document.getElementById('auteur-detail-erreur');
        erreur.hidden = true;
        try {
            const reponse = await fetch(carte.dataset.detailsUrl, { signal: requeteFiche.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!reponse.ok) throw new Error('Impossible de charger la fiche de ce livre.');
            const html = await reponse.text();
            if (numero !== numeroFiche || !fiche.isConnected) return;
            const zone = document.getElementById('auteur-detail-conteneur');
            zone.innerHTML = html;
            zone.querySelector('dialog').showModal();
        } catch (e) {
            if (e.name === 'AbortError' || numero !== numeroFiche) return;
            erreur.textContent = e.message;
            erreur.hidden = false;
        }
    }
    ecouter(fiche, 'click', event => {
        if (event.target.closest('#auteur-details-fermer')) {
            document.getElementById('auteur-details-livre')?.close();
            return;
        }
        const carte = event.target.closest('[data-details-url]');
        if (carte) ouvrirLivre(carte);
    });
    ecouter(fiche, 'keydown', event => {
        const carte = event.target.closest('[data-details-url]');
        if (carte && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            ouvrirLivre(carte);
        }
    });
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
