<?php
/**
 * auth/login.php — Pàgina d'autenticació de CultiuConnect.
 *
 * GET  → mostra el formulari de login
 * POST → valida credencials, inicia sessió, redirigeix (PRG)
 *
 * Taula `usuari`:
 *   id_usuari, nom_usuari (UNIQUE), contrasenya (bcrypt),
 *   nom_complet, rol ('admin'|'tecnic'|'responsable'|'operari'),
 *   estat ('actiu'|'inactiu'), id_treballador (FK nullable)
 *
 * Seguretat aplicada:
 *   - password_verify() per a bcrypt (mai MD5/SHA1)
 *   - session_regenerate_id() après login
 *   - Missatge d'error genèric (no revela si usuari/contrasenya és incorrecte)
 *   - Protecció CSRF amb token de sessió
 *   - Límit intents (5 en 15 min) per IP via $_SESSION
 */

require_once __DIR__ . '/../config/db.php';

// CORRECCIÓ: cc_session_start() s'ha de cridar ABANS del require_once de helpers.php
// perquè helpers.php defineix funcions que depenen de la sessió. Si s'inclou helpers
// sense sessió activa, les funcions internes poden iniciar-la sense les cookies
// configurades correctament, trencant el token CSRF entre GET i POST.
//
// Iniciem la sessió manualment aquí, amb totes les opcions de seguretat,
// abans que qualsevol altre codi pugui interferir.
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/helpers.php';

// cc_enforce_auth_i_rols() ja NO s'executa automàticament a helpers.php.
// A login.php no cal cridar-la perquè és una pàgina pública.

// Si ja hi ha sessió activa, redirigeix directament al dashboard
if (!empty($_SESSION['usuari_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Genera token CSRF si no existeix o si la sessió és nova/buida
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -----------------------------------------------------------
// Control d'intents fallits per IP (simple, sense BD)
// -----------------------------------------------------------
$ip_key     = 'login_fails_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
$max_intents = 5;
$bloc_segons = 15 * 60; // 15 minuts

$fails      = $_SESSION[$ip_key]['n']    ?? 0;
$primer_fail = $_SESSION[$ip_key]['t']   ?? 0;

// Reset si ha passat la finestra de bloqueig
if ($fails > 0 && (time() - $primer_fail) > $bloc_segons) {
    unset($_SESSION[$ip_key]);
    $fails = 0;
}

$bloquejat = $fails >= $max_intents;

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verificació CSRF
    $csrf_ok = isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);

    if (!$csrf_ok) {
        error_log("[CultiuConnect] CSRF fail: POST=" . ($_POST['csrf_token'] ?? 'null') . " vs SESSION=" . ($_SESSION['csrf_token'] ?? 'null'));
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        set_flash('error', 'Sol·licitud no vàlida (CSRF). Torna-ho a intentar.');
        session_write_close();
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }

    $nom_usuari = sanitize($_POST['nom_usuari'] ?? '');
    $contrasenya = $_POST['contrasenya'] ?? '';   // No sanititzem la contrasenya!

    if (empty($nom_usuari) || empty($contrasenya)) {
        set_flash('error', 'Cal introduir el nom d\'usuari i la contrasenya.');
        session_write_close();
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }

    try {
        $pdo  = connectDB();
        $stmt = $pdo->prepare("
            SELECT id_usuari, nom_usuari, contrasenya, nom_complet, rol, estat, id_treballador
            FROM usuari
            WHERE nom_usuari = ?
            LIMIT 1
        ");
        $stmt->execute([$nom_usuari]);
        $usuari = $stmt->fetch();

        // Missatge genèric tant si l'usuari no existeix com si la contrasenya és incorrecta.
        // Entorn dev: accepta contrasenya en text pla o en hash bcrypt.
        $password_match = false;
        if ($usuari) {
            $hash_info = password_get_info((string)$usuari['contrasenya']);
            $es_hash = !empty($hash_info['algo']) && (($hash_info['algoName'] ?? 'unknown') !== 'unknown');
            $password_match = $es_hash
                ? password_verify($contrasenya, $usuari['contrasenya'])
                : hash_equals((string)$usuari['contrasenya'], (string)$contrasenya);

            if (!$password_match) {
                error_log("[CultiuConnect] Password mismatch for user: $nom_usuari");
            }
        } else {
            error_log("[CultiuConnect] User not found: $nom_usuari");
        }

        $credencials_ok = $usuari
            && $usuari['estat'] === 'actiu'
            && $password_match;

        if (!$credencials_ok) {
            if ($bloquejat) {
                set_flash('error', 'Massa intents fallits. Espera 15 minuts.');
                session_write_close();
                header('Location: ' . BASE_URL . 'auth/login.php');
                exit;
            }

            // Registrar intent fallit
            if (!isset($_SESSION[$ip_key])) {
                $_SESSION[$ip_key] = ['n' => 0, 't' => time()];
            }
            $_SESSION[$ip_key]['n']++;

            $restants = $max_intents - $_SESSION[$ip_key]['n'];
            $msg = $restants > 0
                ? 'Credencials incorrectes. ' . $restants . ' intent(s) restant(s).'
                : 'Massa intents fallits. Espera 15 minuts.';

            set_flash('error', $msg);
            session_write_close();
            header('Location: ' . BASE_URL . 'auth/login.php');
            exit;
        }

        // --- LOGIN CORRECTE ---
        unset($_SESSION[$ip_key]);

        // Regenerar ID de sessió (prevenció session fixation)
        session_regenerate_id(true);

        $_SESSION['usuari_id']       = (int)$usuari['id_usuari'];
        $_SESSION['usuari_nom']      = $usuari['nom_complet'] ?: $usuari['nom_usuari'];
        $_SESSION['usuari_rol']      = $usuari['rol'];
        $_SESSION['usuari_username'] = $usuari['nom_usuari'];
        $_SESSION['login_time']      = time();

        // Assignar ID del treballador per al log d'accions (ja obtingut de la taula usuari)
        $_SESSION['usuari']['id_treballador'] = $usuari['id_treballador'] ? (int)$usuari['id_treballador'] : null;

        // Registrar acció de login (només si té id_treballador vinculat)
        if ($_SESSION['usuari']['id_treballador']) {
            registrar_accio(
                'INICI SESSIÓ: ' . ($usuari['nom_complet'] ?: $usuari['nom_usuari']),
                'usuari',
                (int)$usuari['id_usuari'],
                'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'desconeguda')
            );
        }

        // Redirigir a la pàgina que intentava accedir (si escau) o al dashboard
        $redir = $_SESSION['intent_url'] ?? BASE_URL . 'index.php';
        unset($_SESSION['intent_url']);

        set_flash('success', 'Benvingut/da, ' . ($usuari['nom_complet'] ?: $usuari['nom_usuari']) . '!');
        session_write_close();
        header('Location: ' . $redir);
        exit;

    } catch (Exception $e) {
        error_log('[CultiuConnect] login.php: ' . $e->getMessage());
        set_flash('error', 'Error intern. Torna-ho a intentar.');
        session_write_close();
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

// -----------------------------------------------------------
// GET: mostrar formulari
// -----------------------------------------------------------
$titol_pagina  = 'Accés — CultiuConnect';
$pagina_activa = '';

$temps_bloqueig = $bloquejat
    ? max(0, $bloc_segons - (time() - ($primer_fail ?: time())))
    : 0;
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titol_pagina) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/estils_globals.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="pagina-login">

<div class="login-contenidor">

    <div class="login-logo">
        <i class="fas fa-seedling" aria-hidden="true"></i>
        <span>CultiuConnect</span>
    </div>

    <h1 class="login-titol">Accés a la plataforma</h1>
    <p class="login-subtitol">Gestió intel·ligent de l'explotació fruitera</p>

    <?php
    $flash_error = has_flash('error') ? get_flash('error') : null;
    $flash_success = has_flash('success') ? get_flash('success') : null;

    // Missatge flash (errors de POST anterior)
    if ($flash_error): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($flash_error['missatge'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php if ($flash_success): ?>
        <div class="flash flash--success" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <?= e($flash_success['missatge'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php if ($bloquejat): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-lock" aria-hidden="true"></i>
            Massa intents fallits. Espera
            <strong><?= ceil($temps_bloqueig / 60) ?> minut(s)</strong> abans de tornar a intentar-ho.
        </div>
    <?php else: ?>

    <form id="form-login"
          method="POST"
          action="<?= BASE_URL ?>auth/login.php"
          class="formulari-login"
          novalidate>

        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

        <div class="form-grup">
            <label for="nom_usuari" class="form-label">
                <i class="fas fa-user" aria-hidden="true"></i>
                Nom d'usuari
            </label>
            <input type="text"
                   id="nom_usuari"
                   name="nom_usuari"
                   class="form-input"
                   autocomplete="username"
                   autofocus
                   required
                   placeholder="nom.usuari"
                   value="<?= e($_POST['nom_usuari'] ?? '') ?>">
        </div>

        <div class="form-grup">
            <label for="contrasenya" class="form-label">
                <i class="fas fa-lock" aria-hidden="true"></i>
                Contrasenya
            </label>
            <div class="input-amb-boto">
                <input type="password"
                       id="contrasenya"
                       name="contrasenya"
                       class="form-input"
                       autocomplete="current-password"
                       required
                       placeholder="••••••••">
                <button type="button"
                        class="btn-toggle-password"
                        aria-label="Mostrar/amagar contrasenya"
                        onclick="togglePassword()">
                    <i class="fas fa-eye" id="icona-ull" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="boto-login">
            <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
            Entrar
        </button>

    </form>

    <?php endif; ?>

    <p class="login-peu">
        © <?= date('Y') ?> CultiuConnect — Projecte 2n DAW
    </p>

</div>

<script>
function togglePassword() {
    const input = document.getElementById('contrasenya');
    const icona = document.getElementById('icona-ull');
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    icona.className = visible ? 'fas fa-eye-slash' : 'fas fa-eye';
}
</script>

</body>
</html>