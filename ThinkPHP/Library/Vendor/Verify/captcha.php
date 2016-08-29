<?php
function fdd_verify_code($name = 'verify_code' , $len = 6 , $width = 65 , $height = 25){

    $type   = 'png';

    header("Content-type: image/".$type);
    srand((double)microtime()*1000000);

    $randval = random_string($len);

    if($type!='gif'&&function_exists('imagecreatetruecolor')){
      $im=@imagecreatetruecolor($width,$height);
    }else{
      $im=@imagecreate($width,$height);
    }

    $r=Array(225,211,255,223);
    $g=Array(225,236,237,215);
    $b=Array(225,236,166,125);
    $key=rand(0,3);
    $backColor=ImageColorAllocate($im,$r[$key],$g[$key],$b[$key]);//背景色（随机）
    $borderColor=ImageColorAllocate($im,127,157,185);//边框色
    $pointColor=ImageColorAllocate($im,255,170,255);//点颜色
    @imagefilledrectangle($im,0,0,$width - 1,$height - 1,$backColor);//背景位置
    @imagerectangle($im,0,0,$width-1,$height-1,$borderColor); //边框位置
    $stringColor=ImageColorAllocate($im,255,51,153);

    for($i=0;$i<=100;$i++){
      $pointX=rand(2,$width-2);
      $pointY=rand(2,$height-2);
      @imagesetpixel($im,$pointX,$pointY,$pointColor);
    }

    @imagestring($im,5,5,1,$randval,$stringColor);
    $ImageFun='Image'.$type;
    $ImageFun($im);
    @imagedestroy($im);
    session($name,$randval);
}
