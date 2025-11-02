/**
 * /assets/js/license-calculator.js
 * Lógica JavaScript para o simulador de lucro do Licenciamento BacoSearch.
 *
 * Última Atualização: 02/07/2025 - Removido CPC; Adicionados cálculos para receita líquida mensal,
 * 80% licenciado; Removida a exibição e cálculo dos 20% da Matrix; Adicionada verificação de elementos nulos.
 * CORREÇÃO: Tradução do texto 'ROI Estimado:' agora vem do PHP para o JS.
 */

function formatEuro(val) {
    return `€ ${parseFloat(val).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ".")}`;
}

document.addEventListener('DOMContentLoaded', () => {
    const inputs = {
        individuals: document.getElementById('calc-individuals'),
        locals: document.getElementById('calc-locals'),
        services: document.getElementById('calc-services')
    };

    const results = {
        receitaMensal: document.getElementById('res-receita-mensal'),
        stripe: document.getElementById('res-stripe'),
        imposto: document.getElementById('res-imposto'),
        sistema: document.getElementById('res-sistema'),
        marketing: document.getElementById('res-marketing'),
        totalDescontos: document.getElementById('res-total-descontos'),
        receitaLiquida: document.getElementById('res-receita-liquida'),
        licenciadoShare: document.getElementById('res-licenciado-share'),
        resultadoLicenciado: document.getElementById('res-resultado-licenciado'),
        investimento: document.getElementById('res-investimento'),
        lucroLiquido: document.getElementById('res-lucro-liquido')
    };

    // Reference to the ROI element. It should be passed from PHP.
    const roiElement = document.getElementById('res-roi-after-investment');

    // Verifique se roiTranslation foi definido no PHP antes de ser usado aqui.
    // Isso é crucial! Se 'roiTranslation' não estiver definido, o JS vai falhar.
    // O console.error abaixo é um fallback de segurança.
    if (typeof roiTranslation === 'undefined') {
        console.error('Erro: A variável roiTranslation não foi definida no PHP. Por favor, adicione "const roiTranslation = \'<?= htmlspecialchars($translations[\'label_estimated_roi\'] ?? \'Estimated ROI:\'); ?>\';" no seu calculator.php.');
        // Pode-se optar por parar a execução ou desabilitar a funcionalidade da calculadora aqui.
        return; 
    }

    // Filters out the removed matrixShare from the check
    const elementsToCheck = [
        ...Object.values(inputs),
        results.receitaMensal, results.stripe, results.imposto, results.sistema,
        results.marketing, results.totalDescontos, results.receitaLiquida,
        results.licenciadoShare, results.resultadoLicenciado,
        results.investimento, results.lucroLiquido
    ];
    
    // Adicionamos o roiElement à verificação de existência, mas com cuidado, pois ele pode ser opcionalmente nulo no HTML
    // A função formatROI já lida com a sua nulidade.
    const allRequiredElementsExist = elementsToCheck.every(el => el !== null);

    if (!allRequiredElementsExist) {
        console.error('Erro: Um ou mais elementos do DOM para a calculadora não foram encontrados.');
        // Opcionalmente, você pode querer ocultar a calculadora ou exibir uma mensagem
        return;
    }
    
    // Function to format ROI, handling potential null element
    const formatROI = (calculatedRoi) => {
        if (roiElement) {
            // Agora usamos a variável 'roiTranslation' que vem do PHP
            roiElement.textContent = `${roiTranslation} ${calculatedRoi}%`;
        }
    };


    const calcular = () => {
        const valores = {
            individuals: parseInt(inputs.individuals.value) || 0,
            locals: parseInt(inputs.locals.value) || 0,
            services: parseInt(inputs.services.value) || 0
        };

        // É ALTAMENTE RECOMENDADO PASSAR ESSES VALORES DE PREÇO DO PHP (calculatorConfigs)
        // PARA GARANTIR CONSISTÊNCIA ENTRE FRONTEND E BACKEND.
        // Se eles forem alterados no backend, o JS não se atualiza automaticamente.
        // Exemplo: const valIndiv = calculatorConfigs.product_prices.individuals;
        const valIndiv = 14.90;
        const valLocal = 19.90;
        const valServ = 9.90;

        const receitaMensal = (valores.individuals * valIndiv + 
                               valores.locals * valLocal + 
                               valores.services * valServ);

        // É ALTAMENTE RECOMENDADO PASSAR ESSES VALORES DE TAXAS DO PHP (calculatorConfigs)
        // PARA GARANTIR CONSISTÊNCIA ENTRE FRONTEND E BACKEND.
        // Exemplo: const stripeFee = (receitaMensal * calculatorConfigs.fees_and_taxes.stripe_fee_percent);
        const stripeFee = (receitaMensal * 0.035);
        const imposto = (receitaMensal * 0.15);
        const sistemaFee = (receitaMensal * 0.03);
        const marketingFee = (receitaMensal * 0.035);

        const totalDescontos = (stripeFee + imposto + sistemaFee + marketingFee);

        const receitaLiquida = (receitaMensal - totalDescontos);

        // A porcentagem do licenciado também deve vir de calculatorConfigs.license_share_percent
        const licenciadoShare = (receitaLiquida * 0.80);
        
        const resultadoLicenciado = (licenciadoShare * 12);
        // O custo inicial também deve vir de calculatorConfigs.initial_investment_cost
        const initialInvestment = 1500; 
        const lucroLiquido = (resultadoLicenciado - initialInvestment);
        
        // Calculate ROI for display
        const calculatedRoi = (initialInvestment > 0) ? ((lucroLiquido / initialInvestment) * 100) : 'N/A';

        // Update DOM elements
        results.receitaMensal.textContent = formatEuro(receitaMensal);
        results.stripe.textContent = formatEuro(stripeFee);
        results.imposto.textContent = formatEuro(imposto);
        results.sistema.textContent = formatEuro(sistemaFee);
        results.marketing.textContent = formatEuro(marketingFee);
        results.totalDescontos.textContent = formatEuro(totalDescontos);
        results.receitaLiquida.textContent = formatEuro(receitaLiquida);
        results.licenciadoShare.textContent = formatEuro(licenciadoShare);
        results.resultadoLicenciado.textContent = formatEuro(resultadoLicenciado);
        results.investimento.textContent = formatEuro(initialInvestment); 
        results.lucroLiquido.textContent = formatEuro(lucroLiquido);
        
        // Update ROI
        if (calculatedRoi !== 'N/A') {
            formatROI(calculatedRoi.toFixed(2));
        } else {
            formatROI('N/A');
        }
    };

    // Set initial values if they are passed from PHP (from the `initialInputValues` object)
    if (typeof initialInputValues !== 'undefined') {
        inputs.individuals.value = initialInputValues.individuals;
        inputs.locals.value = initialInputValues.locals;
        inputs.services.value = initialInputValues.services;
    }
    
    // Add event listeners
    Object.values(inputs).forEach(input => input.addEventListener('input', calcular));
    
    // Initial calculation when the page loads
    calcular();
});