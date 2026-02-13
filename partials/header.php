<?php
// Este archivo actúa como la plantilla principal para el encabezado de todas las páginas.
// Incluye la barra de navegación superior, los estilos y los scripts necesarios.

// Verificamos si la página actual está definida para marcar el enlace activo en el menú.
$currentPage = $_GET['page'] ?? 'rutas'; // Por defecto, 'rutas' es la página de inicio.
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistema de Gestión de Cobros</title>
  
  <!-- Librerías Externas -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Estilos Personalizados para el Tema Oscuro -->
  <style>
    body { 
      font-family: 'Inter', sans-serif; 
      background-color: #111827; 
      color: #d1d5db; 
    }
    .table-header-custom { background-color: #374151; }
    .late-payment { color: #f87171; font-weight: bold; }
    .on-time { color: #4ade80; font-weight: bold; }
    .summary-card { 
      background-color: #1f2937; 
      border: 1px solid #374151; 
      border-radius: 0.75rem; 
      padding: 1.5rem; 
      transition: all 0.3s ease; 
    }
    .summary-card:hover { 
      transform: translateY(-5px); 
      border-color: #4b5563; 
    }
    .form-element-dark { 
      background-color: #374151; 
      border-color: #4b5563; 
      color: #d1d5db; 
    }
    .form-element-dark:focus { 
      --tw-ring-color: #3b82f6; 
      border-color: #3b82f6; 
    }
    /* Estilos para filas intercaladas (Zebra-striping) */
    tbody .row-even {
      background-color: #2d3748; /* bg-gray-800 */
    }
    tbody .row-odd {
      background-color: rgba(55, 65, 81, 0.5); /* bg-gray-700/50 */
    }
    tbody tr:hover { background-color: #4a5568; } /* bg-gray-600 */

    .nav-link { 
      display: flex;
      align-items: center;
      padding: 8px 12px; 
      border-radius: 6px; 
      transition: background-color 0.3s, color 0.3s; 
      color: #d1d5db;
      font-size: 0.875rem;
      font-weight: 500;
      white-space: nowrap;
    }
    .nav-link:hover { background-color: #374151; color: #ffffff; }
    .nav-link.active { 
      background-color: #3b82f6; 
      color: #ffffff; 
    }
    
    /* --- ESTILOS PARA TABLAS RESPONSIVAS --- */
    @media (max-width: 768px) {
      .responsive-table thead {
        display: none; /* Ocultar cabeceras en móvil */
      }
      .responsive-table tr {
        display: block;
        margin-bottom: 1rem;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 1px solid #374151;
      }
      .responsive-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        text-align: right;
        border-bottom: 1px solid #374151;
      }
      .responsive-table td:last-child {
        border-bottom: none;
      }
      .responsive-table td::before {
        content: attr(data-label);
        font-weight: bold;
        text-align: left;
        margin-right: 1rem;
        color: #9ca3af; /* Color de la etiqueta */
      }
    }

    @media print {
      body { background-color: #ffffff; color: #000000; }
      .no-print { display: none; } 
      .print-container { padding: 0; }
      table { width: 100%; border-collapse: collapse; font-size: 10px; }
      th, td { border: 1px solid #ccc; padding: 4px; } 
      h1, h2 { color: #000; }
    }
  </style>
</head>
<body class="min-h-screen">
  <header class="bg-gray-800 shadow-md no-print">
    <nav class="container mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <div class="text-white font-bold text-xl">
          <a href="index.php"><i class="fas fa-hand-holding-dollar text-blue-400"></i> Cobranzas</a>

        </div>
        <div class="hidden md:flex items-center space-x-2">
          <?php if (isset($_SESSION['user_id'])): ?>
            <!-- ***** BARRA DE NAVEGACIÓN ACTUALIZADA ***** -->
            <a href="index.php?page=rutas" class="nav-link <?= $currentPage == 'rutas' ? 'active' : '' ?>"><i class="fas fa-route mr-2"></i>Rutas</a>

            <a href="index.php?page=clientes" class="nav-link <?= in_array($currentPage, ['clientes', 'agregar_cliente', 'editar_cliente']) ? 'active' : '' ?>"><i class="fas fa-users mr-2"></i>Clientes</a>
            <a href="index.php?page=atrasados" class="nav-link <?= $currentPage == 'atrasados' ? 'active' : '' ?>"><i class="fas fa-exclamation-triangle mr-2"></i>Atrasados</a>

            <a href="index.php?page=reporte_general" class="nav-link <?= $currentPage == 'reporte_general' ? 'active' : '' ?>"><i class="fas fa-file-alt mr-2"></i>Reporte Gral.</a>

            <!-- ***** NUEVO ENLACE AÑADIDO ***** -->
            <a href="index.php?page=finalizados" class="nav-link <?= $currentPage == 'finalizados' ? 'active' : '' ?>"><i class="fas fa-tasks mr-2"></i>Finalizados</a>

            
            <div class="border-l border-gray-600 h-8 mx-2"></div>


            <span class="text-gray-300 text-sm"><i class="fas fa-user-circle mr-2"></i><?= htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario') ?></span>
            <a href="logout.php" class="nav-link bg-red-600 hover:bg-red-700 text-white" title="Cerrar Sesión">

              <i class="fas fa-sign-out-alt"></i>
            </a>

          <?php else: ?>
            <a href="login.php" class="nav-link">Iniciar Sesión</a>
          <?php endif; ?>
        </div>
      </div>
    </nav>
  </header>
  
  <!-- Contenedor principal que se cierra en footer.php -->

  <main class="container mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <!-- CORRECCIÓN: Esta etiqueta <div> faltaba. Es el contenedor principal del contenido. -->
    <div class="bg-gray-800 p-6 rounded-lg border border-gray-700">