<?php
/**
 * includes/email_service.php
 * -----------------------------------------------------
 *  Serviço de E-mail para Maison de Luxo
 *  Funcionalidades: Confirmação de pedidos, Recuperação de carrinho abandonado
 *  PHP 8.2+ Orientado a Objetos
 * -----------------------------------------------------
 */

namespace MaisonDeLuxo\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    /**
     * Configurações de e-mail
     * Em produção, usar SMTP real (ex: SendGrid, Amazon SES, Mailgun)
     */
    private const SMTP_HOST = 'smtp.gmail.com';
    private const SMTP_PORT = 587;
    private const SMTP_USER = 'noreply@maisondeluxo.com'; // Substituir pelo seu e-mail
    private const SMTP_PASS = ''; // Substituir pela senha/app password
    private const SMTP_SECURE = 'tls';
    
    /**
     * Remetente padrão
     */
    private const FROM_EMAIL = 'noreply@maisondeluxo.com';
    private const FROM_NAME = 'Maison de Luxo';
    
    /**
     * URL base do site
     */
    private string $siteUrl;
    
    /**
     * Instância do PHPMailer
     */
    private ?PHPMailer $mailer = null;

    /**
     * Construtor
     * @param string|null $siteUrl URL base do site
     */
    public function __construct(?string $siteUrl = null)
    {
        $this->siteUrl = $siteUrl ?? SITE_URL ?? 'https://maisondeluxo.com';
    }

    /**
     * Configura o PHPMailer
     * @return PHPMailer
     */
    private function getMailer(): PHPMailer
    {
        if ($this->mailer === null) {
            $this->mailer = new PHPMailer(true);
            
            // Configurações do servidor
            $this->mailer->isSMTP();
            $this->mailer->Host = self::SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = self::SMTP_USER;
            $this->mailer->Password = self::SMTP_PASS;
            $this->mailer->SMTPSecure = self::SMTP_SECURE;
            $this->mailer->Port = self::SMTP_PORT;
            
            // Charset e encoding
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            
            // Remetente
            $this->mailer->setFrom(self::FROM_EMAIL, self::FROM_NAME);
            
            // Modo debug em desenvolvimento
            if (defined('APP_ENV') && APP_ENV === 'development') {
                $this->mailer->SMTPDebug = 0; // 2 para ver output
            }
        }
        
        return $this->mailer;
    }

    /**
     * Envia e-mail de confirmação de pedido
     * 
     * @param array $pedido Dados do pedido
     * @param array $itens Itens do pedido
     * @param string $clienteEmail E-mail do cliente
     * @param string $clienteNome Nome do cliente
     * @return bool True se enviado com sucesso
     */
    public function enviarConfirmacaoPedido(
        array $pedido,
        array $itens,
        string $clienteEmail,
        string $clienteNome
    ): bool {
        try {
            $mailer = $this->getMailer();
            $mailer->clearAddresses();
            $mailer->addAddress($clienteEmail, $clienteNome);
            
            $mailer->Subject = '✓ Confirmação de Pedido #' . $pedido['id'];
            $mailer->isHTML(true);
            $mailer->Body = $this->renderTemplateConfirmacaoPedido($pedido, $itens, $clienteNome);
            $mailer->AltBody = $this->renderTextoConfirmacaoPedido($pedido, $itens, $clienteNome);
            
            $result = $mailer->send();
            
            // Log do envio
            error_log("[EMAIL] Confirmação de pedido #{$pedido['id']} enviada para {$clienteEmail}");
            
            return $result;
            
        } catch (Exception $e) {
            error_log("[EMAIL ERRO] Falha ao enviar confirmação de pedido #{$pedido['id']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia e-mail de recuperação de carrinho abandonado
     * Com cupom de desconto para incentivar a compra
     * 
     * @param string $clienteEmail E-mail do cliente
     * @param string $clienteNome Nome do cliente
     * @param array $carrinhoItens Itens no carrinho
     * @param float $carrinhoTotal Valor total do carrinho
     * @param string $cupomCodigo Código do cupom de desconto
     * @param int $cupomDesconto Percentual de desconto
     * @return bool True se enviado com sucesso
     */
    public function enviarRecuperacaoCarrinho(
        string $clienteEmail,
        string $clienteNome,
        array $carrinhoItens,
        float $carrinhoTotal,
        string $cupomCodigo = 'VOLTE10',
        int $cupomDesconto = 10
    ): bool {
        try {
            $mailer = $this->getMailer();
            $mailer->clearAddresses();
            $mailer->addAddress($clienteEmail, $clienteNome);
            
            $mailer->Subject = '🎁 Você esqueceu algo especial...';
            $mailer->isHTML(true);
            $mailer->Body = $this->renderTemplateCarrinhoAbandonado(
                $clienteNome,
                $carrinhoItens,
                $carrinhoTotal,
                $cupomCodigo,
                $cupomDesconto
            );
            $mailer->AltBody = $this->renderTextoCarrinhoAbandonado(
                $clienteNome,
                $carrinhoItens,
                $carrinhoTotal,
                $cupomCodigo,
                $cupomDesconto
            );
            
            $result = $mailer->send();
            
            // Log do envio
            error_log("[EMAIL] Recuperação de carrinho enviada para {$clienteEmail} com cupom {$cupomCodigo}");
            
            return $result;
            
        } catch (Exception $e) {
            error_log("[EMAIL ERRO] Falha ao enviar recuperação de carrinho para {$clienteEmail}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia e-mail de boas-vindas para novo cliente
     * 
     * @param string $clienteEmail E-mail do cliente
     * @param string $clienteNome Nome do cliente
     * @return bool True se enviado com sucesso
     */
    public function enviarBoasVindas(string $clienteEmail, string $clienteNome): bool
    {
        try {
            $mailer = $this->getMailer();
            $mailer->clearAddresses();
            $mailer->addAddress($clienteEmail, $clienteNome);
            
            $mailer->Subject = '✨ Bem-vindo à Maison de Luxo';
            $mailer->isHTML(true);
            $mailer->Body = $this->renderTemplateBoasVindas($clienteNome);
            $mailer->AltBody = $this->renderTextoBoasVindas($clienteNome);
            
            $result = $mailer->send();
            
            error_log("[EMAIL] Boas-vindas enviada para {$clienteEmail}");
            
            return $result;
            
        } catch (Exception $e) {
            error_log("[EMAIL ERRO] Falha ao enviar boas-vindas para {$clienteEmail}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Renderiza template HTML de confirmação de pedido
     */
    private function renderTemplateConfirmacaoPedido(array $pedido, array $itens, string $clienteNome): string
    {
        $itensHTML = '';
        foreach ($itens as $item) {
            $itensHTML .= sprintf('
                <tr>
                    <td style="padding: 12px 0; border-bottom: 1px solid #e0e0e0;">
                        <strong>%s</strong><br>
                        <small style="color: #666;">Quantidade: %d</small>
                    </td>
                    <td style="padding: 12px 0; border-bottom: 1px solid #e0e0e0; text-align: right;">
                        R$ %s
                    </td>
                </tr>
            ',
                htmlspecialchars($item['nome']),
                $item['quantidade'],
                number_format($item['preco'] * $item['quantidade'], 2, ',', '.')
            );
        }
        
        return sprintf('
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body { font-family: Georgia, serif; background: #f5f5f5; margin: 0; padding: 20px; }
                    .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #1a1a1a 0%%, #2d2d2d 100%%); color: #d4af37; padding: 40px 30px; text-align: center; }
                    .header h1 { margin: 0; font-size: 28px; font-weight: 400; letter-spacing: 2px; }
                    .content { padding: 30px; }
                    .greeting { font-size: 18px; color: #333; margin-bottom: 20px; }
                    .message { color: #666; line-height: 1.6; margin-bottom: 30px; }
                    .items-table { width: 100%%; border-collapse: collapse; margin: 20px 0; }
                    .total { background: #f9f9f9; padding: 20px; text-align: right; font-size: 20px; color: #d4af37; font-weight: bold; }
                    .footer { background: #f5f5f5; padding: 20px; text-align: center; color: #999; font-size: 12px; }
                    .btn { display: inline-block; padding: 14px 30px; background: #d4af37; color: #fff; text-decoration: none; border-radius: 4px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>MAISON DE LUXO</h1>
                        <p style="margin: 10px 0 0; opacity: 0.8;">Pedido Confirmado</p>
                    </div>
                    <div class="content">
                        <p class="greeting">Olá, %s</p>
                        <p class="message">
                            Agradecemos por sua compra! Seu pedido <strong>#%s</strong> foi confirmado e está sendo processado com todo cuidado.
                            Em breve você receberá informações sobre o envio.
                        </p>
                        
                        <table class="items-table">
                            %s
                        </table>
                        
                        <div class="total">
                            Total: R$ %s
                        </div>
                        
                        <div style="text-align: center;">
                            <a href="%s/pedidos.php?id=%s" class="btn">Acompanhar Pedido</a>
                        </div>
                    </div>
                    <div class="footer">
                        <p>Maison de Luxo © %s - Todos os direitos reservados</p>
                        <p>Dúvidas? Entre em contato: contato@maisondeluxo.com</p>
                    </div>
                </div>
            </body>
            </html>
        ',
            $clienteNome,
            $pedido['id'],
            $itensHTML,
            number_format($pedido['total'], 2, ',', '.'),
            $this->siteUrl,
            $pedido['id'],
            date('Y')
        );
    }

    /**
     * Renderiza versão em texto puro da confirmação de pedido
     */
    private function renderTextoConfirmacaoPedido(array $pedido, array $itens, string $clienteNome): string
    {
        $texto = "Olá, {$clienteNome}\n\n";
        $texto .= "Seu pedido #{$pedido['id']} foi confirmado!\n\n";
        $texto .= "Itens:\n";
        
        foreach ($itens as $item) {
            $texto .= "- {$item['nome']} (x{$item['quantidade']}): R$ " . number_format($item['preco'] * $item['quantidade'], 2, ',', '.') . "\n";
        }
        
        $texto .= "\nTotal: R$ " . number_format($pedido['total'], 2, ',', '.') . "\n";
        $texto .= "\nObrigado por comprar na Maison de Luxo!";
        
        return $texto;
    }

    /**
     * Renderiza template HTML de carrinho abandonado
     */
    private function renderTemplateCarrinhoAbandonado(
        string $clienteNome,
        array $carrinhoItens,
        float $carrinhoTotal,
        string $cupomCodigo,
        int $cupomDesconto
    ): string {
        $itensHTML = '';
        foreach ($carrinhoItens as $item) {
            $itensHTML .= sprintf('
                <tr>
                    <td style="padding: 12px 0; border-bottom: 1px solid #e0e0e0;">
                        <strong>%s</strong>
                    </td>
                    <td style="padding: 12px 0; border-bottom: 1px solid #e0e0e0; text-align: right;">
                        R$ %s
                    </td>
                </tr>
            ',
                htmlspecialchars($item['nome']),
                number_format($item['preco'] * ($item['quantidade'] ?? 1), 2, ',', '.')
            );
        }
        
        return sprintf('
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Georgia, serif; background: #f5f5f5; margin: 0; padding: 20px; }
                    .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #d4af37 0%%, #b8941f 100%%); color: #fff; padding: 40px 30px; text-align: center; }
                    .header h1 { margin: 0; font-size: 26px; }
                    .content { padding: 30px; }
                    .coupon { background: #f9f9f9; border: 2px dashed #d4af37; padding: 20px; text-align: center; margin: 20px 0; }
                    .coupon-code { font-size: 32px; color: #d4af37; font-weight: bold; letter-spacing: 4px; }
                    .btn { display: inline-block; padding: 16px 40px; background: #d4af37; color: #fff; text-decoration: none; border-radius: 4px; font-size: 16px; }
                    .footer { background: #f5f5f5; padding: 20px; text-align: center; color: #999; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>🎁 Um Presente Especial Para Você</h1>
                    </div>
                    <div class="content">
                        <p style="font-size: 18px; color: #333;">Olá, %s</p>
                        <p style="color: #666; line-height: 1.6;">
                            Notamos que você deixou algumas peças exclusivas no seu carrinho.
                            Como valorizamos seu interesse, preparamos um desconto especial!
                        </p>
                        
                        <div class="coupon">
                            <p style="margin: 0 0 10px; color: #666;">Use o cupom:</p>
                            <div class="coupon-code">%s</div>
                            <p style="margin: 10px 0 0; color: #666;">%d%% de desconto</p>
                        </div>
                        
                        <table style="width: 100%%; margin: 20px 0;">
                            %s
                        </table>
                        
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="%s/carrinho.php" class="btn">Completar Minha Compra</a>
                        </div>
                        
                        <p style="color: #999; font-size: 14px; text-align: center;">
                            * Cupom válido por 48 horas
                        </p>
                    </div>
                    <div class="footer">
                        <p>Maison de Luxo © %s</p>
                    </div>
                </div>
            </body>
            </html>
        ',
            $clienteNome,
            $cupomCodigo,
            $cupomDesconto,
            $itensHTML,
            $this->siteUrl,
            date('Y')
        );
    }

    /**
     * Renderiza versão em texto puro do carrinho abandonado
     */
    private function renderTextoCarrinhoAbandonado(
        string $clienteNome,
        array $carrinhoItens,
        float $carrinhoTotal,
        string $cupomCodigo,
        int $cupomDesconto
    ): string {
        $texto = "Olá, {$clienteNome}\n\n";
        $texto .= "Você deixou itens especiais no seu carrinho!\n\n";
        $texto .= "Como agradecimento, use o cupom {$cupomCodigo} para ganhar {$cupomDesconto}% de desconto.\n\n";
        
        foreach ($carrinhoItens as $item) {
            $texto .= "- {$item['nome']}: R$ " . number_format($item['preco'] * ($item['quantidade'] ?? 1), 2, ',', '.') . "\n";
        }
        
        $texto .= "\nComplete sua compra em: {$this->siteUrl}/carrinho.php";
        $texto .= "\n\n* Cupom válido por 48 horas";
        
        return $texto;
    }

    /**
     * Renderiza template HTML de boas-vindas
     */
    private function renderTemplateBoasVindas(string $clienteNome): string
    {
        return sprintf('
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Georgia, serif; background: #f5f5f5; margin: 0; padding: 20px; }
                    .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
                    .header { background: #1a1a1a; color: #d4af37; padding: 40px 30px; text-align: center; }
                    .content { padding: 30px; color: #666; line-height: 1.6; }
                    .btn { display: inline-block; padding: 14px 30px; background: #d4af37; color: #fff; text-decoration: none; border-radius: 4px; }
                    .footer { background: #f5f5f5; padding: 20px; text-align: center; color: #999; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1 style="margin: 0;">Bem-vindo à Maison de Luxo</h1>
                    </div>
                    <div class="content">
                        <p>Olá, %s</p>
                        <p>É um prazer tê-lo conosco! Explore nossa curadoria exclusiva de peças raras.</p>
                        <p style="text-align: center; margin: 30px 0;">
                            <a href="%s" class="btn">Explorar Coleção</a>
                        </p>
                    </div>
                    <div class="footer">
                        <p>Maison de Luxo © %s</p>
                    </div>
                </div>
            </body>
            </html>
        ',
            $clienteNome,
            $this->siteUrl,
            date('Y')
        );
    }

    /**
     * Renderiza versão em texto puro de boas-vindas
     */
    private function renderTextoBoasVindas(string $clienteNome): string
    {
        return "Olá, {$clienteNome}\n\nBem-vindo à Maison de Luxo!\n\nExplore nossa coleção exclusiva em: {$this->siteUrl}\n\nObrigado por se juntar a nós!";
    }
}

// Função helper para uso procedural
if (!function_exists('\\MaisonDeLuxo\\Services\\enviar_email_pedido')) {
    function enviar_email_pedido(array $pedido, array $itens, string $email, string $nome): bool
    {
        $service = new \MaisonDeLuxo\Services\EmailService();
        return $service->enviarConfirmacaoPedido($pedido, $itens, $email, $nome);
    }
}

if (!function_exists('\\MaisonDeLuxo\\Services\\enviar_email_carrinho_abandonado')) {
    function enviar_email_carrinho_abandonado(
        string $email,
        string $nome,
        array $itens,
        float $total,
        string $cupom = 'VOLTE10',
        int $desconto = 10
    ): bool {
        $service = new \MaisonDeLuxo\Services\EmailService();
        return $service->enviarRecuperacaoCarrinho($email, $nome, $itens, $total, $cupom, $desconto);
    }
}
