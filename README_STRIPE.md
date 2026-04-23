# Integração Stripe - Sistema de Pagamento

## 📋 Visão Geral

Sistema completo de pagamento via Stripe integrado à loja Maison de Luxo.

## 🚀 Instalação

### 1. Instale o SDK do Stripe

```bash
composer require stripe/stripe-php
```

### 2. Execute o Script SQL

No seu banco de dados, execute:

```sql
-- Tabela de pedidos com integração Stripe
CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cliente_id` INT NOT NULL,
  `produto_id` INT NOT NULL,
  `status` ENUM('pendente','pago','cancelado','reembolsado') NOT NULL DEFAULT 'pendente',
  `stripe_session_id` VARCHAR(255) DEFAULT NULL,
  `stripe_payment_intent` VARCHAR(255) DEFAULT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`) ON DELETE CASCADE,
  INDEX (`stripe_session_id`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. Configure as Chaves no `config.php`

Edite `/workspace/config.php` e substitua:

```php
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_PUBLISHABLE_KEY');
define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_WEBHOOK_SECRET');
define('SITE_URL', 'https://seusite.com');
```

**Onde obter:**
- Acesse [Stripe Dashboard](https://dashboard.stripe.com/)
- Em **Developers > API Keys**, pegue as chaves de teste (prefixo `sk_test_` e `pk_test_`)
- Em **Developers > Webhooks**, crie um webhook para gerar o segredo

### 4. Configure o Webhook no Stripe Dashboard

1. Acesse **Developers > Webhooks** no Stripe Dashboard
2. Clique em **Add endpoint**
3. URL: `https://seusite.com/stripe/webhook.php`
4. Eventos a ouvir:
   - `checkout.session.completed`
   - `payment_intent.payment_failed`
5. Copie o **Signing Secret** gerado e coloque em `STRIPE_WEBHOOK_SECRET`

## 📁 Estrutura de Arquivos

```
/workspace/
├── config.php                 # Configurações do Stripe
├── produto.php                # Página do produto com botão "Comprar"
├── stripe/
│   ├── checkout.php           # Cria sessão de pagamento
│   ├── success.php            # Página de sucesso
│   ├── cancel.php             # Página de cancelamento
│   └── webhook.php            # Webhook para confirmação automática
└── admin/
    ├── admin.php              # Painel com link para Pedidos
    ├── pedidos.php            # Lista todos os pedidos
    └── pedido_atualizar.php   # Atualiza status manualmente
```

## 🔄 Fluxo de Pagamento

1. **Cliente clica em "Comprar Agora"** na página do produto
2. **Sistema verifica:**
   - Cliente está logado?
   - Produto tem estoque?
3. **Cria registro de pedido** no banco (status: pendente)
4. **Redireciona para Checkout do Stripe** (hospedado pelo Stripe)
5. **Cliente paga** com cartão no ambiente seguro do Stripe
6. **Stripe retorna** para `success.php` ou `cancel.php`
7. **Webhook confirma** pagamento e atualiza pedido automaticamente
8. **Estoque é decrementado** quando pagamento é confirmado

## 🛒 Funcionalidades

### Frontend (Cliente)
- ✅ Botão "Comprar Agora" na página do produto
- ✅ Redirecionamento seguro para Stripe Checkout
- ✅ Página de sucesso com resumo do pedido
- ✅ Página de cancelamento com opção de tentar novamente
- ✅ Verificação de estoque em tempo real
- ✅ Requer login do cliente

### Backend (Admin)
- ✅ Menu "📦 Pedidos" no painel administrativo
- ✅ Lista completa de pedidos com status
- ✅ Detalhes do pagamento (Session ID, Payment Intent)
- ✅ Alteração manual de status (para casos especiais)
- ✅ Badges coloridas por status
- ✅ Informações do cliente e produto

### Segurança
- ✅ Verificação de autenticação do cliente
- ✅ Validação de estoque antes de criar pedido
- ✅ Prepared statements contra SQL Injection
- ✅ Webhook com verificação de assinatura
- ✅ HTTPS recomendado para produção

## 🧪 Modo de Teste

Sem o SDK instalado, o sistema opera em **modo demonstração**:
- Cria registro de pedido no banco
- Exibe mensagem informativa
- Simula aprovação automática
- Permite testar fluxo sem processar pagamentos reais

## 🔧 Personalização

### Moeda
Para mudar de BRL para outra moeda, edite `stripe/checkout.php`:
```php
'currency' => 'brl', // usd, eur, gbp, etc.
```

### E-mail de Confirmação
Adicione em `stripe/webhook.php` após confirmar pagamento:
```php
mail($cliente_email, "Pedido #$pedido_id confirmado", "Seu pagamento foi aprovado!");
```

### URLs Personalizadas
Edite em `config.php`:
```php
define('SITE_URL', 'https://sualoja.com');
```

## 📊 Status dos Pedidos

| Status | Descrição | Ação |
|--------|-----------|------|
| `pendente` | Aguardando pagamento | Pode ser cancelado ou atualizado |
| `pago` | Pagamento confirmado | Estoque decrementado |
| `cancelado` | Cancelado pelo cliente/admin | Produto volta ao estoque* |
| `reembolsado` | Reembolso processado | Para disputas/devoluções |

*Para devolver estoque ao cancelar, adicione lógica em `pedido_atualizar.php`

## 🐛 Troubleshooting

### Erro: "SDK do Stripe não instalado"
```bash
composer require stripe/stripe-php
```

### Webhook não funciona
- Verifique se `SITE_URL` está correto
- Teste webhook local com [Stripe CLI](https://stripe.com/docs/stripe-cli)
- Confira logs de erro do PHP

### Pagamento não atualiza pedido
- Verifique eventos configurados no webhook
- Confira `STRIPE_WEBHOOK_SECRET` está correto
- Veja logs em `stripe/webhook.php`

## 📞 Suporte Stripe

- [Documentação Oficial](https://stripe.com/docs)
- [Dashboard de Testes](https://dashboard.stripe.com/test)
- [Números de Cartão para Teste](https://stripe.com/docs/testing#cards)

### Cartões de Teste
| Número | Resultado |
|--------|-----------|
| 4242 4242 4242 4242 | Sucesso |
| 4000 0000 0000 9995 | Falha |
| 4000 0025 0000 3155 | Requer autenticação 3D Secure |

Use qualquer data futura e qualquer CVV para testes.
