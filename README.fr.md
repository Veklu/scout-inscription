[Read in English](README.md)

# Scout Inscription

Un plugin WordPress pour gerer les inscriptions en ligne d'un groupe Scouts du Canada. Comprend un formulaire d'inscription en plusieurs etapes, fiches medicales, suivi des paiements, verification par code QR et conformite complete a la Loi 25 du Quebec.

## Fonctionnalites

- **Formulaire d'inscription en 5 etapes** — Informations de l'enfant, fiche medicale, acceptation des risques, consentements, confirmation
- **Portail famille** — Les parents peuvent consulter et gerer leurs inscriptions
- **Suivi des paiements** — Suivre les acomptes et paiements complets par inscription
- **Verification par code QR** — Codes QR signes HMAC-SHA256 pour la verification securisee d'identite
- **Generation de PDF** — Fiches medicales, acceptation des risques et documents sommaires
- **Notifications par courriel** — Courriels de confirmation avec codes QR et resume quotidien pour les administrateurs
- **Support MFA** — Authentification multifacteur pour l'acces administrateur aux donnees sensibles
- **Chiffrement des donnees** — Chiffrement au repos pour les informations personnelles sensibles
- **Journal d'acces** — Piste d'audit complete pour l'acces aux donnees (conformite Loi 25)
- **Export CSV** — Exporter les inscriptions avec acces aux champs selon le role

## Installation

1. Compresser le dossier `scout-inscription` en `.zip`
2. WordPress : Extensions > Ajouter > Televerser
3. Activer l'extension

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[scout_inscription]` | Affiche le formulaire d'inscription |
| `[scout_famille]` | Portail famille pour consulter les inscriptions |
| `[scout_verify]` | Page de verification des codes QR |

## Roles et capacites

Le plugin cree deux roles personnalises :
- **Animateur scout** — Peut consulter les inscriptions de son unite
- **Tresorier scout** — Peut gerer les paiements et exporter les donnees financieres

## Internationalisation

Le plugin supporte le francais (par defaut) et l'anglais. Pour changer de langue, modifier la locale WordPress via Reglages > General > Langue du site.

## Licence

[MIT](LICENSE)
