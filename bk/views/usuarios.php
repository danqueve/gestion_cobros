<?php
// --- GESTIÓN DE USUARIOS Y PERMISOS ---

// Verificar si es administrador (seguridad estricta)
if (!esAdmin()) {
    echo "<div class='bg-red-900 text-white p-4 rounded text-center font-bold shadow-lg border border-red-700'>";
    echo "<i class='fas fa-lock mr-2'></i>Acceso denegado. Solo los administradores pueden gestionar usuarios.";
    echo "</div>";
    exit;
}

$error = '';
$success = '';

// --- ACCIONES: CREAR / EDITAR / ELIMINAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ELIMINAR USUARIO
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id_borrar = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        
        // Evitar borrarse a sí mismo
        if ($id_borrar && $id_borrar != $_SESSION['user_id']) {
            try {
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id_borrar]);
                $success = "Usuario eliminado correctamente.";
            } catch (PDOException $e) {
                $error = "Error al eliminar: " . $e->getMessage();
            }
        } else {
            $error = "No puedes eliminar tu propia cuenta de administrador.";
        }
    }
    
    // 2. CREAR O EDITAR USUARIO
    elseif (isset($_POST['action']) && ($_POST['action'] === 'create' || $_POST['action'] === 'edit')) {
        // CORRECCIÓN: Variables para nombre_usuario
        $usuario = trim($_POST['usuario']); 
        $nombre_completo = trim($_POST['nombre_completo'] ?? $usuario); // Usamos el mismo si no se especifica
        $password = $_POST['password']; 
        $rol = $_POST['rol'];
        
        $zonas_seleccionadas = isset($_POST['zonas']) && is_array($_POST['zonas']) ? implode(',', $_POST['zonas']) : '';
        
        if (empty($usuario)) {
            $error = "El nombre de usuario es obligatorio.";
        } else {
            try {
                // MODO CREAR
                if ($_POST['action'] === 'create') {
                    // Validar si existe (usando nombre_usuario)
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE nombre_usuario = ?");
                    $stmtCheck->execute([$usuario]);
                    if ($stmtCheck->fetchColumn() > 0) {
                        throw new Exception("El usuario '$usuario' ya existe.");
                    }

                    if (empty($password)) {
                        throw new Exception("La contraseña es obligatoria.");
                    }
                    
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // CORRECCIÓN: Insertar en las columnas correctas
                    $sql = "INSERT INTO usuarios (nombre_usuario, nombre_completo, password, rol, zonas_asignadas) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$usuario, $nombre_completo, $hash, $rol, $zonas_seleccionadas]);
                    $success = "Usuario '$usuario' creado exitosamente.";
                    
                // MODO EDITAR
                } elseif ($_POST['action'] === 'edit') {
                    $id_edit = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                    
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        // CORRECCIÓN: Update con columnas correctas
                        $sql = "UPDATE usuarios SET nombre_usuario = ?, nombre_completo = ?, password = ?, rol = ?, zonas_asignadas = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$usuario, $nombre_completo, $hash, $rol, $zonas_seleccionadas, $id_edit]);
                    } else {
                        $sql = "UPDATE usuarios SET nombre_usuario = ?, nombre_completo = ?, rol = ?, zonas_asignadas = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$usuario, $nombre_completo, $rol, $zonas_seleccionadas, $id_edit]);
                    }
                    $success = "Datos actualizados correctamente.";
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// --- LISTAR USUARIOS ---
$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY id ASC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2 class="text-2xl font-bold text-gray-200 mb-6 flex items-center">
    <div class="bg-blue-600 p-2 rounded-lg mr-3 shadow-lg">
        <i class="fas fa-users-cog text-white"></i>
    </div>
    Gestión de Usuarios y Permisos
</h2>

<?php if ($error): ?>
    <div class="bg-red-900 border border-red-700 text-red-100 px-4 py-3 rounded mb-6 shadow-md flex items-center">
        <i class="fas fa-exclamation-circle mr-3 text-xl"></i><span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="bg-green-900 border border-green-700 text-green-100 px-4 py-3 rounded mb-6 shadow-md flex items-center">
        <i class="fas fa-check-circle mr-3 text-xl"></i><span><?= htmlspecialchars($success) ?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- FORMULARIO -->
    <div class="lg:col-span-1">
        <div class="bg-gray-800 p-6 rounded-lg border border-gray-700 shadow-xl sticky top-6">
            <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-600 pb-3 flex justify-between items-center">
                <span id="form-title"><i class="fas fa-user-plus mr-2 text-blue-400"></i>Nuevo Usuario</span>
                <button onclick="resetForm()" class="text-xs text-gray-400 hover:text-white bg-gray-700 px-2 py-1 rounded">Limpiar</button>
            </h3>
            
            <form method="POST" action="index.php?page=usuarios" id="user-form">
                <input type="hidden" name="action" id="form-action" value="create">
                <input type="hidden" name="user_id" id="form-user-id" value="">
                
                <!-- Usuario (Login) -->
                <div class="mb-4">
                    <label class="block text-gray-300 text-xs font-bold mb-2 uppercase">Usuario (Login)</label>
                    <input type="text" name="usuario" id="input-usuario" class="w-full bg-gray-900 text-white border border-gray-600 rounded py-2 px-3 focus:border-blue-500 transition-colors" placeholder="Ej: jperez" required>
                </div>

                <!-- Nombre Completo (Visual) -->
                <div class="mb-4">
                    <label class="block text-gray-300 text-xs font-bold mb-2 uppercase">Nombre Completo</label>
                    <input type="text" name="nombre_completo" id="input-nombre-completo" class="w-full bg-gray-900 text-white border border-gray-600 rounded py-2 px-3 focus:border-blue-500 transition-colors" placeholder="Ej: Juan Perez">
                </div>
                
                <!-- Contraseña -->
                <div class="mb-4">
                    <label class="block text-gray-300 text-xs font-bold mb-2 uppercase">Contraseña</label>
                    <input type="password" name="password" id="input-password" class="w-full bg-gray-900 text-white border border-gray-600 rounded py-2 px-3 focus:border-blue-500 transition-colors" placeholder="••••••">
                    <p class="text-xs text-gray-500 mt-1" id="password-help">* Obligatoria al crear.</p>
                </div>
                
                <!-- Rol -->
                <div class="mb-5">
                    <label class="block text-gray-300 text-xs font-bold mb-2 uppercase">Rol / Perfil</label>
                    <select name="rol" id="input-rol" class="w-full bg-gray-900 text-white border border-gray-600 rounded py-2 px-3 focus:border-blue-500">
                        <option value="cobrador">Cobrador</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <!-- Zonas -->
                <div class="mb-6" id="container-zonas">
                    <label class="block text-gray-300 text-xs font-bold mb-2 uppercase">Zonas Asignadas</label>
                    <div class="bg-gray-900 p-3 rounded border border-gray-600 max-h-48 overflow-y-auto custom-scrollbar">
                        <?php foreach($NOMBRES_ZONAS as $id_zona => $nombre_zona): ?>
                        <label class="flex items-center p-2 rounded hover:bg-gray-800 cursor-pointer mb-1">
                            <input type="checkbox" name="zonas[]" value="<?= $id_zona ?>" id="zona-<?= $id_zona ?>" class="form-checkbox h-4 w-4 text-blue-600 bg-gray-700 border-gray-500 rounded">
                            <span class="ml-3 text-gray-300 text-sm"><span class="font-bold text-gray-500 mr-1">#<?= $id_zona ?></span> <?= htmlspecialchars($nombre_zona) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow-lg transition-colors" id="btn-submit">
                    <i class="fas fa-save mr-2"></i>Guardar Usuario
                </button>
            </form>
        </div>
    </div>

    <!-- LISTADO -->
    <div class="lg:col-span-2">
        <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Usuario</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-400 uppercase">Rol</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase">Zonas</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 bg-gray-800">
                        <?php foreach($usuarios as $usr): ?>
                        <tr class="hover:bg-gray-750 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-white"><?= htmlspecialchars($usr['nombre_usuario']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-300"><?= htmlspecialchars($usr['nombre_completo'] ?? '-') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if($usr['rol'] === 'admin'): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-purple-900 text-purple-200 border border-purple-700">Admin</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded bg-blue-900 text-blue-200 border border-blue-700">Cobrador</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-300">
                                <?php 
                                    if($usr['rol'] === 'admin') echo '<span class="text-gray-500 italic">Todo</span>';
                                    else {
                                        $z_ids = !empty($usr['zonas_asignadas']) ? explode(',', $usr['zonas_asignadas']) : [];
                                        echo empty($z_ids) ? '<span class="text-red-400">Ninguna</span>' : implode(', ', array_map(function($id) use ($NOMBRES_ZONAS) { return $NOMBRES_ZONAS[$id] ?? $id; }, $z_ids));
                                    }
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick='editarUsuario(<?= json_encode($usr) ?>)' class="text-blue-400 hover:text-blue-300 mr-3"><i class="fas fa-edit"></i></button>
                                <?php if($usr['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" action="index.php?page=usuarios" class="inline" onsubmit="return confirm('¿Eliminar usuario?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $usr['id'] ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-400"><i class="fas fa-trash-alt"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('user-form').reset();
    document.getElementById('form-action').value = 'create';
    document.getElementById('form-user-id').value = '';
    document.getElementById('form-title').innerHTML = '<i class="fas fa-user-plus mr-2 text-blue-400"></i>Nuevo Usuario';
    document.getElementById('btn-submit').innerHTML = '<i class="fas fa-save mr-2"></i>Guardar Usuario';
    document.getElementById('btn-submit').className = 'w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow-lg transition-colors';
    
    document.querySelectorAll('input[type="checkbox"][name="zonas[]"]').forEach(cb => cb.checked = false);
    toggleZonas('cobrador');
}

function editarUsuario(user) {
    resetForm();
    document.getElementById('form-action').value = 'edit';
    document.getElementById('form-user-id').value = user.id;
    document.getElementById('input-usuario').value = user.nombre_usuario; // CORREGIDO: nombre_usuario
    document.getElementById('input-nombre-completo').value = user.nombre_completo || '';
    document.getElementById('input-rol').value = user.rol;
    
    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit mr-2 text-yellow-400"></i>Editar Usuario';
    document.getElementById('btn-submit').innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Actualizar';
    document.getElementById('btn-submit').className = 'w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded shadow-lg transition-colors';
    document.getElementById('password-help').innerText = '* Dejar vacío para no cambiar.';
    
    if (user.zonas_asignadas) {
        user.zonas_asignadas.split(',').forEach(zId => {
            const cb = document.getElementById('zona-' + zId);
            if(cb) cb.checked = true;
        });
    }
    toggleZonas(user.rol);
    document.getElementById('user-form').scrollIntoView({ behavior: 'smooth' });
}

function toggleZonas(rol) {
    const container = document.getElementById('container-zonas');
    if (rol === 'admin') {
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
    } else {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    }
}

document.getElementById('input-rol').addEventListener('change', function() { toggleZonas(this.value); });
</script>