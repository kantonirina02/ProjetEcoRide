# Charte graphique EcoRide

## Identite visuelle
- **Logo / marque** : logotype textuel "EcoRide" (courbes douces) pour rappeler la mobilite verte.
- **Palette principale (alignee sur le SCSS actuel)** :
  - Vert clair `#2ECC71` (CTA, header)
  - Bleu doux `#3498DB` (liens secondaires, hover)
  - Gris clair `#F5F5F5` (fonds)
  - Gris neutre `#D9D9D9` (separateurs, surfaces)
  - Anthracite `#263238` (texte fort)
- **Degrade autorise** : `linear-gradient(120deg, #2ECC71 0%, #3498DB 100%)` pour les hero/sections fortes.

## Typographies
- **Titres / CTA** : Poppins ou équivalent sans-serif arrondi (700 pour titres, 500 pour CTA)
- **Texte courant** : Roboto / system-ui (400/500)
- **Fallback** : "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif

## Iconographie & illustrations
- Pack Bootstrap Icons (privilegier `bi-tree`, `bi-lightning`, `bi-car-front`).
- Photos libres d'empreinte ecologique (arbres, covoiturage) ; teinte verte appliquee a 60 % via `mix-blend-mode: multiply`.

## Maquettes
Trois formats desktop + trois formats mobile doivent respecter :
1. **Accueil** : hero vert/gris, carte des stats, section temoignages.
2. **Recherche covoiturage** : panneau filtrage (switch eco, prix, duree, note).
3. **Espace utilisateur** : layout carte (photo ronde, credits, vehicules).

### Variables SCSS utilisees (coherence avec `_custom.scss`)
```scss
$primary: #2ECC71;
$secondary: #3498DB;
$dark: #D9D9D9;
$light: #F5F5F5;
$body-color: #263238;
$font-family-sans-serif: "Roboto", sans-serif !default;
```

Les textes destines a l'impression (charte PDF) peuvent partir de ce Markdown ; exporter en PDF via votre editeur.
