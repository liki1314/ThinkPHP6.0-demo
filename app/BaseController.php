<?php

declare(strict_types=1);

namespace app;

use think\App;
use think\exception\ValidateException;
use think\Validate;
use think\facade\Config;
use think\helper\Str;
use think\helper\Arr;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    /**
     * 当前页数
     *
     * @var int
     */
    protected $page = 1;

    /**
     * 每页数量
     *
     * @var int
     */
    protected $rows = 50;

    /**
     * 请求参数
     *
     * @var array
     */
    protected $param;

    // 初始化
    protected function initialize()
    {
        $this->param = array_filter(
            array_merge($this->request->except(['create_time', 'update_time', 'delete_time']), $this->request->file() ?? []),
            function ($key) {
                if (!is_string($key)) {
                    return true;
                }
                return preg_match('/^[\w\.\*]+$/', $key);
            },
            ARRAY_FILTER_USE_KEY
        );

        $this->page = empty($this->param['page']) ? $this->page : intval($this->param['page']);
        $this->rows = empty($this->param['rows']) ? Config::get('app.rows', $this->rows) : intval($this->param['rows']);

        if (isset($this->request->user)) {
            $this->param['user'] = $this->request->user;
        }
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    protected function success($data = '')
    {
        return json(['result' => 0, 'data' => $data, 'msg' => lang('success')]);
    }

    /**
     * 列表页查询,按搜索字段定义搜索器
     *
     * @param mixed $model 查询主表模型类名
     * @param array $searchField 指定搜索字段(不传默认搜索前端传递过来的全部字段)
     * @return \think\model\Collection
     */
    protected function searchList($model, array $searchField = [], $mobile = true)
    {
        $searchData = array_filter(
            $this->param,
            function ($data, $key) {
                return (!empty($data) || '0' == $data) && strpos($key, '_') !== 0;
            },
            ARRAY_FILTER_USE_BOTH
        );

        $search = $searchField ?: array_merge(array_keys($searchData), ['default']);

        if (is_string($model) && false !== strpos($model, '\\')) {
            $model = new $model;
        }
        $query = $model->withSearch(
            array_filter(
                array_unique($search),
                function ($fieldName) use ($model) {
                    return method_exists($model, 'search' . Str::studly($fieldName) . 'Attr');
                }
            ),
            $searchData
        );

        if ($model->isPage === false) {
            return $query->select();
        }
        return $query->paginate(['list_rows' => $this->rows, 'page' => $this->page], $mobile && $this->request->isMobile() ? true : ($model->total ?? false));
    }
}
