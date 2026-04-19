# Tests - CultiuConnect

Actualment no hi ha una suite de tests automatitzada mantinguda dins del projecte. Aquesta carpeta es fa servir com a punt de referencia per a la validacio manual i per deixar clar l'estat real del projecte.

## Estat actual

- No hi ha PHPUnit ni smoke tests actius versionats.
- Els documents antics que mencionaven `tools/smoke_test.php` o `tools/php_lint_all.php` ja no corresponen amb el contingut actual del workspace.

## Validacio manual recomanada

### 1. Arrencar l'entorn

```powershell
docker compose up --build
```

### 2. Comprovacions basiques

- obrir `http://localhost:8080/`,
- verificar el login,
- comprovar que el panell principal carrega KPIs i alertes,
- revisar la navegacio dels moduls principals,
- comprovar que el mapa GIS mostra geometries,
- validar que phpMyAdmin connecta a `cultiuconnect`.

### 3. Si s'han perdut geometries

Despres de reinicialitzar el volum de MySQL, es pot restaurar la informacio geoespacial de suport amb:

```powershell
php tools\restaurar_geometries.php
```

### 4. Si la base de dades no arrenca correctament

```powershell
docker compose down -v
docker compose up --build
```

Comprova tambe que els fitxers de `database/` siguin presents i que `config/db_config.php` coincideixi amb les credencials del `docker-compose.yaml`.
