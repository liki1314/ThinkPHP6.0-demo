<?php

declare(strict_types=1);

namespace app;

use think\Model;
use app\common\Collection as CommonCollection;

abstract class BaseModel extends Model
{
    protected $resultSetType = CommonCollection::class;

    protected $jsonAssoc = true;

    protected $map = [];

    /**
     * url路径字段
     *
     * @var array
     */
    protected $urlFields = [];

    private $_isPage = true;

    private $_total = null;

    /* public function getAttr(string $name)
    {
        $value = parent::getAttr($name);

        if (in_array($name, $this->urlFields)) {
            return Upload::getFileUrl($value);
        }

        return $value;
    }

    public function setAttr(string $name, $value, array $data = []): void
    {
        if (in_array($name, $this->urlFields) && $value instanceof \think\file\UploadedFile) {
            $this->data[$this->map[$name] ?? $name] = Upload::putFile($value);
        } else {
            parent::setAttr($name, $value, $data);
        }
    } */

    public static function onBeforeInsert(Model $model)
    {
        $model->invoke(function (Request $request) use ($model) {
            $model->set('create_by', $request->user['user_account_id'] ?? 0);
        });
    }

    public static function onBeforeWrite($model)
    {
        $data = $model->getChangedData();
        foreach ($model->getMap() as $name => $field) {
            if (isset($data[$name])) {
                $model->set($field, $model[$name]);
            }
        }
    }

    public function getMap()
    {
        return $this->map;
    }

    public function getIsPageAttr()
    {
        return $this->_isPage;
    }

    public function setIsPageAttr($value)
    {
        $this->_isPage = $value;
    }

    public function getTotalAttr()
    {
        return $this->_total;
    }

    public function setTotalAttr($value)
    {
        $this->_total = $value;
    }

    public function searchExcelAttr($query, $value, $data)
    {
        if (request()->has('_excel', 'route') && !empty($value)) {
            $this->isPage = false;
        }
    }

    public function searchIdAttr($query, $value)
    {
        $this->isPage = false;
        $query->whereIn($this->pk, $value);
    }
}
