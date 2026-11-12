# SAÉ 5 — Jardins de Cocagne

Rayane ABABSA et Théo BOREL · BUT 3 Informatique · 2026–2027

## Démarrer la démonstration locale

Le code est disponible sur la [branche `rendu-15/repartition-paniers`](https://github.com/theounniv/SAE5-ABABSA-BOREL/tree/rendu-15/repartition-paniers). Installer Git et Docker avec Docker Compose v2, démarrer Docker et libérer le port local 8000.

```sh
git clone --branch rendu-15/repartition-paniers https://github.com/theounniv/SAE5-ABABSA-BOREL.git
cd SAE5-ABABSA-BOREL
docker compose up --build -d app
```

Ouvrir [l’accueil](http://127.0.0.1:8000/). Il affiche les noms du binôme et les liens vers les deux modules. Cette version utilise des données de démonstration et fonctionne localement, sans PostgreSQL ni authentification. Les résultats de formulaire ne sont pas conservés après la fermeture de la page.

## R15 — Organisation des tournées

Ouvrir [les tournées](http://127.0.0.1:8000/tournees), garder l’exemple et cliquer sur **Calculer les tournées**. Les tournées affichent pour chaque véhicule les paniers affectés, les dépôts dans l’ordre de visite et la distance estimée à vol d’oiseau, retour au chargement compris.

Le calcul propose une répartition que l’on peut modifier dans le champ **Dépôts affectés aux véhicules**, avec une ligne au format `dépôt;véhicule`. Cliquer sur **Vérifier la répartition manuelle** pour contrôler la proposition. Par exemple, avec les données d’origine, cette répartition est valide.

```text
1;1
2;1
3;2
4;2
```

La répartition automatique peut laisser un dépôt sans véhicule même si une solution existe. La liste des dépôts non affectés permet de repérer ce cas et de proposer une correction manuelle.

## R16 — Calendrier de livraison

Ouvrir [le calendrier](http://127.0.0.1:8000/calendrier). Par exemple, choisir l’année **2027**, la tournée **Mercredi matin**, le **mercredi**, une semaine de départ **impaire** et saisir `1,31,32` pour les semaines fermées. Cliquer sur **Générer le calendrier** avec **25**, puis **20** et **17** livraisons. Vérifier que le nombre de dates placées correspond au nombre demandé.

Le calendrier tient compte des jours fériés et des semaines fermées. Pour déplacer une livraison, modifier une date dans la colonne **Date retenue** puis cliquer sur **Vérifier les dates**. La colonne **Date prévue** indique le point de départ de la vérification.

## Lancer les vérifications

Depuis la racine du dépôt, avec l’application démarrée par la commande précédente, lancer les quatre scripts suivants. Docker monte le dossier `tests` en lecture seule et utilise les dépendances déjà installées dans l’image.

```sh
docker compose run --rm --no-deps --volume "./tests:/app/tests:ro" app php tests/Tournee/repartition_paniers.php
docker compose run --rm --no-deps --volume "./tests:/app/tests:ro" app php tests/Tournee/organisation_tournees.php
docker compose run --rm --no-deps --volume "./tests:/app/tests:ro" app php tests/Calendrier/calendrier_livraisons.php
docker compose run --rm --no-deps --volume "./tests:/app/tests:ro" --env BASE_URL=http://app:8000 app php tests/smoke.php
```

Le [workflow GitHub Actions](.github/workflows/verifications.yml) reconstruit l’image, exécute ces scripts et vérifie les dépendances verrouillées avec `composer audit` sur les demandes de fusion vers `main` et les mises à jour de `main`.

## Arrêter

```sh
docker compose down
```
