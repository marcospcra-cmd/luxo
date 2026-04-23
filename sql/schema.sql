-- =====================================================
--  E-COMMERCE DE LUXO - ESQUEMA DO BANCO DE DADOS
--  Compatível com MySQL 5.7+ / MariaDB (Hostinger)
-- =====================================================

CREATE TABLE IF NOT EXISTS `produtos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(180) NOT NULL,
  `categoria` ENUM('Esmeraldas','Esculturas','Cangas') NOT NULL,
  `preco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `descricao_curta` VARCHAR(255) DEFAULT NULL,
  `especificacoes_tecnicas` TEXT,
  `imagem_url` VARCHAR(255) DEFAULT NULL,
  `estoque` INT NOT NULL DEFAULT 0,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Galeria secundária (várias imagens por produto)
CREATE TABLE IF NOT EXISTS `produto_imagens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `produto_id` INT NOT NULL,
  `imagem_url` VARCHAR(255) NOT NULL,
  FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de administradores
CREATE TABLE IF NOT EXISTS `administradores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario` VARCHAR(60) NOT NULL UNIQUE,
  `senha_hash` VARCHAR(255) NOT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
--  CLIENTES (login público) e FAVORITOS
-- =====================================================
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(120) NOT NULL,
  `email` VARCHAR(180) NOT NULL UNIQUE,
  `senha_hash` VARCHAR(255) NOT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Usuário admin padrão -> login: admin  | senha: admin123
-- IMPORTANTE: troque a senha após o primeiro login.
INSERT INTO `administradores` (`usuario`, `senha_hash`)
VALUES ('admin', '$2y$10$8K1p/a0dURXAm7QiTRqeYeNoZ3K9w4qj9oZk3y5fS8cNJ0K6fO0Bm')
ON DUPLICATE KEY UPDATE usuario = usuario;

-- Produtos de exemplo
INSERT INTO `produtos` (`nome`,`categoria`,`preco`,`descricao_curta`,`especificacoes_tecnicas`,`imagem_url`,`estoque`) VALUES
('Esmeralda Colombiana 3.2ct','Esmeraldas',24800.00,'Pedra natural lapidação oval','Quilates: 3.2ct\nOrigem: Muzo, Colômbia\nLapidação: Oval\nCertificado: GIA','uploads/sample-emerald.jpg',1),
('Escultura em Bronze "Vento"','Esculturas',9800.00,'Peça única assinada','Material: Bronze patinado\nAltura: 62cm\nPeso: 8.4kg\nAssinatura: Numerada 1/8','uploads/sample-sculpture.jpg',1),
('Canga Seda Pura - Atlântica','Cangas',890.00,'Estampa exclusiva pintada à mão','Material: 100% seda\nDimensões: 140x90cm\nAcabamento: Bainha rolê manual','uploads/sample-canga.jpg',12);
