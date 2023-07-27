ThinkPHP 6.0
===============

> 运行环境要求PHP7.1+。
## 安装配置
1.根目录下运行:composer install

2.复制.example.env文件为.env文件,并修改对应环境的配置参数

3.Linux自动部署将根目录下runtime文件夹给0777权限


## 编码规范
1.所有的数据库操作一般情况全部使用模型方式访问

2.各应用有基础控制器和基础模型Base，一般情况下所有新建业务控制器和模型继承对应应用的基础控制器和模型

3.所有的更新删除操作一般情况先查询后更新删除（先查询可判断当前数据是否有效，保持数据一致性）

4.所有连表查询一般情况使用关联模型（在模型中声明关联关系）

5.计划任务统一使用think-cron扩展包管理

6.客户端请求参数统一使用BaseController的成员变量param去访问

7.一般不需要手动捕获异常，由系统统一处理

8.验证规则按照由易到难排序，需要访问数据库或调接口的验证放在后面

9.遇到错误直接抛异常，一般不需要判断是否操作成功，没有异常就代表成功

10.建议字段搜索器只封装查询表达式，其它的任何查询构造器以及链式操作使用搜索标识的搜索器

## 项目建议规范
1.查询接口

    - 使用搜索器实现，Base控制器已经封装了带分页的查询方法，只需要实现对应模型的搜索器即可
    
    - 数据表字段的搜索器只定义查询条件，非查询条件的搜索器（比如排序、连表）定义搜索标识（非表字段名称）
    
    - 搜索字段的验证放在路由里面验证，不要单独再定义验证器
    
    - 字段需要组合拼接或计算的可以使用获取器实现

2.新增修改接口

    - 数据验证在控制器里面验证，调用基础控制器封装的验证方法，建议定义验证器，如果字段较少验证较简单可以不需要定义验证器直接在控制器中定义验证规则
    
    - 字段如果需要设置默认值使用模型事件实现，如果需要组合计算则可以使用修改器或者模型事件

3.删除接口

    - 参数验证在路由里面验证，如需加删除条件可以使用模型事件实现

4.批量新增修改删除

    - 如果需要走模型事件或修改器，或者需要拿到操作之后的业务数据，可以走模型的批量新增修改删除，如非此种情况直接走Query类的增删改

## MVC最佳实践(摘自Yii2权威指南)
1.控制器

    - 可访问请求数据

    - 可根据请求数据调用模型的方法和其他服务组件

    - 可使用视图构造响应

    - 不应处理应被模型处理的请求数据

    - 应避免嵌入HTML或其他展示代码，这些代码最好在 视图中处理

2.模型

    - 可包含属性来展示业务数据

    - 可包含方法实现业务逻辑

    - 不应直接访问请求，session和其他环境数据，这些数据应该由控制器传入到模型

    - 应避免嵌入HTML或其他展示代码，这些代码最好在视图中处理

    - 单个模型中避免太多的场景

3.视图

    - 应主要包含展示代码，如HTML, 和简单的PHP代码来控制、格式化和渲染数据

    - 不应包含执行数据查询代码，这种代码放在模型中

    - 应避免直接访问请求数据，如 $_GET, $_POST，这种应在控制器中执行， 如果需要请求数据，应由控制器推送到视图

    - 可读取模型属性，但不应修改它们
# ThinkPHP6.0-demo
