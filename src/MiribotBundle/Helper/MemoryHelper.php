<?php
/**
 * Created by PhpStorm.
 * User: Khue Quang Nguyen
 * Date: 21-Aug-17
 * Time: 22:15
 */

namespace MiribotBundle\Helper;

use Symfony\Component\Cache\Simple\FilesystemCache;

class MemoryHelper extends FilesystemCache
{
    /**
     * MemoryHelper constructor.
     * @param string $namespace
     * @param int $defaultLifetime
     * @param string $directory
     */
    public function __construct($namespace = '', $defaultLifetime = 3600, $directory = 'miribot')
    {
        parent::__construct($namespace, $defaultLifetime, $directory);
    }

    /**
     * Remember the last sentence of bot's response
     * @param $answer
     */
    public function rememberLastSentence($answer)
    {
        $sentences = $this->sentenceSplitting($answer);
        $this->set('bot_last_sentence', end($sentences));
    }

    /**
     * Recall the last sentence of bot's response
     * @return mixed|null|string
     */
    public function recallLastSentence()
    {
        $sentence = $this->get('bot_last_sentence');
        return $sentence ? $sentence : "";
    }

    /**
     * Remember user data
     * @param $name
     * @param $data
     */
    public function rememberUserData($name, $data)
    {
        $this->set("user_data.{$name}", $data);
    }

    /**
     * Recall user data
     * @param string $name
     * @return mixed|null
     */
    public function recallUserData($name)
    {
        return $this->get("user_data.{$name}");
    }

    /**
     * Forget a user data
     * @param $name
     * @return bool
     */
    public function forgetUserData($name)
    {
        return $this->delete("user_data.{$name}");
    }

    /**
     * Split the input text into sentences
     * @param $text
     * @return array
     */
    protected function sentenceSplitting($text)
    {
        return preg_split("/[.!?\n]/", trim($text), -1, PREG_SPLIT_NO_EMPTY);
    }
}