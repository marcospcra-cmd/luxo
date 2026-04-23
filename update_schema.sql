-- Adiciona colunas de vídeo à tabela produtos
ALTER TABLE produtos 
ADD COLUMN IF NOT EXISTS video_url VARCHAR(500) DEFAULT NULL AFTER imagem_url,
ADD COLUMN IF NOT EXISTS video_destaque VARCHAR(500) DEFAULT NULL AFTER video_url;

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
