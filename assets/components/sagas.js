// Propose les sagas déjà enregistrées uniquement pour les auteurs du livre en cours de saisie.
import { initialiserPage } from './initialisation-page.js';

initialiserPage(ecouter => {
    const form = document.getElementById('form-ajout-livre');
    const champ = document.getElementById('modal-saga');
    const liste = document.getElementById('modal-suggestions-sagas');
    const auteurs = document.getElementById('modal-liste-auteurs');
    const modal = document.getElementById('modal-ajout-livre');
    if (!form || !champ || !liste || !auteurs || !modal) return;
    let attente;
    let controleur;
    let numero = 0;
    let selection = -1;
    // Transmet les auteurs saisis sans doublons ni espaces superflus pour limiter les suggestions.
    const nomsAuteurs = () => [...new Set([...auteurs.querySelectorAll('input[name="auteurs[]"]')]
        .map(input => input.value.trim().replace(/\s+/g, ' ')).filter(Boolean))];

    // Annule aussi les réponses du contexte précédent si un auteur, le livre ou le formulaire change.
    function fermer() {
        clearTimeout(attente);
        controleur?.abort();
        numero++;
        selection = -1;
        liste.replaceChildren();
        liste.hidden = true;
        champ.setAttribute('aria-expanded', 'false');
        champ.removeAttribute('aria-activedescendant');
    }
    // Attend une pause de saisie avant de rechercher les sagas des auteurs du formulaire.
    function proposer() {
        fermer();
        const texte = champ.value.trim();
        const noms = nomsAuteurs();
        if (!texte || !noms.length) return;
        const contexte = JSON.stringify(noms);
        const requete = numero;
        attente = setTimeout(async () => {
            controleur = new AbortController();
            try {
                const url = new URL(form.dataset.rechercheSagas, window.location.origin);
                url.searchParams.set('q', texte);
                noms.forEach(nom => url.searchParams.append('auteurs[]', nom));
                const reponse = await fetch(url, { signal: controleur.signal, headers: { Accept: 'application/json' } });
                if (!reponse.ok) return;
                const resultat = await reponse.json();
                // Ignore une réponse devenue obsolète après une autre saisie ou la fermeture du formulaire.
                if (requete !== numero || !modal.open || !form.isConnected || champ.value.trim() !== texte
                    || contexte !== JSON.stringify(nomsAuteurs()) || document.activeElement !== champ) return;
                const propositions = [...new Set(resultat.sagas || [])];
                if (!propositions.length) return;
                liste.replaceChildren(...propositions.map((nom, index) => {
                    const option = document.createElement('div');
                    option.id = `saga-option-${index}`;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.textContent = nom;
                    return option;
                }));
                liste.hidden = false;
                champ.setAttribute('aria-expanded', 'true');
            } catch {
                // Une nouvelle saga reste saisissable même si les propositions sont indisponibles.
            }
        }, 250);
    }
    // Recopie la saga choisie et rend le champ disponible pour poursuivre la saisie.
    function choisir(option) {
        if (!option) return;
        champ.value = option.textContent;
        fermer();
        champ.focus();
    }
    ecouter(champ, 'input', proposer);
    ecouter(champ, 'focus', proposer);
    ecouter(champ, 'blur', fermer);
    // Conserve le focus jusqu’au clic pour éviter que la liste se ferme avant la sélection.
    ecouter(liste, 'pointerdown', event => event.preventDefault());
    ecouter(liste, 'click', event => choisir(event.target.closest('[role="option"]')));
    // Invalide les suggestions dès que les auteurs changent ou que le formulaire est quitté.
    ecouter(auteurs, 'input', fermer);
    ecouter(auteurs, 'change', fermer);
    ecouter(auteurs, 'click', event => { if (event.target.closest('button')) fermer(); });
    ecouter(form, 'reset', fermer);
    ecouter(modal, 'close', fermer);
    // Parcourt les propositions sans soumettre le livre ni empêcher la saisie d’une saga nouvelle.
    ecouter(champ, 'keydown', event => {
        if (liste.hidden) return;
        const options = [...liste.children];
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            fermer();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            selection = (selection + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === selection)));
            champ.setAttribute('aria-activedescendant', options[selection].id);
            options[selection].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && selection >= 0) {
            event.preventDefault();
            choisir(options[selection]);
        }
    });
});
