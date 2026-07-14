<?php
namespace SerbenConnect\Registration;

use SerbenConnect\Services\ClientesService;
use SerbenConnect\Support\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class RegistrationController
{
    public function register(): void
    {
        add_action('wp_ajax_serben_external_register', [$this, 'handle']);
        add_action('wp_ajax_nopriv_serben_external_register', [$this, 'handle']);
    }

    public function handle(): void
    {
        check_ajax_referer('serben_external_register', 'nonce');

        if (!$this->allowRequest()) {
            wp_send_json_error(['message' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.'], 429);
        }

        $raw = isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];
        $data = $this->normalize($raw);
        $errors = $this->validate($data);

        if ($errors) {
            wp_send_json_error(['message' => 'Revise os campos destacados.', 'errors' => $errors], 422);
        }

        $service = new ClientesService();
        $lookup = $service->buscarPorDocumento($data['cpf_cnpj']);
        $existing = $service->extractCliente($lookup);

        if ($existing) {
            wp_send_json_error([
                'message' => 'Este CPF já possui cadastro. Use a opção de primeiro acesso para criar sua senha no site.',
                'code' => 'already_exists',
            ], 409);
        }

        $result = $service->cadastrar($data);
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];
        $apiRejected = isset($body['status']) && ($body['status'] === false || $body['status'] === 0 || $body['status'] === '0');

        if (empty($result['ok']) || $apiRejected) {
            $message = $body['error'] ?? $body['message'] ?? 'A API não conseguiu concluir o cadastro.';
            Logger::add('error', 'POST', 'Clientes', (int)($result['code'] ?? 0), 'Falha no cadastro externo', [
                'api_message' => $message,
                'cpf' => $this->maskDocument($data['cpf_cnpj']),
            ]);
            wp_send_json_error(['message' => sanitize_text_field((string)$message)], 400);
        }

        wp_send_json_success([
            'message' => 'Cadastro realizado com sucesso. Agora você pode criar seu acesso ao site.',
        ]);
    }

    private function normalize(array $raw): array
    {
        $digits = static function ($value): string {
            return preg_replace('/\D+/', '', (string)$value);
        };

        return [
            'cpf_cnpj' => $digits($raw['cpf_cnpj'] ?? ''),
            'nome' => sanitize_text_field((string)($raw['nome'] ?? '')),
            'rg' => sanitize_text_field((string)($raw['rg'] ?? '')),
            'sexo' => strtoupper(sanitize_text_field((string)($raw['sexo'] ?? ''))),
            'data_nasc' => sanitize_text_field((string)($raw['data_nasc'] ?? '')),
            'fone' => $digits($raw['fone'] ?? ''),
            'celular' => $digits($raw['celular'] ?? ''),
            'email' => sanitize_email((string)($raw['email'] ?? '')),
            'estado' => strtoupper(sanitize_text_field((string)($raw['estado'] ?? ''))),
            'cidade' => sanitize_text_field((string)($raw['cidade'] ?? '')),
            'bairro' => sanitize_text_field((string)($raw['bairro'] ?? '')),
            'cep' => $digits($raw['cep'] ?? ''),
            'endereco' => sanitize_text_field((string)($raw['endereco'] ?? '')),
            'numero' => sanitize_text_field((string)($raw['numero'] ?? '')),
            'complemento' => sanitize_text_field((string)($raw['complemento'] ?? '')),
            'cnpj_corporacao' => $digits($raw['cnpj_corporacao'] ?? ''),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        $required = ['cpf_cnpj', 'nome', 'sexo', 'data_nasc', 'fone', 'celular', 'email', 'estado', 'cidade', 'bairro', 'cep', 'endereco', 'numero'];
        foreach ($required as $field) {
            if ($data[$field] === '') {
                $errors[$field] = 'Campo obrigatório.';
            }
        }

        if ($data['cpf_cnpj'] !== '' && !$this->validDocument($data['cpf_cnpj'])) {
            $errors['cpf_cnpj'] = 'CPF ou CNPJ inválido.';
        }
        if ($data['sexo'] !== '' && !in_array($data['sexo'], ['F', 'M'], true)) {
            $errors['sexo'] = 'Use F ou M.';
        }
        if ($data['email'] !== '' && !is_email($data['email'])) {
            $errors['email'] = 'E-mail inválido.';
        }
        if ($data['estado'] !== '' && !preg_match('/^[A-Z]{2}$/', $data['estado'])) {
            $errors['estado'] = 'Informe a UF com duas letras.';
        }
        if ($data['cep'] !== '' && strlen($data['cep']) !== 8) {
            $errors['cep'] = 'O CEP deve ter 8 dígitos.';
        }
        foreach (['fone', 'celular'] as $phone) {
            if ($data[$phone] !== '' && !in_array(strlen($data[$phone]), [10, 11], true)) {
                $errors[$phone] = 'Informe 10 ou 11 dígitos.';
            }
        }
        if ($data['data_nasc'] !== '') {
            $date = \DateTime::createFromFormat('Y-m-d', $data['data_nasc']);
            if (!$date || $date->format('Y-m-d') !== $data['data_nasc']) {
                $errors['data_nasc'] = 'Use uma data válida no formato AAAA-MM-DD.';
            }
        }
        if ($data['cnpj_corporacao'] !== '' && strlen($data['cnpj_corporacao']) !== 14) {
            $errors['cnpj_corporacao'] = 'O CNPJ da corporação deve ter 14 dígitos.';
        }

        return $errors;
    }

    private function validDocument(string $document): bool
    {
        return strlen($document) === 11 ? $this->validCpf($document) : (strlen($document) === 14 && $this->validCnpj($document));
    }

    private function validCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int)$cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int)$cpf[$t] !== $digit) {
                return false;
            }
        }
        return true;
    }

    private function validCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        $weights = [[5,4,3,2,9,8,7,6,5,4,3,2], [6,5,4,3,2,9,8,7,6,5,4,3,2]];
        foreach ([12, 13] as $index => $length) {
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int)$cnpj[$i] * $weights[$index][$i];
            }
            $remainder = $sum % 11;
            $digit = $remainder < 2 ? 0 : 11 - $remainder;
            if ((int)$cnpj[$length] !== $digit) {
                return false;
            }
        }
        return true;
    }

    private function allowRequest(): bool
    {
        $ip = sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $key = 'serben_reg_rate_' . md5($ip);
        $count = (int)get_transient($key);
        if ($count >= 8) {
            return false;
        }
        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    private function maskDocument(string $document): string
    {
        return substr($document, 0, 3) . str_repeat('*', max(0, strlen($document) - 5)) . substr($document, -2);
    }
}
