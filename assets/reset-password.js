// Permet d’afficher ou masquer chaque mot de passe et synchronise son icône et son libellé.
import { initialiserPage } from './components/initialisation-page.js';

initialiserPage((ecouter) => {
    document.querySelectorAll('[data-reset-password-toggle]').forEach((bouton) => {
        const champ = document.getElementById(bouton.dataset.resetPasswordToggle);
        const image = bouton.querySelector('img');
        if (!champ || !image) {
            return;
        }

        // Affiche l’icône et le libellé de l’action possible sur le mot de passe.
        function synchroniser() {
            const affiche = champ.type === 'text';
            image.src = affiche ? image.dataset.imageMasquer : image.dataset.imageAfficher;
            bouton.setAttribute('aria-pressed', String(affiche));
            bouton.setAttribute(
                'aria-label',
                affiche ? 'Masquer le mot de passe' : 'Afficher le mot de passe'
            );
        }

        ecouter(bouton, 'click', () => {
            champ.type = champ.type === 'password' ? 'text' : 'password';
            synchroniser();
        });

        // Reprend l’état réel du champ si le navigateur restaure la page depuis son historique.
        ecouter(window, 'pageshow', synchroniser);
        synchroniser();
    });
});
