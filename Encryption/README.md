# laravel Encryption 详解

## 简介
Encryption是laravel自带的一个加密模块，让我们先来看看文档说明

> Configuration

> Before using Laravel's encrypter, you should set the key option of your config/app.php configuration file to a 32 character, random string. If this value is not properly set, all values encrypted by Laravel will be insecure.

意思就是在使用laravel的encrypter前，需要在config/app.php设置一下key（秘钥）和cipher（加密方式）。

```
# from config/app.php
'key' => env('APP_KEY'),
'cipher' => 'AES-256-CBC',
```
env方法指明了读取`.env`文件的`APP_KEY`，这个只能够通过 `php artisan key:generate`生成，也是整个应用程序的key，`cipher`表明了加密的方式，默认`AES-256-CBC`。

## 加密时做了什么？


在`Crypt::encrypt($password)`时，将会调用 [`Illuminate\Encryption\Encrypter`](https://github.com/laravel/framework/blob/5.2/src/Illuminate/Encryption/Encrypter.php)的`encrypt`方法
```
public function encrypt($value)
{}
```

```
# 实际执行如下
$encrypt = new Illuminate\Encryption\Encrypter($key, $cipher);
$encrypt->encrypt($value);
```
咦？为什么不是静态方法？别急，这章我们下节在讲，此时你需要知道事实上laravel是调用了encrypt方法就行了

```
public function __construct($key, $cipher = 'AES-128-CBC')
{
        $key = (string) $key;

        if (static::supported($key, $cipher)) {
            $this->key = $key;
            $this->cipher = $cipher;
        } else {
            throw new RuntimeException('The only supported ciphers are AES-128-CBC and AES-256-CBC with the correct key lengths.');
        }
}
```

Encrypter的构造函数传入了两个参数，也就是一开始在config/app.php里面看到的那两个参数，然后构造函数会做一个校验的处理
```
public function encrypt($value)
{
        $iv = random_bytes($this->getIvSize());

        $value = \openssl_encrypt(serialize($value), $this->cipher, $this->key, 0, $iv);

        if ($value === false) {
            throw new EncryptException('Could not encrypt the data.');
        }

        // Once we have the encrypted value we will go ahead base64_encode the input
        // vector and create the MAC for the encrypted value so we can verify its
        // authenticity. Then, we'll JSON encode the data in a "payload" array.
        $mac = $this->hash($iv = base64_encode($iv), $value);

        $json = json_encode(compact('iv', 'value', 'mac'));

        if (! is_string($json)) {
            throw new EncryptException('Could not encrypt the data.');
        }

        return base64_encode($json);
}
```
接下来就是加密，`random_bytes`方法生成随机字符，这个函数很陌生是吧，我们去<http://php.net/manual/en/function.random-bytes.php>看一下

发现`random_bytes`居然是php7的方法，那运行在PHP5不就报错了吗？

然而，laravel也帮你处理好了，在vendor引入的`paragonie/random_compat`中已经包含了此函数，方便兼容php5以及php7，相关介绍：<https://github.com/laravel/framework/issues/11448>

接下来就是用[openssl_encrypt](http://php.net/manual/zh/function.openssl-encrypt.php)进行加密、[hash_hmac](http://php.net/manual/zh/function.hash-hmac.php)加密以及base64_encode，这里就不做详细讲解，核心函数已列出具体请自行查询

## laravel 是如何进行解密的？

解密，无非就是把加密的步骤倒着操作一遍，让我们先来看看代码

```
public function decrypt($payload)
{
        $payload = $this->getJsonPayload($payload);

        $iv = base64_decode($payload['iv']);

        $decrypted = \openssl_decrypt($payload['value'], $this->cipher, $this->key, 0, $iv);

        if ($decrypted === false) {
            throw new DecryptException('Could not decrypt the data.');
        }

        return unserialize($decrypted);
}
```

方法中`$this->getJsonPayload($payload);`来源于父类`Illuminate\Encryption\BaseEncrypter`
```
protected function getJsonPayload($payload)
{
        $payload = json_decode(base64_decode($payload), true);

        // If the payload is not valid JSON or does not have the proper keys set we will
        // assume it is invalid and bail out of the routine since we will not be able
        // to decrypt the given value. We'll also check the MAC for this encryption.
        if (! $payload || $this->invalidPayload($payload)) {
            throw new DecryptException('The payload is invalid.');
        }

        if (! $this->validMac($payload)) {
            throw new DecryptException('The MAC is invalid.');
        }

        return $payload;
}
```

加密时最后的两个步骤就是`json_encode`和`base64_encode`现在把它解成一个数组`$payload`，然后对其进行校验
```
protected function invalidPayload($data)
{
        return ! is_array($data) || ! isset($data['iv']) || ! isset($data['value']) || ! isset($data['mac']);
}
```
这一步非常简单，就是看加密时加进去的数组是否存在，不存在则抛出异常`throw new DecryptException('The payload is invalid.');`

```
protected function validMac(array $payload)
{
        $bytes = random_bytes(16);

        $calcMac = hash_hmac('sha256', $this->hash($payload['iv'], $payload['value']), $bytes, true);

        return hash_equals(hash_hmac('sha256', $payload['mac'], $bytes, true), $calcMac);
}
```

这个方法如果你看得有点绕，证明你想太多了。简单来说就是通过相同的秘钥重新计算哈希值进行比较，若相等则返回true。