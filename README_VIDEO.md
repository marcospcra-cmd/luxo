# Funcionalidade de Vídeo - Implementação

## Resumo das Alterações

### 1. Banco de Dados
Execute o script SQL para adicionar as novas colunas:
```sql
ALTER TABLE produtos 
ADD COLUMN video_url VARCHAR(500) DEFAULT NULL AFTER imagem_url,
ADD COLUMN video_destaque VARCHAR(500) DEFAULT NULL AFTER video_url;
```

### 2. Painel Administrativo (admin/produto_form.php)
- **Upload de vídeo do produto**: MP4, WebM ou Ogg (máx 50MB)
- **URL de vídeo de destaque**: YouTube ou Vimeo para exibição na página inicial
- Validação de tipo e tamanho
- Preview do vídeo no formulário de edição

### 3. Painel de Listagem (admin/admin.php)
- Nova coluna "Vídeo" mostrando badges:
  - "✓ Vídeo" - quando há vídeo uploadado
  - "Destaque" - quando é o vídeo em destaque na home

### 4. Página Inicial (index.php)
- Exibe vídeo de destaque (YouTube/Vimeo) na seção hero
- Conversão automática de URL regular para embed
- Suporte para YouTube e Vimeo

### 5. Página do Produto (produto.php)
- Exibe vídeo do produto abaixo da galeria de imagens
- Player HTML5 nativo com controles

## Como Usar

### Adicionar Vídeo a um Produto:
1. Acesse o painel administrativo
2. Edite ou crie um produto
3. Upload de vídeo: selecione arquivo MP4/WebM/Ogg (até 50MB)
4. Vídeo de destaque: cole URL do YouTube/Vimeo
5. Salve

### Vídeo de Destaque na Home:
- O sistema exibe automaticamente o primeiro vídeo de destaque cadastrado
- URLs suportadas:
  - `https://www.youtube.com/watch?v=VIDEO_ID`
  - `https://youtu.be/VIDEO_ID`
  - `https://vimeo.com/VIDEO_ID`

## Estrutura de Arquivos
```
/workspace/
├── admin/
│   ├── produto_form.php  (formulário com upload de vídeo)
│   └── admin.php         (tabela com indicador de vídeo)
├── index.php             (página inicial com vídeo de destaque)
├── produto.php           (página do produto com vídeo)
├── uploads/              (diretório para vídeos e imagens)
└── update_schema.sql     (script SQL para atualizar banco)
```
