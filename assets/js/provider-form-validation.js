/**
 * /assets/js/provider-form-validation.js
 * Script de validação e interatividade para o formulário de registo/edição de anunciante.
 * Centraliza todo o JS inline dos módulos PHP.
 *
 * NOTA: Depende de 'window.escapeHtml' (de utils.js) e 'window.appConfig' (de head.php).
 *
 * ÚLTIMA ATUALIZAÇÃO: 04/07/2025
 * - Corrigido TypeError em validateCurrencies: Adicionada verificação de existência do elemento.
 * - REMOVIDA LÓGICA DE AUTOCOMPLETE INTEGRADA: Esta é agora responsabilidade exclusiva de map_autocomplete.js.
 * - Ajustes menores e otimizações.
 */
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('provider-registration-form');
    const registerForm = document.getElementById('register-form');    

    const activeForm = form || registerForm; // Determina qual formulário estamos usando

    if (!activeForm) {
        // Se nenhum dos formulários estiver nesta página, não faz mais nada.
        return;
    }

    // Certifica-se de que JS_TRANSLATIONS e SITE_URL_JS estão disponíveis
    const JS_TRANSLATIONS = window.appConfig.translations || {}; // Usar .translations conforme ajustado no head.php
    const SITE_URL_JS = window.appConfig.site_url || '';

    // Função auxiliar para mostrar erros de validação no frontend
    function displayFrontendErrors(errorsArray, formElement = activeForm) {
        // Usa o container específico para erros JS no formulário de provedor, ou um genérico se não existe.
        const errorContainerJs = formElement.querySelector('#error-container-js') || document.querySelector('.error-message');
        
        if (errorContainerJs) {
            const errorTitle = '<strong>' + window.escapeHtml(JS_TRANSLATIONS.form_errors_title || 'Por favor, corrija os seguintes erros:') + '</strong>';
            const errorList = '<ul>' + errorsArray.map(e => `<li>${window.escapeHtml(e)}</li>`).join('') + '</ul>';
            errorContainerJs.innerHTML = errorTitle + errorList;
            errorContainerJs.classList.add('show');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            console.error('Elemento para exibir erros JS (#error-container-js ou .error-message) não encontrado.');
            alert(errorsArray.join('\n')); // Fallback simples se o container não existe
        }
    }

    // --- MÓDULO: PROFILE (Palavras-Chave e Validação de Idiomas) ---
    function initProfileModule() {
        if (!form) return; // Esta função só se aplica ao formulário de provedor

        const keywordsContainer = form.querySelector('.keywords-container');
        if (keywordsContainer) {
            const addKeywordButton = keywordsContainer.querySelector('.add-keyword');
            const keywordLimitDisplay = form.querySelector('.keyword-limit');
            const maxKeywords = 30;
            const placeholderKeyword = JS_TRANSLATIONS.placeholder_keyword || 'Sua palavra-chave';
            const keywordLimitTextTemplate = JS_TRANSLATIONS.keyword_limit_text || 'Máximo 30 palavras-chave (Atual: %s)';

            const updateKeywordCount = () => {
                const count = keywordsContainer.querySelectorAll('.keyword-input').length;
                if (keywordLimitDisplay) {
                    keywordLimitDisplay.textContent = keywordLimitTextTemplate.replace('%s', count);
                }
                if (addKeywordButton) {
                    addKeywordButton.disabled = count >= maxKeywords;
                }
            };

            if (addKeywordButton) {
                addKeywordButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (keywordsContainer.querySelectorAll('.keyword-input').length < maxKeywords) {
                        const newInput = document.createElement('input');
                        newInput.type = 'text';
                        newInput.name = 'keywords[]';
                        newInput.className = 'keyword-input form-control';
                        newInput.maxLength = 30;
                        newInput.placeholder = window.escapeHtml(placeholderKeyword);
                        keywordsContainer.insertBefore(newInput, addKeywordButton);
                        newInput.addEventListener('input', updateKeywordCount);
                        updateKeywordCount();
                        newInput.focus();
                    }
                });
            }
            keywordsContainer.querySelectorAll('.keyword-input').forEach(input => {
                input.addEventListener('input', updateKeywordCount);
            });
            updateKeywordCount();
        }
        // Idade: Campo readonly, calculado no PHP, sem interatividade JS aqui.
    }

    // --- MÓDULO: BODY (Características Físicas) ---
    function initBodyModule() {
        if (!form) return;
        // Nada de JS interativo complexo aqui, validações primariamente no backend.
    }

    // --- MÓDULO: SERVICES (Aviso Legal Condicional para Sexo Oral Sem Preservativo) ---
    function initServicesModule() {
        if (!form) return;

        const oralNoCondomRadios = form.querySelectorAll('.oral-no-condom-radio');
        const warningDiv = form.querySelector('#oral-no-condom-warning');
        if (oralNoCondomRadios.length > 0 && warningDiv) {
            const updateWarningVisibility = () => {
                const selectedValue = form.querySelector('input[name="oral_sex_no_condom"]:checked')?.value;
                warningDiv.style.display = (selectedValue === 'do' || selectedValue === 'negotiable') ? 'block' : 'none';
            };
            oralNoCondomRadios.forEach(radio => radio.addEventListener('change', updateWarningVisibility));
            updateWarningVisibility(); // Chamada inicial
        }
    }

    // --- MÓDULO: VALUES (Validação de Moedas Aceitas) ---
    function initValuesModule() {
        if (!form) return;

        const currencySelectors = form.querySelectorAll('.currency-selector');
        // AQUI ESTÁ A CORREÇÃO DO ERRO 'Cannot read properties of null (reading 'style')'
        const currencyValidationFeedback = document.getElementById('currency-validation-feedback'); // Tente obter de forma mais direta para evitar null
        // Adicionando uma verificação para garantir que o elemento existe
        if (!currencyValidationFeedback) {
            console.warn("Elemento '#currency-validation-feedback' não encontrado. Validação de moeda pode não exibir feedback visual.");
            // Não retorna, pois a validação ainda pode ser feita, apenas o feedback visual será ausente.
        }

        function validateCurrencies() {
            const selectedCurrencies = [];
            let realSelectedInFirst = false;
            let hasDuplicateReal = false;

            currencySelectors.forEach((select, index) => {
                const selectedValue = select.value;
                if (selectedValue) {
                    selectedCurrencies.push(selectedValue);
                    if (selectedValue === 'Real' && index === 0) {
                        realSelectedInFirst = true;
                    }
                }
            });
            
            // Check for duplicate 'Real' if selected in the first dropdown
            if (realSelectedInFirst) {
                const realCount = selectedCurrencies.filter(currency => currency === 'Real').length;
                if (realCount > 1) {
                    hasDuplicateReal = true;
                }
            }
            
            if (currencyValidationFeedback) { // Só manipula o estilo se o elemento existe
                if (hasDuplicateReal) {
                    currencyValidationFeedback.style.display = 'block';
                    currencyValidationFeedback.textContent = window.escapeHtml(JS_TRANSLATIONS.currency_real_duplicate_error || 'Se "Real" for selecionado na primeira opção, não pode ser repetido.');
                    return false;
                } else {
                    currencyValidationFeedback.style.display = 'none';
                }
            }
            return true;
        }

        currencySelectors.forEach(select => {
            select.addEventListener('change', validateCurrencies);
        });
        validateCurrencies(); // Initial validation
    }

    // --- MÓDULO: MEDIA (Pré-visualização e Gestão de Uploads) ---
    function initMediaModule() {
        if (!form) return;

        const mainPhotoInput = document.getElementById('main_photo');
        const mainPhotoPreview = document.getElementById('main-photo-preview');
        const galleryPhotosInput = document.getElementById('gallery_photos');
        const galleryPhotosContainer = document.getElementById('gallery-photos-container');
        const videosInput = document.getElementById('videos');
        const videosPreviewContainer = document.getElementById('videos-preview');

        const MAX_PHOTO_SIZE = 5 * 1024 * 1024; // 5MB
        const ALLOWED_PHOTO_TYPES = ['image/jpeg', 'image/png'];
        const MAX_GALLERY_PHOTOS = 20;

        const MAX_VIDEO_LIMIT = 12; // Definido aqui.
        const MAX_VIDEO_SIZE = 100 * 1024 * 1024; // 100MB
        const MAX_VIDEO_DURATION_SECONDS = 30; // 30 segundos
        const ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm'];

        const alertMaxPhotos = JS_TRANSLATIONS.alert_max_photos || 'Você pode enviar até %s fotos.';
        const alertPhotoFormatSize = JS_TRANSLATIONS.alert_photo_format_size || 'Por favor, selecione imagens JPEG/PNG de até 5MB.';
        const alertMaxVideos = JS_TRANSLATIONS.alert_max_videos || 'Você pode enviar até %s vídeos.';
        const alertVideoFormatSize = JS_TRANSLATIONS.alert_video_format_size || 'Por favor, selecione vídeos MP4/WebM de até 100MB.';
        const alertVideoDuration = JS_TRANSLATIONS.alert_video_duration || 'Os vídeos devem ter no máximo 30 segundos.';

        // Foto Principal
        if (mainPhotoInput && mainPhotoPreview) {
            mainPhotoInput.addEventListener('change', function() {
                mainPhotoPreview.innerHTML = '';
                const file = this.files[0];
                if (!file) {
                    const currentPhotoUrl = mainPhotoPreview.dataset.currentUrl;
                    if (currentPhotoUrl) {
                        mainPhotoPreview.innerHTML = `<img src="${window.escapeHtml(currentPhotoUrl)}" alt="${window.escapeHtml(JS_TRANSLATIONS.alt_main_photo_current || 'Foto Principal Atual')}">`;
                    }
                    return;
                }

                if (file.size > MAX_PHOTO_SIZE || !ALLOWED_PHOTO_TYPES.includes(file.type)) {
                    alert(window.escapeHtml(alertPhotoFormatSize));
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = e => mainPhotoPreview.innerHTML = `<img src="${window.escapeHtml(e.target.result)}" alt="${window.escapeHtml(JS_TRANSLATIONS.preview_text || 'Pré-visualização')}">`; // Adicione preview_text à sua tradução
                reader.readAsDataURL(file);
            });
        }

        // Galeria de Fotos
        if (galleryPhotosInput && galleryPhotosContainer) {
            galleryPhotosContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-photo')) {
                    const photoItem = e.target.closest('.photo-item');
                    if (photoItem) {
                        const photoPath = photoItem.dataset.path;
                        if (photoPath) {
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'removed_gallery_photos[]';
                            hiddenInput.value = photoPath;
                            form.appendChild(hiddenInput);
                        }
                        photoItem.remove();
                    }
                }
            });

            galleryPhotosInput.addEventListener('change', function() {
                const newFiles = Array.from(this.files);
                const currentUploadedCount = galleryPhotosContainer.querySelectorAll('.photo-item').length;
                
                if (currentUploadedCount + newFiles.length > MAX_GALLERY_PHOTOS) {
                    alert(window.escapeHtml(alertMaxPhotos.replace('%s', MAX_GALLERY_PHOTOS)));
                    this.value = '';
                    return;
                }

                newFiles.forEach(file => {
                    if (file.size > MAX_PHOTO_SIZE || !ALLOWED_PHOTO_TYPES.includes(file.type)) {
                        alert(window.escapeHtml(`${file.name} ` + alertPhotoFormatSize));
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'photo-item';
                        div.innerHTML = `<img src="${window.escapeHtml(e.target.result)}" alt="${window.escapeHtml(JS_TRANSLATIONS.new_photo_text || 'Nova foto')}"><span class="remove-photo">&times;</span>`;
                        galleryPhotosContainer.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
                this.value = '';
            });
        }

        // Vídeos
        if (videosInput && videosPreviewContainer) {
            videosPreviewContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-video')) {
                    const videoItem = e.target.closest('.video-item');
                    if (videoItem) {
                        const videoPath = videoItem.dataset.path;
                        if (videoPath) {
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'removed_videos[]';
                            hiddenInput.value = videoPath;
                            form.appendChild(hiddenInput);
                        }
                        videoItem.remove();
                    }
                }
            });

            videosInput.addEventListener('change', function() {
                const newFiles = Array.from(this.files);
                const currentUploadedCount = videosPreviewContainer.querySelectorAll('.video-item').length;

                if (currentUploadedCount + newFiles.length > MAX_VIDEO_LIMIT) { // Usando a constante definida
                    alert(window.escapeHtml(alertMaxVideos.replace('%s', MAX_VIDEO_LIMIT))); // Usando a constante
                    this.value = '';
                    return;
                }

                newFiles.forEach(file => {
                    if (file.size > MAX_VIDEO_SIZE || !ALLOWED_VIDEO_TYPES.includes(file.type)) {
                        alert(window.escapeHtml(`${file.name} ` + alertVideoFormatSize));
                        return;
                    }
                    const video = document.createElement('video');
                    video.preload = 'metadata';
                    video.onloadedmetadata = function() {
                        window.URL.revokeObjectURL(video.src);
                        if (video.duration > MAX_VIDEO_DURATION_SECONDS) {
                            alert(window.escapeHtml(`${file.name} ` + alertVideoDuration));
                            videosInput.value = '';
                            return;
                        }
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'video-item';
                            div.innerHTML = `<video src="${window.escapeHtml(e.target.result)}" controls></video><span class="remove-video">&times;</span>`;
                            videosPreviewContainer.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    };
                    video.onerror = function() {
                        alert(window.escapeHtml(JS_TRANSLATIONS.video_load_error || `Não foi possível carregar o vídeo ${file.name} para verificar a duração.`));
                        videosInput.value = '';
                    };
                    video.src = URL.createObjectURL(file);
                });
                this.value = '';
            });
        }
    }


    // --- MÓDULO: CONTACT & REGISTER (Atualização da Bandeira do Telefone para AMBOS os formulários) ---
    function initPhoneFlagModule() {
        const registerPhoneCodeSelect = document.getElementById('phone_code');
        const providerPhoneCodeSelect = document.getElementById('advertised_phone_code');

        function updateFlagForSelect(selectElement) {
            if (!selectElement) return;
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            if (selectedOption) {
                const flagUrl = selectedOption.getAttribute('data-flag');
                if (flagUrl) {
                    selectElement.style.backgroundImage = `url('${window.escapeHtml(flagUrl)}')`;
                    selectElement.style.backgroundRepeat = `no-repeat`;
                    selectElement.style.backgroundPosition = `8px center`;
                    selectElement.style.paddingLeft = `35px`;
                } else {
                    selectElement.style.backgroundImage = 'none';
                    selectElement.style.paddingLeft = '12px';
                }
            }
        }

        if (registerPhoneCodeSelect) {
            updateFlagForSelect(registerPhoneCodeSelect);
            registerPhoneCodeSelect.addEventListener('change', function() { updateFlagForSelect(this); });
        }
        if (providerPhoneCodeSelect) {
            updateFlagForSelect(providerPhoneCodeSelect);
            providerPhoneCodeSelect.addEventListener('change', function() { updateFlagForSelect(this); });
        }
    }

    // --- MÓDULO: LOGISTICS (Campos Condicionais e Lógica do Raio/Amenities) ---
    // A lógica de Autocomplete de Cidade está agora EXCLUSIVAMENTE em map_autocomplete.js
    function initLogisticsModule() {
        if (!form) return;

        const servesNearbyRadios = form.querySelectorAll('.serves-nearby');
        const radiusSection = form.querySelector('.radius-section');
        const serviceLocationsCheckboxes = form.querySelectorAll('.service-location');
        const amenitiesSection = form.querySelector('.amenities-section');

        // Lógica de mostrar/esconder o raio de atendimento
        if (servesNearbyRadios.length > 0 && radiusSection) {
            const nearbyCitiesRadiusInput = radiusSection.querySelector('#nearby_cities_radius');
            const updateRadiusVisibility = () => {
                const selectedValue = form.querySelector('input[name="serves_nearby_cities"]:checked')?.value;
                const isVisible = selectedValue === '1';
                radiusSection.classList.toggle('show', isVisible);
                if (nearbyCitiesRadiusInput) {
                    nearbyCitiesRadiusInput.required = isVisible;
                    if (!isVisible) {
                        nearbyCitiesRadiusInput.value = '';
                    }
                }
            };
            servesNearbyRadios.forEach(radio => radio.addEventListener('change', updateRadiusVisibility));
            updateRadiusVisibility();
        }

        // Lógica de mostrar/esconder a seção de comodidades
        if (serviceLocationsCheckboxes.length > 0 && amenitiesSection) {
            const updateAmenitiesVisibility = () => {
                const selectedLocations = Array.from(serviceLocationsCheckboxes)
                                                .filter(input => input.checked)
                                                .map(input => input.value);
                amenitiesSection.classList.toggle('show', selectedLocations.includes('own_place') || selectedLocations.includes('club'));
            };
            serviceLocationsCheckboxes.forEach(checkbox => checkbox.addEventListener('change', updateAmenitiesVisibility));
            updateAmenitiesVisibility();
        }

        // REMOVIDA A LÓGICA DE AUTOCOMPLETE DE CIDADE ANTIGA AQUI.
        // O autocompletar agora é totalmente gerido por `map_autocomplete.js`.
    }

    // --- MÓDULO: SECURITY (Consents e Checkboxes) ---
    function initSecurityModule() {
        if (!form) return;
        // Nada de JS interativo complexo aqui, validações primariamente no backend.
    }

    // --- ORQUESTRADOR DE INICIALIZAÇÃO DOS MÓDULOS ---
    if (form) { // Para o formulário de provedor
        initProfileModule();
        initBodyModule();
        initServicesModule();
        initValuesModule();
        initMediaModule();
        initLogisticsModule();
        initSecurityModule();
    }
    initPhoneFlagModule(); // O módulo de bandeira de telefone é chamado independentemente do tipo de formulário

    // VALIDAÇÃO GERAL NO ENVIO DO FORMULÁRIO (FRONTEND)
    activeForm.addEventListener('submit', function(event) {
        let errors = [];
        const currentForm = this;

        // Limpa erros visuais e mensagens de erro anteriores
        currentForm.querySelectorAll('.form-control.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        currentForm.querySelectorAll('.invalid-feedback').forEach(el => el.style.display = 'none');
        
        const errorContainerJs = currentForm.querySelector('#error-container-js');
        if (errorContainerJs) {
            errorContainerJs.innerHTML = '';
            errorContainerJs.classList.remove('show');
        }

        // --- Validações para campos do formulário de REGISTRO INICIAL (register.php) ---
        if (currentForm.id === 'register-form') {
            const realName = document.getElementById('real_name');
            if (realName && (!realName.value.trim() || realName.value.length > 150)) {
                errors.push(JS_TRANSLATIONS.full_name_error || "O Nome Completo é obrigatório e deve ter no máximo 150 caracteres.");
                realName.classList.add('is-invalid');
            }

            const birthDate = document.getElementById('birth_date');
            if (birthDate && !birthDate.value) {
                errors.push(JS_TRANSLATIONS.birth_date_error || "Data de nascimento é obrigatória.");
                birthDate.classList.add('is-invalid');
            } else if (birthDate) {
                const birth = new Date(birthDate.value);
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                const monthDiff = today.getMonth() - birth.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                    age--;
                }
                if (age < 18 || birth > today) {
                    errors.push(JS_TRANSLATIONS.age_min_error || "Você deve ter pelo menos 18 anos e a data não pode ser no futuro.");
                    birthDate.classList.add('is-invalid');
                }
            }

            const email = document.getElementById('email');
            if (email && (!email.value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value))) {
                errors.push(JS_TRANSLATIONS.email_error || "Por favor, insira um email válido.");
                email.classList.add('is-invalid');
            }

            const phoneNumber = document.getElementById('phone_number');
            if (phoneNumber && (!phoneNumber.value.trim() || !/^\d{7,15}$/.test(phoneNumber.value))) {
                errors.push(JS_TRANSLATIONS.phone_error || "Por favor, insira um número de telefone válido (7 a 15 dígitos).");
                phoneNumber.classList.add('is-invalid');
            }

            const password = document.getElementById('password');
            if (password && (!password.value || password.value.length < 8)) {
                errors.push(JS_TRANSLATIONS.password_error || "A senha deve ter pelo menos 8 caracteres.");
                password.classList.add('is-invalid');
            }

            const repeatPassword = document.getElementById('repeat_password');
            if (repeatPassword && (repeatPassword.value !== password.value)) {
                errors.push(JS_TRANSLATIONS.repeat_password_error || "As senhas não coincidem.");
                repeatPassword.classList.add('is-invalid');
            }

            const accountType = document.getElementById('account_type');
            if (accountType && !accountType.value) {
                errors.push(JS_TRANSLATIONS.account_type_error || "Por favor, selecione um tipo de conta válido.");
                accountType.classList.add('is-invalid');
            }
        }


        // --- Validações para campos do formulário de PROVEDOR (register_providers.php) ---
        if (currentForm.id === 'provider-registration-form') {
            // Validação de Perfil (Profile.php)
            const artisticName = document.getElementById('artistic_name');
            if (artisticName && (!artisticName.value.trim() || artisticName.value.length > 50)) {
                errors.push(JS_TRANSLATIONS.artistic_name_error || "O Nome Artístico é obrigatório e deve ter no máximo 50 caracteres.");
                artisticName.classList.add('is-invalid');
            }
            const description = document.getElementById('description');
            if (description && (description.value.length < 250 || description.value.length > 500)) { // CORREÇÃO: min 250, max 500
                errors.push(JS_TRANSLATIONS.description_error || "A descrição deve ter entre 250 e 500 caracteres.");
                description.classList.add('is-invalid');
            }
            // Validações de select obrigatórios no módulo profile
            ['gender', 'nationality_id'].forEach(id => {
                const selectEl = document.getElementById(id);
                if (selectEl && !selectEl.value) {
                    const errorKey = `${id}_error`;
                    errors.push(JS_TRANSLATIONS[errorKey] || `Selecione um(a) ${id.replace('_id', '')} válido(a).`);
                    selectEl.classList.add('is-invalid');
                }
            });
            // Validação para idiomas falados (languages)
            const languagesCheckboxes = form.querySelectorAll('input[name="languages[]"]:checked');
            if (languagesCheckboxes.length === 0) {
                errors.push(JS_TRANSLATIONS.spoken_languages_error || "Selecione pelo menos um idioma.");
                form.querySelector('.language-columns')?.classList.add('is-invalid');
            }

            // Validação de Mídia (Media.php) - Limites de fotos/vídeos
            const galleryPhotosContainer = document.getElementById('gallery-photos-container');
            const videosPreviewContainer = document.getElementById('videos-preview');
            // Nota: Os inputs type="file" já têm validação de tamanho/tipo no change event
            // e podem limpar o próprio input. Aqui é para limite de quantidade.
            if (galleryPhotosContainer && galleryPhotosContainer.querySelectorAll('.photo-item').length > 20) {
                errors.push(JS_TRANSLATIONS.gallery_photos_limit_exceeded || `Máximo de 20 fotos na galeria.`);
                // Marcar o input de file como inválido
                document.getElementById('gallery_photos')?.classList.add('is-invalid');
            }
            if (videosPreviewContainer && videosPreviewContainer.querySelectorAll('.video-item').length > MAX_VIDEO_LIMIT) { // Usando a constante
                errors.push(JS_TRANSLATIONS.videos_limit_exceeded || `Máximo de ${MAX_VIDEO_LIMIT} vídeos.`); // Usando a constante
                document.getElementById('videos')?.classList.add('is-invalid');
            }
            const onlyfansUrl = document.getElementById('onlyfans_url');
            if (onlyfansUrl && onlyfansUrl.value && !/^(https?:\/\/(?:www\.)?onlyfans\.com\/[a-zA-Z0-9_-]+(?:\/?)(?:\?.*)?(?:#.*)?)$/i.test(onlyfansUrl.value)) {
                errors.push(JS_TRANSLATIONS.onlyfans_url_invalid_error || "URL do OnlyFans inválida.");
                onlyfansUrl.classList.add('is-invalid');
            }


            // Validação de Contato (Contact.php)
            const advertisedPhoneNumber = document.getElementById('advertised_phone_number');
            const advertisedPhoneCode = document.getElementById('advertised_phone_code'); // Adicionado
            if (advertisedPhoneCode && !advertisedPhoneCode.value) { // Valida o DDI
                errors.push(JS_TRANSLATIONS.advertised_phone_code_missing_error || "Selecione o código de telefone para o anúncio.");
                advertisedPhoneCode.classList.add('is-invalid');
            }
            if (advertisedPhoneNumber && (!advertisedPhoneNumber.value.trim() || !/^\d{7,15}$/.test(advertisedPhoneNumber.value))) {
                errors.push(JS_TRANSLATIONS.advertised_phone_number_invalid_error || "Informe um número de telefone válido para o anúncio (7 a 15 dígitos).");
                advertisedPhoneNumber.classList.add('is-invalid');
            }

            // Validação de Logística (Logistics.php)
            const adCityPlaceId = document.getElementById('ad_city_place_id');
            // O ID do input de autocomplete é `address-autocomplete-input`, não `ad_city_autocomplete`
            const addressAutocompleteInput = document.getElementById('address-autocomplete-input'); 

            if (adCityPlaceId && !adCityPlaceId.value) {
                errors.push(JS_TRANSLATIONS.ad_city_place_id_required_error || "A cidade do anúncio é obrigatória. Por favor, utilize a função de autocompletar cidade e selecione uma opção válida.");
                // Marca o campo de autocomplete visual como inválido
                addressAutocompleteInput?.classList.add('is-invalid'); 
            }
            const servesNearbyYes = form.querySelector('input[name="serves_nearby_cities"][value="1"]');
            const nearbyCitiesRadiusInput = document.getElementById('nearby_cities_radius');
            if (servesNearbyYes && servesNearbyYes.checked && nearbyCitiesRadiusInput && (!nearbyCitiesRadiusInput.value || parseFloat(nearbyCitiesRadiusInput.value) < 1 || parseFloat(nearbyCitiesRadiusInput.value) > 500)) {
                errors.push(JS_TRANSLATIONS.nearby_cities_radius_range_error || "O raio de atendimento deve ser um número entre 1 e 500 km.");
                nearbyCitiesRadiusInput.classList.add('is-invalid');
            }
            const serviceLocationsCheckboxes = form.querySelectorAll('input[name="service_locations[]"]:checked'); // Seletores mais precisos
            if (serviceLocationsCheckboxes.length === 0) {
                errors.push(JS_TRANSLATIONS.service_locations_required_error || "Selecione pelo menos um local de atendimento.");
                form.querySelector('.form-group .checkbox-columns')?.classList.add('is-invalid'); // Marca o grupo de checkboxes
            }
            // Validação de comodidades (amenities)
            const amenitiesCheckboxes = form.querySelectorAll('input[name="amenities[]"]:checked');
            // Se as comodidades não são obrigatórias, esta validação pode ser mais branda.
            // Se forem obrigatórias, adicione:
            // if (amenitiesCheckboxes.length === 0 && form.querySelector('.amenities-section.show')) { // Apenas se a seção está visível
            //    errors.push(JS_TRANSLATIONS.amenities_required_error || "Selecione pelo menos uma comodidade disponível.");
            //    form.querySelector('.amenities-section .checkbox-columns')?.classList.add('is-invalid');
            // }


            // Validação de Segurança (Security.php)
            const acceptTerms = document.getElementById('accept_terms');
            if (acceptTerms && !acceptTerms.checked) {
                errors.push(JS_TRANSLATIONS.accept_terms_required_error || "Você deve aceitar os Termos e Condições.");
                acceptTerms.classList.add('is-invalid');
            }
            const gdprConsent = document.getElementById('gdpr_consent');
            if (gdprConsent && !gdprConsent.checked) {
                errors.push(JS_TRANSLATIONS.gdpr_consent_required_error || "Você deve consentir com o tratamento de dados pessoais (GDPR).");
                gdprConsent.classList.add('is-invalid');
            }
            const legalDeclaration = document.getElementById('legal_declaration');
            if (legalDeclaration && !legalDeclaration.checked) {
                errors.push(JS_TRANSLATIONS.legal_declaration_required_error || "Você deve declarar ser maior de idade e que suas atividades são legais.");
                legalDeclaration.classList.add('is-invalid');
            }
            const allowReviewsRadios = form.querySelectorAll('input[name="allow_reviews"]');
            const isAllowReviewsSelected = Array.from(allowReviewsRadios).some(radio => radio.checked);
            if (!isAllowReviewsSelected) {
                errors.push(JS_TRANSLATIONS.allow_reviews_selection_error || "Selecione se permite avaliações.");
                allowReviewsRadios[0]?.closest('.form-group')?.classList.add('is-invalid');
            }

            // Validação de Preços (Values.php)
            const baseHourlyRate = document.getElementById('base_hourly_rate');
            if (baseHourlyRate && (!baseHourlyRate.value || parseFloat(baseHourlyRate.value) <= 0)) {
                errors.push(JS_TRANSLATIONS.base_hourly_rate_required_error || "Preço de 1h é obrigatório e deve ser um número positivo.");
                baseHourlyRate.classList.add('is-invalid');
            }

            const optionalPriceFields = ['price_15_min', 'price_30_min', 'price_2_hr', 'price_overnight']; // 'price_overnight' se aplicável
            optionalPriceFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && field.value && parseFloat(field.value) < 0) {
                    errors.push(JS_TRANSLATIONS.optional_price_invalid_error || `Preço inválido para ${fieldId}.`);
                    field.classList.add('is-invalid');
                }
            });

            // Validação de Moedas Aceitas (Values.php) - Re-chamada da função para garantir que a validação mais recente seja refletida
            const areCurrenciesValid = validateCurrencies(); // Chama a função de validação de moedas
            if (!areCurrenciesValid) {
                // Se validateCurrencies já adicionou um erro específico, apenas adicione um erro genérico para parar o submit.
                errors.push(JS_TRANSLATIONS.currency_validation_general_error || "Verifique a seleção de moedas.");
                // Note: os campos específicos já são marcados como is-invalid dentro de validateCurrencies
            }

        }


        // Se houver erros, impede o envio e mostra a lista
        if (errors.length > 0) {
            event.preventDefault();
            event.stopPropagation(); // Impede a propagação do evento, para que o submit não vá para o backend
            displayFrontendErrors(errors, currentForm);
        }
    });
});