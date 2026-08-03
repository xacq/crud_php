<?php
// ============================================================
// 1. FUNCIONES AUXILIARES (compartidas)
// ============================================================
function escapar($valor) {
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

function valorPost($campo) {
    return trim($_POST[$campo] ?? '');
}

// ============================================================
// 2. CONFIGURACIÓN Y CONEXIÓN A LA BD
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'gimnasio_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_IMAGE_SIZE', 2 * 1024 * 1024); // 2 MB

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// ============================================================
// 3. OBTENER DATOS DEL CLIENTE (para mostrar en el formulario)
// ============================================================
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$cliente = null;
$errorCarga = '';

if ($id > 0) {
    $sql = "SELECT * FROM clientes WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        $errorCarga = "El cliente solicitado no existe.";
    } elseif ($cliente['estado'] === 'inactivo') {
        $errorCarga = "Este cliente está inactivo y no puede ser editado.";
    }
} else {
    $errorCarga = "ID de cliente no válido.";
}

// ============================================================
// 4. PROCESAR EL FORMULARIO DE EDICIÓN (POST)
// ============================================================
$errores = [];
$mensajeExito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cliente) {
    // Recoger datos del formulario
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

    // --- Validaciones ---
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

    // --- Subida de nueva fotografía (si se envía) ---
    $ruta_foto = $cliente['fotografia']; // Mantener la actual por defecto
    $foto_subida = false;

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
                // Crear directorio si no existe
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0777, true);
                }
                // Generar nombre único
                $nombre_archivo = 'cliente_' . uniqid() . '.' . $extension;
                $nueva_ruta = UPLOAD_DIR . $nombre_archivo;
                if (move_uploaded_file($archivo['tmp_name'], $nueva_ruta)) {
                    // Si se subió correctamente, eliminar la foto anterior si existía
                    if (!empty($cliente['fotografia']) && file_exists(__DIR__ . '/' . $cliente['fotografia'])) {
                        unlink(__DIR__ . '/' . $cliente['fotografia']);
                    }
                    $ruta_foto = 'uploads/' . $nombre_archivo; // Guardar ruta relativa
                    $foto_subida = true;
                } else {
                    $errores[] = "No se pudo guardar la imagen.";
                }
            }
        }
    }

    // --- Actualización si no hay errores ---
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

        $sql = "UPDATE clientes SET
                    tipo_documento = :tipo_documento,
                    numero_documento = :numero_documento,
                    nombres = :nombres,
                    apellidos = :apellidos,
                    fecha_nacimiento = :fecha_nacimiento,
                    sexo = :sexo,
                    telefono = :telefono,
                    telefono_secundario = :telefono_secundario,
                    correo = :correo,
                    ciudad = :ciudad,
                    direccion = :direccion,
                    contacto_emergencia_nombre = :contacto_emergencia_nombre,
                    contacto_emergencia_parentesco = :contacto_emergencia_parentesco,
                    contacto_emergencia_telefono = :contacto_emergencia_telefono,
                    estado = :estado,
                    observaciones = :observaciones,
                    fotografia = :fotografia
                WHERE id = :id";

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
            ':fotografia'                    => $ruta_foto,
            ':id'                            => $id
        ]);

        $mensajeExito = "Cliente actualizado correctamente.";
        // Recargar los datos del cliente para mostrarlos actualizados en el formulario
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $cliente = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar cliente | Gimnasio</title>
    <style>
        /* ===== ESTILOS (consistentes) ===== */
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px; }
        .contenedor { max-width: 900px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .cabecera { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .cabecera h1 { margin: 0; color: #2c3e50; }
        .acciones-superior { display: flex; gap: 10px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9em; display: inline-block; }
        .btn-volver { background: #6c757d; color: #fff; }
        .btn-volver:hover { background: #5a6268; }
        .alerta { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alerta-exito { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alerta-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
        .foto-actual { margin: 10px 0; }
        .foto-actual img { max-width: 100px; max-height: 100px; border-radius: 5px; border: 1px solid #ddd; }
        @media (max-width: 600px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="contenedor">
    <div class="cabecera">
        <h1>Editar cliente</h1>
        <div class="acciones-superior">
            <a href="lista_clientes.php" class="btn btn-volver">← Volver a la lista</a>
        </div>
    </div>

    <?php if ($errorCarga): ?>
        <div class="alerta alerta-error"><?= escapar($errorCarga) ?></div>
    <?php endif; ?>

    <?php if ($mensajeExito): ?>
        <div class="alerta alerta-exito"><?= escapar($mensajeExito) ?></div>
    <?php endif; ?>

    <?php if ($errores): ?>
        <div class="alerta alerta-error">
            <strong>No fue posible guardar los cambios:</strong>
            <ul>
                <?php foreach ($errores as $error): ?>
                    <li><?= escapar($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($cliente): ?>
        <form method="post" enctype="multipart/form-data" autocomplete="off">
            <!-- Identificación -->
            <section class="seccion">
                <h2>Identificación</h2>
                <div class="grid">
                    <div>
                        <label for="tipo_documento">Tipo de documento <span class="requerido">*</span></label>
                        <select id="tipo_documento" name="tipo_documento" required>
                            <option value="cedula" <?= $cliente['tipo_documento'] === 'cedula' ? 'selected' : '' ?>>Cédula</option>
                            <option value="pasaporte" <?= $cliente['tipo_documento'] === 'pasaporte' ? 'selected' : '' ?>>Pasaporte</option>
                            <option value="ruc" <?= $cliente['tipo_documento'] === 'ruc' ? 'selected' : '' ?>>RUC</option>
                            <option value="otro" <?= $cliente['tipo_documento'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                        </select>
                    </div>
                    <div>
                        <label for="numero_documento">Número de documento <span class="requerido">*</span></label>
                        <input id="numero_documento" name="numero_documento" type="text" maxlength="30" value="<?= escapar($cliente['numero_documento']) ?>" required>
                    </div>
                    <div>
                        <label for="nombres">Nombres <span class="requerido">*</span></label>
                        <input id="nombres" name="nombres" type="text" maxlength="100" value="<?= escapar($cliente['nombres']) ?>" required>
                    </div>
                    <div>
                        <label for="apellidos">Apellidos <span class="requerido">*</span></label>
                        <input id="apellidos" name="apellidos" type="text" maxlength="100" value="<?= escapar($cliente['apellidos']) ?>" required>
                    </div>
                    <div>
                        <label for="fecha_nacimiento">Fecha de nacimiento</label>
                        <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" max="<?= date('Y-m-d') ?>" value="<?= escapar($cliente['fecha_nacimiento']) ?>">
                    </div>
                    <div>
                        <label for="sexo">Sexo</label>
                        <select id="sexo" name="sexo">
                            <option value="no_especificado" <?= $cliente['sexo'] === 'no_especificado' ? 'selected' : '' ?>>No especificado</option>
                            <option value="masculino" <?= $cliente['sexo'] === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="femenino" <?= $cliente['sexo'] === 'femenino' ? 'selected' : '' ?>>Femenino</option>
                            <option value="otro" <?= $cliente['sexo'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                        </select>
                    </div>
                    <div class="campo-completo">
                        <label for="fotografia">Fotografía</label>
                        <?php if (!empty($cliente['fotografia']) && file_exists(__DIR__ . '/' . $cliente['fotografia'])): ?>
                            <div class="foto-actual">
                                <img src="<?= escapar($cliente['fotografia']) ?>" alt="Foto actual">
                                <span style="display:block; font-size:0.8em; color:#6c757d;">Foto actual</span>
                            </div>
                        <?php endif; ?>
                        <input id="fotografia" name="fotografia" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <span class="ayuda">Formatos permitidos: JPG, PNG y WEBP. Tamaño máximo: 2 MB. Dejar en blanco para mantener la actual.</span>
                    </div>
                </div>
            </section>

            <!-- Contacto -->
            <section class="seccion">
                <h2>Información de contacto</h2>
                <div class="grid">
                    <div>
                        <label for="telefono">Teléfono principal <span class="requerido">*</span></label>
                        <input id="telefono" name="telefono" type="tel" maxlength="20" value="<?= escapar($cliente['telefono']) ?>" required>
                    </div>
                    <div>
                        <label for="telefono_secundario">Teléfono secundario</label>
                        <input id="telefono_secundario" name="telefono_secundario" type="tel" maxlength="20" value="<?= escapar($cliente['telefono_secundario']) ?>">
                    </div>
                    <div>
                        <label for="correo">Correo electrónico</label>
                        <input id="correo" name="correo" type="email" maxlength="150" value="<?= escapar($cliente['correo']) ?>">
                    </div>
                    <div>
                        <label for="ciudad">Ciudad</label>
                        <input id="ciudad" name="ciudad" type="text" maxlength="100" value="<?= escapar($cliente['ciudad']) ?>">
                    </div>
                    <div class="campo-completo">
                        <label for="direccion">Dirección</label>
                        <input id="direccion" name="direccion" type="text" maxlength="255" value="<?= escapar($cliente['direccion']) ?>">
                    </div>
                </div>
            </section>

            <!-- Emergencia y estado -->
            <section class="seccion">
                <h2>Contacto de emergencia y estado</h2>
                <div class="grid">
                    <div>
                        <label for="contacto_emergencia_nombre">Nombre completo</label>
                        <input id="contacto_emergencia_nombre" name="contacto_emergencia_nombre" type="text" maxlength="150" value="<?= escapar($cliente['contacto_emergencia_nombre']) ?>">
                    </div>
                    <div>
                        <label for="contacto_emergencia_parentesco">Parentesco</label>
                        <input id="contacto_emergencia_parentesco" name="contacto_emergencia_parentesco" type="text" maxlength="50" value="<?= escapar($cliente['contacto_emergencia_parentesco']) ?>">
                    </div>
                    <div>
                        <label for="contacto_emergencia_telefono">Teléfono</label>
                        <input id="contacto_emergencia_telefono" name="contacto_emergencia_telefono" type="tel" maxlength="20" value="<?= escapar($cliente['contacto_emergencia_telefono']) ?>">
                    </div>
                    <div>
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <option value="activo" <?= $cliente['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="inactivo" <?= $cliente['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                            <option value="suspendido" <?= $cliente['estado'] === 'suspendido' ? 'selected' : '' ?>>Suspendido</option>
                            <option value="retirado" <?= $cliente['estado'] === 'retirado' ? 'selected' : '' ?>>Retirado</option>
                        </select>
                    </div>
                    <div class="campo-completo">
                        <label for="observaciones">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" maxlength="5000"><?= escapar($cliente['observaciones']) ?></textarea>
                    </div>
                </div>
            </section>

            <div class="acciones">
                <button class="boton boton-secundario" type="reset">Limpiar</button>
                <button class="boton boton-principal" type="submit">Actualizar cliente</button>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>