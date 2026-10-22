// Reprend les classes de components.css pour les badges créés sans rechargement de la page.
export function creerBadgeLecture(statut) {
    const libelle = String(statut ?? '').trim() || 'Pas commencé';

    // Reconnaît le même statut malgré les accents, les majuscules ou les espaces superflus.
    const normalise = libelle
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('fr')
        .replace(/\s+/g, ' ');

    const classes = {
        'en cours': 'en-cours',
        'en pause': 'en-pause',
        'termine': 'termine',
        'termines': 'termine',
        'abandonne': 'abandonne',
        'abandonnes': 'abandonne'
    };

    const badge = document.createElement('span');
    badge.className = `badge-lecture badge-lecture--${classes[normalise] || 'pas-commence'}`;
    badge.textContent = libelle;

    return badge;
}
