// Permet d’afficher ou de masquer les listes du tableau de bord indépendamment.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage((ecouter) => {
    const page = document.getElementById('page-dashboard');
    if (!page) return;
    // Associe chaque flèche à sa liste pour que les sections se replient indépendamment.
    page.querySelectorAll('.dashboard-basculer').forEach(bouton => {
        const liste = document.getElementById(bouton.getAttribute('aria-controls'));
        if (!liste) return;
        // Synchronise la visibilité de la liste et le libellé annoncé aux lecteurs d’écran.
        const actualiser = () => {
            const ouvert = bouton.getAttribute('aria-expanded') === 'true';
            liste.hidden = !ouvert;
            bouton.setAttribute('aria-label', `${ouvert ? 'Masquer' : 'Afficher'} ${bouton.dataset.nom}`);
        };
        // Inverse l’état de la section lorsque sa flèche est activée.
        ecouter(bouton, 'click', () => {
            bouton.setAttribute('aria-expanded', String(bouton.getAttribute('aria-expanded') !== 'true'));
            actualiser();
        });
        actualiser();
    });
});
