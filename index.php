<?php
include("Task.php");

function parseTasks(&$data)
{
    foreach($data as $item)
    {
        $name = $item['name'];
        $command = $item['command'];
        $dependencies = $item['dependencies'];

        if (empty($name) || empty($command))
        {
            http_response_code(400);
            exit;
        }

        $task = new Task();
        $task->name = $name;
        $task->command = $command;
        $task->dependenciesName = $dependencies;
        $tasks[$name] = $task;
    }
    
    foreach($tasks as $task)
    {
        if (!empty($task->dependenciesName))
        {
            foreach($task->dependenciesName as $name)
            {
                if (empty($task->name))
                {
                    http_response_code(400);
                    exit; 
                }
            }
        }
    }

    return $tasks;
}

// returns the Tasks on which no other task depends
function nothingDependingOnTasks(&$tasks)
{
    foreach ($tasks as $item)
    {
        if (!empty($item->dependenciesName))
        {
            foreach($item->dependenciesName as $dependingOnName)
            {
                //tasks that depend on task $dependingOnName
                $dependingOnTasks[$dependingOnName][] = $item;
            }
        }
    }

    foreach ($tasks as $item)
    {
        if (empty($dependingOnTasks[$item->name]))
        {
            $result[] = $item;
        }
    }

    return $result;
}

//returns array that contains the tasks's names with needed order
function arrayDependency (&$last, &$tasks)
{
    $result = [];
    $seen = [];
    $unResolvedQueu = new \Ds\Queue();
    $unResolvedQueu->push($last->name);
    while (!$unResolvedQueu->isEmpty())
    {
        $taskName = $unResolvedQueu->pop();
        $seen[] = $taskName;
        $task = $tasks[$taskName];
        $result[] = $taskName;
        if (!empty($task->dependenciesName))
        {
            foreach ($task->dependenciesName as $name)
            {
                $unResolvedQueu->push($name);
            }
        }
    }

    $result = array_reverse($result);
    $result = array_unique($result);

    return $result;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$filename = $data['filename'];
if (empty($filename))
{
    http_response_code(400);
    exit;
}

$data = $data['tasks'];
if (empty($data))
{
    http_response_code(400);
    exit; 
}
$tasks = parseTasks($data);
$nothingDependingOn = nothingDependingOnTasks($tasks);

$result = [];
foreach ($nothingDependingOn as $last)
{
    $result = array_merge($result, arrayDependency($last, $tasks));
}

$result = array_unique($result);

if (!$file = fopen($filename, 'w'))
{
    http_response_code(500);
    echo "error open";
}
foreach ($result as $item)
{
    if (!fwrite($file, $tasks[$item]->command . "\n"))
    {
        http_response_code(500);
        echo "error write";
    }
}
if (!fclose($file))
{
    http_response_code(500);
    echo "error close";
}
