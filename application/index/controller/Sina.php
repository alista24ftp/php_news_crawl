<?php
namespace app\index\controller;
ini_set('max_execution_time', '1800');

use app\common\controller\Base;
use app\common\model\News;
use think\Db;

class Sina extends Base
{

  public function convertTime($timestr)
  {
    $timestr = preg_replace('/年/', '-', $timestr);
    $timestr = preg_replace('/月/', '-', $timestr);
    $timestr = preg_replace('/日/', '', $timestr);
    $datepart = explode(" ", $timestr);
    return strtotime($datepart[0]);
  }

  public function insertIntoDb($d)
  {
    Db::table('jt_news')->insert([
      'news_title'=>$d['title'],
      'news_columnid'=>$d['columnid'],
      'news_columnviceid'=>$d['columnviceid'],
      'news_auto'=>1,
      'news_source'=>$d['source'],
      'news_content'=>$d['content'],
      'news_scontent'=>$d['scontent'],
      'news_img'=>$d['image'],
      'news_pic_type'=>1,
      'news_time'=>$d['pdate'],
      'news_back'=>0,
      'news_open'=>1,
      'comment_status'=>1
    ]);
  }

  public function testArticle()
  {
    $url = "http://www.sohu.com/a/270473665_102828";
    $html = $this->getHtml($url);
    //preg_match("/<div class=\"date-source\".*>(.*)<\/div>/U", $html, $datesource);
    dump($html);
  }

  public function crawlJiadianNews()
  {
    $articles = $this->getJiadianLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/<span class=\"source\">(.*)<\/span>/U", $html, $source_arr);
      preg_match("/(<!--新增众测推广文案end.*)<!--/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['source'] = $source;
      $article['content'] = $content;
      $article['category'] = '家电';
      $article['columnid'] = 35;
      $article['columnviceid'] = 15;
      array_push($data, $article);
       //$this->insertIntoDb($article);
    }
    dump($data);
  }

  public function getJiadianLinks()
  {
    $links = [];
    $html = $this->getHtml("http://tech.sina.com.cn/elec/");
    $items = preg_split("/<div class=\"news-item.*img-news-item\">/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h2.*>(.*)<\/h2>/U", $item, $h2);
        $l = '';
        if(!empty($h2)){
          preg_match("/<a.*href=\"(.*)\"/U", $h2[1], $link_arr);
          $l = (empty($link_arr)) ? '' : $link_arr[1];
        }
        $title = (empty($h2)) ? '' : trim(strip_tags($h2[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        $image = (empty($img)) ? '' : $img[1];
        preg_match("/<div class=\"p\">(.*)<\/div>/U", $item, $scontent_arr);
        $scontent = empty($scontent_arr) ? '' : trim(strip_tags($scontent_arr[1]));
        preg_match("/<div class=\"time\">(.*)<\/div>/U", $item, $pdate_arr);
        if(!empty($pdate_arr)){
          preg_match("/(年)/U", $pdate_arr[1], $year);
          $pdate = empty($year) ? '2018-' . trim($pdate_arr[1]) : trim($pdate_arr[1]);
        }
        $pdate = empty($pdate_arr) ? '' : $this->convertTime($pdate);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image,
          'scontent'=>$scontent,
          'pdate'=>$pdate
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlFashionIconNews()
  {
    $articles = $this->getFashionIconLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
      $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
      $scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '时尚人物';
      $article['columnid'] = 55;
      $article['columnviceid'] = 18;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getFashionIconLinks()
  {
    $links = [];
    $html = $this->getHtml("http://fashion.sohu.com/1062");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlClothingNews()
  {
    $articles = $this->getClothingLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
      $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
      $scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '潮流时装';
      $article['columnid'] = 53;
      $article['columnviceid'] = 18;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getClothingLinks()
  {
    $links = [];
    $html = $this->getHtml("http://www.sohu.com/tag/63676");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlJewelryNews()
  {
    $articles = $this->getJewelryLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
      $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
      $scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '奢饰珠宝';
      $article['columnid'] = 52;
      $article['columnviceid'] = 18;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getJewelryLinks()
  {
    $links = [];
    $html = $this->getHtml("http://www.sohu.com/tag/63689");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlMovieNews()
  {
    $articles = $this->getMovieLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '电影';
      $article['columnid'] = 37;
      $article['columnviceid'] = 3;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getMovieLinks()
  {
    $links = [];
    $html = $this->getHtml("http://yule.sohu.com/1409");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlTVNews()
  {
    $articles = $this->getTVLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '电视剧';
      $article['columnid'] = 38;
      $article['columnviceid'] = 3;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getTVLinks()
  {
    $links = [];
    $html = $this->getHtml("http://yule.sohu.com/1408");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlLicaiNews()
  {
    $articles = $this->getLicaiLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '理财';
      $article['columnid'] = 27;
      $article['columnviceid'] = 2;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getLicaiLinks()
  {
    $links = [];
    $html = $this->getHtml("http://business.sohu.com/998");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlWaihuiNews()
  {
    $articles = $this->getWaihuiLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '外汇';
      $article['columnid'] = 26;
      $article['columnviceid'] = 2;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getWaihuiLinks()
  {
    $links = [];
    $html = $this->getHtml("http://www.sohu.com/tag/67040");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlMobileNews()
  {
    $articles = $this->getMobileLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '手机';
      $article['columnid'] = 32;
      $article['columnviceid'] = 15;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getMobileLinks()
  {
    $links = [];
    $html = $this->getHtml("http://www.sohu.com/tag/59740");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlDigitalNews()
  {
    $articles = $this->getDigitalLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '数码';
      $article['columnid'] = 34;
      $article['columnviceid'] = 15;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getDigitalLinks()
  {
    $links = [];
    $html = $this->getHtml("http://it.sohu.com/936");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlLiuxueNews()
  {
    $articles = $this->getLiuxueLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '留学';
      $article['columnid'] = 50;
      $article['columnviceid'] = 17;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getLiuxueLinks()
  {
    $links = [];
    $html = $this->getHtml("http://learning.sohu.com/18");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlNBANews()
  {
    $articles = $this->getNBALinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = 'NBA';
      $article['columnid'] = 28;
      $article['columnviceid'] = 5;
      array_push($data, $article);
      $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getNBALinks()
  {
    $links = [];
    $html = $this->getHtml("http://sports.sohu.com/nba.shtml");
    $html = iconv("gb2312", "utf-8//IGNORE",$html);
    $items = preg_split("/<div class=\"article-list\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h3.*>(.*)<\/h3>/U", $item, $h3);
        $l = '';
        if(!empty($h3)){
          $link = preg_split("/<a/U", $h3[1]);
          preg_match("/href=\"(.*)\">/U", $link[2], $link_arr);
          $l = (empty($link_arr)) ? '' : $link_arr[1];
        }
        $title = (empty($h3)) ? '' : trim(preg_replace("/comment num/", "", strip_tags($h3[1])));

        preg_match("/发表于(.*)<\//U", $item, $pdate_arr);
        $pdate = (empty($pdate_arr)) ? '' : strtotime(trim(strip_tags($pdate_arr[1])));
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'pdate'=>$pdate,
          'image'=>''
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlBeautyNews()
  {
    $articles = $this->getBeautyLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '美容';
      $article['columnid'] = 54;
      $article['columnviceid'] = 18;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getBeautyLinks()
  {
    $links = [];
    $html = $this->getHtml("http://fashion.sohu.com/1051");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlAbroadNews()
  {
    $articles = $this->getAbroadLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
      $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
      $scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '境外';
      $article['columnid'] = 59;
      $article['columnviceid'] = 4;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getAbroadLinks()
  {
    $links = [];
    $html = $this->getHtml("http://travel.sohu.com/1447");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlHangyeNews()
  {
    $articles = $this->getHangyeLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
      $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
      $scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '旅游行业';
      $article['columnid'] = 58;
      $article['columnviceid'] = 4;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getHangyeLinks()
  {
    $links = [];
    $html = $this->getHtml("http://travel.sohu.com/1450");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlGonglueNews()
  {
    $articles = $this->getGonglueLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
      $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
      $scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '旅游攻略';
      $article['columnid'] = 57;
      $article['columnviceid'] = 4;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getGonglueLinks()
  {
    $links = [];
    $html = $this->getHtml("http://travel.sohu.com/1448");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlTravelNews()
  {
    $articles = $this->getTravelLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1 id=\"chan_newsTitle\">(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div id=\"chan_newsInfo\">.*)<div id=\"chan_newsDetail\">/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/\&nbsp;(.*)参与评论/U", $mdata[1], $source_arr);
      }
      preg_match("/(<div id=\"chan_newsDetail\">.*)<!-SSE LASTPAGE_CLEAR_END SSE->/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '旅游';
      $article['columnid'] = 56;
      $article['columnviceid'] = 4;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getTravelLinks()
  {
    $links = [];
    $html = $this->getHtml("https://travel.china.com/");
    $items = preg_split("/<div class=\"travel_news\">/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h3.*>(.*)<\/h3>/U", $item, $h3);
        $l = '';
        if(!empty($h3)){
          preg_match("/<a.*href=\"(.*)\"/U", $h3[1], $link_arr);
          $l = (empty($link_arr)) ? '' : $link_arr[1];
        }
        $title = (empty($h3)) ? '' : trim(strip_tags($h3[1]));
        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : trim($img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlCelebrityNews()
  {
    $articles = $this->getCelebrityLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1 id=\"chan_newsTitle\">(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div id=\"chan_newsInfo\">.*)<div id=\"chan_newsDetail\">/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        //preg_match("/\&nbsp\;<a.*>(.*)<\//U", $mdata[1], $source_arr);
        preg_match("/\&nbsp;(.*)参与评论/U", $mdata[1], $source_arr);
      }
      preg_match("/(<div id=\"chan_newsDetail\">.*)<!-- 广告位/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      //$source = !empty($source_arr) ? trim(strip_tags($source_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '明星';
      $article['columnid'] = 36;
      $article['columnviceid'] = 3;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getCelebrityLinks()
  {
    $links = [];
    $html = $this->getHtml("https://ent.china.com/star/news/index.html");
    $items = preg_split("/<div class=\"wntjItem.*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h3 class=\"tit\">(.*)<\/h3>/U", $item, $h3);
        $l = '';
        if(!empty($h3)){
          preg_match("/<a.*href=\"(.*)\"/U", $h3[1], $link_arr);
          $l = (empty($link_arr)) ? '' : $link_arr[1];
        }
        $title = (empty($h3)) ? '' : trim(strip_tags($h3[1]));
        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : trim($img[1]);
        preg_match("/<span class=\"time\">(.*)<\/span>/U", $item, $pdate_arr);
        $pdate = empty($pdate_arr) ? '' : $pdate_arr[1];
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image//,
          //'pdate' => $pdate
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function getNewCelebrityLinks()
  {
      $links = [];
      $jsonStr = $this->getHtml("https://feed.sina.com.cn/api/roll/get?pageid=107&lid=1245&num=30&versionNumber=1.2.4&page=1&encode=utf-8&callback=feedCardJsonpCallback&_=".time());
      preg_match('/^.*?data\":(\[.*?)}}\);}catch\(e\)/', $jsonStr, $jsonData);
      dump(json_decode($jsonData[1]));
  }

  public function crawlNewCars()
  {
    $articles = $this->getNewCarsLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1 id=\"chan_newsTitle\">(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div id=\"chan_newsInfo\">.*)<div id=\"chan_newsDetail\">/U", $html, $mdata);
      if(!empty($mdata)) preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
      preg_match("/来源：(.*)<\//U", $html, $source_arr);
      preg_match("/(<div id=\"chan_newsDetail\">.*)<!-SSE LASTPAGE_CLEAR_END SSE->/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags($source_arr[1])) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
    //  $article['source'] = $source;
      $article['content'] = $content;
      $article['category'] = '新车';
      $article['columnid'] = 40;
      $article['columnviceid'] = 6;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getNewCarsLinks()
  {
    $links = [];
    $html = $this->getHtml("https://auto.china.com/zhuanzai/newcar/index.html");
    $items = explode('<div class="item">', $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h3 class=\"item-tit\">(.*)<\/h3>/U", $item, $h3);
        $l = '';
        if(!empty($h3)){
          preg_match("/<a.*href=\"(.*)\"/U", $h3[1], $link_arr);
          $l = (empty($link_arr)) ? '' : $link_arr[1];
        }
        $title = (empty($h3)) ? '' : trim(strip_tags($h3[1]));
        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        $image = (empty($img)) ? '' : trim($img[1]);
        preg_match("/<p class=\"item-summary\">(.*)</U", $item, $scontent_arr);
        $scontent = (empty($scontent_arr)) ? '' : $scontent_arr[1];
        preg_match("/<span class=\"item-time\">(.*)<\/span>/U", $item, $pdate_arr);
        $pdate = empty($pdate_arr) ? '' : $pdate_arr[1];
        preg_match("/<em class=\"item-press\">(.*)<\/em>/U", $item, $source_arr);
        $source = empty($source_arr) ? '' : $source_arr[1];
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image,
          'scontent'=>$scontent,
          'pdate' => $pdate,
          'source'=>$source
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlInternetNews()
  {
    $articles = $this->getInternetLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1 id=\"chan_newsTitle\">(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div id=\"chan_newsInfo\">.*)<div id=\"chan_newsDetail\">/U", $html, $mdata);
      if(!empty($mdata)) preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
      preg_match("/来源：(.*)<\//U", $html, $source_arr);
      preg_match("/(<div id=\"chan_newsDetail\">.*)<div class=\"kong10\"/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags($source_arr[1])) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $article['category'] = '互联网';
      $article['columnid'] = 33;
      $article['columnviceid'] = 15;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getInternetLinks()
  {
    $links = [];
    $html = $this->getHtml("https://tech.china.com/internet/");
    $items = explode('<div class="con_item">', $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h2>(.*)<\/h2>/U", $item, $h2);
        $l = '';
        if(!empty($h2)){
          preg_match("/<a.*href=\"(.*)\"/U", $h2[1], $link_arr);
          $l = (empty($link_arr)) ? '' : $link_arr[1];
        }
        $title = (empty($h2)) ? '' : trim(strip_tags($h2[1]));
        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        $image = (empty($img)) ? '' : trim($img[1]);
        preg_match("/<div class=\"conR_txt\"><a.*>(.*)<\/a>/U", $item, $scontent_arr);
        $scontent = (empty($scontent_arr)) ? '' : $scontent_arr[1];
        preg_match("/<div class=\"fenglei\">(.*)\&/U", $item, $pdate_arr);
        $pdate = empty($pdate_arr) ? '' : $pdate_arr[1];
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image,
          'scontent'=>$scontent//,
          //'pdate' => $pdate
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlJiaju()
  {
    $articles = $this->getJiajuLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<div class=\"h-title\">(.*)<\/div>/U", $html, $title_arr);
      preg_match("/class=\"h-time\".*>(.*)<\//U", $html, $pdate_arr);
      preg_match("/id=\"source\".*>(.*)<\//U", $html, $source_arr);
      preg_match("/(<div id=\"p-detail\">.*)<div class=\"l-ad-1\"/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags($source_arr[1])) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $article['category'] = '家居';
      $article['columnid'] = 44;
      $article['columnviceid'] = 16;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getJiajuLinks()
  {
    $links = [];
    $html = $this->getHtml("http://www.xinhuanet.com/jiaju/");
    $li = explode('<li class="clearfix">', $html);
    foreach($li as $k => $item){
      if($k>0){
        preg_match("/<h3>(.*)<\/h3>/U", $item, $h3);
        $l = '';
        if(!empty($h3)){
          preg_match("/<a.*href=\"(.*)\"/U", $h3[1], $link_arr);
          $l = (empty($link_arr)) ? '' : $link_arr[1];
        }
        $title = (empty($h3)) ? '' : trim(strip_tags($h3[1]));
        preg_match("/<img.*data-original=(.*)\/>/U", $item, $img);
        $image = (empty($img)) ? '' : trim($img[1]);
        preg_match("/<p class=\"summary\">(.*)<\/p>/U", $item, $scontent_arr);
        $scontent = (empty($scontent_arr)) ? '' : $scontent_arr[1];
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image,
          'scontent'=>$scontent
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlXiaoyuanNews()
  {
    $articles = $this->getXiaoyuanLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
      $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
      $scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '校园';
      $article['columnid'] = 51;
      $article['columnviceid'] = 17;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getXiaoyuanLinks()
  {
    $links = [];
    $html = $this->getHtml("http://learning.sohu.com/1201");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }


  public function crawlKaoyanNews()
  {
    $articles = $this->getKaoyanLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
      $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
      $scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '考研';
      $article['columnid'] = 49;
      $article['columnviceid'] = 17;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getKaoyanLinks()
  {
    $links = [];
    $html = $this->getHtml("http://learning.sohu.com/20");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlGaokaoNews()
  {
    $articles = $this->getGaokaoLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<div class=\"h-title\">(.*)<\/div>/U", $html, $title_arr);
      preg_match("/class=\"h-time\".*>(.*)<\//U", $html, $pdate_arr);
      preg_match("/id=\"source\".*>(.*)<\//U", $html, $source_arr);
      preg_match("/(<div id=\"p-detail\">.*)<div class=\"l-ad-1\"/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags($source_arr[1])) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue; continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $article['category'] = '高考';
      $article['columnid'] = 48;
      $article['columnviceid'] = 17;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getGaokaoLinks()
  {
    $links = [];
    $html = $this->getHtml("http://education.news.cn/gaokao/index.htm");
    $li = explode('<li class="clearfix">', $html);
    foreach($li as $k => $item){
      if($k>0){
        preg_match("/<h3>(.*)<\/h3>/U", $item, $h3);
        $l = '';
        if(!empty($h3)){
          preg_match("/<a.*href=\"(.*)\"/U", $h3[1], $link_arr);
          $l = (empty($link_arr)) ? '' : $link_arr[1];
        }
        $title = (empty($h3)) ? '' : trim(strip_tags($h3[1]));
        preg_match("/<img.*data-original=(.*)\/>/U", $item, $img);
        $image = (empty($img)) ? '' : trim($img[1]);
        preg_match("/<p class=\"summary\">(.*)<\/p>/U", $item, $scontent_arr);
        $scontent = (empty($scontent_arr)) ? '' : $scontent_arr[1];
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image,
          'scontent'=>$scontent
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlFundNews()
  {
    $articles = $this->getFundLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '基金';
      $article['columnid'] = 25;
      $article['columnviceid'] = 2;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getFundLinks()
  {
    $links = [];
    $html = $this->getHtml("http://www.sohu.com/tag/66047");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function crawlStockNews()
  {
    $articles = $this->getStockLinks();
    $data = [];
    foreach($articles as $k => $article){
      $url = $article['link'];
      if($url == '') continue;
      $html = $this->getHtml($url);
      preg_match("/<h1.*>(.*)<\/h1>/U", $html, $title_arr);
      preg_match("/(<div class=\"article-info\">.*)<\/div>/U", $html, $mdata);
      if(!empty($mdata)) {
        preg_match("/(\d{4}-\d{2}-\d{2})/U", $mdata[1], $pdate_arr);
        preg_match("/来源:(.*)<\//U", $mdata[1], $source_arr);
      }
      preg_match("/(<article class=\"article\".*<\/article>)/U", $html, $content_arr);
      $title = !empty($title_arr) ? trim(strip_tags($title_arr[1])) : '';
      $pdate = !empty($pdate_arr) ? trim(strip_tags($pdate_arr[1])) : '';
      $source = !empty($source_arr) ? trim(strip_tags(preg_replace("/\&nbsp\;/", "", $source_arr[1]))) : '';
      $content = !empty($content_arr) ? $content_arr[1] : '';
      if($title == '' || $content == '') continue;
      $article['title'] = $title;
      $article['pdate'] = strtotime($pdate);
      $article['source'] = $source;
      $article['content'] = $content;
      $scontent = '';
			$scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
			$scontent = mb_substr($scontent,0,50,'utf8')."...";
      $article['scontent'] = $scontent;
      $article['category'] = '股票';
      $article['columnid'] = 24;
      $article['columnviceid'] = 2;
      array_push($data, $article);
       $this->insertIntoDb($article);
    }
    //dump($data);
  }

  public function getStockLinks()
  {
    $links = [];
    $html = $this->getHtml("http://business.sohu.com/997");
    $items = preg_split("/<div data-role=\"news-item\".*>/U", $html);
    foreach($items as $k => $item){
      if($k>0){
        preg_match("/<h4.*>(.*)<\/h4>/U", $item, $h4);
        $l = '';
        if(!empty($h4)){
          preg_match("/<a.*href=\"(.*)\"/U", $h4[1], $link_arr);
          $l = (empty($link_arr)) ? '' : preg_replace("/.*\/\//U", "http://", $link_arr[1]);
        }
        $title = (empty($h4)) ? '' : trim(strip_tags($h4[1]));

        preg_match("/<img.*src=\"(.*)\"/U", $item, $img);
        if(empty($img)){
          preg_match("/<img.*data-original=\"(.*)\"/U", $item, $img);
        }
        $image = (empty($img)) ? '' : preg_replace("/.*\/\//U", "http://", $img[1]);
        array_push($links,[
          'link'=>$l,
          'title'=>$title,
          'image'=>$image
        ]);
      }
    }
    //dump($links);
    return $links;
  }

  public function getHtml($url)
  {
    $context = stream_context_create(array('http'=>array('ignore_errors'=>true)));
    $html = @file_get_contents($url, false, $context);
    //$html = iconv("gb2312", "utf-8//IGNORE",$html);
    $html = preg_replace("/[\t\n\r]+/", "", $html);
    return $html;
  }
}
