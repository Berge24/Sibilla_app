<?php
// utils/helpers.php

/**
 * Formatta una data nel formato italiano
 * @param string $date Data in formato Y-m-d
 * @return string Data formattata (es. 01/01/2025)
 */
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Genera un URL sicuro
 * @param string $path Percorso relativo
 * @return string URL completo
 */
function url($path = '') {
    return URL_ROOT . '/' . ltrim($path, '/');
}

/**
 * Escape HTML per prevenire XSS
 * @param string $text Testo da escape
 * @return string Testo con escape
 */
function esc($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Reindirizza a un'altra pagina
 * @param string $url URL di destinazione
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}