<?php
namespace App\Core;

final class Validator
{
    /** @var array<string, array<int, string>> */
    private array $errors = [];

    /**
     * Controller’larda kullanılan statik kısa yol.
     * $rules değerleri "required|max:200|slug" gibi string gelebilir; burada diziye dönüştürülür.
     *
     * @param array<string,mixed> $data
     * @param array<string,string|array<int,string>> $rules
     * @return array{ok:bool, errors: array<string, array<int,string>>}
     */
    public static function make(array $data, array $rules): array
    {
        // Kuralları normalize et: "a|b|c" -> ["a","b","c"]
        $normalized = [];
        foreach ($rules as $field => $ruleList) {
            if (is_string($ruleList)) {
                $parts = array_map('trim', explode('|', $ruleList));
            } elseif (is_array($ruleList)) {
                $parts = $ruleList;
            } else {
                $parts = [];
            }
            // Geriye dönük uyumluluk: pattern:slug → slug
            $parts = array_map(function ($r) {
                return $r === 'pattern:slug' ? 'slug' : $r;
            }, $parts);

            $normalized[$field] = $parts;
        }

        $v  = new self();
        $ok = $v->validate($data, $normalized);
        return ['ok' => $ok, 'errors' => $v->errors()];
    }    

    /**
     * @param array<string,mixed> $data
     * @param array<string, array<int, string>> $rules
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleList) {
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                switch ($name) {
                    case 'required':
                        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
                            $this->add($field, 'Bu alan zorunludur.');
                        }
                        break;

                    case 'min':
                        $min = (int)$param;
                        if (is_string($value) && mb_strlen(trim($value)) < $min) {
                            $this->add($field, "En az {$min} karakter olmalıdır.");
                        }
                        break;

                    case 'max':
                        $max = (int)$param;
                        if (is_string($value) && mb_strlen(trim($value)) > $max) {
                            $this->add($field, "En fazla {$max} karakter olabilir.");
                        }
                        break;

                    case 'between':
                        [$a, $b] = array_map('intval', explode(',', (string)$param, 2));
                        $len = is_string($value) ? mb_strlen(trim($value)) : 0;
                        if ($len < $a || $len > $b) {
                            $this->add($field, "{$a}-{$b} karakter aralığında olmalıdır.");
                        }
                        break;

                    case 'integer':
                        if (!filter_var($value, FILTER_VALIDATE_INT) && $value !== 0 && $value !== '0') {
                            $this->add($field, 'Geçerli bir tam sayı giriniz.');
                        }
                        break;

                    case 'boolean':
                        if (!in_array($value, [true,false,0,1,'0','1','on','off'], true)) {
                            $this->add($field, 'Geçerli bir mantıksal değer giriniz.');
                        }
                        break;

                    case 'email':
                        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $this->add($field, 'Geçerli bir e-posta adresi giriniz.');
                        }
                        break;

                    case 'url':
                        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
                            $this->add($field, 'Geçerli bir URL giriniz.');
                        }
                        break;

                    case 'in':
                        $allowed = array_map('trim', explode(',', (string)$param));
                        if (!in_array((string)$value, $allowed, true)) {
                            $this->add($field, 'Geçersiz değer.');
                        }
                        break;

                    case 'slug':
                        if (!is_string($value) || !preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*$~', $value)) {
                            $this->add($field, 'Slug sadece küçük harf, rakam ve tire içerebilir.');
                        }
                        break;

                    case 'nullable':
                        if ($value === null || $value === '') { break 2; }
                        break;

                    default:
                        // bilinmeyen kuralı geç
                        break;
                }
            }
        }
        return empty($this->errors);
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function add(string $field, string $msg): void
    {
        $this->errors[$field][] = $msg;
    }
}
