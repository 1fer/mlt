<?php

/**
 * Class for video processing with Melt
 * @version 1.3.2
 * @author Andchir <andchir@gmail.com>
 */

namespace Andchir;

class VideoProcessing {

    /** @var string */
    private $profileName = 'hdv_720_25p';
    /** @var int */
    private $width = 1280;
    /** @var int */
    private $height = 720;
    /** @var int */
    private $fps = 25;
    /** @var string */
    private $format;
    private $innerKeys = ['joinClips', 'inputSource', 'clipOption'];
    private $consumer = [];
    private $optionsArray = [];
    private $commandsArray = [];
    public $config = [];

    public function __construct($config = [])
    {
        $this->config = array_merge([
            'melt_path' => '/usr/bin/melt',
            'tmp_dir_path' => dirname(__DIR__)
                . DIRECTORY_SEPARATOR . 'uploads'
                . DIRECTORY_SEPARATOR . 'tmp',
            'wipes_dir_path' => dirname(__DIR__)
                . DIRECTORY_SEPARATOR . 'assets'
                . DIRECTORY_SEPARATOR . 'wipes',
            'debug' => false,
            'logging' => false,
            'max_log_size' => 200 * 1024,
            'date_format' => 'd/m/Y H:i:s',
            'session_start' => false
        ], $config);

        if ($this->config['session_start'] && !session_id()) {
            session_start();
        }
    }

    /**
     * Set output size
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function setOutputSize($width, $height)
    {
        $this->width = $width;
        $this->height = $height;
        return $this;
    }

    /**
     * get width
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * Get height
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Get fps
     * @return int
     */
    public function getFps()
    {
        return $this->fps;
    }

    /**
     * @param $fps
     * @return $this
     */
    public function setFps($fps)
    {
        $this->fps = $fps;
        return $this;
    }

    /**
     * Get format
     * @return string
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * Set output format
     * @param string $format
     * @return $this
     */
    public function setOutputFormat($format)
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Update configuration
     * @param $config
     * @return $this
     */
    public function updateConfig($config)
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Add option
     * @param $optionArray
     * @param string $key
     * @return $this
     */
    public function addOption($optionArray, $key = 'main')
    {
        if (!isset($this->optionsArray[$key])) {
            $this->optionsArray[$key] = [];
        }
        $this->optionsArray[$key][] = $optionArray;
        return $this;
    }

    /**
     * Create commands array
     */
    public function createCommands()
    {
        $this->commandsArray = [];
        if ($this->config['debug']) {
            print_r($this->optionsArray);
        }
        foreach ($this->optionsArray as $k => $optionsArr) {
            $cmd = $this->config['melt_path'];
            foreach ($optionsArr as $index => $optionArr) {
                foreach ($optionArr as $key => $val) {
                    if (!in_array($key, $this->innerKeys)) {
                        $cmd .= " \\" . PHP_EOL . "-{$key}";
                    }
                    $cmd .= $this->getOptionsString($val, true);
                }
            }
            if ($this->profileName && !empty($this->consumer[$k])) {
                $cmd .= " \\" . PHP_EOL . "-profile {$this->profileName} -progress";
            }
            $cmd .= $this->getConsumerString($k);
            $this->commandsArray[$k] = trim($cmd);
        }
    }

    /**
     * Get consumer string
     * @param string $key
     * @return string
     */
    public function getConsumerString($key = 'main')
    {
        if (empty($this->consumer[$key])) {
            return '';
        }
        $output = " \\" . PHP_EOL . "-consumer ";
        foreach ($this->consumer[$key] as $consumerName => $opts) {
            $output .= $consumerName . ":\"{$opts['outputPath']}\"";
            $output .= $this->getOptionsString($opts['options'], true);
        }
        return $output;
    }

    /**
     * Set output video options
     * @param string $outputPath
     * @param array $options
     * @param string $key
     * @return $this
     */
    public function setOutputVideoOptions($outputPath, $options = [], $key = 'main')
    {
        $defaultOptions = [
            'mp4' => [
                'vcodec' => 'libx264',
                'vb' => '5000k',
                'acodec' => 'aac',
                'ab' => '128k',
                'frequency' => 44100,
                'deinterlace' => 1
            ],
            'webm' => [
                'vcodec' => 'libvpx',
                'vb' => '5000k',
                'acodec' => 'libvorbis',
                'ab' => '128k',
                'frequency' => 44100,
                'deinterlace' => 1
            ]
        ];
        if (!$this->getFormat()) {
            $format = self::getExtension($outputPath);
            if (!in_array($format, array_keys($defaultOptions))) {
                $format = 'mp4';
            }
            $this->setOutputFormat($format);
        }
        $this->consumer[$key] = [
            'avformat' => [
                'outputPath' => $outputPath,
                'options' => array_merge($defaultOptions[$this->getFormat()], $options)
            ]
        ];
        return $this;
    }

    /**
     * Get option string
     * @param array|string $options
     * @param bool $prefixNewLine
     * @param bool $prefixSpace
     * @return string
     */
    public function getOptionsString($options, $prefixNewLine = false, $prefixSpace = false)
    {
        if (!is_array($options)) {
            return ' ' . $options;
        }
        $output = '';
        foreach ($options as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $output .= " -{$key} ";
                }
                $output .= $this->getOptionsString($value) . ' ';
            } else {
                if (!is_numeric($key) && !in_array($key, $this->innerKeys)) {
                    $output .= "{$key}=";
                }
                $output .= is_numeric($value) ? $value . ' ' : "\"{$value}\" ";
            }
        }
        $output = trim($output);
        $prefix = '';
        if (!empty($output) && $prefixNewLine) {
            $prefix = " \\" . PHP_EOL;
        }
        if (!empty($output) && $prefixSpace) {
            $prefix = ' ';
        }
        return $prefix . $output;
    }

    /**
     * Set profile
     * @param string $profileName
     * @return $this
     */
    public function setProfile($profileName)
    {
        $profiles = [
            'atsc_1080p_25' => [
                'frame_rate_num' => 25,
                'frame_rate_den' => 1,
                'width' => 1920,
                'height' => 1080,
                'progressive' => 1,
                'sample_aspect_num' => 1,
                'sample_aspect_den' => 1,
                'display_aspect_num' => 16,
                'display_aspect_den' => 9
            ],
            'atsc_1080p_24' => [
                'frame_rate_num' => 24,
                'frame_rate_den' => 1,
                'width' => 1920,
                'height' => 1080,
                'progressive' => 1,
                'sample_aspect_num' => 1,
                'sample_aspect_den' => 1,
                'display_aspect_num' => 16,
                'display_aspect_den' => 9
            ],
            'atsc_720p_25' => [
                'frame_rate_num' => 25,
                'frame_rate_den' => 1,
                'width' => 1280,
                'height' => 720,
                'progressive' => 1,
                'sample_aspect_num' => 1,
                'sample_aspect_den' => 1,
                'display_aspect_num' => 16,
                'display_aspect_den' => 9
            ],
            'atsc_720p_24' => [
                'frame_rate_num' => 25,
                'frame_rate_den' => 1,
                'width' => 1280,
                'height' => 720,
                'progressive' => 1,
                'sample_aspect_num' => 1,
                'sample_aspect_den' => 1,
                'display_aspect_num' => 16,
                'display_aspect_den' => 9
            ],
            'hdv_1080_25p' => [
                'frame_rate_num' => 25,
                'frame_rate_den' => 1,
                'width' => 1440,
                'height' => 1080,
                'progressive' => 1,
                'sample_aspect_num' => 4,
                'sample_aspect_den' => 3,
                'display_aspect_num' => 16,
                'display_aspect_den' => 9
            ],
            'hdv_720_25p' => [
                'frame_rate_num' => 25,
                'frame_rate_den' => 1,
                'width' => 1280,
                'height' => 720,
                'progressive' => 1,
                'sample_aspect_num' => 1,
                'sample_aspect_den' => 1,
                'display_aspect_num' => 16,
                'display_aspect_den' => 9
            ],
            'dv_pal' => [
                'frame_rate_num' => 25,
                'frame_rate_den' => 1,
                'width' => 720,
                'height' => 576,
                'progressive' => 0,
                'sample_aspect_num' => 16,
                'sample_aspect_den' => 15,
                'display_aspect_num' => 4,
                'display_aspect_den' => 3
            ],
            'dv_pal_wide' => [
                'frame_rate_num' => 25,
                'frame_rate_den' => 1,
                'width' => 720,
                'height' => 576,
                'progressive' => 0,
                'sample_aspect_num' => 64,
                'sample_aspect_den' => 45,
                'display_aspect_num' => 16,
                'display_aspect_den' => 9
            ]
        ];

        $this->profileName = $profileName;
        if (isset($profiles[$this->profileName])) {
            $this->setOutputSize($profiles[$this->profileName]['width'], $profiles[$this->profileName]['height']);
            $this->setFps($profiles[$this->profileName]['frame_rate_num']);
        }
        return $this;
    }

    /**
     * Run command and get output
     * @param string $key
     * @return string
     */
    public function getOutput($key = 'main')
    {
        $this->createCommands();
        $this->clearOptions();
        return shell_exec($this->commandsArray[$key]);
    }

    /**
     * Get command
     * @param string $key
     * @return string
     */
    public function getCommandOutput($key = 'main')
    {
        $this->createCommands();
        $this->clearOptions();
        return $this->commandsArray[$key];
    }

    /**
     * Add ready made transition
     * @param string $transitionName
     * @param int $durationFrames
     * @param array $options
     * @param string $key
     * @return $this
     */
    public function addReadyMadeTransition($transitionName, $durationFrames, $options = [], $key = 'main')
    {
        $opt = array_merge([
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
            'inOpacity' => 100,
            'outOpacity' => 100
        ], $options);
        if (!isset($opt['inWidth'])) {
            $opt['inWidth'] = $opt['width'];
        }
        if (!isset($opt['inHeight'])) {
            $opt['inHeight'] = $opt['height'];
        }
        if (!isset($opt['outWidth'])) {
            $opt['outWidth'] = $opt['width'];
        }
        if (!isset($opt['outHeight'])) {
            $opt['outHeight'] = $opt['height'];
        }

        $this->addOption(['mix' => $durationFrames], $key);

        switch ($transitionName) {
            case 'fade':

                $this->addOption(['mixer' => 'luma'], $key);

                break;
            case 'shiftRightIn':

                $animationStr = "0={$opt['outWidth']}/0:{$opt['inWidth']}x{$opt['inHeight']}:{$opt['inOpacity']};";
                $animationStr .= "{$durationFrames}=0/0:{$opt['outWidth']}x{$opt['outHeight']}:{$opt['outOpacity']}";

                $this->addOption(['mixer' => [
                    'region',
                    ['composite.geometry' => $animationStr]
                ]], $key);

                break;
            case 'shiftRightOut':

                $animationStr = "0=0/0:{$opt['inWidth']}x{$opt['inHeight']}:{$opt['inOpacity']};";
                $animationStr .= "{$durationFrames}={$opt['inWidth']}/0:{$opt['outWidth']}x{$opt['outHeight']}:{$opt['outOpacity']}";

                $this->addOption(['mixer' => [
                    'region',
                    ['a_track' => 1, 'b_track' => 0],
                    ['composite.geometry' => $animationStr]
                ]], $key);

                break;
            case 'shiftLeftIn':

                $animationStr = "0=-{$opt['outWidth']}/0:{$opt['inWidth']}x{$opt['inHeight']}:{$opt['inOpacity']};";
                $animationStr .= "{$durationFrames}=0/0:{$opt['outWidth']}x{$opt['outHeight']}:{$opt['outOpacity']}";

                $this->addOption(['mixer' => [
                    'region',
                    ['composite.geometry' => $animationStr]
                ]], $key);

                break;
            case 'shiftLeftOut':

                $animationStr = "0=0/0:{$opt['inWidth']}x{$opt['inHeight']}:{$opt['inOpacity']};";
                $animationStr .= "{$durationFrames}=-{$opt['inWidth']}/0:{$opt['outWidth']}x{$opt['outHeight']}:{$opt['outOpacity']}";

                $this->addOption(['mixer' => [
                    'region',
                    ['a_track' => 1, 'b_track' => 0],
                    ['composite.geometry' => $animationStr]
                ]], $key);

                break;
            case 'shiftTopIn':

                $animationStr = "0=0/-{$opt['outHeight']}:{$opt['inWidth']}x{$opt['inHeight']}:{$opt['inOpacity']};";
                $animationStr .= "{$durationFrames}=0/0:{$opt['outWidth']}x{$opt['outHeight']}:{$opt['outOpacity']}";

                $this->addOption(['mixer' => [
                    'region',
                    ['composite.geometry' => $animationStr]
                ]], $key);

                break;
            case 'shiftTopOut':

                $animationStr = "0=0/0:{$opt['inWidth']}x{$opt['inHeight']}:{$opt['inOpacity']};";
                $animationStr .= "{$durationFrames}=0/-{$opt['inHeight']}:{$opt['outWidth']}x{$opt['outHeight']}:{$opt['outOpacity']}";

                $this->addOption(['mixer' => [
                    'region',
                    ['a_track' => 1, 'b_track' => 0],
                    ['composite.geometry' => $animationStr]
                ]], $key);

                break;
            case 'shiftBottomIn':

                $animationStr = "0=0/{$opt['outHeight']}:{$opt['inWidth']}x{$opt['inHeight']}:{$opt['inOpacity']};";
                $animationStr .= "{$durationFrames}=0/0:{$opt['outWidth']}x{$opt['outHeight']}:{$opt['outOpacity']}";

                $this->addOption(['mixer' => [
                    'region',
                    ['composite.geometry' => $animationStr]
                ]], $key);

                break;
            case 'shiftBottomOut':

                $animationStr = "0=0/0:{$opt['inWidth']}x{$opt['inHeight']}:{$opt['inOpacity']};";
                $animationStr .= "{$durationFrames}=0/{$opt['inHeight']}:{$opt['outWidth']}x{$opt['outHeight']}:{$opt['outOpacity']}";

                $this->addOption(['mixer' => [
                    'region',
                    ['a_track' => 1, 'b_track' => 0],
                    ['composite.geometry' => $animationStr]
                ]], $key);

                break;
            case 'wipeIn':
            case 'wipeOut':

                $opt = array_merge([
                    'wipeName' => 'linear_x.pgm',
                    'wipePath' => $this->config['wipes_dir_path'] . DIRECTORY_SEPARATOR . 'linear_x.pgm',
                    'softness' => 0
                ], $options);

                $resourcePath = !empty($opt['wipeName'])
                    ? $this->config['wipes_dir_path'] . DIRECTORY_SEPARATOR . $opt['wipeName']
                    : $opt['wipePath'];

                $this->addOption(['mixer' => ['luma', [
                    'resource' => $resourcePath,
                    'softness' => $opt['softness'],
                    'automatic' => 1,
                    'fill' => 1,
                    'invert' => $transitionName == 'wipeIn' ? 0 : 1
                ]]], $key);

                break;
        }
        return $this;
    }

    /**
     * Disable audio
     * @return $this
     */
    public function disableAudio()
    {
        $this->addOption(['clipOption' => ['audio_index' => '-1']]);
        return $this;
    }

    /**
     * Disable video
     * @return $this
     */
    public function disableVideo()
    {
        $this->addOption(['clipOption' => ['video_index' => '-1']]);
        return $this;
    }

    /**
     * Clear options
     */
    public function clearOptions()
    {
        $this->optionsArray = [];
        $this->consumer = [];
        $this->format = null;
    }

    /**
     * Add background audio
     * @param string $filePath
     * @param array $options
     * @return $this
     */
    public function addBackgroundAudio($filePath, $options)
    {
        $input = [$filePath];
        if (isset($options['delay'])) {
            array_unshift($input, ['blank' => [$options['delay']]]);
            unset($options['delay']);
        }
        array_push($input, $options);
        $this->addOption(['audio-track' => $input]);
        return $this;
    }

    /**
     * Add watermark
     * @param $filePath
     * @param boolean $attachToAll
     * @param array $options
     * @return $this
     */
    public function addWatermark($filePath, $attachToAll = false, $options = [])
    {
        $defaultOptions = [
            'width' => $this->getWidth(),
            'height' => $this->getHeight(),
            'left' => 0,
            'top' => 0
        ];
        $options = array_merge($defaultOptions, $options);
        if (!isset($options['composite.geometry'])) {
            $options['composite.geometry'] = "{$options['left']}/{$options['top']}:{$options['width']}x{$options['height']}";
        }
        unset($options['width'], $options['height'], $options['left'], $options['top']);

        if ($attachToAll) {
            $opt = ['attach-track' => ["watermark:{$filePath}"], $options];
        } else {
            $opt = ['attach' => ["watermark:{$filePath}"], $options];
        }
        $this->addOption($opt);

        return $this;
    }

    /**
     * Add text overlay
     * @param string $textContent
     * @param bool $attachToAll
     * @param array $options
     * @return $this
     */
    public function addTextOverlay($textContent, $attachToAll = false, $options = [])
    {
        $defaultOptions = [
            'in' => 0,
            'out' => 0,
            'fgcolour' => '#ffffff',
            'bgcolour' => 0,
            'olcolour' => '#000000',
            'outline' => 1,
            'pad' => '0x0',
            'size' => 48,
            'weight' => 400,
            'style' => 'normal',
            'halign' => 'left',
            'valign' => 'top',
            'family' => 'Ubuntu'
        ];
        $options = array_merge($defaultOptions, $options);

        if (!empty($options['slideFrom'])) {
            $duration = isset($options['duration']) ? (int) $options['duration'] : 50;
            $inOpacity = isset($options['inOpacity']) ? (int) $options['inOpacity'] : 100;
            $outOpacity = isset($options['outOpacity']) ? (int) $options['outOpacity'] : 100;
            switch ($options['slideFrom']) {
                case 'top':
                    $options['geometry'] = "0=0%/-100%:100%x100%:{$inOpacity};";
                    $options['geometry'] .= "{$duration}=0%/0%:100%x100%:{$outOpacity}";
                    break;
                case 'bottom':
                    $options['geometry'] = "0=0%/100%:100%x100%:{$inOpacity};";
                    $options['geometry'] .= "{$duration}=0%/0%:100%x100%:{$outOpacity}";
                    break;
                case 'right':
                    $options['geometry'] = "0=100%/0%:100%x100%:{$inOpacity};";
                    $options['geometry'] .= "{$duration}=0%/0%:100%x100%:{$outOpacity}";
                    break;
                default:
                    $options['geometry'] = "0=-100%/0%:100%x100%:{$inOpacity};";
                    $options['geometry'] .= "{$duration}=0%/0%:100%x100%:{$outOpacity}";
                    break;
            }
        }

        if ($attachToAll) {
            $opt = ['attach-track' => ["dynamictext:{$textContent}"], $options];
        } else {
            $opt = ['attach' => ["dynamictext:{$textContent}"], $options];
        }
        $this->addOption($opt);

        return $this;
    }

    /**
     * Render
     * @return array
     */
    public function render()
    {
        $uniqueStr = uniqid('log_', true);
        $progressLogPath = $this->config['tmp_dir_path'] . DIRECTORY_SEPARATOR . $uniqueStr . '.txt';

        $cmd = $this->getCommandOutput();
        $cmd .= ' \\' . PHP_EOL .  "2> \"{$progressLogPath}\"";

        if ($pid = $this->execInBackground($cmd)) {
            if (isset($_SESSION)) {
                $_SESSION['pid'] = $pid;
                $_SESSION['uniqueStr'] = $uniqueStr;
            }
            return [$pid, $progressLogPath];
        } else {
            if (!empty($_SESSION)) {
                unset($_SESSION['pid'], $_SESSION['uniqueStr']);
            }
        }
        return [null, null];
    }

    /**
     * Get rendering percent
     * @param string|null $progressLogPath
     * @param string|null $pid
     * @return int|null
     */
    public function getRenderingPercent($progressLogPath = null, $pid = null)
    {
        $percent = null;
        if (!$progressLogPath) {
            $uniqueStr = isset($_SESSION['uniqueStr']) ? $_SESSION['uniqueStr'] : '';
            if (!$uniqueStr) {
                return $percent;
            }
            $progressLogPath = $this->config['tmp_dir_path'] . DIRECTORY_SEPARATOR . $uniqueStr . '.txt';
        }

        if (!file_exists($progressLogPath)) {
            return $percent;
        }

        $fileContent = file_get_contents($progressLogPath);

        preg_match_all("/percentage:(?:\s+)(\d+)/", $fileContent, $matches);
        $percent = isset($matches[1]) && is_array($matches[1])
            ? (int) array_pop($matches[1])
            : 0;

        if ($percent >= 99) {
            sleep(2);
            $percent = 100;
        }

        // Check by PID
        if (!$pid && !empty($_SESSION)) {
            $pid = isset($_SESSION['pid']) ? (int) $_SESSION['pid'] : 0;
        }
        if ($pid) {
            $pidsArr = $this->getPidArr();
            if (!in_array($pid, $pidsArr)) {
                $percent = 100;
            }
        }

        if ($percent === 100) {
            unlink($progressLogPath);
            if (!empty($_SESSION)) {
                unset($_SESSION['pid'], $_SESSION['uniqueStr']);
            }
        }

        return $percent;
    }

    /**
     * Get clip media properties
     * @param string $filePath
     * @return array
     */
    public function getClipProperties($filePath)
    {
        $output = [];
        $xmlString = shell_exec($this->config['melt_path'] . " \"{$filePath}\" -consumer xml");
        try {
            $xml = new \SimpleXMLElement($xmlString);
        } catch (\Exception $e) {
            $xml = false;
        }
        if(is_object($xml)) {
            foreach ($xml->producer->property as $element) {
                $name = (string) $element->attributes()['name'];
                $output[$name] = (string) $element;
            }
        }
        return $output;
    }


    /**
     * Get PIDs array
     * @return array
     */
    public function getPidArr()
    {
        $tmp = trim(shell_exec('pidof -x ' . $this->config['melt_path']));
        if ($this->config['logging']) {
            $this->logging('PIDS: ' . $tmp);
        }
        $pidCurrentArr = $tmp ? explode(' ', $tmp) : [];
        return array_map('intval', $pidCurrentArr);
    }

    /**
     * Execute cmd in the background
     * @param string $cmd
     * @return string
     */
    public function execInBackground($cmd) {
        $pid = '';
        if (substr(php_uname(), 0, 7) == "Windows"){
            pclose(popen("start /B ". $cmd, "r"));
        }
        else {
            $pid = shell_exec("nohup $cmd > /dev/null & echo $!");
        }
        if ($this->config['logging']) {
            $this->logging("Command:\n" . $cmd);
            $this->logging("Start PID: " . $pid);
        }
        return trim($pid);
    }

    /**
     * Logging string to text file
     * @param string|array $str
     * @param int $userId
     * @return bool
     */
    public function logging($str, $userId = 0)
    {
        if( $userId ){
            $logFilePath = $this->config['tmp_dir_path']
                . DIRECTORY_SEPARATOR . $userId
                . DIRECTORY_SEPARATOR . 'log.txt';
        } else {
            $logFilePath = $this->config['tmp_dir_path'] . DIRECTORY_SEPARATOR . 'log.txt';
        }
        if (is_array($str)) {
            $str = json_encode($str);
        }

        if (!is_dir(dirname($logFilePath))) {
            mkdir(dirname($logFilePath));
            chmod(dirname($logFilePath), 0777);
        }

        if(file_exists( $logFilePath )
            && filesize( $logFilePath ) >= $this->config['max_log_size']){
                @unlink($logFilePath);
        }

        $fp = fopen($logFilePath, 'a');
        $str = PHP_EOL . PHP_EOL . date($this->config['date_format']) . PHP_EOL . $str;

        fwrite($fp, $str);
        fclose($fp);

        return true;
    }

    /**
     * Is associate array
     * @param array $arr
     * @return bool
     */
    public static function isAssoc($arr)
    {
        if (array() === $arr) {
            return false;
        }
        ksort($arr);
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Get file extension
     * @param $filePath
     * @return string
     */
    public static function getExtension($filePath)
    {
        $temp_arr = $filePath ? explode('.', $filePath) : array();
        $ext = count($temp_arr) > 1 ? end($temp_arr) : '';
        return strtolower($ext);
    }

    /**
     * Float to string
     * @param $input
     * @param string $comma
     * @return int
     */
    public static function floatToString($input, $comma = '.')
    {
        if ($input === 0) {
            return 0;
        }
        if (!is_float($input)) {
            $input = round(floatval($input), 3);
        }
        return number_format($input, 3, $comma, '');
    }

}
