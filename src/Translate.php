<?php

namespace Hungnm28\Translate;

use Hungnm28\Translate\Traits\SingletonTrait;
use Illuminate\Support\Facades\Storage;

/**
 *
 */
class Translate
{
    use SingletonTrait;

    /**
     * @var array
     */
    private $texts = [];
    /**
     * @var array
     */
    private $defaults = [];
    /**
     * @var
     */
    private $pathLang;
    /**
     * @var
     */
    private $pathDefault;

    /**
     * @param $text
     * @param $prefix
     * @return array|mixed|string
     */
    public function trans($text, $prefix = null)
    {
        $text = $this->validate($text);
        $this->getTrans($prefix);
        $key = md5($text);
        $return = data_get($this->texts, $key);
        if ($return) {
            return $return;
        }
        $this->getDefault($prefix);
        $return = data_get($this->defaults, $key);
        if ($return) {
            return $return;
        }
        $return = $text;
        $this->defaults[$key] = $return;
        $this->saveDefault();
        return $return;
    }

    /**
     * @param $key
     * @param $text
     * @param $prefix
     * @return void
     */
    public function updateTrans($key, $text, $prefix = null)
    {
        $text = $this->validate($text);
        $this->getTrans($prefix);
        if (isset($this->texts[$key])) {
            $this->texts[$key] = $text;
            $this->saveTrans();
        }
    }


    /**
     * @param $prefix
     * @param $locale
     * @return void
     */
    function translateAll($prefix = null, $locale = null)
    {
        $this->getDefault($prefix);
        $this->getTrans($prefix);
        foreach ($this->defaults as $key => $text) {
            if (!isset($this->texts[$key])) {
                $this->translateOnly($text, $prefix, $locale);
            }
        }
    }


    /**
     * @param $text
     * @param $prefix
     * @param $locale
     * @return array|mixed
     */
    public function translateOnly($text, $prefix = null, $locale = null)
    {
        if (!$locale) {
            $locale = env("APP_LOCALE", 'vi');
        }

        $text = $this->validate($text);
        $key = md5($text);
        $this->getTrans($prefix);
        $transClient = new \Google\Cloud\Translate\V2\TranslateClient([
            'keyFilePath' => base_path(env("GOOGLE_KEY_FILE")),
            'projectId' => env("GOOGLE_PROJECT_ID"),
            'suppressKeyFileNotice' => true,
        ]);

        $result = $transClient->translate($text, [
            'target' => $locale,
        ]);
        $text = data_get($result, "text");
        if ($text) {
            $this->texts[$key] = $text;
            $this->saveTrans();
        }
        return $text;
    }


    /**
     * @param $prefix
     * @return array
     */
    public function getTrans($prefix = null)
    {
        if ($this->texts) {
            return $this->texts;
        }
        $this->setPathTran($prefix);

        $this->texts = (array)json_decode(Storage::disk('local')->get($this->pathLang), 1);
        return $this->texts;
    }

    /**
     * @param $prefix
     * @return void
     */
    public function resetDefault($prefix = null)
    {
        $this->setPathDefault($prefix);
        Storage::disk("local")->put($this->pathDefault, json_encode([]));
    }

    /**
     * @param $prefix
     * @return void
     */
    public function resetTrans($prefix = null)
    {
        $this->setPathTran($prefix);
        Storage::disk("local")->put($this->pathLang, json_encode([]));
    }

    /**
     * @return void
     */
    private function saveTrans()
    {
        Storage::disk("local")->put($this->pathLang, json_encode($this->texts));
    }

    /**
     * @param $prefix
     * @return array
     */
    public function getDefault($prefix = null)
    {
        if ($this->defaults) {
            return $this->defaults;
        }
        $this->setPathDefault($prefix);
        $this->defaults = (array)json_decode(Storage::disk('local')->get($this->pathDefault), 1);
        return $this->defaults;
    }

    /**
     * @return void
     */
    private function saveDefault()
    {
        Storage::disk("local")->put($this->pathDefault, json_encode($this->defaults));
    }

    /**
     * @param $prefix
     * @return void
     */
    private function setPathDefault($prefix = null)
    {
        $this->pathDefault = "translate/" . $prefix . "default.json";
    }

    /**
     * @param $prefix
     * @return void
     */
    private function setPathTran($prefix = null)
    {
        $this->pathLang = "translate/" . $prefix . env('APP_LOCALE', config('app.locale')) . ".json";
    }

    /**
     * @param $str
     * @return string
     */
    private function validate($str)
    {
        $this->removeHTML($str);
        $this->replaceMQ($str);
        $this->replaceSpecialChar($str);
        $str = trim($str);
        return $str;
    }

    /**
     * @param $string
     * @return array|string|string[]|null
     */
    private function removeHTML($string)
    {
        $breaks = array("<br />", "<br>", "<br/>");
        $string = str_replace('&nbsp;', ' ', $string);
        $string = preg_replace('/(?:\s*<br[^>]*>\s*){3,}/s', "<br>", $string);
        $string = str_ireplace($breaks, "\r\n", $string);
        $string = preg_replace('/<script.*?\>.*?<\/script>/si', ' ', $string);
        $string = preg_replace('/<style.*?\>.*?<\/style>/si', ' ', $string);
        $string = preg_replace('/<\/.*?\>/si', "\r\n", $string);
        $string = preg_replace('/<.*?\>/si', ' ', $string);


        return $string;
    }

    /**
     * @param $text
     * @return array|string|string[]
     */
    private function replaceMQ($text)
    {
        $text = str_replace("\'", "'", $text);
        $text = str_replace("'", "''", $text);

        return $text;
    }

    /**
     * @param $string
     * @param $replace
     * @return string
     */
    private function replaceSpecialChar($string, $replace = "")
    {
        $search = array('/', '\\', ':', ';', '!', '@', '#', '$', '%', '^', '*', '(', ')', '_', '+', '=', '|', '{', '}', '[', ']', '"', "'", '<', '>', ',', '?', '~', '`', '&', '.');
        $string = str_replace($search, $replace, $string);
        $string = trim($string, " ");
        return $string;
    }

}
