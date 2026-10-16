// Gère les filtres, le tri, l’achat et la suppression des livres de la wishlist.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage((ecouter) => {
    const page = document.getElementById('page-wishlist');
    if (!page) return;
    const grille = document.getElementById('wishlist-livres');
    const recherche = document.getElementById('wishlist-recherche');
    const genre = document.getElementById('wishlist-genre');
    const boutonDate = document.getElementById('wishlist-date');
    const message = document.getElementById('wishlist-message');
    let recents = true;
    // Ignore les accents et les majuscules pour faciliter les recherches.
    const normaliser = texte => String(texte || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

    // Applique la recherche, le genre et le tri par date, puis actualise le compteur de la wishlist.
    function filtrer() {
        const cartes = [...grille.querySelectorAll('.wishlist-carte')];
        const texte = normaliser(recherche.value);
        cartes.sort((a, b) => {
            const difference = Number(b.dataset.date) - Number(a.dataset.date) || Number(b.dataset.id) - Number(a.dataset.id);
            return recents ? difference : -difference;
        });
        let visibles = 0;
        cartes.forEach(carte => {
            carte.hidden = !normaliser(carte.dataset.recherche).includes(texte)
                || Boolean(genre.value && !carte.dataset.genres.split('|').includes(genre.value));
            if (!carte.hidden) visibles++;
            grille.appendChild(carte);
        });
        document.getElementById('wishlist-compteur').textContent = `${visibles} livre${visibles > 1 ? 's' : ''} dans ma wishlist`;
        document.getElementById('wishlist-vide').hidden = cartes.length !== 0;
        document.getElementById('wishlist-aucun-resultat').hidden = cartes.length === 0 || visibles !== 0;
        boutonDate.textContent = recents ? 'Date d’ajout : plus récents ↓' : 'Date d’ajout : plus anciens ↑';
        boutonDate.setAttribute('aria-label', `Date d’ajout : ${recents ? 'plus récents' : 'plus anciens'} d’abord. Inverser le tri.`);
    }
    // Actualise les résultats à mesure que la recherche est saisie.
    ecouter(recherche, 'input', filtrer);
    // Actualise la wishlist lorsque le genre sélectionné change.
    ecouter(genre, 'change', filtrer);
    // Applique la recherche de la wishlist sans rechargement.
    ecouter(document.getElementById('wishlist-recherche-form'), 'submit', event => { event.preventDefault(); filtrer(); });
    // Inverse le tri entre les ajouts les plus récents et les plus anciens.
    ecouter(boutonDate, 'click', () => { recents = !recents; filtrer(); });
    // Rétablit la recherche vide, tous les genres et le tri par ajouts récents.
    ecouter(document.getElementById('wishlist-reinitialiser'), 'click', () => {
        recherche.value = ''; genre.value = ''; recents = true; filtrer();
    });
    // Gère les actions des cartes, y compris celles ajoutées après le chargement.
    ecouter(grille, 'click', async event => {
        if (!(event.target instanceof Element)) return;
        const bouton = event.target.closest('button[data-action]');
        const carte = bouton?.closest('.wishlist-carte');
        if (!carte || bouton.disabled) return;
        message.hidden = true;
        const action = bouton.dataset.action;
        if (action === 'modifier') {
            document.dispatchEvent(new CustomEvent('livre:modifier', { detail: { id: carte.dataset.id } }));
            return;
        }
        const boutons = [...carte.querySelectorAll('button')];
        boutons.forEach(element => { element.disabled = true; });
        try {
            const donnees = new FormData();
            donnees.append('_token', carte.dataset.token);
            const reponse = await fetch(carte.dataset[action], {
                method: 'POST', body: donnees,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.success) throw new Error(resultat.message || 'Impossible d’enregistrer cette action.');
            carte.remove();
            filtrer();
        } catch (erreur) {
            message.textContent = erreur.message;
            message.hidden = false;
        } finally {
            boutons.forEach(element => { element.disabled = false; });
        }
    });
    filtrer();
});
