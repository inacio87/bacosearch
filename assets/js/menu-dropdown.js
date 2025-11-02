// /assets/js/menu-dropdown.js

document.addEventListener('DOMContentLoaded', function () {
    const menuButton = document.querySelector('.city-bar .menu-button');
    const dropdownContent = document.getElementById('dropdown-menu');

    // Se os elementos necessários não existirem, o script não tenta ser executado.
    if (!menuButton || !dropdownContent) {
        return;
    }

    // Adiciona evento de clique para abrir/fechar o menu.
    menuButton.addEventListener('click', function (e) {
        e.preventDefault(); // Evita o comportamento padrão do botão.

        const isExpanded = this.getAttribute('aria-expanded') === 'true';

        // Alterna a classe 'show' no conteúdo do dropdown para controlar a visibilidade via CSS.
        dropdownContent.classList.toggle('show', !isExpanded);

        // Atualiza o atributo ARIA para acessibilidade.
        this.setAttribute('aria-expanded', !isExpanded);
    });

    // Suporte a teclado para o botão do menu.
    menuButton.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault(); // Impede rolagem da página.
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            dropdownContent.classList.toggle('show', !isExpanded);
            this.setAttribute('aria-expanded', !isExpanded);
        }
    });

    // Evento para fechar o menu ao clicar fora dele.
    document.addEventListener('click', function (event) {
        // Verifica se o clique não foi no botão do menu nem dentro do conteúdo do menu.
        if (!menuButton.contains(event.target) && !dropdownContent.contains(event.target)) {
            if (dropdownContent.classList.contains('show')) {
                dropdownContent.classList.remove('show');
                menuButton.setAttribute('aria-expanded', 'false');
            }
        }
    });
});