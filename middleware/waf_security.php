<?php
/**
 * middleware/waf_security.php
 * -----------------------------------------------------
 *  Web Application Firewall (WAF) para Maison de Luxo
 *  Proteções: SQL Injection, XSS, Rate Limiting básico
 *  PHP 8.2+ Orientado a Objetos
 * -----------------------------------------------------
 */

namespace MaisonDeLuxo\Middleware;

class WAFSecurity
{
    /**
     * Taxa máxima de requisições por minuto por IP
     */
    private const RATE_LIMIT_MAX_REQUESTS = 60;
    
    /**
     * Janela de tempo em segundos para rate limiting
     */
    private const RATE_LIMIT_WINDOW = 60;
    
    /**
     * Prefixo para chaves de rate limiting no session/storage
     */
    private const RATE_LIMIT_PREFIX = 'rate_limit_';
    
    /**
     * Lista de patterns suspeitos para SQL Injection
     */
    private array $sqlInjectionPatterns = [
        '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|ALTER|CREATE|TRUNCATE)\b)/i',
        '/(\b(OR|AND)\b\s+\d+\s*=\s*\d+)/i',
        '/(\'|--|#|\/\*|\*\/)/',
        '/(\bEXEC\b|\bEXECUTE\b)/i',
        '/(\bWAITFOR\b|\bBENCHMARK\b)/i',
        '/(\bSLEEP\b)/i'
    ];
    
    /**
     * Lista de tags HTML perigosas para XSS
     */
    private array $xssPatterns = [
        '/<script[^>]*>.*?<\/script>/is',
        '/<iframe[^>]*>.*?<\/iframe>/is',
        '/<object[^>]*>.*?<\/object>/is',
        '/<embed[^>]*>/is',
        '/javascript:/i',
        '/on(load|error|click|mouse|focus|blur|change|submit|reset|select|abort|keydown|keypress|keyup|resize|scroll|unload|beforeunload)\s*=/i',
        '/expression\s*\(/i',
        '/vbscript:/i'
    ];

    /**
     * Inicializa todas as proteções do WAF
     * Deve ser chamado no início de cada requisição
     */
    public static function init(): void
    {
        $waf = new self();
        $waf->startSecureSession();
        $waf->checkRateLimit();
        $waf->sanitizeInput();
        $waf->setSecurityHeaders();
    }

    /**
     * Inicia sessão com configurações de segurança reforçadas
     */
    public function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configurações de sessão seguras
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Lax');
            
            session_start();
            
            // Regenera ID da sessão periodicamente para prevenir session fixation
            if (!isset($_SESSION['_created'])) {
                $_SESSION['_created'] = time();
            } elseif (time() - $_SESSION['_created'] > 1800) {
                // 30 minutos
                session_regenerate_id(true);
                $_SESSION['_created'] = time();
            }
        }
        
        // Gera token CSRF se não existir
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Verifica e aplica rate limiting baseado no IP do cliente
     * @throws \RuntimeException Se exceder o limite de requisições
     */
    public function checkRateLimit(): void
    {
        $ip = $this->getClientIP();
        $key = self::RATE_LIMIT_PREFIX . md5($ip);
        $now = time();
        
        // Limpa registros antigos
        if (isset($_SESSION[$key])) {
            $_SESSION[$key] = array_filter(
                $_SESSION[$key],
                fn($timestamp) => ($now - $timestamp) < self::RATE_LIMIT_WINDOW
            );
        } else {
            $_SESSION[$key] = [];
        }
        
        // Adiciona timestamp atual
        $_SESSION[$key][] = $now;
        
        // Verifica se excedeu o limite
        if (count($_SESSION[$key]) > self::RATE_LIMIT_MAX_REQUESTS) {
            http_response_code(429); // Too Many Requests
            header('Retry-After: ' . self::RATE_LIMIT_WINDOW);
            exit(json_encode([
                'error' => 'Muitas requisições. Tente novamente em ' . self::RATE_LIMIT_WINDOW . ' segundos.',
                'code' => 'RATE_LIMIT_EXCEEDED'
            ]));
        }
    }

    /**
     * Sanitiza todos os inputs GET, POST e REQUEST
     * Previne SQL Injection e XSS
     */
    public function sanitizeInput(): void
    {
        $arrays = [&$_GET, &$_POST, &$_REQUEST];
        
        foreach ($arrays as &$array) {
            foreach ($array as $key => $value) {
                // Sanitiza chaves
                $safeKey = $this->sanitizeKey($key);
                if ($safeKey !== $key) {
                    unset($array[$key]);
                    $key = $safeKey;
                    $array[$key] = $value;
                }
                
                // Sanitiza valores
                if (is_array($value)) {
                    $this->sanitizeArray($value);
                } else {
                    $array[$key] = $this->sanitizeValue($value);
                }
            }
        }
    }

    /**
     * Define headers de segurança HTTP
     */
    public function setSecurityHeaders(): void
    {
        // Previne clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Protege contra MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS Protection para navegadores mais antigos
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy (CSP) básica
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://js.stripe.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; frame-src https://www.youtube.com https://player.vimeo.com https://js.stripe.com;");
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    /**
     * Obtém o IP real do cliente considerando proxies
     */
    private function getClientIP(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return trim($ip);
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Sanitiza uma chave de array
     */
    private function sanitizeKey(string $key): string
    {
        // Remove caracteres especiais das chaves
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
    }

    /**
     * Sanitiza um valor individual
     */
    private function sanitizeValue(string $value): string
    {
        // Trim whitespace
        $value = trim($value);
        
        // Verifica SQL Injection
        foreach ($this->sqlInjectionPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                error_log("[WAF] SQL Injection detectado: " . substr($value, 0, 100));
                http_response_code(400);
                exit(json_encode(['error' => 'Requisição inválida detectada.', 'code' => 'SQL_INJECTION']));
            }
        }
        
        // Verifica XSS
        foreach ($this->xssPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                error_log("[WAF] XSS detectado: " . substr($value, 0, 100));
                http_response_code(400);
                exit(json_encode(['error' => 'Requisição inválida detectada.', 'code' => 'XSS']));
            }
        }
        
        // Escapa caracteres HTML para output seguro
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $value;
    }

    /**
     * Sanitiza um array recursivamente
     */
    private function sanitizeArray(array &$array): void
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->sanitizeArray($array[$key]);
            } else {
                $array[$key] = $this->sanitizeValue($value);
            }
        }
    }

    /**
     * Valida token CSRF
     * @param string|null $token Token recebido na requisição
     * @return bool True se válido
     */
    public static function validateCSRF(?string $token): bool
    {
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Retorna token CSRF atual
     */
    public static function getCSRFToken(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    /**
     * Log de tentativas de ataque para auditoria
     */
    public static function logAttack(string $type, string $details): void
    {
        $logFile = __DIR__ . '/../logs/security.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $logEntry = sprintf(
            "[%s] %s | IP: %s | UA: %s | Details: %s\n",
            $timestamp,
            $type,
            $ip,
            $userAgent,
            substr($details, 0, 500)
        );
        
        // Cria diretório de logs se não existir
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Inicialização automática se o arquivo for incluído diretamente
if (!function_exists('\\MaisonDeLuxo\\Middleware\\waf_init')) {
    function waf_init(): void
    {
        WAFSecurity::init();
    }
}
