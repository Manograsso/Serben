<?php
namespace SerbenConnect\Registration;

if (!defined('ABSPATH')) {
    exit;
}

class FieldGlossary
{
    public static function all(): array
    {
        return [
            'cpf_cnpj' => ['label' => 'CPF ou CNPJ', 'elementor_id' => 'serben_cpf_cnpj', 'required' => true, 'type' => 'text', 'format' => 'Somente números; CPF com 11 ou CNPJ com 14 dígitos.'],
            'nome' => ['label' => 'Nome completo', 'elementor_id' => 'serben_nome', 'required' => true, 'type' => 'text', 'format' => 'Texto.'],
            'rg' => ['label' => 'RG', 'elementor_id' => 'serben_rg', 'required' => false, 'type' => 'text', 'format' => 'Texto opcional.'],
            'sexo' => ['label' => 'Sexo', 'elementor_id' => 'serben_sexo', 'required' => true, 'type' => 'select', 'format' => 'F ou M.'],
            'data_nasc' => ['label' => 'Data de nascimento', 'elementor_id' => 'serben_data_nasc', 'required' => true, 'type' => 'date', 'format' => 'AAAA-MM-DD.'],
            'fone' => ['label' => 'Telefone', 'elementor_id' => 'serben_fone', 'required' => true, 'type' => 'tel', 'format' => '10 ou 11 dígitos.'],
            'celular' => ['label' => 'Celular', 'elementor_id' => 'serben_celular', 'required' => true, 'type' => 'tel', 'format' => '10 ou 11 dígitos.'],
            'email' => ['label' => 'E-mail', 'elementor_id' => 'serben_email', 'required' => true, 'type' => 'email', 'format' => 'E-mail válido.'],
            'estado' => ['label' => 'Estado (UF)', 'elementor_id' => 'serben_estado', 'required' => true, 'type' => 'text', 'format' => 'Duas letras, por exemplo SP.'],
            'cidade' => ['label' => 'Cidade', 'elementor_id' => 'serben_cidade', 'required' => true, 'type' => 'text', 'format' => 'Texto.'],
            'bairro' => ['label' => 'Bairro', 'elementor_id' => 'serben_bairro', 'required' => true, 'type' => 'text', 'format' => 'Texto.'],
            'cep' => ['label' => 'CEP', 'elementor_id' => 'serben_cep', 'required' => true, 'type' => 'text', 'format' => '8 dígitos.'],
            'endereco' => ['label' => 'Endereço', 'elementor_id' => 'serben_endereco', 'required' => true, 'type' => 'text', 'format' => 'Logradouro.'],
            'numero' => ['label' => 'Número', 'elementor_id' => 'serben_numero', 'required' => true, 'type' => 'text', 'format' => 'Número ou SN.'],
            'complemento' => ['label' => 'Complemento', 'elementor_id' => 'serben_complemento', 'required' => false, 'type' => 'text', 'format' => 'Texto opcional.'],
            'cnpj_corporacao' => ['label' => 'CNPJ da corporação', 'elementor_id' => 'serben_cnpj_corporacao', 'required' => false, 'type' => 'text', 'format' => '14 dígitos; opcional.'],
        ];
    }

    public static function apiMap(): array
    {
        $map = [];
        foreach (self::all() as $apiField => $definition) {
            $map[$definition['elementor_id']] = $apiField;
        }
        return $map;
    }
}
