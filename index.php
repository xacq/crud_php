<?php
// ============================================================
// 0. FUNCIONES AUXILIARES (necesarias para el HTML)
// ============================================================
function escapar($valor) {
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function valorPost($campo) {
    return trim($_POST[$campo] ?? '');
}

// ============================================================
// 1. CONFIGURACIÓN
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'gimnasio_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_IMAGE_SIZE', 2 * 1024 * 1024); // 2 MB

// ============================================================
// 2. CONEXIÓN A LA BD
// ============================================================
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// ============================================================
// 3. VARIABLES DE MENSAJES
// ============================================================
$errores = [];
$mensajeExito = '';

// ============================================================
// 4. PROCESAR EL FORMULARIO (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 4.1 Recoger todos los campos (coinciden con el HTML) ---
    $tipo_documento             = valorPost('tipo_documento');
    $numero_documento           = valorPost('numero_documento');
    $nombres                    = valorPost('nombres');
    $apellidos                  = valorPost('apellidos');
    $fecha_nacimiento           = valorPost('fecha_nacimiento');
    $sexo                       = valorPost('sexo');
    $telefono                   = valorPost('telefono');
    $telefono_secundario        = valorPost('telefono_secundario');
    $correo                     = valorPost('correo');
    $ciudad                     = valorPost('ciudad');
    $direccion                  = valorPost('direccion');
    $contacto_emergencia_nombre = valorPost('contacto_emergencia_nombre');
    $contacto_emergencia_parentesco = valorPost('contacto_emergencia_parentesco');
    $contacto_emergencia_telefono   = valorPost('contacto_emergencia_telefono');
    $estado                     = valorPost('estado');
    $observaciones              = valorPost('observaciones');

    // --- 4.2 Validaciones ---
    if (!in_array($tipo_documento, ['cedula', 'pasaporte', 'ruc', 'otro'])) {
        $errores[] = "Tipo de documento no válido.";
    }
    if (empty($numero_documento) || strlen($numero_documento) > 30) {
        $errores[] = "Número de documento obligatorio (máx. 30 caracteres).";
    }
    if (empty($nombres) || strlen($nombres) > 100) {
        $errores[] = "Nombres obligatorios (máx. 100 caracteres).";
    }
    if (empty($apellidos) || strlen($apellidos) > 100) {
        $errores[] = "Apellidos obligatorios (máx. 100 caracteres).";
    }
    if (empty($telefono) || strlen($telefono) > 20) {
        $errores[] = "Teléfono principal obligatorio (máx. 20 caracteres).";
    }
    if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Correo electrónico no válido.";
    }
    if (!empty($fecha_nacimiento)) {
        $fecha = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        if (!$fecha || $fecha->format('Y-m-d') !== $fecha_nacimiento) {
            $errores[] = "Fecha de nacimiento no válida (formato YYYY-MM-DD).";
        }
    }

    // --- 4.3 Subida de fotografía ---
    $ruta_foto = null;
    if (isset($_FILES['fotografia']) && $_FILES['fotografia']['error'] !== UPLOAD_ERR_NO_FILE) {
        $archivo = $_FILES['fotografia'];
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $errores[] = "Error al subir la fotografía.";
        } elseif ($archivo['size'] > MAX_IMAGE_SIZE) {
            $errores[] = "La imagen no debe superar los 2 MB.";
        } else {
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                $errores[] = "Solo se permiten JPG, PNG o WEBP.";
            } else {
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0777, true);
                }
                $nombre_archivo = 'cliente_' . uniqid() . '.' . $extension;
                $ruta_foto = UPLOAD_DIR . $nombre_archivo;
                if (!move_uploaded_file($archivo['tmp_name'], $ruta_foto)) {
                    $errores[] = "No se pudo guardar la imagen.";
                    $ruta_foto = null;
                }
            }
        }
    }

    // --- 4.4 Inserción si no hay errores ---
    if (empty($errores)) {
        // Convertir campos vacíos a NULL para la BD
        $fecha_nacimiento_db = !empty($fecha_nacimiento) ? $fecha_nacimiento : null;
        $sexo_db = !empty($sexo) ? $sexo : null;
        $correo_db = !empty($correo) ? $correo : null;
        $telefono_secundario_db = !empty($telefono_secundario) ? $telefono_secundario : null;
        $ciudad_db = !empty($ciudad) ? $ciudad : null;
        $direccion_db = !empty($direccion) ? $direccion : null;
        $contacto_emergencia_nombre_db = !empty($contacto_emergencia_nombre) ? $contacto_emergencia_nombre : null;
        $contacto_emergencia_parentesco_db = !empty($contacto_emergencia_parentesco) ? $contacto_emergencia_parentesco : null;
        $contacto_emergencia_telefono_db = !empty($contacto_emergencia_telefono) ? $contacto_emergencia_telefono : null;
        $observaciones_db = !empty($observaciones) ? $observaciones : null;

        $sql = "INSERT INTO clientes (
                    tipo_documento,
                    numero_documento,
                    nombres,
                    apellidos,
                    fecha_nacimiento,
                    sexo,
                    telefono,
                    telefono_secundario,
                    correo,
                    ciudad,
                    direccion,
                    contacto_emergencia_nombre,
                    contacto_emergencia_parentesco,
                    contacto_emergencia_telefono,
                    estado,
                    observaciones,
                    fotografia
                ) VALUES (
                    :tipo_documento,
                    :numero_documento,
                    :nombres,
                    :apellidos,
                    :fecha_nacimiento,
                    :sexo,
                    :telefono,
                    :telefono_secundario,
                    :correo,
                    :ciudad,
                    :direccion,
                    :contacto_emergencia_nombre,
                    :contacto_emergencia_parentesco,
                    :contacto_emergencia_telefono,
                    :estado,
                    :observaciones,
                    :fotografia
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tipo_documento'                => $tipo_documento,
            ':numero_documento'              => $numero_documento,
            ':nombres'                       => $nombres,
            ':apellidos'                     => $apellidos,
            ':fecha_nacimiento'              => $fecha_nacimiento_db,
            ':sexo'                          => $sexo_db,
            ':telefono'                      => $telefono,
            ':telefono_secundario'           => $telefono_secundario_db,
            ':correo'                        => $correo_db,
            ':ciudad'                        => $ciudad_db,
            ':direccion'                     => $direccion_db,
            ':contacto_emergencia_nombre'    => $contacto_emergencia_nombre_db,
            ':contacto_emergencia_parentesco'=> $contacto_emergencia_parentesco_db,
            ':contacto_emergencia_telefono'  => $contacto_emergencia_telefono_db,
            ':estado'                        => $estado,
            ':observaciones'                 => $observaciones_db,
            ':fotografia'                    => $ruta_foto
        ]);

        $id = $pdo->lastInsertId();
        $mensajeExito = "¡Cliente registrado con éxito! ID: $id";

        // Limpiar variables para que el formulario se muestre vacío (opcional)
        // Si no se limpian, los campos mantienen los valores enviados.
        // Para simplificar, dejamos que se vean los datos enviados (así el estudiante ve lo que guardó).
        // Si quieres limpiar, puedes hacer un redirect o resetear $_POST.
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de clientes | Gimnasio</title>
    <style>
        /* ===== ESTILOS BÁSICOS (embebidos para ser autónomo) ===== */
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
        .contenedor { max-width: 900px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .cabecera { text-align: center; margin-bottom: 30px; }
        .cabecera h1 { margin: 0; color: #2c3e50; }
        .cabecera p { color: #7f8c8d; }
        .alerta { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alerta-exito { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alerta-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alerta-error ul { margin: 5px 0 0 20px; }
        .seccion { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 25px; }
        .seccion h2 { margin-top: 0; color: #34495e; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .campo-completo { grid-column: 1 / -1; }
        label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 0.9em; }
        .requerido { color: #e74c3c; }
        input, select, textarea { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 1em; }
        input:focus, select:focus, textarea:focus { border-color: #80bdff; outline: 0; box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25); }
        textarea { height: 80px; resize: vertical; }
        .ayuda { display: block; font-size: 0.8em; color: #6c757d; margin-top: 5px; }
        .acciones { display: flex; justify-content: flex-end; gap: 15px; margin-top: 20px; }
        .boton { padding: 10px 25px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
        .boton-principal { background: #007bff; color: #fff; }
        .boton-principal:hover { background: #0069d9; }
        .boton-secundario { background: #6c757d; color: #fff; }
        .boton-secundario:hover { background: #5a6268; }
        .btn-nuevo { background: #28a745; color: #fff; padding: 8px 16px; text-decoration: none; border-radius: 4px; }
        .btn-nuevo:hover { background: #218838; }
        @media (max-width: 600px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main class="contenedor">
    <header class="cabecera">
        <h1>Registro de clientes</h1>
        <p>Ingresa la información personal y de contacto del nuevo cliente.</p>
    </header>
        <div class="cabecera">
            <a href="./lista_clientes.php" class="btn-nuevo">Lista de clientes</a>
        </div>

    <?php if ($mensajeExito !== ''): ?>
        <div class="alerta alerta-exito">
            <?= escapar($mensajeExito) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
        <div class="alerta alerta-error">
            <strong>No fue posible guardar el registro:</strong>
            <ul>
                <?php foreach ($errores as $error): ?>
                    <li><?= escapar($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" autocomplete="off" action="">
        <!-- SECCIÓN IDENTIFICACIÓN -->
        <section class="seccion">
            <h2>Identificación</h2>
            <div class="grid">
                <div>
                    <label for="tipo_documento">Tipo de documento <span class="requerido">*</span></label>
                    <select id="tipo_documento" name="tipo_documento" required>
                        <option value="cedula" <?= valorPost('tipo_documento') === 'cedula' ? 'selected' : '' ?>>Cédula</option>
                        <option value="pasaporte" <?= valorPost('tipo_documento') === 'pasaporte' ? 'selected' : '' ?>>Pasaporte</option>
                        <option value="ruc" <?= valorPost('tipo_documento') === 'ruc' ? 'selected' : '' ?>>RUC</option>
                        <option value="otro" <?= valorPost('tipo_documento') === 'otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>
                <div>
                    <label for="numero_documento">Número de documento <span class="requerido">*</span></label>
                    <input id="numero_documento" name="numero_documento" type="text" maxlength="30" value="<?= escapar(valorPost('numero_documento')) ?>" required>
                </div>
                <div>
                    <label for="nombres">Nombres <span class="requerido">*</span></label>
                    <input id="nombres" name="nombres" type="text" maxlength="100" value="<?= escapar(valorPost('nombres')) ?>" required>
                </div>
                <div>
                    <label for="apellidos">Apellidos <span class="requerido">*</span></label>
                    <input id="apellidos" name="apellidos" type="text" maxlength="100" value="<?= escapar(valorPost('apellidos')) ?>" required>
                </div>
                <div>
                    <label for="fecha_nacimiento">Fecha de nacimiento</label>
                    <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" max="<?= date('Y-m-d') ?>" value="<?= escapar(valorPost('fecha_nacimiento')) ?>">
                </div>
                <div>
                    <label for="sexo">Sexo</label>
                    <select id="sexo" name="sexo">
                        <option value="no_especificado" <?= valorPost('sexo') === 'no_especificado' ? 'selected' : '' ?>>No especificado</option>
                        <option value="masculino" <?= valorPost('sexo') === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                        <option value="femenino" <?= valorPost('sexo') === 'femenino' ? 'selected' : '' ?>>Femenino</option>
                        <option value="otro" <?= valorPost('sexo') === 'otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>
                <div class="campo-completo">
                    <label for="fotografia">Fotografía</label>
                    <input id="fotografia" name="fotografia" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <span class="ayuda">Formatos permitidos: JPG, PNG y WEBP. Tamaño máximo: 2 MB.</span>
                </div>
            </div>
        </section>

        <!-- SECCIÓN CONTACTO -->
        <section class="seccion">
            <h2>Información de contacto</h2>
            <div class="grid">
                <div>
                    <label for="telefono">Teléfono principal <span class="requerido">*</span></label>
                    <input id="telefono" name="telefono" type="tel" maxlength="20" value="<?= escapar(valorPost('telefono')) ?>" required>
                </div>
                <div>
                    <label for="telefono_secundario">Teléfono secundario</label>
                    <input id="telefono_secundario" name="telefono_secundario" type="tel" maxlength="20" value="<?= escapar(valorPost('telefono_secundario')) ?>">
                </div>
                <div>
                    <label for="correo">Correo electrónico</label>
                    <input id="correo" name="correo" type="email" maxlength="150" value="<?= escapar(valorPost('correo')) ?>">
                </div>
                <div>
                    <label for="ciudad">Ciudad</label>
                    <input id="ciudad" name="ciudad" type="text" maxlength="100" value="<?= escapar(valorPost('ciudad')) ?>">
                </div>
                <div class="campo-completo">
                    <label for="direccion">Dirección</label>
                    <input id="direccion" name="direccion" type="text" maxlength="255" value="<?= escapar(valorPost('direccion')) ?>">
                </div>
            </div>
        </section>

        <!-- SECCIÓN EMERGENCIA Y ESTADO -->
        <section class="seccion">
            <h2>Contacto de emergencia y estado</h2>
            <div class="grid">
                <div>
                    <label for="contacto_emergencia_nombre">Nombre completo</label>
                    <input id="contacto_emergencia_nombre" name="contacto_emergencia_nombre" type="text" maxlength="150" value="<?= escapar(valorPost('contacto_emergencia_nombre')) ?>">
                </div>
                <div>
                    <label for="contacto_emergencia_parentesco">Parentesco</label>
                    <input id="contacto_emergencia_parentesco" name="contacto_emergencia_parentesco" type="text" maxlength="50" value="<?= escapar(valorPost('contacto_emergencia_parentesco')) ?>">
                </div>
                <div>
                    <label for="contacto_emergencia_telefono">Teléfono</label>
                    <input id="contacto_emergencia_telefono" name="contacto_emergencia_telefono" type="tel" maxlength="20" value="<?= escapar(valorPost('contacto_emergencia_telefono')) ?>">
                </div>
                <div>
                    <label for="estado">Estado inicial</label>
                    <select id="estado" name="estado">
                        <option value="activo" <?= valorPost('estado') === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= valorPost('estado') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                        <option value="suspendido" <?= valorPost('estado') === 'suspendido' ? 'selected' : '' ?>>Suspendido</option>
                        <option value="retirado" <?= valorPost('estado') === 'retirado' ? 'selected' : '' ?>>Retirado</option>
                    </select>
                </div>
                <div class="campo-completo">
                    <label for="observaciones">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" maxlength="5000"><?= escapar(valorPost('observaciones')) ?></textarea>
                </div>
            </div>
        </section>

        <div class="acciones">
            <button class="boton boton-secundario" type="reset">Limpiar</button>
            <button class="boton boton-principal" type="submit">Registrar cliente</button>
        </div>
    </form>
</main>
</body>
</html>