<?php
$arr = array(
    'name' => "huyongde",
    'age' => 23,
    "birthday" => "19890110",
);

var_dump(current($arr));
var_dump(key($arr));
var_dump(next($arr));
var_dump(current($arr));
var_dump(key($arr));

function xrange($start, $limit, $step = 1) {
    for($i=$start;$i<=$limit;$i++){
        yield $i;
    }
}
var_dump(xrange(1,100000000));
foreach (xrange(0,10,2) as $key => $value) {
    printf("%d %d \n", $key, $value);
}

function printer() {
    while(true) {
        printf("receive: %s \n", yield);
    }

}
$printer = printer();
$printer->send("hello world!");
$printer->send("huyongde !");
var_dump($printer);

function gen() {
    $ret = (yield 'yield1');
    var_dump($ret);
    $ret1 = (yield "yield2");
}

echo "start new \\n\n\n";
$gen = gen();
var_dump($gen->current());
var_dump($gen->send("ret1"));
//如上会输出: yield1 ret1  yield2

echo "second send !\n";
var_dump($gen->current());
//会输出: yield2
//
//

echo "实现logger \n";
function logger($filename) {
    $fileHandle = fopen($filename, "a");
    while(true) {
        fwrite($fileHandle, yield . "\n");
    }
}

$logger = logger(__DIR__ . "/test.log");
$logger->send("Foo");
$logger->send("Bar");

//
/// 下面定义一个类，实现对携程函数的封装。

class Task {
    protected $taskId;
    protected $coroutine;
    protected $sendValue = null;
    protected $beforeFirstYield = true;

    public function __construct($taskid, Generator $coroutine) {
        $this->taskId  = $taskid;
        $this->coroutine = $coroutine;
    }

    public function getTaskId() {
        return $this->taskId;
    }

    public function setSendValue($sendValue) {
        $this->sendValue = $sendValue;
    }

    public function run() {
        if ($this->beforeFirstYield) { //添加beforeFirstYield主要是为了能够收到send触发的第一个yield的返回。
            echo "first yield \n";
            $this->beforeFirstYield = false;
            return $this->coroutine->current();
        } else {
            /*
            $this->coroutine->next();
            return $this->coroutine->current();
             */
            $retval = $this->coroutine->send($this->sendValue);
            $retval = $this->coroutine->send("xxx");
            $this->sendValue = null;
            return $retval;
        }

    }

    public function isFinished() {
        return !$this->coroutine->valid();
    }
}

class Scheduler {
    protected $maxTaskId = 0;
    protected $taskMap = [];
    protected $taskQueue;

    public function __construct() {
        $this->taskQueue = new splQueue();
    }

    public function newTask (Generator $coroutine) {
        $tid = ++$this->maxTaskId;
        $task = new Task($tid, $coroutine);
        $this->taskMap[$tid] = $task;
        $this->schedule($task);
        return $tid;
    }

    public function schedule($task) {
        $this->taskQueue->enqueue($task);
    }

    public function run() {
        while(!$this->taskQueue->isEmpty()) {
            //sleep(1);
            //var_dump($this->taskQueue);
            $task = $this->taskQueue->dequeue();
            $task->run();

            if ($task->isFinished()) {
                unset($this->taskMap[$task->getTaskId()]);
            } else {
                $this->schedule($task);
            }
        }
    }
}

function task1() {
    for ($i=0; $i<5; $i++) {
        echo "task 1 $i \n";
        yield;
    }
}



function task2() {
    for ($i=0; $i<15; $i++) {
        echo "task 2 $i\n";
        yield;
    }
}

echo "\n\n多任务调度器 \n";
$scheduler = new Scheduler();

$scheduler->newTask(task1());
$scheduler->newTask(task2());

$scheduler->run();


