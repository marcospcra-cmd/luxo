-- =====================================================
-- MIGRAÇÃO: Sistema de Perfis de Usuários
-- E-commerce de Luxo - Maison de Luxo
-- =====================================================

-- Adicionar campos de perfil à tabela clientes (se ainda não existir)
-- Nota: Para MySQL 5.7+, use múltiplos ALTER TABLE separados
ALTER TABLE `clientes` ADD COLUMN `cpf_cnpj` VARCHAR(14) DEFAULT NULL AFTER `senha_hash`;
ALTER TABLE `clientes` ADD COLUMN `telefone_whatsapp` VARCHAR(15) DEFAULT NULL AFTER `cpf_cnpj`;
ALTER TABLE `clientes` ADD COLUMN `foto_perfil_url` VARCHAR(500) DEFAULT NULL AFTER `telefone_whatsapp`;
ALTER TABLE `clientes` ADD COLUMN `endereco_rua` VARCHAR(255) DEFAULT NULL AFTER `foto_perfil_url`;
ALTER TABLE `clientes` ADD COLUMN `endereco_numero` VARCHAR(20) DEFAULT NULL AFTER `endereco_rua`;
ALTER TABLE `clientes` ADD COLUMN `endereco_bairro` VARCHAR(100) DEFAULT NULL AFTER `endereco_numero`;
ALTER TABLE `clientes` ADD COLUMN `endereco_cidade` VARCHAR(100) DEFAULT NULL AFTER `endereco_bairro`;
ALTER TABLE `clientes` ADD COLUMN `endereco_estado` CHAR(2) DEFAULT NULL AFTER `endereco_cidade`;
ALTER TABLE `clientes` ADD COLUMN `endereco_cep` VARCHAR(9) DEFAULT NULL AFTER `endereco_estado`;
ALTER TABLE `clientes` ADD COLUMN `data_nascimento` DATE DEFAULT NULL AFTER `endereco_cep`;
ALTER TABLE `clientes` ADD COLUMN `preferencias` TEXT DEFAULT NULL AFTER `data_nascimento`;
ALTER TABLE `clientes` ADD COLUMN `atualizado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `criado_em`;

-- Criar índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_clientes_email ON `clientes`(`email`);
CREATE INDEX IF NOT EXISTS idx_clientes_cpf_cnpj ON `clientes`(`cpf_cnpj`);

-- Adicionar coluna email na tabela administradores (para integração com RLS do Supabase)
ALTER TABLE `administradores` ADD COLUMN `email` VARCHAR(180) DEFAULT NULL AFTER `usuario`;

-- Atualizar admin padrão com email (se necessário)
UPDATE `administradores` SET email = 'admin@maisondeluxo.com' WHERE usuario = 'admin' AND (email IS NULL OR email = '');

-- Tabela de cupons (para funcionalidade já implementada)
CREATE TABLE IF NOT EXISTS `cupons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(50) NOT NULL UNIQUE,
  `tipo` ENUM('percentual', 'fixo') NOT NULL DEFAULT 'percentual',
  `valor` DECIMAL(10,2) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `compra_minima` DECIMAL(10,2) DEFAULT 0.00,
  `validade_inicio` DATE DEFAULT NULL,
  `validade_fim` DATE DEFAULT NULL,
  `usos_maximos` INT DEFAULT NULL,
  `usos_count` INT NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`codigo`),
  INDEX (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentários nas colunas para documentação
ALTER TABLE `clientes` COMMENT 'Clientes da loja com perfis completos para gestão de usuários';
ALTER TABLE `cupons` COMMENT 'Cupons de desconto para campanhas promocionais';
