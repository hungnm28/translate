<?php
if(!function_exists('lTranslate')){
    function lTranslate(){
        return \Hungnm28\Translate\Translate::getInstance();
    }

}
if(!function_exists("lTrans")){
    function lTrans($text=''){
        return lTranslate('lang')->trans($text);
    }
}

