import { initialiserPage } from './components/initialisation-page.js';

initialiserPage(ecouter => {
    // Affiche ou masque chaque saisie indépendamment, comme sur la page de connexion.
    document.querySelectorAll('[data-reset-password-toggle]').forEach(bouton => {
        ecouter(bouton, 'click', () => {
            const champ = document.getElementById(bouton.dataset.resetPasswordToggle);
            const image = bouton.querySelector('img');
            const visible = champ.type === 'password';
            champ.type = visible ? 'text' : 'password';
            bouton.setAttribute('aria-pressed', String(visible));
            bouton.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            image.src = visible ? image.dataset.imageMasquer : image.dataset.imageAfficher;
        });
    });
});
