// Test de la logique des filtres ; le DOM minimal ne remplace pas une recette navigateur.
const fs = require('fs'),
    vm = require('vm'),
    assert = require('assert');

// Reproduit les champs et les cartes nécessaires aux filtres, sans navigateur.
const inputs = {};

for (const key of ['genre', 'statut', 'note', 'auteurs', 'date', 'contact']) {
    inputs[key] = { value: '', listeners: {} };
}
const reset = { listeners: {} };
const form = {
    listeners: {},
    dataset: {},
    querySelector: (s) =>
        s.includes('reinitialiser')
            ? reset
            : inputs[s.split('-').pop().replace(']', '')],
};
let cards = [
    {
        dataset: {
            livreId: '1',
            auteurNom: 'Zulu',
            auteurPrenom: 'Alice',
            note: '1',
            dateAjout: '10',
        },
    },
    {
        dataset: {
            livreId: '2',
            auteurNom: 'Alpha',
            auteurPrenom: 'Zoé',
            note: '1.5',
            dateAjout: '20',
        },
    },
    {
        dataset: {
            livreId: '3',
            auteurNom: 'Milieu',
            auteurPrenom: 'Marc',
            note: '5',
            dateAjout: '30',
        },
    },
];
const container = {
    querySelectorAll: () => cards.slice(),
    appendChild: (c) => {
        cards = cards.filter((x) => x !== c);
        cards.push(c);
    },
};

// Exécute le module réel dans un contexte isolé pour vérifier ses tris.
const ctx = { Intl };
vm.createContext(ctx);
vm.runInContext(
    fs
        .readFileSync(
            require('path').join(
                __dirname,
                '../assets/components/filtres-livres.js',
            ),
            'utf8',
        )
        .replace('export function', 'function') +
        ';this.creer=creerFiltresLivres;',
    ctx,
);
ctx.creer({
    page: { querySelector: () => form },
    conteneur: container,
    selecteur: '.card',
    ecouter: (e, k, f) => {
        if (e) {
            e.listeners[k] = f;
        }
    },
    actualiser: () => {},
});
const choose = (k, v) => {
    inputs[k].value = v;
    inputs[k].listeners.change();
};

const order = () =>
    cards
        .filter((c) => !c.hidden)
        .map((c) => c.dataset.livreId)
        .join();

// Vérifie les quatre ordres proposés pour les noms et prénoms.
for (const [value, expected] of [
    ['nom-asc', '2,3,1'],
    ['nom-desc', '1,3,2'],
    ['prenom-asc', '1,3,2'],
    ['prenom-desc', '2,3,1'],
]) {
    choose('auteurs', value);
    assert.equal(order(), expected);
}

// Une tranche de notes reste active quand le sens du tri par date change.
choose('date', 'asc');
choose('note', '1');
assert.equal(order(), '1,2');
assert.equal(inputs.date.value, 'asc');
choose('date', 'desc');
assert.equal(order(), '2,1');
assert.equal(inputs.note.value, '1');
reset.listeners.click();
assert.equal(order(), '3,2,1');
console.log(
    '4 tris auteur, note 1★ + ancienneté dans les deux ordres de sélection, réinitialisation : OK',
);

// La wishlist conserve genre et statut lors d’un changement de tri.
for (const c of cards) {
    c.dataset.genres = c.dataset.livreId === '3' ? 'Roman' : 'Polar';
    c.dataset.statutLecture = 'Pas commencé';
    c.dataset.auteurs = c.dataset.auteurNom;
}
inputs.genre.value = 'Polar';
inputs.statut.value = 'Pas commencé';
choose('date', 'asc');
choose('auteurs', 'asc');
assert.equal(order(), '2,1');
assert.equal(inputs.date.value, 'asc');
assert.equal(inputs.genre.value, 'Polar');

// Les prêts combinent aussi le tri des contacts, les auteurs et la date.
for (const c of cards) {
    c.dataset.contactPrenom = 'Alice';
    c.dataset.contactNom = 'Martin';
}
choose('contact', 'asc');
assert.equal(order(), '2,1');
assert.equal(inputs.auteurs.value, 'asc');
assert.equal(inputs.date.value, 'asc');
choose('date', 'asc');
assert.equal(order(), '1,2');
assert.equal(inputs.contact.value, 'asc');
console.log(
    'Wishlist : genre + statut + auteur + date ; prêts : genre + contact + auteur + date : OK',
);
