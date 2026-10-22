// Initialise chaque page sans cumuler les écouteurs lors des navigations Turbo.
export function initialiserPage(initialiser) {
    let controleur;

    // Retire tous les écouteurs de la page pour éviter les déclenchements en double.
    function nettoyer() {
        controleur?.abort();
        controleur = null;
    }

    // Crée les écouteurs une seule fois pour la page actuellement affichée.
    function demarrer() {
        if (controleur) {
            return;
        }

        controleur = new AbortController();
        const { signal } = controleur;
        const ecouter = (cible, evenement, action) => {
            cible?.addEventListener(evenement, action, { signal });
        };

        initialiser(ecouter);
    }

    // Retire les écouteurs avant la mise en cache ou le remplacement de la page par Turbo.
    document.addEventListener('turbo:before-cache', nettoyer);
    document.addEventListener('turbo:before-render', nettoyer);

    // Réactive les interactions après chaque navigation ou restauration Turbo.
    document.addEventListener('turbo:load', demarrer);

    // Attend la disponibilité du document lors du premier chargement.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer, { once: true });
    } else {
        demarrer();
    }
}
