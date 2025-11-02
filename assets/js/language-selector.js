/**
 * /assets/js/language-selector.js - VERSÃO FINAL E ROBUSTA
 *
 * RESPONSABILIDADES:
 * 1. Lida com a abertura e fecho do menu dropdown do seletor de idiomas.
 * 2. Deteta o clique num novo idioma de forma segura.
 * 3. Coloca o código do idioma selecionado no formulário escondido.
 * 4. Submete o formulário para o backend.
 *
 * ÚLTIMA ATUALIZAÇÃO: 28/06/2025
 */
document.addEventListener('DOMContentLoaded', function () {
    const selector = document.querySelector('.language-selector .custom-select');
    if (!selector) {
        return; // Se não houver seletor na página, o script não faz nada.
    }

    const selectedOption = selector.querySelector('.selected-option');
    const optionsList = selector.querySelector('.options-list');
    const languageForm = document.getElementById('language-form');
    const languageInput = document.getElementById('language-input');

    // Validação final para garantir que todos os elementos necessários existem.
    if (!selectedOption || !optionsList || !languageForm || !languageInput) {
        console.error('Elementos essenciais do seletor de idiomas (selected-option, options-list, language-form, ou language-input) não foram encontrados no DOM.');
        return;
    }

    // Abre/fecha o dropdown de idiomas.
    selectedOption.addEventListener('click', function (event) {
        event.stopPropagation(); // Impede que o clique se propague e feche o menu imediatamente.
        const isVisible = optionsList.style.display === 'block';
        optionsList.style.display = isVisible ? 'none' : 'block';
    });

    // Fecha o dropdown se o utilizador clicar em qualquer outro sítio na página.
    document.addEventListener('click', function () {
        if (optionsList.style.display === 'block') {
            optionsList.style.display = 'none';
        }
    });

    // Lida com o clique numa das opções de idioma na lista.
    // Usamos 'mousedown' em vez de 'click' para uma resposta mais imediata.
    optionsList.addEventListener('mousedown', function (event) {
        // Encontra o elemento .option que foi clicado, mesmo que o clique tenha sido num filho (span).
        const optionElement = event.target.closest('.option');

        if (optionElement) {
            const selectedValue = optionElement.getAttribute('data-value');
            if (selectedValue) {
                // Coloca o valor (ex: 'de-de') no input escondido do formulário.
                languageInput.value = selectedValue;
                // Submete o formulário para enviar a escolha para o PHP.
                languageForm.submit();
            }
        }
    });
});