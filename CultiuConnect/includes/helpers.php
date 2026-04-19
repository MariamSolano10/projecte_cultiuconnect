<?php
/**
 * includes/helpers.php — Funcions d'utilitat reutilitzables per a CultiuConnect.
 */

// ==========================================================
// SESSIÓ (bootstrap segur)
// ==========================================================
function cc_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;

    // Endureix cookies de sessió abans d'iniciar-la
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    // PHP 7.3+ accepta array amb SameSite
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function e(?string $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize(?string $valor): string
{
    return trim(strip_tags((string)$valor));
}

function sanitize_int(mixed $valor): ?int
{
    $filtrat = filter_var($valor, FILTER_VALIDATE_INT);
    return ($filtrat !== false) ? (int)$filtrat : null;
}

function sanitize_decimal(mixed $valor): ?float
{
    if ($valor === null || $valor === '' || $valor === false) return null;
    $normalitzat = str_replace(',', '.', (string)$valor);
    $filtrat     = filter_var($normalitzat, FILTER_VALIDATE_FLOAT);
    return ($filtrat !== false) ? (float)$filtrat : null;
}

/**
 * Registra una acció a la taula log_accions
 * @param string $accio - Descripció de l'acció realitzada
 * @param string|null $taula_afectada - Taula afectada (opcional)
 * @param int|null $id_registre_afectat - ID del registre afectat (opcional)
 * @param string|null $comentaris - Comentaris addicionals (opcional)
 * @return bool - True si s'ha registrat correctament
 */
function registrar_accio(string $accio, ?string $taula_afectada = null, ?int $id_registre_afectat = null, ?string $comentaris = null): bool
{
    // Obtener ID del treballador de la sessió
    $id_treballador = $_SESSION['usuari']['id_treballador'] ?? null;
    
    if (!$id_treballador) {
        return false; // No hi ha usuari autenticat
    }
    
    try {
        $pdo = connectDB();
        
        $stmt = $pdo->prepare("
            INSERT INTO log_accions 
                (id_treballador, data_hora, accio, taula_afectada, id_registre_afectat, comentaris)
            VALUES 
                (:id_treballador, NOW(), :accio, :taula_afectada, :id_registre_afectat, :comentaris)
        ");
        
        return $stmt->execute([
            ':id_treballador' => $id_treballador,
            ':accio' => $accio,
            ':taula_afectada' => $taula_afectada,
            ':id_registre_afectat' => $id_registre_afectat,
            ':comentaris' => $comentaris
        ]);
        
    } catch (Exception $e) {
        error_log('[CultiuConnect] registrar_accio(): ' . $e->getMessage());
        return false;
    }
}

const MESOS_CA = [
    1  => 'gener',    2  => 'febrer',   3  => 'març',
    4  => 'abril',    5  => 'maig',     6  => 'juny',
    7  => 'juliol',   8  => 'agost',    9  => 'setembre',
    10 => 'octubre',  11 => 'novembre', 12 => 'desembre',
];

function format_data(?string $data, bool $curta = false): string
{
    if (empty($data) || $data === '0000-00-00') return '—';
    try {
        $dt = new DateTime($data);
    } catch (Exception) {
        return '—';
    }
    if ($curta) {
        return $dt->format('d/m/Y');
    }
    $dia = (int)$dt->format('j');
    $mes = MESOS_CA[(int)$dt->format('n')];
    $any = $dt->format('Y');
    $preposicio = in_array($mes[0], ['a', 'e', 'i', 'o', 'u']) ? 'd\'' : 'de ';
    return "{$dia} {$preposicio}{$mes} de {$any}";
}

function format_datetime(?string $datetime): string
{
    if (empty($datetime)) return '—';
    try {
        $dt = new DateTime($datetime);
    } catch (Exception) {
        return '—';
    }
    $data = format_data($dt->format('Y-m-d'));
    $hora = $dt->format('H:i');
    return "{$data} a les {$hora}";
}

function dies_restants(?string $data): string
{
    if (empty($data)) return '—';
    try {
        $avui    = new DateTime('today');
        $objectiu = new DateTime($data);
        $diff    = $avui->diff($objectiu);
        $dies    = (int)$diff->days;
    } catch (Exception) {
        return '—';
    }
    if ($dies === 0) return 'avui';
    if ($diff->invert) {
        return 'fa ' . $dies . ($dies === 1 ? ' dia' : ' dies');
    }
    return 'd\'aquí a ' . $dies . ($dies === 1 ? ' dia' : ' dies');
}

function format_kg(?float $valor, int $decimals = 2): string
{
    if ($valor === null) return '—';
    return number_format($valor, $decimals, ',', '.') . ' kg';
}

function format_ha(?float $valor, int $decimals = 2): string
{
    if ($valor === null) return '—';
    return number_format($valor, $decimals, ',', '.') . ' ha';
}

function format_euros(?float $valor, int $decimals = 2): string
{
    if ($valor === null) return '—';
    return number_format($valor, $decimals, ',', '.') . ' €';
}

function format_hores(?float $hores): string
{
    if ($hores === null) return '—';
    $h   = (int)$hores;
    $min = (int)(round($hores - $h, 2) * 60);
    if ($min === 0) return "{$h}h";
    return "{$h}h {$min}min";
}

function set_flash(string $tipus, string $missatge): void
{
    cc_session_start();
    $_SESSION['flash'] = ['tipus' => $tipus, 'missatge' => $missatge];
}

function get_flash(?string $tipus = null): ?array
{
    cc_session_start();
    if (!isset($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    if ($tipus !== null && $flash['tipus'] !== $tipus) return null;
    unset($_SESSION['flash']);
    return $flash;
}

function has_flash(?string $tipus = null): bool
{
    cc_session_start();
    if (!isset($_SESSION['flash'])) return false;
    if ($tipus !== null) return $_SESSION['flash']['tipus'] === $tipus;
    return true;
}

function requereix_sessio(): void
{
    cc_session_start();
    if (empty($_SESSION['usuari_id'])) {
        $_SESSION['intent_url'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . ($_SERVER['REQUEST_URI'] ?? '');
        session_write_close();
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

function requereix_rol(array $rols_permesos): void
{
    cc_session_start();
    $rol = strtolower((string)($_SESSION['usuari_rol'] ?? 'operari'));
    $rols = array_map(fn($r) => strtolower((string)$r), $rols_permesos);
    if (!in_array($rol, $rols, true)) {
        set_flash('error', 'No tens permisos per accedir a aquesta secció.');
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

function usuari_actiu(): ?array
{
    cc_session_start();
    if (empty($_SESSION['usuari_id'])) return null;
    return [
        'id'       => (int)$_SESSION['usuari_id'],
        'nom'      => $_SESSION['usuari_nom']      ?? '',
        'rol'      => $_SESSION['usuari_rol']      ?? 'operari',
        'username' => $_SESSION['usuari_username'] ?? '',
    ];
}

// ==========================================================
// ENFORÇ DE SEGURETAT (Sessió + rols)
// ==========================================================
function cc_path_actual(): string
{
    return (string)($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? ''));
}

function cc_es_public(string $path): bool
{
    // Pàgines públiques (no requereixen login)
    $publics = [
        '/auth/login.php',
        '/auth/logout.php',
        '/tracabilitat.php',
    ];
    foreach ($publics as $p) {
        if (str_ends_with($path, $p)) return true;
    }
    return false;
}

function cc_logout_hard(): void
{
    cc_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }
    session_destroy();
}

function cc_sync_sessio_amb_bd(): void
{
    // Valida que la sessió correspon a un usuari real i actiu.
    // Important: No confiem cegament en $_SESSION['usuari_rol'].
    cc_session_start();
    if (empty($_SESSION['usuari_id'])) return;

    if (!function_exists('connectDB')) {
        // Sense BD disponible no podem validar; mantenim sessió però la seguretat per rol
        // es continuarà aplicant amb el rol actual de sessió.
        return;
    }

    try {
        $pdo = connectDB();
        $stmt = $pdo->prepare("
            SELECT id_usuari, nom_usuari, nom_complet, rol, estat
            FROM usuari
            WHERE id_usuari = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$_SESSION['usuari_id']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u || ($u['estat'] ?? '') !== 'actiu') {
            cc_logout_hard();
            header('Location: ' . BASE_URL . 'auth/login.php');
            exit;
        }

        // Sincronitza camps de sessió (evita escalats de rol per manipulació)
        $_SESSION['usuari_rol']      = (string)($u['rol'] ?? 'operari');
        $_SESSION['usuari_username'] = (string)($u['nom_usuari'] ?? ($_SESSION['usuari_username'] ?? ''));
        $_SESSION['usuari_nom']      = (string)(($u['nom_complet'] ?? '') ?: ($_SESSION['usuari_nom'] ?? ''));

    } catch (Exception $e) {
        // En cas d'error de BD, no trenquem la web.
        // La restricció per rol seguirà funcionant amb el rol en sessió.
        error_log('[CultiuConnect] cc_sync_sessio_amb_bd: ' . $e->getMessage());
    }
}

function cc_enforce_auth_i_rols(?array $rols_permesos = null): void
{
    // Evita executar en CLI/tests
    if (PHP_SAPI === 'cli') return;
    if (!isset($_SERVER['REQUEST_METHOD'])) return;

    $path = cc_path_actual();
    if (cc_es_public($path)) return;

    // 1) Sempre requereix sessió per a tot el backend (incloses APIs)
    requereix_sessio();
    cc_sync_sessio_amb_bd();

    // 2) Si s'han passat rols específics, comprovar-los directament
    if ($rols_permesos !== null) {
        requereix_rol($rols_permesos);
        return;
    }

    // 3) Restriccions per rols (gestió a diferents nivells) - basat en paths
    $gestors = ['admin', 'tecnic', 'responsable'];

    $restriccions = [
        // Personal: gestió de plantilla (permisos sí que és accessible per operari)
        '/modules/personal/personal.php'          => $gestors,
        '/modules/personal/nou_treballador.php'   => $gestors,

        // Inventari / finances / RRHH planificació
        '/modules/estoc/'                         => $gestors,
        '/modules/finances/'                      => $gestors,
        '/modules/planificacio_personal/'         => $gestors,

        // Parametrització i catàlegs
        '/modules/varietats/'                     => $gestors,

        // SIG i estructura de finca (evita edicions per operaris)
        '/modules/parceles/'                      => $gestors,
        '/modules/sectors/'                       => $gestors,
        '/modules/mapa/'                          => $gestors,
    ];

    foreach ($restriccions as $prefix => $rols) {
        if (str_contains($prefix, '/modules/') && str_ends_with($prefix, '.php')) {
            if (str_ends_with($path, $prefix)) {
                requereix_rol($rols);
                return;
            }
        } else {
            if (str_contains($path, $prefix)) {
                requereix_rol($rols);
                return;
            }
        }
    }
}

// ---------------------------------------------------------------
// CORRECCIÓ: cc_enforce_auth_i_rols() JA NO s'executa aquí
// automàticament. Això causava un conflicte de sessió a login.php:
// la funció iniciava la sessió abans que cc_session_start() pogués
// configurar correctament les cookies, trencant el token CSRF.
//
// Ara s'ha de cridar MANUALMENT des de cada pàgina protegida:
//   require_once 'includes/helpers.php';
//   cc_session_start();
//   cc_enforce_auth_i_rols();
// ---------------------------------------------------------------

function nav_activa(string $segment): string
{
    $url_actual = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($url_actual, $segment) ? 'active' : '';
}

function paginar(int $total_registres, int $registres_pagina = 20, ?int $pagina_actual = null): array
{
    $pagina_actual = max(1, $pagina_actual ?? (int)($_GET['pagina'] ?? 1));
    $total_pagines = max(1, (int)ceil($total_registres / $registres_pagina));
    $pagina_actual = min($pagina_actual, $total_pagines);
    $offset        = ($pagina_actual - 1) * $registres_pagina;

    return [
        'limit'          => $registres_pagina,
        'offset'         => $offset,
        'pagina_actual'  => $pagina_actual,
        'total_pagines'  => $total_pagines,
        'hi_ha_anterior' => $pagina_actual > 1,
        'hi_ha_seguent'  => $pagina_actual < $total_pagines,
    ];
}

function obtenirAlertesEstoc(PDO $pdo): array
{
    // Consultem la taula real: producte_quimic
    $sql = "SELECT nom_comercial, estoc_actual, unitat_mesura 
            FROM producte_quimic 
            WHERE estoc_actual <= estoc_minim";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        // Enregistrem l'error al log del servidor per poder depurar sense trencar la web
        error_log("Error a obtenirAlertesEstoc: " . $e->getMessage());
        return [];
    }
}

// ==========================================================
// FUNCIONS D'ALERTES (DASHBOARD / HEADER)
// ==========================================================

/**
 * Alerta de tractaments programats pròxims
 */
function obtenirAlertesTractaments(PDO $pdo): array
{
    // Cerca tractaments pendents on la data prevista estigui dins del marge de dies d'avís configurat
    $sql = "SELECT tp.id_programat, s.nom AS nom_sector, tp.data_prevista, tp.tipus, tp.dies_avis
            FROM tractament_programat tp
            JOIN sector s ON tp.id_sector = s.id_sector
            WHERE tp.estat = 'pendent' 
              AND tp.data_prevista <= DATE_ADD(CURRENT_DATE(), INTERVAL tp.dies_avis DAY)
              AND tp.data_prevista >= CURRENT_DATE()
            ORDER BY tp.data_prevista ASC";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        error_log("Error a obtenirAlertesTractaments: " . $e->getMessage());
        return [];
    }
}

/**
 * Alerta de certificacions de treballadors a punt de caducar
 */
function obtenirAlertesCertificacions(PDO $pdo, int $dies_avis = 30): array
{
    // Avisa amb els dies d'antelació que es passin per paràmetre (30 per defecte)
    $sql = "SELECT c.tipus_certificacio, t.nom, t.cognoms, c.data_caducitat
            FROM certificacio_treballador c
            JOIN treballador t ON c.id_treballador = t.id_treballador
            WHERE c.data_caducitat IS NOT NULL
              AND c.data_caducitat <= DATE_ADD(CURRENT_DATE(), INTERVAL :dies DAY)
              AND c.data_caducitat >= CURRENT_DATE()
            ORDER BY c.data_caducitat ASC";
            
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':dies' => $dies_avis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error a obtenirAlertesCertificacions: " . $e->getMessage());
        return [];
    }
}

/**
 * Alerta de finalització de contractes temporals a punt de venciment
 *
 * Nota: usem `treballador.data_baixa` com a data fi del contracte (si està informada).
 */
function obtenirAlertesContractes(PDO $pdo, int $dies_avis = 30): array
{
    $sql = "SELECT id_treballador, nom, cognoms, rol, tipus_contracte, data_baixa
            FROM treballador
            WHERE tipus_contracte = 'Temporal'
              AND data_baixa IS NOT NULL
              AND data_baixa <= DATE_ADD(CURRENT_DATE(), INTERVAL :dies DAY)
              AND data_baixa >= CURRENT_DATE()
            ORDER BY data_baixa ASC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':dies' => $dies_avis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error a obtenirAlertesContractes: " . $e->getMessage());
        return [];
    }
}

/**
 * Alerta de lots de productes en estoc a punt de caducar
 */
function obtenirAlertesCaducitatEstoc(PDO $pdo, int $dies_avis = 30): array
{
    // Només comprova lots que encara tinguin quantitat disponible
    $sql = "SELECT pq.nom_comercial, ie.num_lot, ie.data_caducitat, ie.quantitat_disponible
            FROM inventari_estoc ie
            JOIN producte_quimic pq ON ie.id_producte = pq.id_producte
            WHERE ie.data_caducitat IS NOT NULL
              AND ie.data_caducitat <= DATE_ADD(CURRENT_DATE(), INTERVAL :dies DAY)
              AND ie.data_caducitat >= CURRENT_DATE()
              AND ie.quantitat_disponible > 0
            ORDER BY ie.data_caducitat ASC";
            
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':dies' => $dies_avis]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error a obtenirAlertesCaducitatEstoc: " . $e->getMessage());
        return [];
    }
}