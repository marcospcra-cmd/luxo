-- Tabela de perfis de usuários vinculada ao Auth do Supabase
CREATE TABLE IF NOT EXISTS perfis_usuarios (
    id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
    nome_completo TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    cpf_cnpj VARCHAR(14),
    telefone_whatsapp VARCHAR(15),
    foto_perfil_url TEXT,
    endereco_rua TEXT,
    endereco_numero VARCHAR(20),
    endereco_bairro TEXT,
    endereco_cidade TEXT,
    endereco_estado CHAR(2),
    endereco_cep VARCHAR(9),
    data_nascimento DATE,
    preferencias TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Índice para buscas por email
CREATE INDEX IF NOT EXISTS idx_perfis_email ON perfis_usuarios(email);

-- Índice para buscas por CPF/CNPJ
CREATE INDEX IF NOT EXISTS idx_perfis_cpf_cnpj ON perfis_usuarios(cpf_cnpj);

-- Função para atualizar automaticamente o updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger para atualizar updated_at
DROP TRIGGER IF EXISTS update_perfis_usuarios_updated_at ON perfis_usuarios;
CREATE TRIGGER update_perfis_usuarios_updated_at
    BEFORE UPDATE ON perfis_usuarios
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Políticas de Segurança (RLS)
ALTER TABLE perfis_usuarios ENABLE ROW LEVEL SECURITY;

-- Política: Usuários podem ver apenas seu próprio perfil
CREATE POLICY "Usuários podem ver próprio perfil"
    ON perfis_usuarios
    FOR SELECT
    USING (auth.uid() = id);

-- Política: Usuários podem editar apenas seu próprio perfil
CREATE POLICY "Usuários podem editar próprio perfil"
    ON perfis_usuarios
    FOR UPDATE
    USING (auth.uid() = id);

-- Política: Usuários podem inserir seu próprio perfil (após registro)
CREATE POLICY "Usuários podem inserir próprio perfil"
    ON perfis_usuarios
    FOR INSERT
    WITH CHECK (auth.uid() = id);

-- Política: Administradores podem ver todos os perfis
-- Primeiro, criamos uma função para verificar se o usuário é admin
CREATE OR REPLACE FUNCTION is_admin()
RETURNS BOOLEAN AS $$
DECLARE
    user_email TEXT;
    is_admin_user BOOLEAN;
BEGIN
    user_email := auth.jwt()->>'email';
    SELECT INTO is_admin_user EXISTS (
        SELECT 1 FROM admins WHERE email = user_email
    );
    RETURN is_admin_user;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Política para administradores verem todos os perfis
CREATE POLICY "Administradores podem ver todos os perfis"
    ON perfis_usuarios
    FOR SELECT
    USING (is_admin());

-- Política para administradores editarem qualquer perfil (se necessário)
CREATE POLICY "Administradores podem editar todos os perfis"
    ON perfis_usuarios
    FOR UPDATE
    USING (is_admin());

-- View para estatísticas de clientes (para o dashboard admin)
CREATE OR REPLACE VIEW view_estatisticas_clientes AS
SELECT 
    COUNT(*) as total_clientes,
    COUNT(*) FILTER (WHERE created_at >= NOW() - INTERVAL '30 days') as novos_clientes_30d,
    COUNT(*) FILTER (WHERE foto_perfil_url IS NOT NULL) as clientes_com_foto,
    COUNT(*) FILTER (WHERE cpf_cnpj IS NOT NULL) as clientes_com_documento
FROM perfis_usuarios;

-- Comentário nas colunas para documentação
COMMENT ON TABLE perfis_usuarios IS 'Perfis de usuários vinculados ao sistema de autenticação';
COMMENT ON COLUMN perfis_usuarios.cpf_cnpj IS 'CPF ou CNPJ para emissão de Nota Fiscal (somente números)';
COMMENT ON COLUMN perfis_usuarios.preferencias IS 'Preferências do cliente: Esmeraldas, Esculturas, Cangas, etc.';
