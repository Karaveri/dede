<?php
namespace App\Helpers;

final class Validator
{
    /**
     * Geri uyumlu API:
     * $vr = \App\Helpers\Validator::make($data, $rules);
     * $vr['ok'] => bool, $vr['errors'] => array
     *
     * @param array<string,mixed> $data
     * @param array<string,string> $rulesPipe  örn: ['ad'=>'required|between:2,100', ...]
     * @return array{ok:bool, errors: array<string, array<int,string>>}
     */
    public static function make(array $data, array $rulesPipe): array
    {
        // Pipe kurallarını çekirdek Validator biçimine çevir
        $rules = [];
        foreach ($rulesPipe as $field => $pipe) {
            $parts = array_map('trim', explode('|', (string)$pipe));
            // App\Core\Validator 'pattern:slug' değil 'slug' beklediği için dönüştürelim
            $parts = array_map(function($r) {
                return $r === 'pattern:slug' ? 'slug' : $r;
            }, $parts);
            $rules[$field] = $parts;
        }

        $v = new \App\Core\Validator();
        $ok = $v->validate($data, $rules);

        return ['ok' => $ok, 'errors' => $v->errors()];
    }
}
