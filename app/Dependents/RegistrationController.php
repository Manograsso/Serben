<?php
namespace SerbenConnect\Dependents;

use SerbenConnect\Providers\CacheManager;
use SerbenConnect\Services\DependentesService;
use SerbenConnect\Support\Logger;

if (!defined('ABSPATH')) { exit; }

class RegistrationController
{
    public function register(): void
    {
        add_action('wp_ajax_serben_dependent_register', [$this,'handle']);
    }

    public function handle(): void
    {
        check_ajax_referer('serben_dependent_register','nonce');
        if(!is_user_logged_in()) wp_send_json_error(['message'=>'Faça login para cadastrar um dependente.'],401);
        $raw=isset($_POST['fields'])&&is_array($_POST['fields'])?wp_unslash($_POST['fields']):[];
        $data=$this->normalize($raw);
        $cpf=(string)get_user_meta(get_current_user_id(),'serben_cpf',true);
        $data['cpf_titular']=preg_replace('/\D+/','',$cpf);
        $errors=$this->validate($data);
        if($errors) wp_send_json_error(['message'=>'Revise os campos destacados.','errors'=>$errors],422);
        $result=(new DependentesService())->cadastrar($data);
        $body=is_array($result['body']??null)?$result['body']:[];
        $apiRejected=isset($body['status'])&&in_array($body['status'],[false,0,'0'],true);
        if(empty($result['ok'])||$apiRejected){
            $message=$body['error']??$body['message']??'A API não conseguiu cadastrar o dependente.';
            Logger::add('error','POST','dependentes',(int)($result['code']??0),'Falha no cadastro de dependente',['api_message'=>$message]);
            wp_send_json_error(['message'=>sanitize_text_field((string)$message)],400);
        }
        (new CacheManager())->delete('contracts_'.md5($data['cpf_titular']));
        wp_send_json_success(['message'=>'Dependente cadastrado com sucesso.']);
    }

    private function normalize(array $r): array
    {
        $d=static function($v){return preg_replace('/\D+/','',(string)$v);};
        return [
            'cpf_titular'=>$d($r['cpf_titular']??''),'cpf'=>$d($r['cpf']??''),'nome'=>sanitize_text_field((string)($r['nome']??'')),
            'data_nasc'=>sanitize_text_field((string)($r['data_nasc']??'')),'sexo'=>strtoupper(sanitize_text_field((string)($r['sexo']??''))),
            'cep'=>$d($r['cep']??''),'estado'=>strtoupper(sanitize_text_field((string)($r['estado']??''))),'cidade'=>sanitize_text_field((string)($r['cidade']??'')),
            'bairro'=>sanitize_text_field((string)($r['bairro']??'')),'endereco'=>sanitize_text_field((string)($r['endereco']??'')),'complemento'=>sanitize_text_field((string)($r['complemento']??'')),
            'numero'=>sanitize_text_field((string)($r['numero']??'')),'parentesco'=>sanitize_text_field((string)($r['parentesco']??'')),'cnpj_corporacao'=>$d($r['cnpj_corporacao']??''),
        ];
    }

    private function validate(array $d): array
    {
        $e=[]; foreach(['cpf_titular','cpf','nome','data_nasc','sexo','cep','estado','cidade','bairro','endereco','numero','parentesco'] as $f){if($d[$f]==='')$e[$f]='Campo obrigatório.';}
        if($d['cpf']!==''&&strlen($d['cpf'])!==11)$e['cpf']='O CPF deve ter 11 dígitos.';
        if($d['sexo']!==''&&!in_array($d['sexo'],['F','M'],true))$e['sexo']='Use F ou M.';
        if($d['cep']!==''&&strlen($d['cep'])!==8)$e['cep']='O CEP deve ter 8 dígitos.';
        if($d['estado']!==''&&!preg_match('/^[A-Z]{2}$/',$d['estado']))$e['estado']='Informe a UF com duas letras.';
        $date=\DateTime::createFromFormat('Y-m-d',$d['data_nasc']); if($d['data_nasc']!==''&&(!$date||$date->format('Y-m-d')!==$d['data_nasc']))$e['data_nasc']='Use uma data válida no formato AAAA-MM-DD.';
        if($d['cnpj_corporacao']!==''&&strlen($d['cnpj_corporacao'])!==14)$e['cnpj_corporacao']='O CNPJ deve ter 14 dígitos.';
        return $e;
    }
}
