<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/26
 * Time: 17:08
 */

namespace app\index\crawl;
ini_set('max_execution_time', '1800');


class Crawl
{
    private $limit;
    private $lastCrawlDate;

    public function __construct($limit=100)
    {
        $this->limit = $limit;
        $this->lastCrawlDate = mktime(0,0,0,2,13,2019);
        //$this->lastCrawlDate = mktime(0,0,0,1,1,2018);
    }

    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function getLastCrawlDate()
    {
        return $this->lastCrawlDate;
    }

    public function getJson($url){
        $context = stream_context_create([
            'http'=>[
                'ignore_errors'=>true,
                'method'=>'GET',
                'header'=>'Accept: application/json\r\n'
            ]
        ]);
        $json = @file_get_contents($url, false, $context);
        return $json;
    }

    public function getProp($pattern, $html)
    {
        preg_match($pattern, $html, $res);
        if(empty($res))
            return null;
        return trim($res[1]);
    }

    public function getHtml($url)
    {
        $context = stream_context_create(array('http'=>array('ignore_errors'=>true)));
        $html = @file_get_contents($url, false, $context);
        //$html = iconv("gb2312", "utf-8//IGNORE",$html);
        $html = preg_replace("/[\t\n\r]+/", "", $html);
        $html = preg_replace("/<script.*?<\/script>/", "", $html);
        $html = preg_replace("/<style.*?<\/style>/", "", $html);
        //$html = preg_replace("/style=\".*?\">/", ">", $html);//

        return $html;
    }

    public function getCurlHtml($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch,CURLOPT_ENCODING , "deflate");

        $header = [
            'Host: pcflow.dftoutiao.com',
            'Connection: keep-alive',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36',
            'Accept: */*',
            'Referer: http://mini.eastday.com/',
            'Accept-Encoding: gzip, deflate',
            'Accept-Language: zh-CN,zh;q=0.9'
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    public function filterLinksByDate($timestamp)
    {
        return $this->lastCrawlDate < $timestamp;
    }
}