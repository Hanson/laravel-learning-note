<?php
/**
 * Created by PhpStorm.
 * User: Hanson
 * Date: 2016/6/10
 * Time: 11:35
 */

require __DIR__ . './../vendor/autoload.php';

$app = new \Illuminate\Container\Container();

$app->bind('car', function(){
   return new \Hanccc\Entity\Car();
});

$app->bind('ship', function(){
   return new \Hanccc\Entity\Ship();
});

echo $app->make('car');
echo $app->make('ship');