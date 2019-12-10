Video processing class
======================

Author: <panevnyk.roman@gmail.com>

Requirements
------------
- PHP 5.3+
- Melt installed on server
- enabled PHP functions exec, shell_exec
- PHP extensions: simplexml, libxml, json

Example of use
--------------

~~~
require __DIR__ . '/vendor/autoload.php';

$videoProcessing = new Andchir\VideoProcessing([
    'melt_path' => '/usr/bin/melt',
    'session_start' => true
]);

$videoProcessing
    ->setProfile('hdv_720_25p')
    ->addOption(['joinClips' => [
        $rootPath . '/uploads/tmp/Cat.mp4',
        $rootPath . '/uploads/tmp/Dog.mp4',
    ]])
    ->setOutputVideoOptions($rootPath . '/uploads/tmp/CatDog.mp4');
    
$videoProcessing->render();
~~~

Install Melt on Ubuntu:
~~~
sudo apt install melt
~~~