# CultiuConnect

Aplicacio web en PHP orientada a la gestio integral d'una explotacio fruitera. El projecte agrupa moduls de camp, produccio, personal, inventari, tracabilitat, qualitat, planificacio i visualitzacio GIS en una sola eina.

## Que inclou actualment

- Panell principal amb KPIs, alertes i accessos rapids.
- Gestio de parcel·les, sectors, files d'arbres i mapa GIS.
- Registre d'operacions, quadern d'explotacio i tractaments programats.
- Monitoratge de plagues, analisis de laboratori i protocols.
- Control de collita, lots de produccio, qualitat i tracabilitat.
- Inventari, proveidors, comandes, finances i maquinaria.
- Gestio de personal, permisos, jornades i planificacio.

## Execucio rapida amb Docker

### Requisits

- Docker
- Docker Compose

### 1. Preparar la configuracio

El fitxer `config/db_config.php` no s'inclou al projecte i s'ha de crear a partir de l'exemple:

```powershell
Copy-Item config\config.example.php config\db_config.php
```

L'exemple ja ve alineat amb el `docker-compose.yaml`.

### 2. Arrencar els serveis

```powershell
docker compose up --build
```

### 3. Accessos

- Aplicacio web: `http://localhost:8080/`
- phpMyAdmin: `http://localhost:8081/`

### Credencials Docker per defecte

| Parametre | Valor |
|---|---|
| Host | `db` |
| Base de dades | `cultiuconnect` |
| Usuari | `cultiu_user` |
| Contrasenya | `cultiu_pass` |
| Root password | `root` |

La carpeta `database/` es munta a `/docker-entrypoint-initdb.d`, de manera que MySQL importa els scripts SQL en el primer inici del volum.

## Estructura resumida

```text
CultiuConnect/
|-- index.php                  # Panell principal
|-- alertes.php                # Centre d'alertes
|-- tracabilitat.php           # Consulta de tracabilitat
|-- auth/                      # Login i logout
|-- api/                       # Endpoints JSON
|-- assets/                    # Imatges i fotos de suport
|-- config/                    # Connexio BD i configuracio base
|-- css/                       # Estils globals i del layout
|-- data/geometries/           # Backups JSON de geometries
|-- database/                  # Esquema, dades inicials i files GIS
|-- docs/                      # Documentacio del projecte i material de suport
|-- includes/                  # Header, footer i helpers comuns
|-- js/                        # Scripts del frontend
|-- libs/                      # FPDF i PHP QR Code
|-- modules/                   # Moduls funcionals de l'aplicacio
|-- tests/                     # Notes de validacio
`-- tools/                     # Eines auxiliars de manteniment
```

## Tecnologies i dependencies

- PHP renderitzat al servidor
- MySQL 8
- Docker Compose
- Leaflet al modul de mapa
- FPDF per a exportacions PDF
- PHP QR Code per a codis QR

## Validacio manual recomanada

Com que no hi ha una suite de tests automatitzada consolidada, la validacio actual es fa de manera manual:

- aixecar l'entorn amb Docker,
- comprovar login i navegacio principal,
- validar que MySQL hagi importat `database/`,
- revisar el mapa GIS i la restauracio de geometries si la BD s'ha reinicialitzat.

Per restaurar geometries des dels fitxers JSON de suport:

```powershell
php tools\restaurar_geometries.php
```

## Notes de desenvolupament

- `config/db_config.php` esta al `.gitignore`.
- La logica comuna de sessio, rols, format i alertes es centralitza a `includes/helpers.php`.
- El menu real disponible a l'aplicacio es defineix a `includes/header.php`, que es una bona referencia per descriure els moduls actius.
