<?php
declare (strict_types = 1);

namespace app\common;

use think\model\Collection as ModelCollection;

class Collection extends ModelCollection
{
    /**
     * 无限分类
     * @param mixed $fId 上级分类id
     * @param string $pk 主键名称
     * @param string $fPk 上级分类键名
     * @return Collection
     */
    public function tree($fId = 0, $pk = 'id', $fPk = 'fid')
    {
        $tree = [];
        foreach ($this->items as $k => $item) {
            if ($item[$fPk] == $fId) {
                unset($this->items[$k]);
                if (!empty($this->items)) {
                    $children = $this->tree($item[$pk], $pk, $fPk);
                    if (!$children->isEmpty()) {
                        $item['children'] = $children;
                    }
                }
                $tree[] = $item;
            }
        }
        return new static($tree);
    }

    public function child($id, $self = true, $pk = 'id', $fPk = 'fid', $clear=true)
    {
        static $list = [];
        if($clear){
            $list = [];
        }
        foreach ($this->items as $key => $item) {
            if ($item[$fPk] == $id) {
                unset($this->items[$key]);
                if (!empty($this->items)) {
                    $this->child($item[$pk], $self, $pk, $fPk, false);
                }
                $list[] = $item;
            }
            if ($item[$pk] == $id && $self) {
                $list[] = $item;
                unset($this->items[$key]);
            }
        }
        return new static($list);
    }

    public function parent($id, $pk = 'id', $fPk = 'fid')
    {
        static $list = [];
        foreach ($this->items as $key => $item) {
            if ($item[$pk] == $id) {
                unset($this->items[$key]);
                if (!empty($item[$fPk])) {
                    $this->parent($item[$fPk]);
                }
                $list[] = $item;
            }
        }
        return new static($list);
    }

    public function visibleSortToArray(array $fields = [])
    {
        $callback = function ($a, $b) use ($fields) {
            return array_search($a, $fields) <=> array_search($b, $fields);
        };

        $items = $this->map(function ($item) use ($callback, $fields) {
            $array = $item->visible($fields)->toArray();
            uksort($array, $callback);
            return $array;
        });

        return $items->toArray();
    }
}
