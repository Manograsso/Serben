<?php
namespace SerbenConnect\Dependents;

if (!defined('ABSPATH')) { exit; }

class FieldGlossary
{
    public static function all(): array
    {
        return [
            'cpf_titular' => ['label'=>'CPF do titular','elementor_id'=>'serben_dep_cpf_titular','required'=>true,'type'=>'text','format'=>'11 dígitos. Pode ficar oculto e ser preenchido automaticamente pelo plugin.'],
            'cpf' => ['label'=>'CPF do dependente','elementor_id'=>'serben_dep_cpf','required'=>true,'type'=>'text','format'=>'11 dígitos.'],
            'nome' => ['label'=>'Nome completo','elementor_id'=>'serben_dep_nome','required'=>true,'type'=>'text','format'=>'Texto.'],
            'data_nasc' => ['label'=>'Data de nascimento','elementor_id'=>'serben_dep_data_nasc','required'=>true,'type'=>'date','format'=>'AAAA-MM-DD.'],
            'sexo' => ['label'=>'Sexo','elementor_id'=>'serben_dep_sexo','required'=>true,'type'=>'select','format'=>'F ou M.'],
            'cep' => ['label'=>'CEP','elementor_id'=>'serben_dep_cep','required'=>true,'type'=>'text','format'=>'8 dígitos.'],
            'estado' => ['label'=>'Estado (UF)','elementor_id'=>'serben_dep_estado','required'=>true,'type'=>'text','format'=>'Duas letras.'],
            'cidade' => ['label'=>'Cidade','elementor_id'=>'serben_dep_cidade','required'=>true,'type'=>'text','format'=>'Texto.'],
            'bairro' => ['label'=>'Bairro','elementor_id'=>'serben_dep_bairro','required'=>true,'type'=>'text','format'=>'Texto.'],
            'endereco' => ['label'=>'Endereço','elementor_id'=>'serben_dep_endereco','required'=>true,'type'=>'text','format'=>'Logradouro.'],
            'complemento' => ['label'=>'Complemento','elementor_id'=>'serben_dep_complemento','required'=>false,'type'=>'text','format'=>'Opcional.'],
            'numero' => ['label'=>'Número','elementor_id'=>'serben_dep_numero','required'=>true,'type'=>'text','format'=>'Número ou SN.'],
            'parentesco' => ['label'=>'Parentesco','elementor_id'=>'serben_dep_parentesco','required'=>true,'type'=>'select','format'=>'ID retornado por GET /parentesco.'],
            'cnpj_corporacao' => ['label'=>'CNPJ da corporação','elementor_id'=>'serben_dep_cnpj_corporacao','required'=>false,'type'=>'text','format'=>'14 dígitos; opcional.'],
        ];
    }

    public static function apiMap(): array
    {
        $map=[]; foreach(self::all() as $api=>$def){$map[$def['elementor_id']]=$api;} return $map;
    }
}
