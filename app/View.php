<?php
namespace App;
final class View{public static function render(string $name,array $data=[]):void{extract($data);require __DIR__.'/../resources/views/'.$name.'.php';}}

