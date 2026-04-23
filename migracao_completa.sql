-- =====================================================
--  MIGRAÇÃO DO BANCO DE DADOS - E-COMMERCE DE LUXO
--  Execute este script para criar/atualizar todas as tabelas
-- =====================================================

-- 1. Tabela de categorias (se não existir)
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL UNIQUE,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `descricao` TEXT DEFAULT NULL,
  `ordem` INT NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`slug`),
  INDEX (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insere categorias padrão
INSERT INTO `categorias` (`nome`, `slug`, `descricao`, `ordem`, `ativo`) VALUES
('Esmeraldas', 'esmeraldas', 'Pedras preciosas esmeraldas de alta qualidade', 1, 1),
('Esculturas', 'esculturas', 'Obras de arte em escultura', 2, 1),
('Cangas', 'cangas', 'Cangas de seda e tecidos premium', 3, 1)
ON DUPLICATE KEY UPDATE nome = nome;

-- 2. Tabela de produtos com código de registro
CREATE TABLE IF NOT EXISTS `produtos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo_registro` VARCHAR(50) DEFAULT NULL,
  `nome` VARCHAR(180) NOT NULL,
  `categoria` VARCHAR(100) NOT NULL,
  `preco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `descricao_curta` VARCHAR(255) DEFAULT NULL,
  `especificacoes_tecnicas` TEXT,
  `imagem_url` VARCHAR(255) DEFAULT NULL,
  `video_url` VARCHAR(500) DEFAULT NULL,
  `video_destaque` VARCHAR(500) DEFAULT NULL,
  `estoque` INT NOT NULL DEFAULT 0,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`categoria`),
  UNIQUE INDEX (`codigo_registro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Galeria de imagens dos produtos
CREATE TABLE IF NOT EXISTS `produto_imagens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `produto_id` INT NOT NULL,
  `imagem_url` VARCHAR(255) NOT NULL,
  FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabela de administradores
CREATE TABLE IF NOT EXISTS `administradores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario` VARCHAR(60) NOT NULL UNIQUE,
  `email` VARCHAR(180) DEFAULT NULL,
  `senha_hash` VARCHAR(255) NOT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin padrão: admin / admin123
INSERT INTO `administradores` (`usuario`, `senha_hash`, `email`)
VALUES ('admin', '$2y$10$8K1p/a0dURXAm7QiTRqeYeNoZ3K9w4qj9oZk3y5fS8cNJ0K6fO0Bm', 'admin@maisondeluxo.com')
ON DUPLICATE KEY UPDATE usuario = usuario;

-- 5. Tabela de clientes
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(120) NOT NULL,
  `email` VARCHAR(180) NOT NULL UNIQUE,
  `senha_hash` VARCHAR(255) NOT NULL,
  `cpf_cnpj` VARCHAR(14) DEFAULT NULL,
  `telefone_whatsapp` VARCHAR(15) DEFAULT NULL,
  `foto_perfil_url` VARCHAR(500) DEFAULT NULL,
  `endereco_rua` VARCHAR(255) DEFAULT NULL,
  `endereco_numero` VARCHAR(20) DEFAULT NULL,
  `endereco_bairro` VARCHAR(100) DEFAULT NULL,
  `endereco_cidade` VARCHAR(100) DEFAULT NULL,
  `endereco_estado` CHAR(2) DEFAULT NULL,
  `endereco_cep` VARCHAR(9) DEFAULT NULL,
  `data_nascimento` DATE DEFAULT NULL,
  `preferencias` TEXT DEFAULT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`email`),
  INDEX (`cpf_cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabela de favoritos
CREATE TABLE IF NOT EXISTS `favoritos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cliente_id` INT NOT NULL,
  `produto_id` INT NOT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_cliente_produto` (`cliente_id`, `produto_id`),
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`) ON DELETE CASCADE,
  INDEX (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabela de PEDIDOS (CRÍTICO - era o erro relatado)
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

-- 8. Tabela de cupons
CREATE TABLE IF NOT EXISTS `cupons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(50) NOT NULL UNIQUE,
  `descricao` TEXT DEFAULT NULL,
  `tipo_desconto` ENUM('percentual', 'fixo') NOT NULL DEFAULT 'percentual',
  `valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `minimo_compra` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `validade` DATE DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `usos_maximos` INT DEFAULT NULL,
  `usos_total` INT NOT NULL DEFAULT 0,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`codigo`),
  INDEX (`ativo`),
  INDEX (`validade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cupom de exemplo
INSERT INTO `cupons` (`codigo`, `descricao`, `tipo_desconto`, `valor`, `minimo_compra`, `validade`, `ativo`)
VALUES 
('PRIMEIRA10', 'Desconto para primeira compra', 'percentual', 10.00, 100.00, DATE_ADD(NOW(), INTERVAL 30 DAY), 1),
('LUXO50', 'R$ 50 de desconto', 'fixo', 50.00, 500.00, DATE_ADD(NOW(), INTERVAL 60 DAY), 1)
ON DUPLICATE KEY UPDATE codigo = codigo;

-- =====================================================
--  PRODUTOS DE EXEMPLO
-- =====================================================
INSERT INTO `produtos` (`nome`,`categoria`,`preco`,`descricao_curta`,`especificacoes_tecnicas`,`imagem_url`,`estoque`) VALUES
('Esmeralda Colombiana 3.2ct','Esmeraldas',24800.00,'Pedra natural lapidação oval','Quilates: 3.2ct\nOrigem: Muzo, Colômbia\nLapidação: Oval\nCertificado: GIA','uploads/sample-emerald.jpg',1),
('Escultura em Bronze "Vento"','Esculturas',9800.00,'Peça única assinada','Material: Bronze patinado\nAltura: 62cm\nPeso: 8.4kg\nAssinatura: Numerada 1/8','uploads/sample-sculpture.jpg',1),
('Canga Seda Pura - Atlântica','Cangas',890.00,'Estampa exclusiva pintada à mão','Material: 100% seda\nDimensões: 140x90cm\nAcabamento: Bainha rolê manual','uploads/sample-canga.jpg',12)
ON DUPLICATE KEY UPDATE nome = nome;

-- =====================================================
--  VERIFICAÇÃO FINAL
-- =====================================================
SELECT 'Migração concluída com sucesso!' AS status;
SELECT COUNT(*) AS total_categorias FROM categorias;
SELECT COUNT(*) AS total_produtos FROM produtos;
SELECT COUNT(*) AS total_clientes FROM clientes;
SELECT 'Tabela pedidos criada com cliente_id' AS verificacao_pedidos;
DESCRIBE pedidos;
