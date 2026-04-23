-- ============================================================
-- SOLAR AMAZÔNIA - Schema Completo e Unificado
-- Versão: 1.0 (Refatoração Total)
-- Descrição: Estrutura limpa sem inconsistências
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- -------------------------------------------------------------
-- 1. CRIAÇÃO DO BANCO DE DADOS (Se não existir)
-- -------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `solar_amazonia` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `solar_amazonia`;

-- -------------------------------------------------------------
-- 2. TABELA: CLIENTES
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `senha_hash` VARCHAR(255) NOT NULL,
  `telefone` VARCHAR(20) DEFAULT NULL,
  `endereco` TEXT DEFAULT NULL,
  `cidade` VARCHAR(100) DEFAULT NULL,
  `estado` VARCHAR(2) DEFAULT NULL,
  `cep` VARCHAR(10) DEFAULT NULL,
  `pais` VARCHAR(100) DEFAULT 'Brasil',
  `is_admin` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 3. TABELA: PRODUTOS (Sistema de Identificação Profissional)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `produtos`;
CREATE TABLE `produtos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  
  -- Identificação Comercial e Única
  `sku` VARCHAR(20) NOT NULL UNIQUE, -- Ex: EMR-ESC-00045 (Gerado automaticamente ou manual)
  `nome` VARCHAR(255) NOT NULL,
  `descricao_curta` VARCHAR(500) DEFAULT NULL,
  `descricao_completa` TEXT DEFAULT NULL,
  `historia_obra` TEXT DEFAULT NULL, -- História detalhada da peça
  
  -- Preços e Estoque
  `preco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `preco_promocional` DECIMAL(10,2) DEFAULT NULL,
  `estoque` INT DEFAULT 1,
  
  -- Identificação Técnica e Rastreio
  `codigo_peca` VARCHAR(50) DEFAULT NULL, -- Legado
  `codigo_rastreio_interno` VARCHAR(50) DEFAULT NULL, -- Código de barras/QR interno
  `certificado_id` VARCHAR(50) DEFAULT NULL, -- ID do Certificado de Autenticidade
  
  -- Especificações Físicas
  `categoria` ENUM('Esculturas', 'Joias', 'Cangas', 'Esmeraldas', 'Outros') DEFAULT 'Outros',
  `material` VARCHAR(100) DEFAULT NULL,
  `peso` VARCHAR(50) DEFAULT NULL,
  `dimensoes` VARCHAR(100) DEFAULT NULL,
  
  -- Mídia
  `imagem_url` VARCHAR(500) DEFAULT NULL,
  `imagens` TEXT DEFAULT NULL, -- JSON
  `video_url` VARCHAR(500) DEFAULT NULL,
  
  -- Regras de Negócio e Status
  `tipo_edicao` ENUM('unica', 'limitada', 'aberta') DEFAULT 'unica',
  `numero_edicao` INT DEFAULT NULL, -- Ex: 5 de 50 (se for limitada)
  `total_edicoes` INT DEFAULT NULL,
  `status_disponibilidade` ENUM('disponivel', 'reservado', 'em_transito', 'vendida', 'conservacao', 'leilao') DEFAULT 'disponivel',
  
  -- Controle Interno
  `ativo` TINYINT(1) DEFAULT 1,
  `destaque` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- Índices para performance
  INDEX `idx_sku` (`sku`),
  INDEX `idx_status` (`status_disponibilidade`),
  INDEX `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 4. TABELA: PEDIDOS
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `pedidos`;
CREATE TABLE `pedidos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cliente_id` INT NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `status` ENUM('pendente','pago','enviado','entregue','cancelado','reembolsado') DEFAULT 'pendente',
  `stripe_session_id` VARCHAR(255) DEFAULT NULL,
  `stripe_payment_intent` VARCHAR(255) DEFAULT NULL,
  `endereco_entrega` TEXT DEFAULT NULL,
  `codigo_rastreio` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5. TABELA: ITENS_DO_PEDIDO
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `itens_do_pedido`;
CREATE TABLE `itens_do_pedido` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` INT NOT NULL,
  `produto_id` INT NOT NULL,
  `quantidade` INT NOT NULL DEFAULT 1,
  `preco_unitario` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`pedido_id`) REFERENCES `pedidos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 6. TABELA: CUPONS
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `cupons`;
CREATE TABLE `cupons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(50) NOT NULL UNIQUE,
  `tipo` ENUM('percentual','fixo') DEFAULT 'percentual',
  `valor` DECIMAL(10,2) NOT NULL,
  `minimo_compra` DECIMAL(10,2) DEFAULT 0.00,
  `validade` DATE DEFAULT NULL,
  `ativo` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 7. TABELA: FAVORITOS
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `favoritos`;
CREATE TABLE `favoritos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cliente_id` INT NOT NULL,
  `produto_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_favorito` (`cliente_id`, `produto_id`),
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 8. DADOS INICIAIS (Seed)
-- -------------------------------------------------------------

-- Admin padrão (senha: admin123)
INSERT INTO `clientes` (`nome`, `email`, `senha_hash`, `is_admin`) VALUES
('Administrador', 'admin@solaramazonia.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Cupom de boas-vindas
INSERT INTO `cupons` (`codigo`, `tipo`, `valor`, `minimo_compra`, `validade`) VALUES
('BEMVINDO10', 'percentual', 10.00, 100.00, DATE_ADD(NOW(), INTERVAL 30 DAY));

-- Produto de Exemplo 1 (Com SKU e Identificação Completa)
INSERT INTO `produtos` (`sku`, `nome`, `descricao_curta`, `descricao_completa`, `historia_obra`, `preco`, `categoria`, `codigo_peca`, `codigo_rastreio_interno`, `certificado_id`, `material`, `peso`, `dimensoes`, `estoque`, `tipo_edicao`, `status_disponibilidade`, `ativo`, `destaque`, `imagens`) VALUES
('ESC-SOL-00001', 'Escultura Solar Dourada', 'Uma peça única inspirada no sol da Amazônia.', 'Esta obra exclusiva foi esculpida manualmente por artesãos locais, utilizando técnicas tradicionais combinadas com design contemporâneo. A peça representa a força e a beleza do sol amazônico, trazendo energia e sofisticação para qualquer ambiente.', 'A madeira utilizada foi extraída de forma sustentável na região do Rio Negro, passando por um processo de secagem natural de 2 anos antes da escultura.', 1250.00, 'Esculturas', 'SOL-001', 'INT-8473629', 'CERT-2024-001', 'Madeira de Lei e Resina Dourada', '2.5 kg', '30cm x 30cm x 15cm', 1, 'unica', 'disponivel', 1, 1, '["https://images.unsplash.com/photo-1618331835717-801e976710b2?auto=format&fit=crop&w=800&q=80", "https://images.unsplash.com/photo-1549887534-1541e9326642?auto=format&fit=crop&w=800&q=80"]');

-- Produto de Exemplo 2 (Edição Limitada)
INSERT INTO `produtos` (`sku`, `nome`, `descricao_curta`, `descricao_completa`, `historia_obra`, `preco`, `categoria`, `codigo_peca`, `codigo_rastreio_interno`, `certificado_id`, `material`, `peso`, `dimensoes`, `estoque`, `tipo_edicao`, `numero_edicao`, `total_edicoes`, `status_disponibilidade`, `ativo`, `destaque`, `imagens`) VALUES
('JOI-ESM-00042', 'Colar Esmeralda Real', 'Colar sofisticado com esmeraldas genuínas.', 'Um colar deslumbrante featuring esmeraldas brasileiras de alta qualidade, montadas em ouro 18k. Cada pedra foi selecionada criteriosamente por sua cor vibrante e pureza.', 'As esmeraldas foram garimpadas legalmente no estado do Pará, com certificação de origem ética. O design foi criado pelo ourives mestre João Silva em 2023.', 3500.00, 'Joias', 'ESM-042', 'INT-9384756', 'CERT-2024-042', 'Ouro 18k e Esmeralda', '15g', '45cm de comprimento', 3, 'limitada', 5, 50, 'disponivel', 1, 1, '["https://images.unsplash.com/photo-1599643478518-17488fbbcd75?auto=format&fit=crop&w=800&q=80"]');

-- Produto de Exemplo 3 (Canga Artesanal)
INSERT INTO `produtos` (`sku`, `nome`, `descricao_curta`, `descricao_completa`, `historia_obra`, `preco`, `categoria`, `codigo_peca`, `certificado_id`, `material`, `peso`, `dimensoes`, `estoque`, `tipo_edicao`, `status_disponibilidade`, `ativo`, `destaque`, `imagens`) VALUES
('CAN-TEX-00015', 'Canga Estampa Folhas', 'Tecido artesonal com estampas de folhas amazônicas.', 'Canga produzida em tear manual por comunidades ribeirinhas, utilizando tintas naturais extraídas de sementes e raízes da floresta.', 'Produzida pela Associação das Mulheres Artesãs do Tapajós, esta peça preserva técnicas ancestrais de tecelagem indígena.', 180.00, 'Cangas', 'CNG-015', 'CERT-2024-015', 'Algodão Natural e Tintas Vegetais', '400g', '180cm x 110cm', 10, 'aberta', 'disponivel', 1, 0, '["https://images.unsplash.com/photo-1606297341956-4d2c7d5e6c68?auto=format&fit=crop&w=800&q=80"]');

COMMIT;
