<?php
// Verificar si la sesión está iniciada
if (!isset($_SESSION)) {
    session_start();
}

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para verificar si el usuario es administrador
function isAdmin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador';
}

// Función para verificar si el usuario está bloqueado
function isBlocked() {
    return isset($_SESSION['bloqueado']) && $_SESSION['bloqueado'] == 1;
}

// Verificar autenticación básica
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Verificar si el usuario está bloqueado
if (isBlocked()) {
    session_destroy();
    header('Location: login.php?error=blocked');
    exit;
} 