/**
 * /assets/js/search-enhanced.js
 * Sistema de busca inteligente com sugestões em tempo real e gestão de erro silenciosa.
 * Estilo de código procedural/funcional, sem uso de 'class'.
 */
(function() {
    'use strict';

    // --- Variáveis de Estado ---
    let searchInput = null;
    let searchButton = null;
    let suggestionsContainer = null;
    let currentSuggestionIndex = -1;
    let suggestions = [];
    let searchTimeout = null;
    let isLoading = false;
    let searchHistory = [];
    let placeholderInterval = null;

    // Acessa as traduções globais passadas pelo PHP
    const jsTranslations = window.FRONTEND_TRANSLATIONS || {};


    // --- Funções ---

    function setupElements() {
        searchInput = document.getElementById('searchInput');
        searchButton = document.querySelector('.search-button');

        if (!searchInput) {
            console.warn('BacoSearch: Campo de busca não encontrado');
            return;
        }

        createSuggestionsContainer();
        bindEvents();
        loadSearchHistory();
        // A animação do placeholder foi movida para o arquivo typewriter-effect.js
        // Se esse arquivo não existir, uma animação mais simples pode ser adicionada aqui.
    }

    function createSuggestionsContainer() {
        const existing = document.getElementById('search-suggestions');
        if (existing) existing.remove();

        suggestionsContainer = document.createElement('div');
        suggestionsContainer.id = 'search-suggestions';
        suggestionsContainer.className = 'search-suggestions';
        suggestionsContainer.style.display = 'none';

        searchInput.parentNode.insertBefore(suggestionsContainer, searchInput.nextSibling);
    }

    function bindEvents() {
        searchInput.addEventListener('input', handleInput);
        searchInput.addEventListener('keydown', handleKeydown);
        searchInput.addEventListener('focus', handleFocus);
        searchInput.addEventListener('blur', handleBlur);

        if (searchButton) {
            searchButton.addEventListener('click', handleSearch);
        }

        document.addEventListener('click', (e) => {
            if (searchInput && suggestionsContainer &&
                !searchInput.contains(e.target) &&
                !suggestionsContainer.contains(e.target)) {
                hideSuggestions();
            }
        });
    }

    function handleInput(e) {
        const term = e.target.value.trim();
        if (searchTimeout) clearTimeout(searchTimeout);

        if (term.length >= 2) {
            showLoadingState();
            searchTimeout = setTimeout(() => {
                fetchSuggestions(term);
            }, 300);
        } else {
            hideSuggestions();
        }
    }

    async function fetchSuggestions(term) {
        if (isLoading) return;
        isLoading = true;

        try {
            const baseUrl = window.appConfig?.site_url || '';
            const apiUrl = `${baseUrl}/search-suggestions-api.php?q=${encodeURIComponent(term)}`;
            const response = await fetch(apiUrl);

            if (!response.ok) {
                // Se a resposta da rede falhar, lança um erro para o bloco catch
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();

            if (data.suggestions && data.suggestions.length > 0) {
                suggestions = data.suggestions;
                renderSuggestions();
                showSuggestions();
            } else {
                // Se não houver sugestões, simplesmente esconde o container
                hideSuggestions();
            }
        } catch (error) {
            // =====================================================================
            // INÍCIO: AJUSTE DE GESTÃO DE ERRO
            // =====================================================================
            
            // Em vez de mostrar um erro para o utilizador, simplesmente escondemos a caixa
            // e registamos o erro no console do navegador para depuração.
            console.error('Falha ao buscar sugestões:', error);
            hideSuggestions();

            // =====================================================================
            // FIM: AJUSTE DE GESTÃO DE ERRO
            // =====================================================================
        } finally {
            isLoading = false;
        }
    }

    function renderSuggestions() {
        if (!suggestions.length) {
            hideSuggestions();
            return;
        }

        const html = suggestions.map((suggestion, index) => `
            <div class="suggestion-item" data-index="${index}" data-text="${escapeHtml(suggestion.text)}">
                <i class="${escapeHtml(getIconClass(suggestion.type))}"></i>
                <span class="suggestion-text">${highlightTerm(suggestion.text)}</span>
                <span class="suggestion-type">${escapeHtml(getTypeLabel(suggestion.type))}</span>
            </div>
        `).join('');

        suggestionsContainer.innerHTML = html;

        suggestionsContainer.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                selectSuggestion(parseInt(item.dataset.index));
            });
            item.addEventListener('mouseenter', () => {
                currentSuggestionIndex = parseInt(item.dataset.index);
                updateSuggestionHighlight();
            });
        });
    }

    function handleKeydown(e) {
        if (!suggestionsContainer || suggestionsContainer.style.display === 'none') {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
            return;
        }
        switch (e.key) {
            case 'ArrowDown': e.preventDefault(); navigateSuggestions(1); break;
            case 'ArrowUp': e.preventDefault(); navigateSuggestions(-1); break;
            case 'Enter':
                e.preventDefault();
                currentSuggestionIndex >= 0 ? selectSuggestion(currentSuggestionIndex) : performSearch();
                break;
            case 'Escape': hideSuggestions(); searchInput.blur(); break;
        }
    }
    
    // As outras funções (handleFocus, handleBlur, utilitárias, etc.) permanecem as mesmas
    function handleFocus() {
        if (window.stopTypewriter) window.stopTypewriter(); // Para a animação do placeholder
        if (searchInput.value === '') searchInput.placeholder = '';
        const term = searchInput.value.trim();
        if (term.length > 0 && suggestions.length > 0) {
            renderSuggestions();
            showSuggestions();
        }
    }

    function handleBlur() {
        setTimeout(() => {
            if (suggestionsContainer && !suggestionsContainer.contains(document.activeElement) && !searchInput.contains(document.activeElement)) {
                hideSuggestions();
            }
            if (searchInput.value === '') {
                if (window.startTypewriter) window.startTypewriter(); // Retoma a animação
            }
        }, 150);
    }
    
    function handleSearch(e) {
        e.preventDefault();
        performSearch();
    }

    function showLoadingState() {
        const loadingText = jsTranslations.loading_suggestions_text || 'Buscando...';
        suggestionsContainer.innerHTML = `<div class="suggestion-loading"><i class="fas fa-spinner fa-spin"></i><span>${escapeHtml(loadingText)}</span></div>`;
        showSuggestions();
    }

    function showSuggestions() {
        suggestionsContainer.style.display = 'block';
        currentSuggestionIndex = -1;
    }

    function hideSuggestions() {
        if (suggestionsContainer) {
            suggestionsContainer.style.display = 'none';
            suggestionsContainer.innerHTML = '';
        }
        currentSuggestionIndex = -1;
    }

    function navigateSuggestions(direction) {
        const maxIndex = suggestions.length - 1;
        currentSuggestionIndex += direction;
        if (currentSuggestionIndex > maxIndex) currentSuggestionIndex = 0;
        if (currentSuggestionIndex < 0) currentSuggestionIndex = maxIndex;
        updateSuggestionHighlight();
        updateInputValue();
    }

    function updateSuggestionHighlight() {
        suggestionsContainer.querySelectorAll('.suggestion-item').forEach((item, index) => {
            item.classList.toggle('highlighted', index === currentSuggestionIndex);
        });
    }

    function updateInputValue() {
        if (currentSuggestionIndex >= 0 && suggestions[currentSuggestionIndex]) {
            searchInput.value = suggestions[currentSuggestionIndex].text;
        }
    }

    function selectSuggestion(index) {
        if (index >= 0 && index < suggestions.length) {
            searchInput.value = suggestions[index].text;
            saveToHistory(suggestions[index].text);
            hideSuggestions();
            performSearch();
        }
    }

    function performSearch() {
        const term = searchInput.value.trim();
        if (term) {
            saveToHistory(term);
            window.location.href = `${window.appConfig?.site_url || ''}/search.php?term=${encodeURIComponent(term)}`;
        }
    }

    function saveToHistory(term) {
        try {
            let history = JSON.parse(localStorage.getItem('bacoSearchHistory') || '[]');
            history = history.filter(item => item.toLowerCase() !== term.toLowerCase());
            history.unshift(term);
            localStorage.setItem('bacoSearchHistory', JSON.stringify(history.slice(0, 10)));
        } catch (e) {}
    }

    function loadSearchHistory() {
        try {
            searchHistory = JSON.parse(localStorage.getItem('bacoSearchHistory') || '[]');
        } catch (e) {
            searchHistory = [];
        }
    }

    function highlightTerm(text) {
        const term = searchInput.value.trim();
        if (!term) return escapeHtml(text);
        const regex = new RegExp(`(${escapeRegex(term)})`, 'gi');
        return escapeHtml(text).replace(regex, '<strong>$1</strong>');
    }

    // --- Funções de Suporte para Rótulos e Ícones ---
    function getTypeLabel(type) {
        // Mapeamento de tipos para rótulos traduzíveis ou padrão
        const labels = {
            'service': jsTranslations.result_type_service || 'Serviço',
            'popular': jsTranslations.result_type_popular || 'Popular',
            'provider': jsTranslations.result_type_provider || 'Anunciante',
            'style': jsTranslations.result_type_style || 'Estilo',
            'roleplay_fantasy': jsTranslations.result_type_roleplay_fantasy || 'Fantasia',
            'video_production': jsTranslations.result_type_video_production || 'Produção',
            'audio_features': jsTranslations.result_type_audio_features || 'Áudio',
            'video_format': jsTranslations.result_type_video_format || 'Formato',
            'location': jsTranslations.result_type_location || 'Local',
            'character_type': jsTranslations.result_type_character_type || 'Personagem',
            'emotional_intent': jsTranslations.result_type_emotional_intent || 'Intenção',
            'additional': jsTranslations.result_type_additional || 'Adicional',
            'received': jsTranslations.result_type_received || 'Recebido',
            'lgbt': jsTranslations.result_type_lgbt || 'LGBT+',
            'massage': jsTranslations.result_type_massage || 'Massagem',
            'fetish': jsTranslations.result_type_fetish || 'Fetiche',
            'digital': jsTranslations.result_type_digital || 'Digital',
            // Adicione mais tipos conforme necessário
            'default': '' // Fallback para tipos desconhecidos
        };
        return labels[type] || labels['default'];
    }

    function getIconClass(type) {
        // Mapeamento de tipos para classes de ícones Font Awesome
        const icons = {
            'service': 'fas fa-hand-sparkles', // Ícone padrão para serviços
            'popular': 'fas fa-fire',
            'provider': 'fas fa-user',
            'style': 'fas fa-palette', // Ex: estilo artístico
            'roleplay_fantasy': 'fas fa-mask', // Ex: máscara de teatro
            'video_production': 'fas fa-video', // Ex: câmera de vídeo
            'audio_features': 'fas fa-volume-up', // Ex: alto-falante
            'video_format': 'fas fa-film', // Ex: rolo de filme
            'location': 'fas fa-map-marker-alt', // Ex: marcador de mapa
            'character_type': 'fas fa-user-circle', // Ex: perfil de usuário
            'emotional_intent': 'fas fa-heart', // Ex: coração
            'additional': 'fas fa-plus-circle',
            'received': 'fas fa-inbox', // Ex: caixa de entrada
            'lgbt': 'fas fa-rainbow', // Ex: arco-íris
            'massage': 'fas fa-spa', // Ex: flor de lótus
            'fetish': 'fas fa-handcuffs', // Ex: algemas (genérico para fetiche)
            'digital': 'fas fa-laptop', // Ex: laptop
            // Adicione mais ícones para outros tipos de categoria se o backend começar a retorná-los
            'default': 'fas fa-search' // Ícone padrão para qualquer outro tipo
        };
        return icons[type] || icons['default'];
    }

    function escapeRegex(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    function escapeHtml(text) {
        if (typeof text !== 'string') return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // --- Ponto de Entrada ---
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupElements);
    } else {
        setupElements();
    }

})();
