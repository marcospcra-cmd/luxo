-- Adiciona colunas de vídeo à tabela produtos
ALTER TABLE produtos 
ADD COLUMN IF NOT EXISTS video_url VARCHAR(500) DEFAULT NULL AFTER imagem_url,
ADD COLUMN IF NOT EXISTS video_destaque VARCHAR(500) DEFAULT NULL AFTER video_url;

-- Adiciona coluna de código de registro único para cada peça
ALTER TABLE produtos 
ADD COLUMN IF NOT EXISTS codigo_registro VARCHAR(50) DEFAULT NULL AFTER id;

-- Cria índice único para código de registro
CREATE UNIQUE INDEX IF NOT EXISTS idx_codigo_registro ON produtos(codigo_registro);

-- Cria tabela de categorias dinâmicas
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

-- Atualiza produtos existentes para usar o ID da categoria (opcional - migração futura)
-- Por enquanto, mantemos a coluna categoria como VARCHAR para compatibilidade

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

-- Tabela de cupons de desconto
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
