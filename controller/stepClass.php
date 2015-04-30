<?php 

 header("content-type:text/html;charset=utf-8");
// $xoops[1] = '小';
// $xoops[2] = '孩';
// $xoops[3] = '子';
// $xoops[4] = '气';


// $steps = new Steps();

// foreach($xoops as $key=>$value){

// $steps->add($key);

// }

// $steps->setCurrent(3);//参数为key值
// echo '上一个下标：'.$steps->getPrev()."<br />";
// echo '指定的下标：'.$steps->getCurrent()."<br />";
// echo '下一个下标：'.$steps->getNext()."<br />";




class RoomController {

 

  public $all;

  public $count;

  public $curr;

 

  public   function __construct() {
    echo 1;die;
    $this->count = 0;

  }

  public function add($step) {

    $this->count++;

    $this->all[$this->count] = $step;

  }

  public function setCurrent($step) {

    reset($this->all);

    for ($i = 1; $i <= $this->count; $i++) {

      if ($this->all[$i] == $step)

        break;

      next($this->all);

    }

    $this->curr = current($this->all);

  }

  public function getCurrent() {

    return $this->curr;

  }

  public function getNext() {

    self::setCurrent($this->curr);

    return next($this->all);

  }

  public function getPrev() {

    self::setCurrent($this->curr);

    return prev($this->all);

  }

}