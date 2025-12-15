# DW Verificação de Peso - WooCommerce

Plugin para WordPress/WooCommerce que monitora, alerta e previne o cadastro de pesos incorretos ou produtos sem peso.

## 📋 Descrição

Este plugin oferece um sistema completo para verificação de pesos de produtos no WooCommerce, incluindo:

- ✅ Validação de peso em tempo real ao salvar produtos
- ✅ Detecção de produtos sem peso cadastrado
- ✅ Alertas visuais no painel administrativo
- ✅ Notificações por e-mail para pesos anormais ou produtos sem peso
- ✅ Relatórios detalhados de produtos com problemas
- ✅ Configuração de limites de peso via painel administrativo
- ✅ Coluna de peso na lista de produtos com indicadores visuais

## 🚀 Instalação

1. Faça o upload do plugin para a pasta `/wp-content/plugins/dw-verifica-peso/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Certifique-se de que o WooCommerce está instalado e ativo
4. Acesse **WooCommerce > Config. Pesos** para configurar os limites de peso

## ⚙️ Configuração

### Configurações Básicas

Acesse **WooCommerce > Config. Pesos** para configurar:

1. **Peso Mínimo (kg)**: Peso mínimo aceitável para produtos
2. **Peso Máximo (kg)**: Peso máximo aceitável para produtos
3. **E-mails para Alerta**: Lista de e-mails que receberão notificações (separados por vírgula)

### Funcionalidades

- **Validação Automática**: Ao salvar um produto, o peso é validado automaticamente
- **Alertas Visuais**: Produtos com peso anormal ou sem peso são destacados na lista
- **Notificações por E-mail**: E-mails são enviados quando produtos problemáticos são cadastrados
- **Relatórios**: Acesse **WooCommerce > Verificar Pesos** para ver todos os produtos com problemas

## 📁 Estrutura do Plugin

```
dw-verifica-peso/
├── dw-verifica-peso.php          # Arquivo principal
├── uninstall.php                  # Script de desinstalação
├── README.md                      # Este arquivo
├── includes/                      # Classes principais
│   ├── class-dw-verifica-peso-validator.php  # Validação de pesos
│   ├── class-dw-verifica-peso-email.php      # Envio de e-mails
│   └── class-dw-verifica-peso-admin.php      # Interface administrativa
├── admin/                         # Arquivos do admin
│   └── views/
│       ├── settings.php           # Página de configurações
│       └── report.php             # Página de relatórios
└── assets/                        # Arquivos estáticos
    └── css/
        └── admin.css              # Estilos do admin
```

## 🔒 Segurança

O plugin implementa as melhores práticas de segurança do WordPress:

- ✅ Verificação de nonces em formulários
- ✅ Sanitização de todos os dados de entrada
- ✅ Validação de permissões de usuário
- ✅ Escape de saída para prevenir XSS
- ✅ Prepared statements para queries SQL

## 🌍 Compatibilidade

- **WordPress**: 5.8 ou superior
- **PHP**: 7.4 ou superior
- **WooCommerce**: 5.0 ou superior (testado até 8.0)

## 📝 Changelog

### 0.1.0
- Versão inicial do plugin
- Reestruturação completa do plugin
- Organização em classes separadas
- Melhorias de segurança
- Detecção de produtos sem peso
- Interface administrativa melhorada
- Edição rápida de pesos (inline)
- Edição em massa de produtos
- Compatibilidade com HPOS (High-Performance Order Storage)
- Código limpo e bem comentado

## 👨‍💻 Desenvolvido por

**David William da Costa**

- GitHub: [https://github.com/agenciadw/](https://github.com/agenciadw/)
- Plugin: [https://github.com/agenciadw/dw-verifica-peso](https://github.com/agenciadw/dw-verifica-peso)

Desenvolvedor de websites e e-commerces há quase 20 anos, especialista em WordPress/WooCommerce e UX/UI Design.

## 📄 Licença

Este plugin é de propriedade de David William da Costa.

## 🐛 Suporte

Para suporte, abra uma issue no repositório do GitHub: [https://github.com/agenciadw/dw-verifica-peso](https://github.com/agenciadw/dw-verifica-peso)

