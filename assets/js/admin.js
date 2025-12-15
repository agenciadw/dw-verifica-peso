/**
 * Scripts JavaScript para o admin do plugin DW Verificação de Peso
 *
 * @package DW_Verifica_Peso
 * @version 0.1.0
 */

(function($) {
    'use strict';

    // Função para processar edição rápida
    function processarEdicaoRapida(button) {
        var productId = button.attr('data-product-id') || button.data('product-id');
        
        if (!productId) {
            alert('Erro: ID do produto não encontrado.');
            return false;
        }
        
        var input = $('.dw-quick-edit-peso[data-product-id="' + productId + '"]');
        if (input.length === 0) {
            alert('Erro: Campo de peso não encontrado.');
            return false;
        }
        
        var peso = input.val();
        var statusSpan = input.closest('.dw-quick-edit-wrapper').find('.dw-quick-edit-status');
        
        if (peso === '' || peso === null || peso === undefined) {
            statusSpan.html('<span style="color: #dc3232;">✗ Informe um peso</span>');
            setTimeout(function() {
                statusSpan.html('');
            }, 3000);
            return false;
        }
        
        button.prop('disabled', true);
        input.prop('readonly', true);
        statusSpan.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');

        if (typeof dwVerificaPeso === 'undefined') {
            statusSpan.html('<span style="color: #dc3232;">✗ Erro de configuração</span>');
            button.prop('disabled', false);
            input.prop('readonly', false);
            return false;
        }
        
        $.ajax({
            url: dwVerificaPeso.ajax_url,
            type: 'POST',
            data: {
                action: 'dw_verifica_peso_quick_edit',
                nonce: dwVerificaPeso.nonce,
                product_id: productId,
                peso: peso
            },
            success: function(response) {
                if (response.success) {
                    statusSpan.html('<span style="color: #46b450;">✓ ' + (dwVerificaPeso.strings.success || 'Sucesso!') + '</span>');
                    if (response.data && response.data.peso) {
                        input.val(response.data.peso);
                    }
                    setTimeout(function() {
                        statusSpan.html('');
                    }, 3000);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    var errorMsg = (response.data && response.data.message) ? response.data.message : (dwVerificaPeso.strings.error || 'Erro ao processar.');
                    statusSpan.html('<span style="color: #dc3232;">✗ ' + errorMsg + '</span>');
                    button.prop('disabled', false);
                    input.prop('readonly', false);
                }
            },
            error: function(xhr, status, error) {
                statusSpan.html('<span style="color: #dc3232;">✗ ' + (dwVerificaPeso.strings.error || 'Erro ao processar.') + '</span>');
                button.prop('disabled', false);
                input.prop('readonly', false);
            }
        });
        
        return false;
    }

    $(document).ready(function() {
        // Verifica se o objeto está disponível
        if (typeof dwVerificaPeso === 'undefined') {
            return;
        }
        
        // Selecionar todos os produtos
        $('#dw-select-all').on('change', function() {
            $('.dw-product-checkbox').prop('checked', $(this).prop('checked'));
            atualizarContadorSelecionados();
        });

        // Atualizar contador quando checkbox individual é alterado
        $('.dw-product-checkbox').on('change', function() {
            atualizarContadorSelecionados();
            
            // Atualiza o estado do checkbox "selecionar todos"
            var total = $('.dw-product-checkbox').length;
            var checked = $('.dw-product-checkbox:checked').length;
            $('#dw-select-all').prop('checked', total === checked);
        });

        // Função para atualizar contador de selecionados
        function atualizarContadorSelecionados() {
            var count = $('.dw-product-checkbox:checked').length;
            if (count > 0) {
                var text = count === 1 
                    ? count + ' ' + (dwVerificaPeso.strings.product_selected || 'produto selecionado')
                    : count + ' ' + (dwVerificaPeso.strings.products_selected || 'produtos selecionados');
                $('.dw-selected-count').text(text);
            } else {
                $('.dw-selected-count').text('');
            }
        }

        // Mostra/esconde campo de peso customizado
        $('#dw-bulk-action-select').on('change', function() {
            var action = $(this).val();
            if (action === 'set_default_weight') {
                $('#dw-peso-customizado-wrapper').show();
            } else {
                $('#dw-peso-customizado-wrapper').hide();
                $('#dw-peso-customizado').val('');
            }
        });

        // Validação do formulário de ação em massa
        $('#dw-bulk-form').on('submit', function(e) {
            var action = $('#dw-bulk-action-select').val();
            var checked = $('.dw-product-checkbox:checked').length;

            // Valida se uma ação foi selecionada
            if (!action || action === '') {
                e.preventDefault();
                e.stopPropagation();
                alert(dwVerificaPeso.strings.select_action || 'Selecione uma ação para aplicar.');
                return false;
            }

            // Valida se há produtos selecionados
            if (checked === 0) {
                e.preventDefault();
                e.stopPropagation();
                alert(dwVerificaPeso.strings.select_products);
                return false;
            }

            // Confirma ação de remover alertas
            if (action === 'remove_flags') {
                if (!confirm(dwVerificaPeso.strings.confirm_delete)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }

            // Confirma ação de definir peso padrão
            if (action === 'set_default_weight') {
                var pesoCustomizado = $('#dw-peso-customizado').val();
                var confirmText;
                
                if (pesoCustomizado && pesoCustomizado !== '') {
                    // Valida peso customizado
                    var pesoFloat = parseFloat(pesoCustomizado.replace(',', '.'));
                    if (isNaN(pesoFloat) || pesoFloat <= 0) {
                        e.preventDefault();
                        e.stopPropagation();
                        alert('Por favor, informe um peso válido maior que zero.');
                        return false;
                    }
                    confirmText = dwVerificaPeso.strings.confirm_set_custom_weight || 
                        'Deseja definir o peso ' + pesoCustomizado + ' kg para os produtos selecionados?';
                } else {
                    confirmText = dwVerificaPeso.strings.confirm_set_weight || 'Deseja definir o peso padrão para os produtos selecionados?';
                }
                
                if (!confirm(confirmText)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }

            // Se passou todas as validações, permite o submit e mostra indicador de carregamento
            var submitButton = $('#dw-bulk-submit');
            submitButton.prop('disabled', true).text(dwVerificaPeso.strings.processing || 'Processando...');
            
            // O formulário será submetido normalmente
            return true;
        });

        // Edição rápida - Salvar peso (específico para botões de peso, não dimensões)
        $(document).on('click', '.dw-quick-edit-save:not(.dw-quick-edit-save-dimensoes)', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            var button = $(this);
            
            // Verifica se é realmente um botão de peso (está dentro de wrapper de peso)
            if (!button.closest('.dw-quick-edit-wrapper').length || button.closest('.dw-quick-edit-dimensoes-wrapper').length) {
                return false;
            }
            
            if (button.prop('disabled')) {
                return false;
            }
            
            return processarEdicaoRapida(button);
        });

        // Previne que o formulário seja submetido quando o botão de edição rápida é clicado
        $(document).on('submit', '#dw-bulk-form, #dw-bulk-form-dimensoes', function(e) {
            // Se o clique veio do botão de edição rápida, previne o submit
            var target = e.originalEvent ? e.originalEvent.target : e.target;
            if ($(target).hasClass('dw-quick-edit-save') || $(target).hasClass('dw-quick-edit-save-dimensoes') || 
                $(target).closest('.dw-quick-edit-save').length || $(target).closest('.dw-quick-edit-save-dimensoes').length) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });

        // Permitir salvar com Enter no campo de peso (usando delegação de eventos)
        $(document).on('keypress', '.dw-quick-edit-peso', function(e) {
            if (e.which === 13) { // Enter
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                var button = $(this).closest('.dw-quick-edit-wrapper').find('.dw-quick-edit-save');
                if (button.length && !button.prop('disabled')) {
                    processarEdicaoRapida(button);
                }
            }
        });

        // Mostrar mensagens de sucesso/erro da URL
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('message') === 'bulk_success') {
            var notice = $('<div class="notice notice-success is-dismissible"><p>' + dwVerificaPeso.strings.success + '</p></div>');
            $('.dw-verifica-peso-summary').after(notice);
            
            // Remove após 5 segundos
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }

        // Foco automático no campo de peso ao clicar
        $('.dw-quick-edit-peso').on('focus', function() {
            $(this).select();
        });

        // === EDIÇÃO RÁPIDA DE DIMENSÕES ===
        
        // Função para processar edição rápida de dimensões
        function processarEdicaoRapidaDimensoes(button) {
            var productId = button.attr('data-product-id') || button.data('product-id');
            
            if (!productId) {
                alert('Erro: ID do produto não encontrado.');
                return false;
            }
            
            var wrapper = button.closest('.dw-quick-edit-dimensoes-wrapper');
            var inputLargura = wrapper.find('.dw-quick-edit-largura[data-product-id="' + productId + '"]');
            var inputAltura = wrapper.find('.dw-quick-edit-altura[data-product-id="' + productId + '"]');
            var inputComprimento = wrapper.find('.dw-quick-edit-comprimento[data-product-id="' + productId + '"]');
            var statusSpan = wrapper.find('.dw-quick-edit-status-dimensoes');
            
            var largura = inputLargura.val();
            var altura = inputAltura.val();
            var comprimento = inputComprimento.val();
            
            // Pelo menos uma medida deve ser informada (mas pode estar vazia para remover)
            if ((!largura || largura === '') && (!altura || altura === '') && (!comprimento || comprimento === '')) {
                statusSpan.html('<span style="color: #dc3232;">✗ Informe pelo menos uma medida</span>');
                setTimeout(function() {
                    statusSpan.html('');
                }, 3000);
                return false;
            }
            
            button.prop('disabled', true);
            inputLargura.prop('readonly', true);
            inputAltura.prop('readonly', true);
            inputComprimento.prop('readonly', true);
            statusSpan.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');

            if (typeof dwVerificaPeso === 'undefined') {
                statusSpan.html('<span style="color: #dc3232;">✗ Erro de configuração</span>');
                button.prop('disabled', false);
                inputLargura.prop('readonly', false);
                inputAltura.prop('readonly', false);
                inputComprimento.prop('readonly', false);
                return false;
            }
            
            $.ajax({
                url: dwVerificaPeso.ajax_url,
                type: 'POST',
                data: {
                    action: 'dw_verifica_peso_quick_edit_dimensoes',
                    nonce: dwVerificaPeso.nonce,
                    product_id: productId,
                    largura: largura,
                    altura: altura,
                    comprimento: comprimento
                },
                success: function(response) {
                    if (response.success) {
                        statusSpan.html('<span style="color: #46b450;">✓ ' + (dwVerificaPeso.strings.success || 'Sucesso!') + '</span>');
                        if (response.data && response.data.largura) {
                            inputLargura.val(response.data.largura);
                        }
                        if (response.data && response.data.altura) {
                            inputAltura.val(response.data.altura);
                        }
                        if (response.data && response.data.comprimento) {
                            inputComprimento.val(response.data.comprimento);
                        }
                        setTimeout(function() {
                            statusSpan.html('');
                        }, 3000);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : (dwVerificaPeso.strings.error || 'Erro ao processar.');
                        statusSpan.html('<span style="color: #dc3232;">✗ ' + errorMsg + '</span>');
                        button.prop('disabled', false);
                        inputLargura.prop('readonly', false);
                        inputAltura.prop('readonly', false);
                        inputComprimento.prop('readonly', false);
                    }
                },
                error: function(xhr, status, error) {
                    statusSpan.html('<span style="color: #dc3232;">✗ ' + (dwVerificaPeso.strings.error || 'Erro ao processar.') + '</span>');
                    button.prop('disabled', false);
                    inputLargura.prop('readonly', false);
                    inputAltura.prop('readonly', false);
                    inputComprimento.prop('readonly', false);
                }
            });
            
            return false;
        }

        // Edição rápida de dimensões - Salvar
        $(document).on('click', '.dw-quick-edit-save-dimensoes', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            var button = $(this);
            
            if (button.prop('disabled')) {
                return false;
            }
            
            return processarEdicaoRapidaDimensoes(button);
        });

        // Selecionar todos os produtos (dimensões)
        $('#dw-select-all-dimensoes').on('change', function() {
            $('.dw-product-checkbox-dimensoes').prop('checked', $(this).prop('checked'));
            atualizarContadorSelecionadosDimensoes();
        });

        // Atualizar contador quando checkbox individual é alterado (dimensões)
        $('.dw-product-checkbox-dimensoes').on('change', function() {
            atualizarContadorSelecionadosDimensoes();
            
            var total = $('.dw-product-checkbox-dimensoes').length;
            var checked = $('.dw-product-checkbox-dimensoes:checked').length;
            $('#dw-select-all-dimensoes').prop('checked', total === checked);
        });

        // Função para atualizar contador de selecionados (dimensões)
        function atualizarContadorSelecionadosDimensoes() {
            var count = $('.dw-product-checkbox-dimensoes:checked').length;
            if (count > 0) {
                var text = count === 1 
                    ? count + ' produto selecionado'
                    : count + ' produtos selecionados';
                $('.dw-selected-count-dimensoes').text(text);
            } else {
                $('.dw-selected-count-dimensoes').text('');
            }
        }

        // Validação do formulário de ação em massa (dimensões)
        $('#dw-bulk-form-dimensoes').on('submit', function(e) {
            var action = $('#dw-bulk-action-select-dimensoes').val();
            var checked = $('.dw-product-checkbox-dimensoes:checked').length;

            if (!action || action === '') {
                e.preventDefault();
                e.stopPropagation();
                alert('Selecione uma ação para aplicar.');
                return false;
            }

            if (checked === 0) {
                e.preventDefault();
                e.stopPropagation();
                alert('Por favor, selecione pelo menos um produto.');
                return false;
            }

            if (action === 'remove_flags_dimensoes') {
                if (!confirm('Tem certeza que deseja remover os alertas dos produtos selecionados?')) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }

            var submitButton = $('#dw-bulk-submit-dimensoes');
            submitButton.prop('disabled', true).text('Processando...');
            
            return true;
        });

        // Permitir salvar com Enter nos campos de dimensões
        $(document).on('keypress', '.dw-quick-edit-largura, .dw-quick-edit-altura, .dw-quick-edit-comprimento', function(e) {
            if (e.which === 13) { // Enter
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                var button = $(this).closest('.dw-quick-edit-dimensoes-wrapper').find('.dw-quick-edit-save-dimensoes');
                if (button.length && !button.prop('disabled')) {
                    processarEdicaoRapidaDimensoes(button);
                }
            }
        });
    });

})(jQuery);
