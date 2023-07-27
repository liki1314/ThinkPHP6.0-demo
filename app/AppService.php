<?php

declare(strict_types=1);

namespace app;

use Exception;
use think\facade\Validate as FacadeValidate;
use think\Service;
use think\Validate;

/**
 * 应用服务类
 */
class AppService extends Service
{
    public function register()
    {
        // 服务注册
    }

    public function boot()
    {
        // 服务启动
        Validate::maker(function ($validate) {
            if (method_exists($validate, 'field')) {
                $validate->field();
            }
            // 验证某个字段的值是否存在
            $validate->extend('exist', function ($value, $rule, array $data = [], string $field = '') {
                if (empty($value)) {
                    return true;
                }

                if (is_string($rule)) {
                    $rule = explode(',', $rule);
                }

                if (false !== strpos($rule[0], '\\')) {
                    // 指定模型类
                    $db = new $rule[0];
                } else {
                    $db = $this->db->name($rule[0]);
                }

                $map = [];

                if (!empty($rule[1])) {
                    // 支持多个字段验证
                    $fields = explode('^', $rule[1]);
                    foreach ($fields as $key) {
                        if (isset($data[$key])) {
                            $map[] = [$key, '=', $data[$key]];
                        } else {
                            $db = $db->whereRaw($key);
                        }
                    }
                }
                if (isset($data[$field])) {
                    $map[] = [$rule[2] ?? $db->getPk(), '=', $data[$field]];
                }

                if ($db->where($map)->field($db->getPk())->find()) {
                    return true;
                }

                return ':attribute ' . lang('not_exist');
                // return false;
            });

            // 验证某个字段的值满足某个规则的时候必须满足规则
            $validate->extend('ruleIfMatch', function ($value, $rule, array $data = [], string $field = '') use ($validate) {
                if (is_null($value) || '' === $value) {
                    return true;
                }

                if (!is_array($rule)) {
                    $rule = explode(',', $rule, 3);
                }

                [$field0, $rule1, $rule2] = $rule;

                $class = \get_class($validate);
                $validate = new $class();
                if ($validate->checkRule($data[$field0] ?? null, $rule1)) {
                    return $validate->check($data, [$field => $rule2]);
                }

                return true;
            });

            // 验证某个字段的值不等于某个值的时候必须满足某个规则
            $validate->extend('ruleIfNot', function ($value, $rule, array $data = [], string $field = '') use ($validate) {
                if (is_null($value) || '' === $value) {
                    return true;
                }

                if (!is_array($rule)) {
                    $rule = explode(',', $rule, 3);
                }

                [$field0, $val, $rule] = $rule;

                if ($data[$field0] != $val) {
                    return $validate->check($data, [$field => $rule]);
                }

                return true;
            });

            // 验证某个字段没有值的时候必须满足给出规则
            $validate->extend('ruleWithout', function ($value, $rule, array $data = [], string $field = '') use ($validate) {
                if (is_null($value) || '' === $value) {
                    return true;
                }

                if (!is_array($rule)) {
                    $rule = explode(',', $rule, 2);
                }

                [$field0, $rule] = $rule;

                if (!isset($data[$field0])) {
                    return $validate->check($data, [$field => $rule]);
                }

                return true;
            });

            // 重写in规则 支持数组包含验证
            $validate->extend('in', function ($value, $rule) {
                if (is_null($value) || '' === $value) {
                    return true;
                }

                $rule = is_array($rule) ? $rule : explode(',', $rule);
                if (is_array($value)) {
                    return empty(array_diff($value, $rule));
                } else {
                    return in_array($value, $rule);
                }
            });

            // 验证地址是否有效存在
            $validate->extend('existUrl', function ($value) {
                if (is_null($value) || '' === $value) {
                    return true;
                }

                $array = get_headers($value, true);
                return !!preg_match('/200/', $array[0]);
            });

            $validate->extend('each', function ($value, $rule = [], array $data = [], string $field = '') use ($validate) {
                if (empty($value)) {
                    return true;
                }

                if (!is_array($value)) {
                    return false;
                }

                if (is_string($rule) && false !== strpos($rule, ';')) {
                    [$rule, $fields] = explode(';', $rule, 2);
                }

                if (is_string($rule) && false !== strpos($rule, '\\')) {
                    $validate = new $rule;
                    $rule = [];
                } else {
                    $class = \get_class($validate);
                    $validate = new $class();
                }

                if (!empty($fields)) {
                    $validate->only(explode('^', $fields));
                }

                if (!isset($value[0])) {
                    $value = [$value];
                }

                // $validate->failException(true);
                foreach ($value as $item) {
                    if (!is_array($item)) {
                        $item = [$field => $item];
                        if (isset($rule[0]) || is_string($rule)) {
                            $rule = [$field => $rule];
                        }
                    }
                    if ($validate->check($item, (array) $rule) === false) {
                        return false;
                    }
                }

                return true;
            });

            $validate->extend('requireIfMatch', function ($value, $rule, array $data = []) use ($validate) {
                [$field, $fieldRule] = explode(',', $rule, 2);

                if (FacadeValidate::checkRule($data[$field] ?? null, $fieldRule)) {
                    return !empty($value) || '0' == $value;
                }

                return true;
            });

            $validate->extend('fileName', function ($value) {

                return is_scalar($value) && 1 === preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9，,¥\_\-\[\]\(\)\+\#\&]+$/u', (string)$value);
            });
        });
    }
}
