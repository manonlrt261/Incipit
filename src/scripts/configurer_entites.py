
from pathlib import Path
import re

dossier = Path("src/Entity")

# nom PHP : type Doctrine, options
champs = {
    "Auteur": {
        "nom": ("string", "length: 255"),
        "prenom": ("string", "length: 255, nullable: true"),
        "nomAffichage": ("string", "length: 255"),
    },
    "Exemplaire": {
        "dateAcquisition": ("date", "nullable: true"),
        "prixAchat": ("decimal", "precision: 10, scale: 2, nullable: true"),
        "provenance": ("string", "length: 255, nullable: true"),
        "etat": ("string", "length: 100, nullable: true"),
        "emplacement": ("string", "length: 255, nullable: true"),
        "commentaire": ("text", "nullable: true"),
        "dateCreation": ("datetime", ""),
    },
    "SessionLecture": {
        "dateDebut": ("date", "nullable: true"),
        "dateFin": ("date", "nullable: true"),
        "statut": ("string", "length: 20"),
        "note": ("decimal", "precision: 2, scale: 1, nullable: true"),
        "avis": ("text", "nullable: true"),
        "dateCreation": ("datetime", ""),
        "dateModification": ("datetime", ""),
    },
    "Contact": {
        "prenom": ("string", "length: 255"),
        "nom": ("string", "length: 255, nullable: true"),
        "email": ("string", "length: 255, nullable: true"),
        "telephone": ("string", "length: 30, nullable: true"),
        "commentaire": ("text", "nullable: true"),
        "dateCreation": ("datetime", ""),
    },
    "Pret": {
        "datePret": ("date", ""),
        "dateRetourPrevue": ("date", "nullable: true"),
        "dateRetourEffective": ("date", "nullable: true"),
        "statut": ("string", "length: 20"),
        "commentaire": ("text", "nullable: true"),
        "dateCreation": ("datetime", ""),
    },
    "Souhait": {
        "priorite": ("string", "length: 20, nullable: true"),
        "prixEstime": ("decimal", "precision: 10, scale: 2, nullable: true"),
        "lienAchat": ("string", "length: 2048, nullable: true"),
        "commentaire": ("text", "nullable: true"),
        "dateAjout": ("datetime", ""),
        "dateAcquisition": ("date", "nullable: true"),
        "statut": ("string", "length: 20"),
    },
    "Utilisateur": {
        "email": ("string", "length: 180, unique: true"),
        "roles": ("json", ""),
        "password": ("string", "length: 255"),
        "dateCreation": ("datetime", ""),
        "dateModification": ("datetime", ""),
    },
    "ParametreConfidentialite": {
        "afficherLivresFavoris": ("boolean", ""),
        "afficherAuteursFavoris": ("boolean", ""),
        "afficherWishlist": ("boolean", ""),
        "dateModification": ("datetime", ""),
    },
    "EvenementOutbox": {
        "identifiantEvenement": ("string", "length: 36, unique: true"),
        "typeEvenement": ("string", "length: 100"),
        "donnees": ("json", ""),
        "dateCreation": ("datetime", ""),
        "dateTransmission": ("datetime", "nullable: true"),
        "statut": ("string", "length: 20"),
        "nombreTentatives": ("integer", ""),
    },
}

# Relations avec les clés étrangères MySQL
relations = {
    "Exemplaire": [("livre", "Livre", "ManyToOne")],
    "SessionLecture": [("livre", "Livre", "ManyToOne")],
    "Pret": [
        ("exemplaire", "Exemplaire", "ManyToOne"),
        ("contact", "Contact", "ManyToOne"),
    ],
    "Souhait": [("livre", "Livre", "ManyToOne")],
    "ParametreConfidentialite": [
        ("utilisateur", "Utilisateur", "OneToOne")
    ],
}

types_php = {
    "string": "?string",
    "text": "?string",
    "decimal": "?string",
    "integer": "?int",
    "boolean": "?bool",
    "date": "?\\DateTimeInterface",
    "datetime": "?\\DateTimeInterface",
    "json": "array",
}

# Vérifier tous les fichiers avant d'en modifier un seul
sources = {}

for nom in champs:
    fichier = dossier / f"{nom}.php"

    if not fichier.exists():
        raise SystemExit(f"Fichier manquant : {fichier}")

    contenu = fichier.read_text()

    if not re.search(r"private\s+\?int\s+\$id\s*=\s*null\s*;", contenu):
        raise SystemExit(f"Structure inattendue : {fichier}")

    if re.search(r"private\s+[^;]+\$(?!id\b)\w+", contenu):
        raise SystemExit(
            f"{fichier} contient déjà des propriétés : aucune modification."
        )

    sources[nom] = contenu

# Ajouter les propriétés et relations
for nom, contenu in sources.items():
    ajouts = []

    for propriete, (type_doctrine, options) in champs[nom].items():
        arguments = f"type: '{type_doctrine}'"

        if options:
            arguments += ", " + options

        type_php = types_php[type_doctrine]
        valeur = "[]" if type_doctrine == "json" else "null"

        ajouts.append(
            f"    #[ORM\\Column({arguments})]\n"
            f"    private {type_php} ${propriete} = {valeur};"
        )

    for propriete, cible, type_relation in relations.get(nom, []):
        ajouts.append(
            f"    #[ORM\\{type_relation}(targetEntity: {cible}::class)]\n"
            f"    #[ORM\\JoinColumn("
            f"name: '{propriete}_id', "
            f"referencedColumnName: 'id', "
            f"nullable: false)]\n"
            f"    private ?{cible} ${propriete} = null;"
        )

    ajout = "\n\n".join(ajouts)

    contenu = re.sub(
        r"(private\s+\?int\s+\$id\s*=\s*null\s*;)",
        lambda m: m.group(1) + "\n\n" + ajout,
        contenu,
        count=1,
    )

    (dossier / f"{nom}.php").write_text(contenu)
    print(f"OK : {nom}")

print("\nLes 9 entités ont été complétées.")
