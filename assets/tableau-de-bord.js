// Gère le repli des sections du tableau de bord et la consultation des livres lus par genre.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage((ecouter) => {
    const page = document.getElementById('page-dashboard');
    if (!page) {
        return;
    }

    const modaleGenre = document.getElementById('dashboard-genre-modal');
    const contenuGenre = document.getElementById('dashboard-genre-contenu');
    let requeteGenre;
    let numeroGenre = 0;

    // Annule un chargement à la fermeture pour qu’une ancienne réponse ne remplace pas le prochain genre.
    ecouter(modaleGenre, 'close', () => {
        numeroGenre++;
        requeteGenre?.abort();
    });
    ecouter(document.getElementById('dashboard-genre-fermer'), 'click', () => modaleGenre.close());

    // Charge les lectures du genre choisi et ignore les réponses d’une sélection précédente.
    ecouter(page, 'click', async (event) => {
        const bouton = event.target.closest('[data-genre-url]');
        if (!bouton) {
            return;
        }

        const numero = ++numeroGenre;
        requeteGenre?.abort();
        requeteGenre = new AbortController();
        document.getElementById('dashboard-genre-titre').textContent =
            `Livres lus : ${bouton.querySelector('.dashboard-genre-nom').textContent}`;
        contenuGenre.textContent = 'Chargement…';
        if (!modaleGenre.open) {
            modaleGenre.showModal();
        }
        try {
            const reponse = await fetch(bouton.dataset.genreUrl, {
                signal: requeteGenre.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!reponse.ok || reponse.redirected) {
                throw new Error(
                    'Impossible de charger les livres. Fermez cette fenêtre et réessayez.'
                );
            }

            const html = await reponse.text();
            if (numero !== numeroGenre || !modaleGenre.isConnected || !modaleGenre.open) {
                return;
            }
            contenuGenre.innerHTML = html;
        } catch (erreur) {
            if (erreur.name !== 'AbortError' && numero === numeroGenre && modaleGenre.isConnected) {
                contenuGenre.textContent = erreur.message;
            }
        }
    });

    // Associe chaque flèche à sa liste pour que les sections se replient indépendamment.
    page.querySelectorAll('.dashboard-basculer').forEach((bouton) => {
        const liste = document.getElementById(bouton.getAttribute('aria-controls'));
        if (!liste) {
            return;
        }

        // Synchronise la visibilité de la liste et le libellé annoncé aux lecteurs d’écran.
        const actualiser = () => {
            const ouvert = bouton.getAttribute('aria-expanded') === 'true';
            liste.hidden = !ouvert;
            bouton.setAttribute(
                'aria-label',
                `${ouvert ? 'Masquer' : 'Afficher'} ${bouton.dataset.nom}`
            );
        };

        // Inverse l’état de la section lorsque sa flèche est activée.
        ecouter(bouton, 'click', () => {
            bouton.setAttribute(
                'aria-expanded',
                String(bouton.getAttribute('aria-expanded') !== 'true')
            );
            actualiser();
        });
        actualiser();
    });
});
