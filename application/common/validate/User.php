<?php
namespace app\common\validate;

use think\Validate;

class User extends Validate
{
  protected $rule = [
    'username'=>[
      'require'=>'require',
      'length'=>'3,50',
      'unique'=>'user',
      'chsAlphaNum'=>'chsAlphaNum'
    ],
    'password'=>[
      'require'=>'require',
      'alphaNum'=>'alphaNum',
      'length'=>'6,20'
    ],
    'age'=>[
      'require'=>'require',
      'number'=>'number'
    ]
  ];
}
