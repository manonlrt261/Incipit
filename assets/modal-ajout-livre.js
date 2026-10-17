// Gère l’ajout et la modification des livres, la recherche ISBN et le choix de leur couverture.
import { initialiserPage } from './components/initialisation-page.js';

import { Html5Qrcode } from 'html5-qrcode';
import { creerGestionnaireCouverture } from './components/couverture.js';

initialiserPage((ecouter) => {
    const modal = document.getElementById('modal-ajout-livre');
    const form = document.getElementById('form-ajout-livre');

    if (!modal || !form) return;

    const modeWishlist = form.dataset.wishlist === '1';

        // MODE MODIFICATION DU LIVRE

    const titreFenetre = document.getElementById('modal-titre-fenetre');
    const descriptionFenetre = document.getElementById('modal-description-fenetre');
    const champLivreId = document.getElementById('modal-livre-id');

    // Charge les informations du livre pour réutiliser le formulaire en mode modification.
    ecouter(document, 'livre:modifier', async event => {
        const id = event.detail?.id;

        if (!id) return;

        try {
            const reponse = await fetch(
                modeWishlist ? `/wishlist/${encodeURIComponent(id)}/details` : `/livre/${encodeURIComponent(id)}/details`,
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            const resultat = await reponse.json();

            if (!reponse.ok || !resultat.success) {
                throw new Error(
                    resultat.message || 'Impossible de charger le livre.'
                );
            }

            const livre = resultat.livre;

            // Réinitialiser le formulaire avant de le remplir
            form.reset();

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
                descriptionFenetre.textContent =
                    'Modifiez les informations de votre livre.';
            }

            const valeurs = {
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

            if (modeWishlist) {
                document.getElementById('modal-publique').checked = Boolean(resultat.publique);
                listeAuteurs.replaceChildren();
                (livre.auteurs_liste?.length ? livre.auteurs_liste : ['']).forEach(nom => {
                    const ligne = document.createElement('div');
                    ligne.className = 'modal-auteur-ligne';
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = 'auteurs[]';
                    input.maxLength = 255;
                    input.value = nom;
                    ligne.appendChild(input);
                    listeAuteurs.appendChild(ligne);
                });
                optionsGenres?.querySelectorAll('input').forEach(input => {
                    input.checked = livre.genres_ids.map(String).includes(input.value);
                });
                actualiserGenres();
                fermerGenres();
                if (livre.image_couverture) {
                    const cheminCouverture = /^(https?:\/\/|\/)/i.test(livre.image_couverture)
                        ? livre.image_couverture : `/${livre.image_couverture}`;
                    gestionnaireCouverture.afficherURL(cheminCouverture, livre.titre);
                    champImageCouverture.value = livre.image_couverture;
                } else {
                    const texte = document.createElement('span');
                    texte.textContent = 'Aucune couverture';
                    apercuCouverture.replaceChildren(texte);
                }
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

        const selection = [
            ...optionsGenres.querySelectorAll('input:checked')
        ].map(input => {
            return input.closest('label')
                ?.querySelector('span')
                ?.textContent.trim() || '';
        }).filter(Boolean);

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
        if (event.target instanceof Element &&
            !event.target.closest('.modal-genres-dropdown')) {
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

    // RECHERCHE AUTOMATIQUE PAR ISBN

    const champRechercheISBN =
        document.getElementById('modal-isbn-recherche');

    const boutonRechercheISBN =
        document.getElementById('modal-bouton-recherche-isbn');

    const messageISBN =
        document.getElementById('modal-message-isbn');

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

    // Interroge Open Library pour préremplir les informations du livre à partir de son ISBN.
    async function rechercherLivreParISBN() {
        if (!champRechercheISBN || !boutonRechercheISBN || !messageISBN) {
            return;
        }

        const isbn = champRechercheISBN.value
            .replace(/[\s-]/g, '')
            .toUpperCase();

        if (!/^(?:\d{13}|\d{9}[\dX])$/.test(isbn)) {
            messageISBN.textContent =
                'Saisis un ISBN de 10 ou 13 caractères.';
            return;
        }

        boutonRechercheISBN.disabled = true;
        messageISBN.textContent = 'Recherche du livre en cours...';

        try {
            const url = new URL('https://openlibrary.org/api/books');

            url.searchParams.set('bibkeys', `ISBN:${isbn}`);
            url.searchParams.set('jscmd', 'data');
            url.searchParams.set('format', 'json');

            const reponse = await fetch(url.toString());

            if (!reponse.ok) {
                throw new Error(
                    'Le service de recherche est momentanément indisponible.'
                );
            }

            const donnees = await reponse.json();
            const livre = donnees[`ISBN:${isbn}`];

            if (!livre) {
                messageISBN.textContent =
                    'Aucun livre trouvé pour cet ISBN.';
                return;
            }

            // Titre et sous-titre
            renseignerChamp('titre', livre.title);
            renseignerChamp('sousTitre', livre.subtitle);

            // Auteurs
            const auteurs = Array.isArray(livre.authors)
                ? livre.authors.map(auteur => auteur.name).filter(Boolean)
                : [];

            if (auteurs.length && listeAuteurs) {
                listeAuteurs.replaceChildren();

                auteurs.slice(0, 50).forEach(nom => {
                    const ligne = document.createElement('div');
                    ligne.className = 'modal-auteur-ligne';

                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = 'auteurs[]';
                    input.maxLength = 255;
                    input.placeholder = "Nom de l'auteur";
                    input.value = nom;

                    ligne.appendChild(input);
                    listeAuteurs.appendChild(ligne);
                });
            }

            // ISBN
            const isbn13 = livre.identifiers?.isbn_13?.[0];
            const isbn10 = livre.identifiers?.isbn_10?.[0];

            renseignerChamp(
                'isbn13',
                isbn13 || (/^\d{13}$/.test(isbn) ? isbn : '')
            );

            renseignerChamp(
                'isbn10',
                isbn10 || (/^\d{9}[\dX]$/.test(isbn) ? isbn : '')
            );

            // Éditeur
            renseignerChamp('edition', livre.publishers?.[0]?.name);

            // Nombre de pages
            if (Number.isInteger(livre.number_of_pages) &&
                livre.number_of_pages > 0) {
                renseignerChamp('nombrePages', livre.number_of_pages);
            }

            // Date de publication, si format AAAA-MM-JJ
            const date = livre.publish_date;

            if (typeof date === 'string' &&
                /^\d{4}-\d{2}-\d{2}$/.test(date)) {
                const dateObjet = new Date(`${date}T12:00:00Z`);

                if (!Number.isNaN(dateObjet.getTime()) &&
                    dateObjet.toISOString().slice(0, 10) === date) {
                    renseignerChamp('datePublication', date);
                }
            }

            // Couverture
            const couverture = livre.cover?.large ||
                livre.cover?.medium ||
                livre.cover?.small;

            if (typeof couverture === 'string' &&
                couverture.startsWith('https://')) {
                renseignerChamp('imageCouverture', couverture);

                gestionnaireCouverture.afficherURL(couverture, livre.title);
            }

            messageISBN.textContent =
                'Livre trouvé ! Vérifie les informations avant de l’ajouter.';

        } catch (erreur) {
            console.error('Erreur recherche ISBN :', erreur);

            messageISBN.textContent =
                'Impossible de rechercher ce livre pour le moment.';
        } finally {
            boutonRechercheISBN.disabled = false;
        }
    }

    // Ce bouton existe déjà dans ton formulaire Twig.
    ecouter(boutonRechercheISBN, 
        'click',
        rechercherLivreParISBN
    );


    // SCANNER ISBN AVEC LA CAMÉRA

    const boutonScannerISBN =
        document.getElementById('modal-bouton-scanner-isbn');

    const zoneScanner =
        document.getElementById('modal-zone-scanner');

    const boutonArreterScanner =
        document.getElementById('modal-arreter-scanner');

    let scannerISBN = null;
    let scanEnCours = false;
    let isbnDetecte = false;

    
// Arrête la lecture des codes et libère la caméra même si son démarrage était encore en cours.
async function arreterScannerISBN() {
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
    async function demarrerScannerISBN() {
        if (!zoneScanner || !champRechercheISBN || scanEnCours) return;

        scanEnCours = true;
        isbnDetecte = false;
        zoneScanner.hidden = false;

        if (messageISBN) {
            messageISBN.textContent =
                'Autorise la caméra, puis place le code-barres devant elle.';
        }

        try {
            scannerISBN = new Html5Qrcode('modal-lecteur-isbn');

            await scannerISBN.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: { width: 280, height: 160 }
                },
                async (texteDecode) => {

                    if (messageISBN) {
                        messageISBN.textContent =
                            `Code détecté : ${texteDecode}`;
    }

    const isbn = texteDecode.replace(/[\s-]/g, '');

                    if (!/^\d{13}$/.test(isbn)) return;
                    if (isbnDetecte) return;

                    isbnDetecte = true;
                    champRechercheISBN.value = isbn;

                    await arreterScannerISBN();
                    await rechercherLivreParISBN();
                },
                () => {}
            );

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

    // Démarre la lecture d’un ISBN avec la caméra.
    ecouter(boutonScannerISBN, 
        'click',
        demarrerScannerISBN
    );

    // Arrête la caméra et la recherche de codes ISBN.
    ecouter(boutonArreterScanner, 
        'click',
        arreterScannerISBN
    );


    // AJOUT DYNAMIQUE DANS LA BIBLIOTHÈQUE


    // GESTION DU MENU DE COUVERTURE

    const boutonCouverture = document.getElementById('modal-bouton-couverture');
    const optionsCouverture = document.getElementById('modal-options-couverture');

    const boutonFichier = document.getElementById('modal-couverture-fichier');
    const boutonPhoto = document.getElementById('modal-couverture-photo');
    const boutonEnLigne = document.getElementById('modal-couverture-en-ligne');

    const fichierCouverture = document.getElementById('modal-fichier-couverture');
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

    // Actualise l’aperçu après sélection d’un fichier de couverture.
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

        if (!navigator.mediaDevices?.getUserMedia) {
            if (cameraErreur) {
                cameraErreur.hidden = false;
                cameraErreur.textContent =
                    'La caméra n’est pas disponible dans ce navigateur.';
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

        canvas.toBlob(blob => {
            if (!blob || !fichierCouverture) return;

            const fichier = new File(
                [blob],
                'couverture-photo.jpg',
                { type: 'image/jpeg' }
            );

            const transfert = new DataTransfer();
            transfert.items.add(fichier);

            fichierCouverture.files = transfert.files;

            afficherCouverture(fichier);
            arreterCameraCouverture();

        }, 'image/jpeg', 0.9);
    });

    // Annule la prise de photo et libère la caméra.
    ecouter(cameraAnnuler, 'click', () => {
        arreterCameraCouverture();
    });

    // Affiche la photo choisie comme nouvelle couverture.
    ecouter(photoCouverture, 'change', () => {
        const fichier = photoCouverture.files?.[0];

        if (!fichier) return;

        if (fichierCouverture) {
            const transfert = new DataTransfer();
            transfert.items.add(fichier);
            fichierCouverture.files = transfert.files;
        }

        afficherCouverture(fichier);
    });

    
    // RECHERCHE DE COUVERTURES EN LIGNE

    const zoneRechercheCouverture =
        document.getElementById('modal-recherche-couverture-zone');

    const inputRechercheCouverture =
        document.getElementById('modal-recherche-couverture-input');

    const boutonLancerRechercheCouverture =
        document.getElementById('modal-lancer-recherche-couverture');

    const boutonFermerRechercheCouverture =
        document.getElementById('modal-fermer-recherche-couverture');

    const messageRechercheCouverture =
        document.getElementById('modal-recherche-couverture-message');

    const resultatsCouvertures =
        document.getElementById('modal-resultats-couvertures');

    let rechercheCouvertureEnCours = false;

    // Ouvre la recherche de couvertures en ligne.
    ecouter(boutonEnLigne, 'click', () => {
        fermerOptionsCouverture();

        if (!zoneRechercheCouverture) return;

        zoneRechercheCouverture.hidden = false;

        const titre = document.getElementById('modal-titre')?.value.trim();

        if (inputRechercheCouverture && titre && !inputRechercheCouverture.value) {
            inputRechercheCouverture.value = titre;
        }

        inputRechercheCouverture?.focus();
    });

    // Ferme la zone de recherche des couvertures.
    ecouter(boutonFermerRechercheCouverture, 'click', () => {
        if (zoneRechercheCouverture) {
            zoneRechercheCouverture.hidden = true;
        }
    });

    // Recherche des couvertures dans Open Library et permet d’en choisir une pour le livre.
    async function rechercherCouverturesEnLigne() {
        if (
            !inputRechercheCouverture ||
            !resultatsCouvertures ||
            rechercheCouvertureEnCours
        ) return;

        const recherche = inputRechercheCouverture.value.trim();

        if (!recherche) {
            if (messageRechercheCouverture) {
                messageRechercheCouverture.textContent =
                    'Saisis un titre ou un auteur.';
            }
            return;
        }

        rechercheCouvertureEnCours = true;
        boutonLancerRechercheCouverture.disabled = true;
        resultatsCouvertures.replaceChildren();

        if (messageRechercheCouverture) {
            messageRechercheCouverture.textContent = 'Recherche en cours...';
        }

        try {
            const url = new URL('https://openlibrary.org/search.json');

            url.searchParams.set('q', recherche);
            url.searchParams.set('fields', 'key,title,author_name,cover_i');
            url.searchParams.set('limit', '30');

            const reponse = await fetch(url.toString());

            if (!reponse.ok) {
                throw new Error('La recherche est momentanément indisponible.');
            }

            const donnees = await reponse.json();

            const livres = (donnees.docs || []).filter(
                livre => Number.isInteger(livre.cover_i)
            );

            if (!livres.length) {
                if (messageRechercheCouverture) {
                    messageRechercheCouverture.textContent =
                        'Aucune couverture trouvée pour cette recherche.';
                }
                return;
            }

            livres.forEach(livre => {
                const bouton = document.createElement('button');
                bouton.type = 'button';
                bouton.className = 'modal-resultat-couverture';

                const image = document.createElement('img');

                image.src =
                    `https://covers.openlibrary.org/b/id/${livre.cover_i}-M.jpg`;

                image.alt = `Couverture de ${livre.title}`;
                image.loading = 'lazy';

                const titre = document.createElement('span');
                titre.textContent = livre.title;

                bouton.append(image, titre);

                ecouter(bouton, 'click', () => {
                    const urlCouverture =
                        `https://covers.openlibrary.org/b/id/${livre.cover_i}-L.jpg`;

                    if (champImageCouverture) {
                        champImageCouverture.value = urlCouverture;
                    }

                    gestionnaireCouverture.afficherURL(urlCouverture, livre.title);

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
                messageRechercheCouverture.textContent =
                    `${livres.length} couverture(s) trouvée(s). Sélectionne une image.`;
            }

        } catch (erreur) {
            console.error('Erreur recherche de couverture :', erreur);

            if (messageRechercheCouverture) {
                messageRechercheCouverture.textContent =
                    'Impossible de rechercher des couvertures pour le moment.';
            }

        } finally {
            rechercheCouvertureEnCours = false;
            boutonLancerRechercheCouverture.disabled = false;
        }
    }

    // Lance la recherche de couvertures avec le texte saisi.
    ecouter(boutonLancerRechercheCouverture, 
        'click',
        rechercherCouverturesEnLigne
    );

    // Permet de lancer la recherche de couverture avec la touche Entrée.
    ecouter(inputRechercheCouverture, 'keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            rechercherCouverturesEnLigne();
        }
    });


    ecouter(document, 'click', event => {
        if (
            event.target instanceof Element &&
            !event.target.closest('.modal-couverture-zone')
        ) {
            fermerOptionsCouverture();
        }
    });

    // Insère la nouvelle carte dans la bibliothèque et signale l’ajout pour actualiser les filtres.
    function ajouterLivreDansBibliotheque(livre) {
        const conteneur = document.getElementById('bibliotheque-livres');

        if (!conteneur || !livre) return;

        const article = document.createElement('article');
        article.className = 'bibliotheque-livre';
        article.dataset.livreId = String(livre.id);

        const auteurs = Array.isArray(livre.auteurs)
            ? livre.auteurs.join(', ')
            : '';

        const genres = Array.isArray(livre.genres)
            ? livre.genres
            : [];

        const statut = livre.statut || 'Pas commencé';

        article.dataset.recherche = [
            livre.titre,
            livre.sousTitre,
            auteurs,
            livre.isbn13,
            livre.isbn10
        ].filter(Boolean).join(' ').toLowerCase();

        // Conserve les données de tri pour intégrer le nouveau livre dans l’ordre actuellement choisi.
        article.dataset.auteurs = auteurs;
        article.dataset.dateAjout = String(livre.dateCreation ?? '');
        article.dataset.genres = genres.join('|');
        article.dataset.statut = statut;

        if (livre.imageCouverture) {
            const image = document.createElement('img');
            image.className = 'bibliotheque-couverture';
            image.src = livre.imageCouverture;
            image.alt = `Couverture de ${livre.titre}`;
            article.appendChild(image);
        } else {
            const couvertureVide = document.createElement('div');
            couvertureVide.className =
                'bibliotheque-couverture bibliotheque-sans-couverture';

            couvertureVide.setAttribute(
                'aria-label',
                'Couverture non renseignée'
            );

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
            'Terminé': 'termine',
            'À lire': 'a-lire'
        };

        badge.className =
            `bibliotheque-badge badge-${classesStatuts[statut] || 'pas-commence'}`;

        badge.textContent = statut;

        informations.append(titre, auteur, badge);
        article.appendChild(informations);

        conteneur.prepend(article);

        document.dispatchEvent(
            new CustomEvent('bibliotheque:livre-ajoute')
        );
    }

    // OUVERTURE ET FERMETURE DE LA FENÊTRE

    // Partage la remise à zéro entre l’ouverture d’un ajout et la fermeture du formulaire.
    function reinitialiserFormulaire() {
        form.reset();

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
        if (listeAuteurs) {
            listeAuteurs.innerHTML = `
                <div class="modal-auteur-ligne">
                    <input
                        type="text"
                        name="auteurs[]"
                        maxlength="255"
                        placeholder="Nom de l'auteur"
                    >
                </div>
            `;
        }
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
        modal.showModal();
    });

    // Ferme la fenêtre en cours.
    ecouter(boutonFermer, 'click', fermer);
    ecouter(boutonAnnuler, 'click', fermer);

    ecouter(modal, 'cancel', event => {
        event.preventDefault();
    });

    // AJOUT ET SUPPRESSION D'AUTEURS

    ecouter(boutonAjouterAuteur, 'click', () => {
        if (!listeAuteurs) return;

        const nombre = listeAuteurs.querySelectorAll(
            'input[name="auteurs[]"]'
        ).length;

        if (nombre >= 50) return;

        const ligne = document.createElement('div');
        ligne.className = 'modal-auteur-ligne';

        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'auteurs[]';
        input.maxLength = 255;
        input.placeholder = "Nom de l'auteur";

        const supprimer = document.createElement('button');
        supprimer.type = 'button';
        supprimer.textContent = '×';

        supprimer.setAttribute(
            'aria-label',
            'Supprimer cet auteur'
        );

        ecouter(supprimer, 'click', () => ligne.remove());

        ligne.append(input, supprimer);
        listeAuteurs.append(ligne);
        input.focus();
    });

    // ENREGISTREMENT DU LIVRE

    ecouter(form, 'submit', async event => {
        event.preventDefault();
        if (boutonValider?.disabled) return;
    
        if (messageErreur) {
            messageErreur.hidden = true;
            messageErreur.textContent = '';
        }

        if (boutonValider) boutonValider.disabled = true;

        try {
            const idLivreEnModification =
                document.getElementById('modal-livre-id')?.value;

            const modeModification = Boolean(idLivreEnModification);

            const urlEnregistrement = modeModification
                ? (modeWishlist ? `/wishlist/${encodeURIComponent(idLivreEnModification)}/modifier` : `/livre/${encodeURIComponent(idLivreEnModification)}/modifier`)
                : form.action;

            const reponse = await fetch(urlEnregistrement, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const resultat = await reponse.json();

            if (!reponse.ok || !resultat.success) {
                throw new Error(
                    resultat.message || "L'enregistrement a échoué."
                );
            }

            fermer();
            if (modeModification || modeWishlist) {
                // Actualise la page pour afficher les modifications ou la wishlist.
                window.location.reload();
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
