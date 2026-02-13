<?php
// --- LÓGICA PARA ELIMINAR UN CLIENTE Y TODOS SUS DATOS ASOCIADOS (CORREGIDA) ---

// El ID que recibimos de la lista de clientes es el ID del cliente directamente.
$cliente_id = $_GET['id'] ?? null;

if (!$cliente_id) {
    // Si no se proporciona un ID, redirigimos con un mensaje de error.
    echo '<script>window.location.href = "index.php?page=clientes&status=error&msg=ID no especificado";</script>';
    exit;
}

try {
    // --- VERIFICACIÓN OPCIONAL: Asegurarse de que el cliente exista antes de intentar borrar ---
    $sql_check = "SELECT id FROM clientes WHERE id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$cliente_id]);
    if ($stmt_check->fetch() === false) {
        // Si el cliente no existe, redirigir con el error "no encontrado".
        echo '<script>window.location.href = "index.php?page=clientes&status=notfound";</script>';
        exit;
    }

    // --- USAR UNA TRANSACCIÓN PARA UN BORRADO SEGURO ---
    $pdo->beginTransaction();

    // Borramos al cliente de la tabla 'clientes'.
    // Si la clave foránea en la tabla 'creditos' está configurada con 'ON DELETE CASCADE',
    // todos los créditos y pagos (si también está en cascada) asociados a este cliente se borrarán automáticamente.
    // Esta es la forma más eficiente y segura de hacerlo.
    $sql_delete = "DELETE FROM clientes WHERE id = ?";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([$cliente_id]);

    // Confirmamos la transacción si todo fue exitoso.
    $pdo->commit();

    // --- Redirigir usando JavaScript para evitar el error de "headers already sent" ---
    echo '<script>window.location.href = "index.php?page=clientes&status=deleted";</script>';
    exit;

} catch (PDOException $e) {
    // Si algo sale mal durante la transacción, la revertimos.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Redirigimos con un mensaje de error detallado.
    $error_msg = urlencode("Error al eliminar el cliente: " . $e->getMessage());
    echo '<script>window.location.href = "index.php?page=clientes&status=error&msg=' . $error_msg . '";</script>';
    exit;
}
?>

