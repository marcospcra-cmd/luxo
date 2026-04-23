# Sistema de Gestão de Usuários e Perfis - Maison de Luxo

## Visão Geral

Este documento descreve a implementação completa do sistema de gestão de usuários e perfis para o e-commerce de luxo.

## Estrutura do Banco de Dados

### Tabela `clientes` (Atualizada)

A tabela de clientes foi expandida com os seguintes campos:

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | INT | Chave primária |
| `nome` | VARCHAR(120) | Nome completo do cliente |
| `email` | VARCHAR(180) | E-mail único |
| `senha_hash` | VARCHAR(255) | Hash da senha |
| `cpf_cnpj` | VARCHAR(14) | CPF ou CNPJ para NF |
| `telefone_whatsapp` | VARCHAR(15) | Número do WhatsApp |
| `foto_perfil_url` | VARCHAR(500) | URL da foto de perfil |
| `endereco_rua` | VARCHAR(255) | Rua do endereço |
| `endereco_numero` | VARCHAR(20) | Número do endereço |
| `endereco_bairro` | VARCHAR(100) | Bairro |
| `endereco_cidade` | VARCHAR(100) | Cidade |
| `endereco_estado` | CHAR(2) | Estado (UF) |
| `endereco_cep` | VARCHAR(9) | CEP |
| `data_nascimento` | DATE | Data de nascimento |
| `preferencias` | TEXT | Preferências (Esmeraldas, Esculturas, Cangas) |
| `criado_em` | TIMESTAMP | Data de cadastro |
| `atualizado_em` | TIMESTAMP | Última atualização |

### Tabela `administradores` (Atualizada)

Adicionado campo `email` para integração com políticas RLS.

## Instalação

### 1. Aplicar Migração no Banco de Dados

Execute o script de migração no seu banco MySQL/MariaDB:

```bash
mysql -u seu_usuario -p nome_do_banco < update_schema_perfis.sql
```

Ou via phpMyAdmin/Workbench, execute o conteúdo do arquivo `update_schema_perfis.sql`.

### 2. Permissões de Upload

Certifique-se de que a pasta de uploads tenha permissões adequadas:

```bash
mkdir -p uploads/perfis
chmod 755 uploads/perfis
chown www-data:www-data uploads/perfis  # Apache/Nginx
```

## Funcionalidades Implementadas

### 1. Página de Cadastro (`cliente_cadastro.php`)

- Formulário completo com validação de dados
- Campos obrigatórios: Nome, E-mail, Senha
- Campos opcionais: CPF/CNPJ, WhatsApp, Data de Nascimento, Preferências
- Validações:
  - CPF: 11 dígitos
  - CNPJ: 14 dígitos
  - Telefone: 10-15 dígitos
  - Senha: 8-120 caracteres
  - E-mail: formato válido e único

### 2. Área do Cliente (`cliente_perfil.php`)

**Requer login do cliente**

Funcionalidades:
- Visualização do perfil completo
- Upload de foto de perfil (JPG, PNG, GIF, WebP - máx 5MB)
- Edição de dados pessoais
- Gestão de endereço de entrega
- Histórico de membro (data de cadastro)

**Validações:**
- Foto: tipos permitidos e tamanho máximo
- Campos formatados (CPF, CEP, Estado)
- CSRF protection em todos os forms

### 3. Painel Administrativo (`admin/clientes.php`)

**Acesso restrito a administradores**

Melhorias implementadas:
- Foto de perfil em miniatura na lista
- Colunas: CPF/CNPJ, Telefone
- Botão "Ver Endereço" expansível
- Resumo estatístico:
  - Total de clientes
  - Clientes com favoritos
  - Clientes com pedidos
  - Clientes com foto de perfil

### 4. Navegação Integrada

Menu dropdown atualizado no header:
- 👤 Meu Perfil (novo)
- ♥ Meus Favoritos
- 🚪 Sair

## Segurança

### Políticas de Segurança (RLS) - Supabase

Para implementação com Supabase, o arquivo `supabase/migrations/002_create_user_profiles.sql` contém:

1. **Política de Leitura**: Usuários veem apenas seu próprio perfil
2. **Política de Edição**: Usuários editam apenas seu próprio perfil
3. **Política de Admin**: Administradores podem ver/editar todos os perfis
4. **Função `is_admin()`**: Verifica se usuário é administrador pelo email

### Validações de Dados

- **CPF/CNPJ**: Apenas números, tamanho válido
- **Telefone**: 10-15 dígitos
- **CEP**: 8 dígitos
- **Estado**: 2 letras maiúsculas
- **Foto**: Tipos MIME validados, tamanho máximo 5MB
- **CSRF**: Token em todos os formulários
- **XSS**: `htmlspecialchars()` em todas as saídas

## Estrutura de Arquivos

```
/workspace
├── cliente_cadastro.php      # Cadastro completo
├── cliente_perfil.php        # Área do cliente (NOVO)
├── admin/clientes.php        # Gestão de clientes (ATUALIZADO)
├── includes/header.php       # Menu atualizado
├── includes/cliente_auth.php # Autenticação
├── uploads/perfis/           # Fotos de perfil
├── sql/schema.sql            # Schema completo
├── update_schema_perfis.sql  # Script de migração
└── supabase/migrations/
    └── 002_create_user_profiles.sql  # Para Supabase
```

## Uso da API de Cupons (Bônus)

A API `api/cupom_validar.php` requer cliente logado:

```javascript
// Exemplo de uso no carrinho
fetch('/api/cupom_validar.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ codigo: 'DESCONTO10' })
})
.then(res => res.json())
.then(data => {
  if (data.valido) {
    // Aplica desconto
  }
});
```

## Próximos Passos Sugeridos

1. **Integração com Supabase Auth**: Migrar autenticação para Supabase
2. **Recuperação de Senha**: Implementar fluxo de reset
3. **Verificação de E-mail**: Confirmar e-mail após cadastro
4. **Histórico de Pedidos**: Mostrar na área do cliente
5. **Notificações**: Alertas de promoções por preferências

## Suporte

Para dúvidas ou problemas, consulte a documentação do projeto ou entre em contato com a equipe de desenvolvimento.
