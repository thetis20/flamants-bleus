# Les Flamants Bleus — site web

Site de la troupe amateure d'improvisation théâtrale Les Flamants Bleus : prochaines dates, formats, liens de réservation et réseaux sociaux. Les membres de la troupe mettent le site à jour en se connectant avec leur compte Google.

PHP 8.4 · Symfony 7.4 · Twig · Firestore · Cloud Run.

## Lancer en local

Docker suffit, PHP n'est pas nécessaire sur la machine.

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app bin/console app:load-demo   # dates et formats du flyer
```

Le site répond sur http://localhost:8000. Les données vont dans un émulateur Firestore, effacé à chaque redémarrage.

Pour tester la connexion, créer `.env.local` (jamais commité) avec `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` (client OAuth de dev, voir `docs/deploy-gcp.md` §6) et `ADMIN_BOOTSTRAP_EMAIL=<votre adresse Gmail>`.

## Tests

```bash
docker compose exec app vendor/bin/phpunit
```

## Espace membres

`/connexion` → « Se connecter avec Google ». Seules les adresses de la liste **Membres** entrent ; la première est amorcée par `ADMIN_BOOTSTRAP_EMAIL`.

- **Dates** : titre, date et heure, format, lieu, prix (texte libre), lien de réservation. Les dates passées disparaissent du site le lendemain.
- **Formats** : titre, adresse de la page, description, logo.
- **Liens suivis** : un lien `/r/<code>` qui compte les arrivées (QR code du flyer, bio Instagram…) puis redirige vers une page du site. QR code téléchargeable en SVG pour l'impression. Les robots d'aperçu de liens ne sont pas comptés ; aucune donnée sur les visiteurs n'est conservée, seulement un compteur par jour.
- **Membres** : ajouter ou retirer des adresses Google.

## Déploiement

Push sur `main` → GitHub Actions lance les tests, construit l'image et la déploie sur Cloud Run. Mise en place initiale : `docs/deploy-gcp.md`.
