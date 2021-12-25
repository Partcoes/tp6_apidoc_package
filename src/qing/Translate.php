<?php
/**
 * Take people's money and get rid of disasters
 * 拿人钱财 与人消灾
 *
 * User: 段帅强
 * Date: 2021/11/16
 * Time: 16:08
 */

namespace Qing\QingDoc\qing;


interface Translate
{
    /**
     * 构建第三方接口参数
     * @return mixed
     */
    public function buildParam();

    /**
     * 进行翻译操作
     * @param string $word
     * @param string $from
     * @param string $to
     * @return mixed
     */
    public function translate(string $word,string $from,string $to);
}