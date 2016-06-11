# Facades

## 原理

> In the context of a Laravel application, a facade is a class that provides access to an object from the container. The machinery that makes this work is in the `Facade` class. Laravel's facades, and any custom facades you create, will extend the base `Illuminate\Support\Facades\Facade` class.

> A facade class only needs to implement a single method: `getFacadeAccessor`. It's the getFacadeAccessor method's job to define what to resolve from the container. The `Facade` base class makes use of the `__callStatic()` magic-method to defer calls from your facade to the resolved object.

简单来说，门面是通过一个魔术方法`__callStatic()`把静态方法映射到真正的方法上。

本文我们要用Route来举例，
```
Route::get('/', function(){
    # 
}
```

在Route调用get方法时发生了什么？我们可以从config/app.php中看到数组 aliases 中含有 `'Route' => Illuminate\Support\Facades\Route::class`，此处的所有aliases会全部注册在容器中，访问Route便等于映射在`Illuminate\Support\Facades\Route::class`这个类，我来看看这个类

```
<?php 

namespace Illuminate\Support\Facades;

/**
 * @see \Illuminate\Routing\Router
 */
class Route extends Facade {

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'router';
    }

}
```

发现Route只有一个方法`getFacadeAccessor`，而继承的`Facade`类也没有相关的get方法。在`Facade`中，我们留意到一个方法`__callStatic()`：

```
public static function __callStatic($method, $args)
{
        $instance = static::getFacadeRoot();

        if (! $instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        switch (count($args)) {
            case 0:
                return $instance->$method();
            case 1:
                return $instance->$method($args[0]);
            case 2:
                return $instance->$method($args[0], $args[1]);
            case 3:
                return $instance->$method($args[0], $args[1], $args[2]);
            case 4:
                return $instance->$method($args[0], $args[1], $args[2], $args[3]);
            default:
                return call_user_func_array([$instance, $method], $args);
        }
}
```
这个方法比较简单，通过`static::getFacadeRoot()`获取一个实例，再调用实例的`$method`方法并传入参数，关键就在于这个`getFacadeRoot()`方法：

```
public static function getFacadeRoot()
{
        return static::resolveFacadeInstance(static::getFacadeAccessor());
}
```

`getFacadeAccessor()`方法就是必须实现的方法，在这里就是为了获取 router，让我们再来看看`resolveFacadeInstance()`方法：
```
protected static function resolveFacadeInstance($name)
{
        if (is_object($name)) {
            return $name;
        }

        if (isset(static::$resolvedInstance[$name])) {
            return static::$resolvedInstance[$name];
        }

        return static::$resolvedInstance[$name] = static::$app[$name];
}
```
在这个方法可以看出，实现上文所提的`getFacadeAccessor()`方法有两种形式，一种是直接返回一个字符串，这种形式需要在服务提供者中进行注册，另一种是可以直接返回一个对象，这种形式则不会再去容器中找此对象。

那么容器返回的到底是哪个对象？在config/app.php中的provider可以看到关于route的服务提供者`App\Providers\RouteServiceProvider::class`，而`RouteServiceProvider`类依赖的是`\Illuminate\Routing\Router`，也就是所有静态的方法实际上访问的都是这个类！

## 总结
Facades 的核心关键词有几个：
* __callStatic方法
* 服务容器
* 服务提供者

在看laravel的代码时，会发现有大量的代码使用的容器，也就是laravel的核心思想，同时也有很多语法糖，尽管有不少人诟病laravel的效率问题，但个人认为框架的核心在于可维护性以及开发效率。也因为laravel的多层抽象使其冠以最优雅框架的名号，极大的降低了代码的耦合度。


