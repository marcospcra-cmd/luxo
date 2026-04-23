# Maison de Luxo — Sistema PHP/MySQL

E-commerce de luxo (Esmeraldas · Esculturas · Cangas) em **PHP 8.2 + MySQL** pronto para Hostinger.

## 🚀 Instalação na Hostinger (passo a passo)

### 1. Banco de dados
1. No **hPanel → Bancos de dados MySQL**, crie um novo banco.
2. Anote: **nome do banco**, **usuário**, **senha**, **host** (geralmente `localhost`).
3. Abra o **phpMyAdmin** desse banco e importe o arquivo `sql/schema.sql`.

### 2. Configuração
Edite o arquivo **`config.php`** com seus dados:
```php
define('DB_NAME', 'u000000000_luxo');
define('DB_USER', 'u000000000_user');
define('DB_PASS', 'sua_senha');
define('WHATSAPP_NUMERO', '5511999999999'); // só dígitos
```

### 3. Upload dos arquivos
1. No hPanel abra **Gerenciador de arquivos** → entre em `public_html/`.
2. Envie todos os arquivos/pastas deste projeto (ou faça upload do zip e descompacte).
3. Garanta que a pasta `uploads/` tenha permissão de escrita (geralmente já tem).

### 4. Primeiro acesso
- Loja:  `https://seu-dominio.com/`
- Login: `https://seu-dominio.com/login.php`
  - Usuário: **admin**
  - Senha:   **admin123**  ← **TROQUE imediatamente** (veja abaixo)

### 5. Trocar a senha do admin
Gere um novo hash com este comando (rode 1x onde tiver PHP, ou em uma página temporária):
```php
echo password_hash('SuaNovaSenhaForte', PASSWORD_BCRYPT);
```
Cole o hash gerado no campo `senha_hash` do registro `admin` na tabela `administradores` (via phpMyAdmin).

---

## ✨ Funcionalidades

- **2 temas** selecionáveis no topo (Dark luxuoso · Minimalista branco editorial)
- Catálogo com **filtros por categoria**
- **Cards** com hover de elevação e efeito de zoom
- Página de produto com **galeria secundária** + **especificações técnicas**
- Botão **Consultar Especialista** que abre o WhatsApp com mensagem pré-pronta
- **Painel admin** com login seguro (`password_hash` + CSRF + sessão regenerada)
- **CRUD completo** com upload validado (JPG/PNG, máx 8MB) e **redimensionamento automático** (1600px) via GD
- Proteção contra **SQL Injection** (PDO + prepared statements em 100% das consultas)
- `.htaccess` bloqueando execução de PHP dentro de `/uploads`

## 📁 Estrutura
```
/
├── config.php             ← credenciais + sessão segura
├── index.php              ← catálogo público
├── produto.php            ← página de detalhe
├── login.php / logout.php ← autenticação admin
├── includes/header.php
├── includes/footer.php
├── admin/
│   ├── _auth.php          ← guarda de sessão
│   ├── admin.php          ← listagem CRUD
│   ├── produto_form.php   ← criar/editar + uploads
│   └── produto_excluir.php
├── assets/css/style.css   ← design system dos 2 temas
├── uploads/               ← imagens enviadas
└── sql/schema.sql         ← banco de dados
```

Bom uso! 💎
