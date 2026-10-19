// Gère l’ajout et la modification des livres, la recherche ISBN et le choix de leur couverture.
import './components/sagas.js';
import { initialiserPage } from './components/initialisation-page.js';

import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import { creerGestionnaireCouverture } from './components/couverture.js';

initialiserPage(ecouter => {
    const modal = document.getElementById('modal-ajout-livre');
    const form = document.getElementById('form-ajout-livre');

    if (!modal || !form) return;

    const modeWishlist = form.dataset.wishlist === '1';

    const champStatut = form.elements.namedItem('statut');

    // Conserve les dates saisies quand le statut change, mais n’envoie que celles qui s’appliquent.
    function actualiserDatesLecture() {
        const statut = champStatut?.value;
        const selectionPile = document.getElementById('modal-pile-a-lire');
        if (selectionPile) {
            // Un livre terminé ne peut pas rejoindre la sélection des prochaines lectures.
            selectionPile.disabled = statut === 'Terminé';
            if (selectionPile.disabled) selectionPile.checked = false;
        }
        // Affiche la note automatique et empêche de saisir une autre valeur pour une lecture abandonnée.
        const note = document.getElementById('modal-note');
        if (note) {
            note.readOnly = statut === 'Abandonné';
            if (note.readOnly) note.value = '0';
        }
        const debutVisible = ['En cours', 'En pause', 'Abandonné', 'Terminé'].includes(statut);
        const finVisible = statut === 'Terminé';
        const dateFin = document.getElementById('modal-date-fin');
        if (finVisible && dateFin && !dateFin.value) {
            const jour = new Date();
            dateFin.value = `${jour.getFullYear()}-${String(jour.getMonth() + 1).padStart(2, '0')}-${String(jour.getDate()).padStart(2, '0')}`;
        }
        for (const [nom, visible] of [['debut', debutVisible], ['fin', finVisible]]) {
            const zone = document.getElementById(`modal-date-${nom}-zone`);
            const champ = document.getElementById(`modal-date-${nom}`);
            if (zone) zone.hidden = !visible;
            if (champ) champ.disabled = !visible;
        }
    }
    ecouter(champStatut, 'change', actualiserDatesLecture);
    actualiserDatesLecture();

    const titreFenetre = document.getElementById('modal-titre-fenetre');
    const descriptionFenetre = document.getElementById('modal-description-fenetre');
    const champLivreId = document.getElementById('modal-livre-id');

    // Charge les informations du livre pour réutiliser le formulaire en mode modification.
    ecouter(document, 'livre:modifier', async event => {
        const id = event.detail?.id;

        if (!id) return;

        try {
            const reponse = await fetch(
                modeWishlist
                    ? `/wishlist/${encodeURIComponent(id)}/details`
                    : `/livre/${encodeURIComponent(id)}/details`,
                {
                    headers: {
                        Accept: 'application/json'
                    }
                }
            );

            const resultat = await reponse.json();

            if (!reponse.ok || !resultat.success) {
                throw new Error(resultat.message || 'Impossible de charger le livre.');
            }

            const livre = resultat.livre;
            const lecture = resultat.lectures?.[0];
            couvertureSelectionnee = null;

            // Efface la saisie précédente avant de préremplir les données du livre sélectionné.
            requetePhotoISBN++;
            annulerSuggestionsAuteurs();
            form.reset();
            const zonePile = document.getElementById('modal-pile-zone');
            if (zonePile) zonePile.hidden = true;

            if (champLivreId) {
                champLivreId.value = String(id);
            }

            if (titreFenetre) {
                titreFenetre.textContent = 'Modifier le livre';
            }

            if (boutonValider) {
                boutonValider.textContent = 'Enregistrer les modifications';
            }

            if (descriptionFenetre) {
                descriptionFenetre.textContent = 'Modifiez les informations de votre livre.';
            }

            const valeurs = {
                statut: lecture?.statut || 'Pas commencé',
                note: lecture?.note,
                dateDebut: lecture?.date_debut,
                dateFin: lecture?.date_fin,
                prixEstime: resultat.prixEstime,
                lienAchat: resultat.lienAchat,
                titre: livre.titre,
                sousTitre: livre.sous_titre,
                saga: livre.saga,
                tome: livre.tome,
                isbn13: livre.isbn13,
                isbn10: livre.isbn10,
                edition: livre.edition,
                langue: livre.langue,
                datePublication: livre.date_publication,
                nombrePages: livre.nombre_pages,
                resume: livre.resume,
                imageCouverture: livre.image_couverture
            };

            Object.entries(valeurs).forEach(([nom, valeur]) => {
                const champ = form.elements.namedItem(nom);

                if (champ && 'value' in champ) {
                    champ.value = valeur ?? '';
                }
            });

            afficherChampsAuteurs(livre.auteurs_liste || []);
            actualiserDatesLecture();
            if (modeWishlist) {
                document.getElementById('modal-publique').checked = Boolean(resultat.publique);
            }
            // Reprend les genres enregistrés pour ne pas les perdre en modifiant un autre champ.
            optionsGenres?.querySelectorAll('input').forEach(input => {
                input.checked = (livre.genres_ids || []).map(String).includes(input.value);
            });
            actualiserGenres();
            fermerGenres();
            if (livre.image_couverture) {
                const cheminCouverture = /^(https?:\/\/|\/)/i.test(livre.image_couverture)
                    ? livre.image_couverture
                    : `/${livre.image_couverture}`;
                gestionnaireCouverture.afficherURL(cheminCouverture, livre.titre);
                champImageCouverture.value = livre.image_couverture;
            } else {
                const texte = document.createElement('span');
                texte.textContent = 'Aucune couverture';
                apercuCouverture.replaceChildren(texte);
            }
            if (boutonCouverture?.lastChild?.nodeType === 3) {
                boutonCouverture.lastChild.textContent = ' Modifier la couverture';
            }

            // Fermer la fiche détaillée
            const modalDetails = document.getElementById('modal-details-livre');

            if (modalDetails?.open) {
                modalDetails.close();
            }

            // Ouvrir le formulaire de modification
            if (!modal.open) {
                modal.showModal();
            }
        } catch (erreur) {
            console.error('Erreur ouverture modification :', erreur);
            alert(erreur.message);
        }
    });

    const boutonGenres = document.getElementById('modal-genres-bouton');
    const optionsGenres = document.getElementById('modal-genres-options');
    const texteGenres = document.getElementById('modal-genres-texte');

    // Referme le menu des genres et met à jour son état accessible.
    function fermerGenres() {
        if (!optionsGenres || !boutonGenres) return;
        optionsGenres.hidden = true;
        boutonGenres.setAttribute('aria-expanded', 'false');
    }

    // Résume les genres cochés dans le bouton du menu déroulant.
    function actualiserGenres() {
        if (!optionsGenres || !texteGenres) return;

        const selection = [...optionsGenres.querySelectorAll('input:checked')]
            .map(input => {
                return input.closest('label')?.querySelector('span')?.textContent.trim() || '';
            })
            .filter(Boolean);

        texteGenres.textContent = selection.length
            ? selection.join(', ')
            : 'Sélectionner un ou plusieurs genres';
    }

    // Ouvre ou referme le choix des genres littéraires.
    ecouter(boutonGenres, 'click', () => {
        if (!optionsGenres) return;

        const ouvrir = optionsGenres.hidden;
        optionsGenres.hidden = !ouvrir;
        boutonGenres.setAttribute('aria-expanded', String(ouvrir));
    });

    // Actualise le résumé des genres lorsqu’une case change.
    ecouter(optionsGenres, 'change', actualiserGenres);

    ecouter(document, 'click', event => {
        if (event.target instanceof Element && !event.target.closest('.modal-genres-dropdown')) {
            fermerGenres();
        }
    });

    const boutonOuvrir = document.getElementById('ouvrir-ajout');
    const boutonFermer = document.getElementById('fermer-ajout');
    const boutonAnnuler = document.getElementById('modal-annuler-ajout');
    const boutonValider = document.getElementById('modal-valider-ajout');
    const boutonAjouterAuteur = document.getElementById('modal-ajouter-auteur');
    const listeAuteurs = document.getElementById('modal-liste-auteurs');
    const messageErreur = document.getElementById('modal-erreur-ajout');

    const suggestionsAuteurs = document.getElementById('modal-suggestions-auteurs');
    let champAuteurActif = null;
    let indexAuteurActif = -1;
    let attenteSuggestionsAuteurs = null;
    let controleurSuggestionsAuteurs = null;

    // Construit les mêmes champs avec suggestions pour la saisie, les API et la modification.
    function creerLigneAuteur(nom = '') {
        const ligne = document.createElement('div');
        ligne.className = 'modal-auteur-ligne';
        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'auteurs[]';
        input.maxLength = 255;
        input.placeholder = "Nom de l'auteur";
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', 'modal-suggestions-auteurs');
        input.setAttribute('aria-expanded', 'false');
        input.autocomplete = 'off';
        input.value = nom;
        const supprimer = document.createElement('button');
        supprimer.type = 'button';
        supprimer.textContent = '×';
        supprimer.setAttribute('aria-label', 'Supprimer cet auteur');
        ecouter(supprimer, 'click', () => ligne.remove());
        ligne.append(input, supprimer);
        return ligne;
    }

    function afficherChampsAuteurs(noms) {
        if (!listeAuteurs) return;
        const valeurs = noms.length ? noms.slice(0, 50) : [''];
        if (suggestionsAuteurs) listeAuteurs.after(suggestionsAuteurs);
        listeAuteurs.replaceChildren(...valeurs.map(nom => creerLigneAuteur(nom)));
    }

    // Annule les réponses tardives quand on change de livre ou ferme le formulaire.
    function annulerSuggestionsAuteurs() {
        clearTimeout(attenteSuggestionsAuteurs);
        controleurSuggestionsAuteurs?.abort();
        controleurSuggestionsAuteurs = null;
        if (champAuteurActif) {
            champAuteurActif.setAttribute('aria-expanded', 'false');
            champAuteurActif.removeAttribute('aria-activedescendant');
        }
        champAuteurActif = null;
        indexAuteurActif = -1;
        if (suggestionsAuteurs) {
            suggestionsAuteurs.hidden = true;
            suggestionsAuteurs.replaceChildren();
        }
    }
    ecouter(modal, 'close', annulerSuggestionsAuteurs);

    // Attend une courte pause de saisie pour proposer les auteurs déjà enregistrés sans multiplier les requêtes.
    ecouter(listeAuteurs, 'input', event => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.name !== 'auteurs[]' || !suggestionsAuteurs) return;
        annulerSuggestionsAuteurs();
        const texte = input.value.trim();
        if (texte.length < 2) return;
        attenteSuggestionsAuteurs = setTimeout(async () => {
            const controleur = new AbortController();
            controleurSuggestionsAuteurs = controleur;
            try {
                const url = new URL(form.dataset.rechercheAuteurs, window.location.origin);
                url.searchParams.set('q', texte);
                const reponse = await fetch(url, { signal: controleur.signal, headers: { Accept: 'application/json' } });
                if (!reponse.ok) return;
                const resultat = await reponse.json();
                if (controleur.signal.aborted || !modal.open || input.value.trim() !== texte) return;
                const noms = [...new Set((resultat.auteurs || []).map(auteur => auteur.nom))];
                if (document.activeElement !== input || !noms.length) return;
                champAuteurActif = input;
                // Place les suggestions sous le champ concerné, y compris pour les auteurs ajoutés ensuite.
                input.closest('.modal-auteur-ligne').appendChild(suggestionsAuteurs);
                suggestionsAuteurs.replaceChildren(...noms.map((nom, index) => {
                    const option = document.createElement('div');
                    option.id = `modal-auteur-option-${index}`;
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', 'false');
                    option.textContent = nom;
                    return option;
                }));
                suggestionsAuteurs.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            } catch {
                // La saisie libre reste disponible si les suggestions ne peuvent pas être chargées.
            }
        }, 250);
    });

    // Valide une suggestion sans empêcher de conserver un nom saisi librement.
    function choisirAuteur(option) {
        if (!champAuteurActif || !option) return;
        champAuteurActif.value = option.textContent;
        // Invalide les suggestions de saga lors du choix d’un autre auteur.
        champAuteurActif.dispatchEvent(new Event('change', { bubbles: true }));
        champAuteurActif.focus();
        annulerSuggestionsAuteurs();
    }
    ecouter(suggestionsAuteurs, 'pointerdown', event => event.preventDefault());
    ecouter(suggestionsAuteurs, 'click', event => choisirAuteur(event.target.closest('[role="option"]')));

    // Conserve le focus dans le champ et annonce l’option parcourue au clavier.
    ecouter(listeAuteurs, 'keydown', event => {
        if (event.target !== champAuteurActif || suggestionsAuteurs.hidden) return;
        const options = [...suggestionsAuteurs.children];
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            annulerSuggestionsAuteurs();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            indexAuteurActif = (indexAuteurActif + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === indexAuteurActif)));
            champAuteurActif.setAttribute('aria-activedescendant', options[indexAuteurActif].id);
            options[indexAuteurActif].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && indexAuteurActif >= 0) {
            event.preventDefault();
            choisirAuteur(options[indexAuteurActif]);
        }
    });
    // Referme les propositions dès que la saisie passe à un autre champ.
    ecouter(listeAuteurs, 'focusout', event => {
        if (event.target instanceof HTMLInputElement) annulerSuggestionsAuteurs();
    });

    // RECHERCHE AUTOMATIQUE PAR ISBN

    const champRechercheISBN = document.getElementById('modal-isbn-recherche');

    const boutonRechercheISBN = document.getElementById('modal-bouton-recherche-isbn');

    const messageISBN = document.getElementById('modal-message-isbn');

    // Complète un champ du formulaire lorsque l’information correspondante est disponible.
    function renseignerChamp(nom, valeur) {
        if (valeur === undefined || valeur === null || valeur === '') {
            return;
        }

        const champ = form.elements.namedItem(nom);

        if (champ && 'value' in champ) {
            champ.value = String(valeur);
        }
    }

    // Recherche dans les catalogues successifs côté serveur pour préremplir le livre à partir de son ISBN.
    async function rechercherLivreParISBN() {
        if (!champRechercheISBN || !boutonRechercheISBN || !messageISBN) {
            return;
        }

        const isbn = champRechercheISBN.value.replace(/[\s-]/g, '').toUpperCase();

        if (!/^(?:\d{13}|\d{9}[\dX])$/.test(isbn)) {
            messageISBN.textContent = 'Saisis un ISBN de 10 ou 13 caractères.';
            return;
        }

        boutonRechercheISBN.disabled = true;
        messageISBN.textContent = 'Recherche du livre en cours...';

        try {
            const url = new URL(form.dataset.rechercheIsbn, window.location.origin);

            url.searchParams.set('isbn', isbn);

            const reponse = await fetch(url.toString());

            if (!reponse.ok) {
                throw new Error('Le service de recherche est momentanément indisponible.');
            }

            const donnees = await reponse.json();
            const livre = donnees.livre;

            if (!livre) {
                messageISBN.textContent = donnees.aucunCatalogueDisponible
                    ? `ISBN ${isbn} lu correctement, mais les catalogues de livres sont momentanément inaccessibles. Réessaie plus tard ou saisis le livre manuellement.`
                    : donnees.indisponible
                        ? `ISBN ${isbn} lu correctement, mais aucun livre trouvé dans les catalogues qui ont répondu. D’autres catalogues sont indisponibles. Tu peux réessayer ou saisir le livre manuellement.`
                        : `ISBN ${isbn} lu correctement, mais ce livre n’a pas été trouvé dans les catalogues consultés. Saisis son titre et son auteur manuellement.`;
                return;
            }

            // Titre et sous-titre
            renseignerChamp('titre', livre.title);
            renseignerChamp('sousTitre', livre.subtitle);

            // Auteurs
            const auteurs = Array.isArray(livre.authors)
                ? livre.authors.map(auteur => auteur.name).filter(Boolean)
                : [];

            if (auteurs.length) afficherChampsAuteurs(auteurs);

            // ISBN
            const isbn13 = livre.identifiers?.isbn_13?.[0];
            const isbn10 = livre.identifiers?.isbn_10?.[0];

            renseignerChamp('isbn13', isbn13 || (/^\d{13}$/.test(isbn) ? isbn : ''));

            renseignerChamp('isbn10', isbn10 || (/^\d{9}[\dX]$/.test(isbn) ? isbn : ''));

            // Éditeur
            renseignerChamp('edition', livre.publishers?.[0]?.name);

            // Nombre de pages
            if (Number.isInteger(livre.number_of_pages) && livre.number_of_pages > 0) {
                renseignerChamp('nombrePages', livre.number_of_pages);
            }

            // Complète les dates partielles avec un mois et un jour pour remplir le champ de date.
            const date = livre.publish_date;

            if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
                const dateObjet = new Date(`${date}T12:00:00Z`);

                if (
                    !Number.isNaN(dateObjet.getTime()) &&
                    dateObjet.toISOString().slice(0, 10) === date
                ) {
                    renseignerChamp('datePublication', date);
                }
            }

            // Couverture
            const couverture = livre.cover?.large || livre.cover?.medium || livre.cover?.small;

            if (typeof couverture === 'string' && couverture.startsWith('https://')) {
                renseignerChamp('imageCouverture', couverture);

                gestionnaireCouverture.afficherURL(couverture, livre.title);
            }

            messageISBN.textContent = auteurs.length
                ? 'Livre trouvé ! Le titre et les auteurs disponibles ont été renseignés. Vérifie les informations avant de l’ajouter.'
                : 'Livre trouvé, mais les catalogues ne fournissent pas son auteur. Complète le champ Auteur avant de l’ajouter.';
        } catch (erreur) {
            console.error('Erreur recherche ISBN :', erreur);

            messageISBN.textContent = 'Impossible de rechercher ce livre pour le moment.';
        } finally {
            boutonRechercheISBN.disabled = false;
        }
    }

    // Lance la recherche ISBN depuis le bouton du formulaire.
    ecouter(boutonRechercheISBN, 'click', rechercherLivreParISBN);

    // SCANNER ISBN AVEC LA CAMÉRA

    const boutonScannerISBN = document.getElementById('modal-bouton-scanner-isbn');

    const zoneScanner = document.getElementById('modal-zone-scanner');
    const photoISBN = document.getElementById('modal-photo-isbn');
    let analysePhotoISBNEnCours = false;
    let requetePhotoISBN = 0;

    const boutonArreterScanner = document.getElementById('modal-arreter-scanner');

    const choixCameraISBN = document.getElementById('modal-camera-isbn');
    const zoomISBN = document.getElementById('modal-zoom-isbn');
    let minuterieHauteResolution = null;
    let lecteurHauteResolution = null;
    let scannerISBN = null;
    let scanEnCours = false;
    let isbnDetecte = false;

    // Arrête la lecture des codes et libère la caméra même si son démarrage était encore en cours.
    async function arreterScannerISBN() {
        clearTimeout(minuterieHauteResolution);
        minuterieHauteResolution = null;
        if (lecteurHauteResolution) {
            try {
                lecteurHauteResolution.clear();
            } catch {
                // Une image en cours d’analyse termine sans réactiver la caméra.
            }
            lecteurHauteResolution = null;
        }
        if (zoomISBN) zoomISBN.hidden = true;

        // Empêcher la prise en compte de nouveaux scans
        scanEnCours = false;
        isbnDetecte = true;

        // Masquer immédiatement la zone du scanner
        if (zoneScanner) {
            zoneScanner.hidden = true;
        }

        const scanner = scannerISBN;

        if (!scanner) {
            return;
        }

        // Ne pas perdre la référence avant l'arrêt réel
        try {
            // Si le démarrage est terminé, couper la caméra
            if (scanner.isScanning) {
                await scanner.stop();
            }
        } catch (erreur) {
            console.error('Erreur arrêt caméra ISBN :', erreur);
        } finally {
            try {
                scanner.clear();
            } catch (erreur) {
                console.error('Erreur nettoyage scanner :', erreur);
            }

            if (scannerISBN === scanner) {
                scannerISBN = null;
            }
        }
    }

    // Active la caméra pour lire un ISBN puis lancer la recherche du livre reconnu.
    async function demarrerScannerISBN(cameraId = null) {
        if (!zoneScanner || !champRechercheISBN || scanEnCours || scannerISBN?.isScanning) return;

        scanEnCours = true;
        isbnDetecte = false;
        zoneScanner.hidden = false;

        if (messageISBN) {
            messageISBN.textContent = 'Autorise la caméra, puis place le code-barres devant elle.';
        }

        try {
            // Évite de chercher des QR codes et autres formats pendant la lecture d’un ISBN.
            const scanner = new Html5Qrcode('modal-lecteur-isbn', {
                formatsToSupport: [Html5QrcodeSupportedFormats.EAN_13]
            });
            scannerISBN = scanner;

            // Après l’autorisation, propose les objectifs disponibles et privilégie la caméra arrière standard.
            let cameras = [];
            try {
                cameras = await Html5Qrcode.getCameras();
            } catch {
                // Le navigateur peut autoriser la vidéo sans exposer la liste des caméras.
            }
            if (scannerISBN !== scanner || !scanEnCours) return;
            const principale = cameras.find(camera =>
                /back|rear|arrière|environment/i.test(camera.label)
                && !/ultra|tele|télé|wide angle|grand.angle/i.test(camera.label));
            const cameraChoisie = cameraId || principale?.id;
            if (choixCameraISBN) {
                choixCameraISBN.replaceChildren();
                cameras.forEach((camera, index) => {
                    const option = document.createElement('option');
                    option.value = camera.id;
                    option.textContent = camera.label || `Caméra ${index + 1}`;
                    choixCameraISBN.appendChild(option);
                });
                choixCameraISBN.hidden = cameras.length < 2;
            }

            // Partage la validation entre la lecture vidéo et l’analyse des images haute résolution.
            const reconnaitreISBN = async texteDecode => {
                if (scannerISBN !== scanner || isbnDetecte) return;
                const isbn = isbnDepuisCodeBarres(texteDecode);
                if (!isbn) return;
                isbnDetecte = true;
                champRechercheISBN.value = isbn;
                await arreterScannerISBN();
                await rechercherLivreParISBN();
            };

            await scanner.start(
                cameraChoisie || { facingMode: 'environment' },
                {
                    fps: 8,
                    // Sans cadre de découpe, un code éloigné ou décentré reste dans la zone analysée.
                    videoConstraints: {
                        ...(cameraChoisie
                            ? { deviceId: { exact: cameraChoisie } }
                            : { facingMode: { ideal: 'environment' } }),
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    }
                },
                reconnaitreISBN,
                () => {}
            );

            // Une fermeture pendant l’autorisation ou le démarrage ne doit pas laisser la caméra active.
            if (scannerISBN !== scanner || !scanEnCours || isbnDetecte) {
                if (scanner.isScanning) await scanner.stop();
                scanner.clear();
                return;
            }

            // Corrige aussi le miroir éventuellement appliqué en ligne par la bibliothèque.
            const reglages = scanner.getRunningTrackSettings?.() || {};
            const mobile = navigator.userAgentData?.mobile
                || /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '')
                || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            const miroir = !mobile && reglages.facingMode !== 'environment';
            const video = document.querySelector('#modal-lecteur-isbn video');
            if (video) video.style.transform = miroir ? 'scaleX(-1)' : 'none';
            if (choixCameraISBN && reglages.deviceId) choixCameraISBN.value = reglages.deviceId;

            await reglerMiseAuPointISBN(scanner);
            if (scannerISBN !== scanner || isbnDetecte) return;
            const capacites = scanner.getRunningTrackCapabilities?.() || {};
            if (zoomISBN && capacites.zoom && capacites.zoom.max > capacites.zoom.min) {
                zoomISBN.min = capacites.zoom.min;
                zoomISBN.max = Math.min(capacites.zoom.max, 4);
                zoomISBN.step = capacites.zoom.step || 0.1;
                zoomISBN.value = reglages.zoom || capacites.zoom.min;
                zoomISBN.hidden = false;
            }

            // Le lecteur vidéo réduit les images à la largeur de l’écran : cette seconde lecture garde les barres fines.
            if (video && typeof video.videoWidth === 'number') {
                const lecteur = new Html5Qrcode('modal-lecteur-isbn-hd', {
                    formatsToSupport: [Html5QrcodeSupportedFormats.EAN_13]
                });
                lecteurHauteResolution = lecteur;
                const canvas = document.createElement('canvas');
                const analyserImage = async () => {
                    if (scannerISBN !== scanner || isbnDetecte) return;
                    try {
                        if (video.readyState >= 2 && video.videoWidth > 0) {
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            canvas.getContext('2d').drawImage(video, 0, 0);
                            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                            if (blob && scannerISBN === scanner && !isbnDetecte) {
                                const resultat = await lecteur.scanFileV2(new File([blob], 'isbn.png', { type: 'image/png' }), false);
                                await reconnaitreISBN(resultat.decodedText);
                            }
                        }
                    } catch {
                        // Une image sans code lisible est normale ; la prochaine est analysée sans alerte.
                    } finally {
                        if (scannerISBN === scanner && !isbnDetecte) {
                            minuterieHauteResolution = setTimeout(analyserImage, 800);
                        } else {
                            try { lecteur.clear(); } catch { /* Le lecteur a déjà été nettoyé. */ }
                        }
                    }
                };
                minuterieHauteResolution = setTimeout(analyserImage, 500);
            }
            if (messageISBN && scannerISBN === scanner && !isbnDetecte) {
                messageISBN.textContent = 'Scan actif. Garde le code entier visible et éloigne le livre jusqu’à obtenir une image nette. Utilise le zoom ou une autre caméra si ces réglages sont proposés.'
                    + (capacites.focusMode?.includes('continuous') ? ' Touche l’image pour relancer l’autofocus.' : ' La mise au point reste gérée par le téléphone.');
            }
        } catch (erreur) {
            console.error('Erreur caméra ISBN :', erreur);

            if (messageISBN) {
                messageISBN.textContent =
                    'Impossible d’ouvrir la caméra. Vérifie les autorisations de Safari.';
            }

            await arreterScannerISBN();
        } finally {
            scanEnCours = false;
        }
    }

    // Reconnaît les appareils mobiles, y compris les iPad qui annoncent un navigateur de Mac.
    function appareilMobile() {
        return Boolean(navigator.userAgentData?.mobile
            || /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '')
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1));
    }

    // La photo et la webcam acceptent uniquement un ISBN EAN-13 dont la clé est correcte.
    function isbnDepuisCodeBarres(texte) {
        const isbn = String(texte).replace(/[\s-]/g, '');
        if (!/^(978|979)\d{10}$/.test(isbn)) return null;
        const somme = [...isbn.slice(0, 12)].reduce((total, chiffre, index) =>
            total + Number(chiffre) * (index % 2 ? 3 : 1), 0);
        return (10 - somme % 10) % 10 === Number(isbn[12]) ? isbn : null;
    }

    if (appareilMobile() && boutonScannerISBN?.lastChild?.nodeType === 3) {
        boutonScannerISBN.lastChild.textContent = ' Photographier le code-barres';
    }

    // Sur ordinateur, conserve le scan vidéo ; sur téléphone, ouvre directement la capture native.
    ecouter(boutonScannerISBN, 'click', () => {
        if (!appareilMobile() || !photoISBN) {
            void demarrerScannerISBN();
            return;
        }
        if (analysePhotoISBNEnCours) return;
        photoISBN.value = '';
        if (messageISBN) messageISBN.textContent = 'Photographie le code-barres entier au centre de l’image. Attends qu’il soit net, puis valide la photo.';
        photoISBN.click();
    });

    // Analyse localement la photo en pleine résolution ; seule la valeur ISBN part vers la recherche de livres.
    ecouter(photoISBN, 'change', async () => {
        const fichier = photoISBN.files?.[0];
        if (!fichier || analysePhotoISBNEnCours || !modal.open) return;
        const numero = ++requetePhotoISBN;
        analysePhotoISBNEnCours = true;
        if (boutonScannerISBN) boutonScannerISBN.disabled = true;
        let lecteurPhoto = null;
        try {
            await arreterScannerISBN();
            arreterCameraCouverture();
            if (numero !== requetePhotoISBN || !modal.open) return;
            if (messageISBN) messageISBN.textContent = 'Analyse du code-barres sur la photo…';
            lecteurPhoto = new Html5Qrcode('modal-lecteur-isbn-photo', {
                formatsToSupport: [Html5QrcodeSupportedFormats.EAN_13],
                // Utilise le décodeur JavaScript pour éviter les blocages de certains détecteurs natifs.
                experimentalFeatures: { useBarCodeDetectorIfSupported: false }
            });
            const resultat = await lecteurPhoto.scanFileV2(fichier, false);
            if (numero !== requetePhotoISBN || !modal.open) return;
            const isbn = isbnDepuisCodeBarres(resultat.decodedText);
            if (!isbn) throw new Error('ISBN non reconnu');
            champRechercheISBN.value = isbn;
            await rechercherLivreParISBN();
        } catch {
            if (numero === requetePhotoISBN && modal.open && messageISBN) {
                messageISBN.textContent = 'Aucun ISBN lisible sur cette photo. Reprends le code-barres entier, net et bien éclairé, ou saisis son numéro manuellement.';
            }
        } finally {
            try {
                lecteurPhoto?.clear();
            } catch {
                // Le formulaire peut avoir été fermé pendant l’analyse.
            }
            photoISBN.value = '';
            analysePhotoISBNEnCours = false;
            if (boutonScannerISBN) boutonScannerISBN.disabled = false;
        }
    });

    // Annuler la capture permet de réessayer sans lancer de requête ni modifier l’ISBN.
    ecouter(photoISBN, 'cancel', () => {
        if (messageISBN) messageISBN.textContent = '';
    });
    ecouter(modal, 'close', () => { requetePhotoISBN++; });

    // Relance l’autofocus uniquement si le navigateur expose ce réglage.
    async function reglerMiseAuPointISBN(scanner) {
        try {
            const modes = scanner.getRunningTrackCapabilities?.()?.focusMode || [];
            if (modes.includes('continuous')) {
                await scanner.applyVideoConstraints({ advanced: [{ focusMode: 'continuous' }] });
            }
        } catch {
            // La caméra continue à scanner même si Safari ne permet pas de piloter la mise au point.
        }
    }

    ecouter(document.getElementById('modal-lecteur-isbn'), 'click', () => {
        if (scannerISBN?.isScanning) reglerMiseAuPointISBN(scannerISBN);
    });

    // Coupe l’ancien flux avant d’ouvrir l’objectif choisi pour éviter les caméras concurrentes.
    ecouter(choixCameraISBN, 'change', async () => {
        const cameraId = choixCameraISBN.value;
        await arreterScannerISBN();
        if (modal.open) await demarrerScannerISBN(cameraId);
    });

    // Le zoom permet de rester à une distance où la caméra peut faire la mise au point.
    ecouter(zoomISBN, 'change', async () => {
        const scanner = scannerISBN;
        if (!scanner?.isScanning) return;
        try {
            await scanner.applyVideoConstraints({ advanced: [{ zoom: Number(zoomISBN.value) }] });
            await reglerMiseAuPointISBN(scanner);
        } catch {
            if (messageISBN) messageISBN.textContent = 'Le zoom n’est pas disponible sur cette caméra. Le scan reste actif.';
        }
    });

    // Arrête la caméra et la recherche de codes ISBN.
    ecouter(boutonArreterScanner, 'click', arreterScannerISBN);

    // GESTION DU MENU DE COUVERTURE

    const boutonCouverture = document.getElementById('modal-bouton-couverture');
    const optionsCouverture = document.getElementById('modal-options-couverture');

    const boutonFichier = document.getElementById('modal-couverture-fichier');
    const boutonPhoto = document.getElementById('modal-couverture-photo');
    const boutonEnLigne = document.getElementById('modal-couverture-en-ligne');

    const fichierCouverture = document.getElementById('modal-fichier-couverture');
    let couvertureSelectionnee = null;
    const photoCouverture = document.getElementById('modal-photo-couverture');
    const apercuCouverture = document.getElementById('modal-apercu-couverture');
    const champImageCouverture = document.getElementById('modal-image-couverture');
    const gestionnaireCouverture = creerGestionnaireCouverture({
        apercu: apercuCouverture,
        fichier: fichierCouverture,
        champImage: champImageCouverture
    });

    // Masque le menu de choix de la couverture.
    function fermerOptionsCouverture() {
        if (optionsCouverture) {
            optionsCouverture.hidden = true;
        }
    }

    // Actualise l’aperçu de couverture avec l’image disponible.
    function afficherCouverture(fichier) {
        if (!fichier || !fichier.type.startsWith('image/')) return;

        couvertureSelectionnee = fichier;
        gestionnaireCouverture.afficherFichier(fichier);
        fermerOptionsCouverture();
    }

    // Ouvre les options pour importer, photographier ou rechercher une couverture.
    ecouter(boutonCouverture, 'click', () => {
        if (!optionsCouverture) return;
        optionsCouverture.hidden = !optionsCouverture.hidden;
    });

    // Ouvre la sélection d’une image sur l’appareil.
    ecouter(boutonFichier, 'click', () => {
        fermerOptionsCouverture();

        if (fichierCouverture) {
            fichierCouverture.click();
        }
    });

    // Referme le menu si la sélection d’un fichier est annulée.
    ecouter(fichierCouverture, 'cancel', () => {
        fermerOptionsCouverture();
    });

    // Actualise l’aperçu après sélection d’un fichier de couverture.
    ecouter(fichierCouverture, 'change', () => {
        const fichier = fichierCouverture.files?.[0];

        if (!fichier) return;

        if (photoCouverture) {
            photoCouverture.value = '';
        }

        afficherCouverture(fichier);
    });

    // APPAREIL PHOTO POUR LA COUVERTURE

    const cameraZone = document.getElementById('modal-camera-zone');
    const cameraVideo = document.getElementById('modal-camera-video');
    const cameraCapturer = document.getElementById('modal-camera-capturer');
    const cameraAnnuler = document.getElementById('modal-camera-annuler');
    const cameraErreur = document.getElementById('modal-camera-erreur');

    let fluxCamera = null;

    // Arrête les pistes vidéo et masque l’appareil photo pour libérer la caméra.
    function arreterCameraCouverture() {
        if (fluxCamera) {
            fluxCamera.getTracks().forEach(piste => piste.stop());
            fluxCamera = null;
        }

        if (cameraVideo) {
            cameraVideo.srcObject = null;
        }

        if (cameraZone) {
            cameraZone.hidden = true;
        }
    }

    // Demande l’accès à la caméra pour photographier la couverture.
    ecouter(boutonPhoto, 'click', async () => {
        fermerOptionsCouverture();
        if (photoCouverture && (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '')
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1))) {
            photoCouverture.click();
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            if (cameraErreur) {
                cameraErreur.hidden = false;
                cameraErreur.textContent = 'La caméra n’est pas disponible dans ce navigateur.';
            }

            if (cameraZone) cameraZone.hidden = false;
            return;
        }

        if (cameraErreur) {
            cameraErreur.hidden = true;
            cameraErreur.textContent = '';
        }

        if (cameraZone) cameraZone.hidden = false;

        try {
            fluxCamera = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false
            });

            cameraVideo.srcObject = fluxCamera;
            await cameraVideo.play();
        } catch (erreur) {
            console.error('Erreur caméra couverture :', erreur);

            arreterCameraCouverture();

            if (cameraZone) cameraZone.hidden = false;

            if (cameraErreur) {
                cameraErreur.hidden = false;
                cameraErreur.textContent =
                    'Impossible d’ouvrir la caméra. Vérifie les autorisations de Safari.';
            }
        }
    });

    // Transforme l’image vidéo en fichier utilisable comme couverture.
    ecouter(cameraCapturer, 'click', () => {
        if (!cameraVideo || !fluxCamera || !cameraVideo.videoWidth) return;

        const canvas = document.createElement('canvas');

        canvas.width = cameraVideo.videoWidth;
        canvas.height = cameraVideo.videoHeight;

        const contexte = canvas.getContext('2d');

        if (!contexte) return;

        contexte.drawImage(cameraVideo, 0, 0);

        canvas.toBlob(
            blob => {
                if (!blob || !fichierCouverture) return;

                const fichier = new File([blob], 'couverture-photo.jpg', { type: 'image/jpeg' });

                afficherCouverture(fichier);
                arreterCameraCouverture();
            },
            'image/jpeg',
            0.9
        );
    });

    // Annule la prise de photo et libère la caméra.
    ecouter(cameraAnnuler, 'click', () => {
        arreterCameraCouverture();
    });

    // Affiche la photo choisie comme nouvelle couverture.
    ecouter(photoCouverture, 'change', () => {
        const fichier = photoCouverture.files?.[0];

        if (!fichier) return;

        afficherCouverture(fichier);
    });

    // RECHERCHE DE COUVERTURES EN LIGNE

    const zoneRechercheCouverture = document.getElementById('modal-recherche-couverture-zone');

    const inputRechercheCouverture = document.getElementById('modal-recherche-couverture-input');

    const boutonLancerRechercheCouverture = document.getElementById(
        'modal-lancer-recherche-couverture'
    );

    const boutonFermerRechercheCouverture = document.getElementById(
        'modal-fermer-recherche-couverture'
    );

    const messageRechercheCouverture = document.getElementById(
        'modal-recherche-couverture-message'
    );

    const resultatsCouvertures = document.getElementById('modal-resultats-couvertures');

    let rechercheCouvertureEnCours = false;
    let numeroRechercheCouverture = 0;

    // Vide les résultats du livre précédent et invalide les réponses qui arriveraient après sa fermeture.
    function reinitialiserRechercheCouverture() {
        numeroRechercheCouverture++;
        rechercheCouvertureEnCours = false;
        if (boutonLancerRechercheCouverture) boutonLancerRechercheCouverture.disabled = false;
        if (inputRechercheCouverture) inputRechercheCouverture.value = '';
        if (messageRechercheCouverture) messageRechercheCouverture.textContent = '';
        resultatsCouvertures?.replaceChildren();
        if (zoneRechercheCouverture) zoneRechercheCouverture.hidden = true;
    }

    // Ouvre la recherche de couvertures en ligne.
    ecouter(boutonEnLigne, 'click', () => {
        fermerOptionsCouverture();

        if (!zoneRechercheCouverture) return;

        zoneRechercheCouverture.hidden = false;

        // Combine le titre et le premier auteur saisi, sans écraser une recherche ajustée manuellement.
        const titre = document.getElementById('modal-titre')?.value.trim();
        const premierAuteur = listeAuteurs?.querySelector('input[name="auteurs[]"]')?.value.trim();
        const recherche = [titre, premierAuteur].filter(Boolean).join(' ');

        if (inputRechercheCouverture && recherche && !inputRechercheCouverture.value) {
            inputRechercheCouverture.value = recherche;
        }

        inputRechercheCouverture?.focus();
    });

    // Ferme la zone de recherche des couvertures.
    ecouter(boutonFermerRechercheCouverture, 'click', () => {
        if (zoneRechercheCouverture) {
            zoneRechercheCouverture.hidden = true;
        }
    });

    // Recherche dans Google Images via le serveur, qui conserve la clé SerpApi privée.
    async function rechercherCouverturesEnLigne() {
        if (!inputRechercheCouverture || !resultatsCouvertures || rechercheCouvertureEnCours)
            return;

        const recherche = inputRechercheCouverture.value.trim();

        if (!recherche) {
            if (messageRechercheCouverture) {
                messageRechercheCouverture.textContent = 'Saisis un titre ou un auteur.';
            }
            return;
        }

        const numeroRequete = ++numeroRechercheCouverture;
        rechercheCouvertureEnCours = true;
        boutonLancerRechercheCouverture.disabled = true;
        resultatsCouvertures.replaceChildren();

        if (messageRechercheCouverture) {
            messageRechercheCouverture.textContent = 'Recherche en cours...';
        }

        try {
            const url = new URL(form.dataset.rechercheCouvertures, window.location.origin);

            url.searchParams.set('q', recherche);

            const reponse = await fetch(url.toString());

            const donnees = await reponse.json();
            if (numeroRequete !== numeroRechercheCouverture) return;
            if (!reponse.ok || !donnees.success) {
                throw new Error(donnees.message || 'La recherche est momentanément indisponible.');
            }
            const livres = donnees.images || [];

            if (!livres.length) {
                if (messageRechercheCouverture) {
                    messageRechercheCouverture.textContent =
                        'Aucune image trouvée.';
                }
                return;
            }

            livres.forEach(livre => {
                const bouton = document.createElement('button');
                bouton.type = 'button';
                bouton.className = 'modal-resultat-couverture';

                const image = document.createElement('img');

                image.src = livre.miniature;
                image.referrerPolicy = 'no-referrer';

                image.alt = `Couverture de ${livre.titre}`;
                image.loading = 'lazy';

                const titre = document.createElement('span');
                titre.textContent = livre.titre;

                bouton.append(image, titre);

                ecouter(bouton, 'click', () => {
                    const urlCouverture = livre.url;

                    if (champImageCouverture) {
                        champImageCouverture.value = urlCouverture;
                    }

                    couvertureSelectionnee = null;
                    gestionnaireCouverture.afficherURL(urlCouverture, livre.titre);

                    // Une couverture en ligne remplace le fichier local.
                    if (fichierCouverture) {
                        fichierCouverture.value = '';
                    }

                    if (photoCouverture) {
                        photoCouverture.value = '';
                    }

                    zoneRechercheCouverture.hidden = true;
                });

                resultatsCouvertures.appendChild(bouton);
            });

            if (messageRechercheCouverture) {
                messageRechercheCouverture.textContent = `${livres.length} couverture(s) trouvée(s). Sélectionne une image.`;
            }
        } catch (erreur) {
            if (numeroRequete !== numeroRechercheCouverture) return;
            console.error('Erreur recherche de couverture :', erreur);

            if (messageRechercheCouverture) {
                messageRechercheCouverture.textContent = erreur.message || 'Impossible de rechercher des couvertures pour le moment.';
            }
        } finally {
            if (numeroRequete === numeroRechercheCouverture) {
                rechercheCouvertureEnCours = false;
                boutonLancerRechercheCouverture.disabled = false;
            }
        }
    }

    // Lance la recherche de couvertures avec le texte saisi.
    ecouter(boutonLancerRechercheCouverture, 'click', rechercherCouverturesEnLigne);

    // Permet de lancer la recherche de couverture avec la touche Entrée.
    ecouter(inputRechercheCouverture, 'keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            rechercherCouverturesEnLigne();
        }
    });

    ecouter(document, 'click', event => {
        if (event.target instanceof Element && !event.target.closest('.modal-couverture-zone')) {
            fermerOptionsCouverture();
        }
    });

    // Insère la nouvelle carte dans la bibliothèque et signale l’ajout pour actualiser les filtres.
    function ajouterLivreDansBibliotheque(livre) {
        // Mes lectures recharge ses cartes complètes et ses compteurs sans recharger la page.
        if (document.getElementById('page-mes-lectures')) {
            document.dispatchEvent(new CustomEvent('bibliotheque:livre-ajoute'));
            return;
        }
        const conteneur = document.getElementById('bibliotheque-livres');

        if (!conteneur || !livre) return;

        const article = document.createElement('article');
        article.className = 'bibliotheque-livre';
        article.dataset.livreId = String(livre.id);

        const auteurs = Array.isArray(livre.auteurs) ? livre.auteurs.join(', ') : '';

        const genres = Array.isArray(livre.genres) ? livre.genres : [];

        const statut = livre.statut || 'Pas commencé';

        article.dataset.recherche = [
            livre.titre,
            livre.sousTitre,
            livre.saga,
            auteurs,
            livre.isbn13,
            livre.isbn10
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        // Conserve les données de tri pour intégrer le nouveau livre dans l’ordre actuellement choisi.
        article.dataset.auteurs = auteurs;
        article.dataset.dateAjout = String(livre.dateCreation ?? '');
        article.dataset.genres = genres.join('|');
        article.dataset.statut = statut;

        if (livre.imageCouverture) {
            const image = document.createElement('img');
            image.className = 'bibliotheque-couverture';
            // Les fichiers importés sont relatifs à la racine, et non à l’URL de la page actuelle.
            image.src = /^(https?:\/\/|\/)/i.test(livre.imageCouverture)
                ? livre.imageCouverture
                : `/${livre.imageCouverture}`;
            image.alt = `Couverture de ${livre.titre}`;
            article.appendChild(image);
        } else {
            const couvertureVide = document.createElement('div');
            couvertureVide.className = 'bibliotheque-couverture bibliotheque-sans-couverture';

            couvertureVide.setAttribute('aria-label', 'Couverture non renseignée');

            article.appendChild(couvertureVide);
        }

        const informations = document.createElement('div');
        informations.className = 'bibliotheque-livre-infos';

        const titre = document.createElement('h2');
        titre.textContent = livre.titre;

        const auteur = document.createElement('p');
        auteur.className = 'bibliotheque-auteur';
        auteur.textContent = auteurs || 'Auteur non renseigné';

        const badge = document.createElement('span');

        const classesStatuts = {
            'Pas commencé': 'pas-commence',
            'En cours': 'en-cours',
            'En pause': 'en-pause',
            Terminé: 'termine',
            'À lire': 'a-lire'
        };

        badge.className = `bibliotheque-badge badge-${classesStatuts[statut] || 'pas-commence'}`;

        badge.textContent = statut;

        informations.append(titre, auteur, badge);
        article.appendChild(informations);

        conteneur.prepend(article);

        document.dispatchEvent(new CustomEvent('bibliotheque:livre-ajoute'));
    }

    // OUVERTURE ET FERMETURE DE LA FENÊTRE

    // Partage la remise à zéro entre l’ouverture d’un ajout et la fermeture du formulaire.
    function reinitialiserFormulaire() {
        requetePhotoISBN++;
        reinitialiserRechercheCouverture();
        form.reset();
        const zonePile = document.getElementById('modal-pile-zone');
        if (zonePile) zonePile.hidden = false;
        couvertureSelectionnee = null;
        if (boutonCouverture?.lastChild?.nodeType === 3) {
            boutonCouverture.lastChild.textContent = ' Ajouter une couverture';
        }
        actualiserDatesLecture();

        if (champLivreId) champLivreId.value = '';
        if (titreFenetre) titreFenetre.textContent = 'Ajouter un livre';
        if (descriptionFenetre) {
            descriptionFenetre.textContent = modeWishlist
                ? 'Complétez les informations pour enrichir votre wishlist.'
                : 'Complétez les informations pour enrichir votre bibliothèque.';
        }
        if (boutonValider) boutonValider.textContent = 'Ajouter le livre';
        if (champRechercheISBN) champRechercheISBN.value = '';
        if (messageISBN) messageISBN.textContent = '';

        // Libère l’ancienne image locale avant de réinitialiser son aperçu.
        gestionnaireCouverture.nettoyer();
        if (apercuCouverture) {
            const texte = document.createElement('span');
            texte.textContent = 'Aucune couverture';
            apercuCouverture.replaceChildren(texte);
        }
        if (champImageCouverture) champImageCouverture.value = '';

        fermerGenres();
        actualiserGenres();
        annulerSuggestionsAuteurs();
        afficherChampsAuteurs([]);
        if (messageErreur) {
            messageErreur.hidden = true;
            messageErreur.textContent = '';
        }
    }

    // Ferme la fenêtre et libère les caméras avant de réinitialiser le formulaire.
    const fermer = () => {
        modal.close();
        arreterCameraCouverture();
        void arreterScannerISBN();
        reinitialiserFormulaire();
    };

    // Ouvre un formulaire vide, y compris après la modification d’un livre.
    ecouter(boutonOuvrir, 'click', () => {
        if (modal.open) return;
        reinitialiserFormulaire();
        // Depuis une saga, prépare le même ajout de livre avec son auteur et son nom déjà renseignés.
        if (document.getElementById('page-saga-modifier')) {
            form.elements.namedItem('saga').value = boutonOuvrir.dataset.saga;
            afficherChampsAuteurs([boutonOuvrir.dataset.auteur]);
        }
        modal.showModal();
    });

    // Ferme la fenêtre en cours.
    ecouter(boutonFermer, 'click', fermer);
    ecouter(boutonAnnuler, 'click', fermer);

    ecouter(modal, 'cancel', event => {
        event.preventDefault();
    });

    // AJOUT ET SUPPRESSION D'AUTEURS

    // Ajoute un champ d’auteur facultatif, dans la limite acceptée par le serveur.
    ecouter(boutonAjouterAuteur, 'click', () => {
        if (!listeAuteurs) return;

        const nombre = listeAuteurs.querySelectorAll('input[name="auteurs[]"]').length;

        if (nombre >= 50) return;

        const ligne = creerLigneAuteur();
        listeAuteurs.appendChild(ligne);
        ligne.querySelector('input')?.focus();
    });

    // Réduit les photos du téléphone avant l’envoi pour éviter les limites de taille du serveur.
    async function preparerCouverture(fichier) {
        const url = URL.createObjectURL(fichier);
        try {
            const image = new Image();
            await new Promise((resolve, reject) => {
                image.onload = resolve;
                image.onerror = () => reject(new Error('Cette image ne peut pas être lue. Choisis une photo au format JPG ou PNG.'));
                image.src = url;
            });
            const ratio = Math.min(1, 1600 / Math.max(image.naturalWidth, image.naturalHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.naturalWidth * ratio));
            canvas.height = Math.max(1, Math.round(image.naturalHeight * ratio));
            const contexte = canvas.getContext('2d');
            contexte.fillStyle = '#ffffff';
            contexte.fillRect(0, 0, canvas.width, canvas.height);
            contexte.drawImage(image, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.85));
            if (!blob) throw new Error('Impossible de préparer cette photo. Choisis une autre image.');
            return new File([blob], 'couverture.jpg', { type: 'image/jpeg' });
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    // Envoie le formulaire vers la bibliothèque ou la wishlist, en ajout ou en modification.

    ecouter(form, 'submit', async event => {
        event.preventDefault();
        if (boutonValider?.disabled) return;

        if (messageErreur) {
            messageErreur.hidden = true;
            messageErreur.textContent = '';
        }

        if (boutonValider) boutonValider.disabled = true;

        try {
            const idLivreEnModification = document.getElementById('modal-livre-id')?.value;

            const modeModification = Boolean(idLivreEnModification);

            const urlEnregistrement = modeModification
                ? modeWishlist
                    ? `/wishlist/${encodeURIComponent(idLivreEnModification)}/modifier`
                    : `/livre/${encodeURIComponent(idLivreEnModification)}/modifier`
                : form.action;

            const donnees = new FormData(form);
            const couverture = couvertureSelectionnee || fichierCouverture?.files?.[0];
            if (couverture && !champImageCouverture?.value) {
                donnees.set('fichierCouverture', await preparerCouverture(couverture));
            }
            // Réutilise le même envoi uniquement si la personne confirme l’avertissement de doublon.
            const envoyer = () => fetch(urlEnregistrement, {
                method: 'POST',
                body: donnees,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            let reponse = await envoyer();
            let resultat = await reponse.json();
            if (reponse.status === 409 && resultat.code === 'livre_deja_present') {
                const confirmer = window.confirm(`${resultat.message}\n\nVoulez-vous quand même ajouter ce livre ?`);
                if (!confirmer) return;
                donnees.set('confirmerDoublon', '1');
                reponse = await envoyer();
                resultat = await reponse.json();
            }

            if (!reponse.ok || !resultat.success) {
                throw new Error(resultat.message || "L'enregistrement a échoué.");
            }

            fermer();
            if (modeModification && document.getElementById('page-mes-lectures')) {
                // Recalcule les cartes et compteurs avec les filtres en place, sans recharger la page.
                document.dispatchEvent(new CustomEvent('bibliotheque:livre-ajoute'));
            } else if (modeModification || modeWishlist) {
                // Actualise les autres pages après une modification ou un ajout à la wishlist.
                window.location.reload();
            } else if (document.getElementById('page-saga-modifier')) {
                // Actualise les choix sans effacer une éventuelle modification du nom de la saga en cours.
                document.dispatchEvent(new CustomEvent('saga:livre-ajoute', { detail: resultat.livre }));
            } else {
                ajouterLivreDansBibliotheque(resultat.livre);
            }
        } catch (erreur) {
            if (messageErreur) {
                messageErreur.textContent = erreur.message;
                messageErreur.hidden = false;
            }
        } finally {
            if (boutonValider) boutonValider.disabled = false;
        }
    });
});
