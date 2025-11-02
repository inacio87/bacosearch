document.addEventListener('DOMContentLoaded', () => {
    console.log("DOM Carregado. Iniciando search-providers.js...");

    const resultsGrid = document.getElementById('results-grid');
    const breadcrumbContainer = document.getElementById('location-breadcrumb');
    if (!resultsGrid || !breadcrumbContainer) {
        console.error('ERRO CRÍTICO: #results-grid ou #location-breadcrumb não encontrado.');
        return;
    }

    const appConfig = window.appConfig || {};
    const locationData = appConfig.location || {};
    console.log("Localização inicial carregada:", locationData);

    const state = {
        filters: {
            planet: 'Terra',
            category: 'provider', // Padrão ativo
            gender: 'female',    // Padrão ativo
            lat: locationData.lat || null,
            lon: locationData.lon || null,
            country: locationData.country_name || locationData.country_code || '',
            region: locationData.region || '',
            city: locationData.city || '',
            price_max: 3000,
            distance: 100
        },
        elements: {
            grid: resultsGrid,
            breadcrumbContainer: breadcrumbContainer,
            spinner: document.getElementById('loading-spinner'),
            filterButtons: document.querySelectorAll('.filter-btn'),
            priceRange: document.getElementById('price-range'),
            distanceRange: document.getElementById('distance-range'),
            applyAdvFiltersBtn: document.getElementById('apply-advanced-filters-btn'),
            locationModal: document.getElementById('location-modal'),
            locationModalList: document.getElementById('location-modal-list'),
            closeLocationModalBtn: document.getElementById('close-location-modal')
        },
        apiEndpoint: (appConfig.site_url || '') + '/api/filter-providers.php',
        isFetching: false,
    };

    const updateBreadcrumbDisplay = () => {
        const planetText = state.filters.planet || 'Terra';
        const countryText = state.filters.country || 'País';
        const regionText = state.filters.region || 'Região';
        const cityText = state.filters.city || 'Cidade';

        breadcrumbContainer.innerHTML = `
            <span class="breadcrumb-link" data-level="planet">${planetText}</span>
            <span class="breadcrumb-separator">&gt;</span>
            <span class="breadcrumb-link" data-level="country" style="cursor: pointer;">${countryText}</span>
            <span class="breadcrumb-separator">&gt;</span>
            <span class="breadcrumb-link" data-level="region" style="cursor: pointer;">${regionText}</span>
            <span class="breadcrumb-separator">&gt;</span>
            <span class="breadcrumb-link" data-level="city" style="cursor: pointer;">${cityText}</span>`;

        // Adicionar evento para mostrar lista de países
        const countryLink = breadcrumbContainer.querySelector('[data-level="country"]');
        if (countryLink) {
            countryLink.addEventListener('click', () => {
                fetchCountriesWithProviders();
            });
        }
    };

    const fetchCountriesWithProviders = async () => {
        try {
            const response = await fetch(`${state.apiEndpoint}?action=countries`);
            const result = await response.json();
            if (result.success && result.countries) {
                state.elements.locationModalList.innerHTML = result.countries.map(c => `<div class="modal-item" data-country="${c}">${c}</div>`).join('');
                state.elements.locationModal.classList.add('show');
                document.querySelectorAll('.modal-item').forEach(item => {
                    item.addEventListener('click', () => {
                        state.filters.country = item.dataset.country;
                        state.filters.region = '';
                        state.filters.city = '';
                        fetchProviders();
                        state.elements.locationModal.classList.remove('show');
                    });
                });
            }
        } catch (error) {
            console.error('Erro ao carregar países:', error);
        }
    };

    const fetchProviders = async () => {
        if (state.isFetching) return;
        state.isFetching = true;
        if (state.elements.spinner) state.elements.spinner.style.display = 'flex';
        state.elements.grid.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i></div>';

        const params = new URLSearchParams({
            lat: state.filters.lat || '',
            lon: state.filters.lon || '',
            city: state.filters.city || '',
            region: state.filters.region || '',
            country: state.filters.country || '',
            gender: state.filters.gender || '',
            category: state.filters.category || '',
            price_max: state.filters.price_max || 3000,
            distance: state.filters.distance || 100,
            page: 1,
            perPage: 10
        });
        console.log("Buscando providers com os parâmetros:", params.toString());

        try {
            const response = await fetch(`${state.apiEndpoint}?${params}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            console.log("API retornou:", result);

            if (result.success) {
                if (result.searchContext) {
                    state.filters.country = result.searchContext.country || state.filters.country;
                    state.filters.region = result.searchContext.region || state.filters.region;
                    state.filters.city = result.searchContext.city || state.filters.city;
                }
                state.elements.grid.innerHTML = result.providers.map(p => `
                    <a href="${appConfig.site_url}/${p.slug || `providers.php?id=${p.id}`}" class="profile-card">
                        <div class="card-photo-container">
                            <img src="${appConfig.site_url}${p.main_photo_url || '/assets/images/placeholder.jpg'}" alt="${p.display_name || 'Perfil'}" class="photo" loading="lazy" onerror="this.onerror=null; this.src='${appConfig.site_url}/assets/images/placeholder.jpg';">
                            <div class="card-overlay-bottom">
                                <h3 class="name">${p.display_name || 'Sem nome'}</h3>
                                <p class="details"><i class="fas fa-map-marker-alt"></i> ${p.ad_city || 'N/A'} (${p.distance || 'N/A'} km)</p>
                            </div>
                        </div>
                    </a>
                `).join('');
                updateBreadcrumbDisplay();
                if (result.totalPages > 1) {
                    let pagination = `<div class="pagination">`;
                    for (let i = 1; i <= result.totalPages; i++) {
                        pagination += `<a href="?page=${i}" class="${i === result.page ? 'active' : ''}">${i}</a>`;
                    }
                    pagination += `</div>`;
                    state.elements.grid.insertAdjacentHTML('beforeend', pagination);
                }
            } else {
                state.elements.grid.innerHTML = `<p class="no-results-message">${appConfig.translations.no_profiles_found || 'Nenhum perfil encontrado'}</p>`;
            }
        } catch (error) {
            console.error('Fetch Error:', error);
            state.elements.grid.innerHTML = `<p class="error-message">Ocorreu um erro ao carregar os perfis.</p>`;
        } finally {
            if (state.elements.spinner) state.elements.spinner.style.display = 'none';
            state.isFetching = false;
        }
    };

    const init = () => {
        console.log("Função init() chamada.");
        updateBreadcrumbDisplay();
        fetchProviders();

        if (state.elements.filterButtons) {
            state.elements.filterButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const filterType = btn.dataset.filter;
                    const filterValue = btn.dataset.value;
                    const isActive = btn.classList.contains('active');

                    document.querySelectorAll(`[data-filter="${filterType}"]`).forEach(b => {
                        b.classList.remove('active');
                        b.style.backgroundColor = ''; // Resetar fundo
                    });

                    if (!isActive) {
                        btn.classList.add('active');
                        btn.style.backgroundColor = '#e0e0e0'; // Fundo diferente para ativo
                        state.filters[filterType] = filterValue;
                    } else {
                        state.filters[filterType] = '';
                    }
                    fetchProviders();
                });
            });
        }

        if (state.elements.priceRange) {
            state.elements.priceRange.addEventListener('input', (e) => {
                state.filters.price_max = e.target.value;
                document.getElementById('price-value').textContent = `${e.target.value} €`;
            });
            state.elements.priceRange.addEventListener('change', fetchProviders);
        }

        if (state.elements.distanceRange) {
            state.elements.distanceRange.addEventListener('input', (e) => {
                state.filters.distance = e.target.value;
                document.getElementById('distance-value').textContent = `${e.target.value} km`;
            });
            state.elements.distanceRange.addEventListener('change', fetchProviders);
        }

        if (state.elements.applyAdvFiltersBtn) {
            state.elements.applyAdvFiltersBtn.addEventListener('click', () => {
                fetchProviders();
                document.getElementById('advanced-filter-modal').classList.remove('show');
            });
        }

        if (state.elements.closeLocationModalBtn) {
            state.elements.closeLocationModalBtn.addEventListener('click', () => {
                state.elements.locationModal.classList.remove('show');
            });
        }
    };

    init();
    window.addEventListener('locationUpdated', (event) => {
        console.log('Localização atualizada via evento:', event.detail);
        state.filters.lat = event.detail.lat || state.filters.lat;
        state.filters.lon = event.detail.lon || state.filters.lon;
        state.filters.country = event.detail.country_name || state.filters.country;
        state.filters.region = event.detail.region || state.filters.region;
        state.filters.city = event.detail.city || state.filters.city;
        updateBreadcrumbDisplay();
        fetchProviders();
    });
});

// Função placeholder para fetchCountriesWithProviders (a ser implementada em filter-providers.php)
async function fetchCountriesWithProviders() {
    try {
        const response = await fetch(`${state.apiEndpoint}?action=countries`);
        const result = await response.json();
        if (result.success && result.countries) {
            state.elements.locationModalList.innerHTML = result.countries.map(c => `<div class="modal-item" data-country="${c}">${c}</div>`).join('');
            state.elements.locationModal.classList.add('show');
            document.querySelectorAll('.modal-item').forEach(item => {
                item.addEventListener('click', () => {
                    state.filters.country = item.dataset.country;
                    state.filters.region = '';
                    state.filters.city = '';
                    fetchProviders();
                    state.elements.locationModal.classList.remove('show');
                });
            });
        }
    } catch (error) {
        console.error('Erro ao carregar países:', error);
    }
}