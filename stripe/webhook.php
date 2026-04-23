<?php
/**
 * webhook.php - Webhook do Stripe para confirmação automática
 * -----------------------------------------------------
 *  Recebe eventos do Stripe e atualiza pedidos automaticamente.
 *  Configure no Dashboard do Stripe: SITE_URL/stripe/webhook.php
 * -----------------------------------------------------
 */
require_once __DIR__ . '/../config.php';

// Lê payload bruto
$input = file_get_contents('php://input');
$headers = getallheaders();
$sig_header = $headers['Stripe-Signature'] ?? '';

$event = null;

try {
    if (!class_exists('\\Stripe\\Stripe')) {
        // SDK não disponível - webhook não funciona sem ele
        http_response_code(503);
        echo json_encode(['error' => 'SDK do Stripe não instalado']);
        exit;
    }
    
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    
    // Verifica assinatura do webhook
    $event = \Stripe\Webhook::constructEvent(
        $input,
        $sig_header,
        STRIPE_WEBHOOK_SECRET
    );
    
} catch (\UnexpectedValueException $e) {
    // Payload inválido
    http_response_code(400);
    exit('Payload inválido');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Assinatura inválida
    http_response_code(400);
    exit('Assinatura inválida');
}

// Processa eventos
switch ($event->type) {
    case 'checkout.session.completed':
        $session = $event->data->object;
        
        if ($session->payment_status === 'paid') {
            $pedido_id = $session->metadata->pedido_id ?? null;
            
            if ($pedido_id) {
                // Atualiza pedido para pago
                $update = $pdo->prepare('UPDATE pedidos SET status = :status, stripe_payment_intent = :pi WHERE id = :id');
                $update->execute([
                    ':status' => 'pago',
                    ':pi' => $session->payment_intent,
                    ':id' => $pedido_id
                ]);
                
                // Busca produto para decrementar estoque
                $stmt = $pdo->prepare('SELECT produto_id FROM pedidos WHERE id = :id');
                $stmt->execute([':id' => $pedido_id]);
                $pedido = $stmt->fetch();
                
                if ($pedido) {
                    // Decrementa estoque
                    $estoque = $pdo->prepare('UPDATE produtos SET estoque = estoque - 1 WHERE id = :id AND estoque > 0');
                    $estoque->execute([':id' => $pedido['produto_id']]);
                }
                
                // Aqui você poderia enviar e-mail de confirmação
                // mail($cliente_email, "Pedido #$pedido_id confirmado", "...");
            }
        }
        break;
        
    case 'payment_intent.payment_failed':
        $payment_intent = $event->data->object;
        
        // Busca pedido pelo payment_intent
        $stmt = $pdo->prepare('SELECT id FROM pedidos WHERE stripe_payment_intent = :pi');
        $stmt->execute([':pi' => $payment_intent->id]);
        $pedido = $stmt->fetch();
        
        if ($pedido) {
            // Marca como cancelado
            $update = $pdo->prepare('UPDATE pedidos SET status = :status WHERE id = :id');
            $update->execute([
                ':status' => 'cancelado',
                ':id' => $pedido['id']
            ]);
        }
        break;
        
    default:
        // Evento não tratado
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
