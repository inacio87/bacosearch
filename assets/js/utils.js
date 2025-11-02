// /assets/js/utils.js

/**
 * Escapa caracteres HTML para prevenir ataques XSS.
 * @param {string} unsafe A string que pode conter HTML.
 * @returns {string} A string sanitizada.
 */
function escapeHtml(unsafe) {
    // Garante que o input seja uma string antes de tentar substituir
    return String(unsafe || '')
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}