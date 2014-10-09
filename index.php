<?php

define('ROOT_DIR',      __DIR__);
define('IMAGES_DIR',    ROOT_DIR . '/images');
define('BUILD_DIR',     ROOT_DIR . '/.tmp');
define('AUTH_FILE',     BUILD_DIR . '/auth.json');
define('PROJECT_DIR',   BUILD_DIR . '/project');
define('ASSETS_DIR',    BUILD_DIR . '/assets');
define('CACHE_DIR',     BUILD_DIR . '/cache');
define('DOCKER_FILE',   BUILD_DIR . '/DOCKERFILE');
define('COMPOSER_PHAR', ROOT_DIR . '/vendor/composer.phar');

if (! file_exists(COMPOSER_PHAR)) {
    echo 'Download composer' . PHP_EOL;
//    system('wget -o '. COMPOSER_PHAR .' http://getcomposer.org/composer.phar');
    system('cd .tmp && wget http://getcomposer.org/composer.phar; cd -');
}

include 'vendor/DockerFileInterface.php';
include 'vendor/WebProjectInterface.php';
include 'vendor/BaseImageInterface.php';
include 'vendor/BaseImage.php';
include 'vendor/DebianImageInterface.php';
include 'vendor/DebianImage.php';

ob_start();
include 'vendor/composer.phar';
ob_end_flush();
//echo \Composer\Composer::VERSION;

exit();

if (! file_exists(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

if (! file_exists(ASSETS_DIR)) {
    mkdir(ASSETS_DIR, 0755, true);
}

if (! file_exists(PROJECT_DIR)) {
    mkdir(PROJECT_DIR, 0755, true);
}

$images = new Images();
$images->buildAll();

/********************************************************************/

class Images
{
    protected $buildFiles = array();
    protected $posignore = array();

    public function __construct()
    {
        // Read the .posignore file to skip specific images.
        if (! file_exists('.posignore')) {
            return;
        }

        $handle = @fopen(".posignore", "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line && substr($line, 0, 1) !== '#') {
                    $this->posignore[] = $line;
                }
            }
            if (!feof($handle)) {
                echo "Error: reading .posignore failed" . PHP_EOL;
            }
            fclose($handle);
        }
    }

    public function add($imageName, $buildFile)
    {
        echo $imageName .' loaded'. PHP_EOL;
        $this->buildFiles[$imageName] = include $buildFile;
    }

    /**
     * Copies files/directories to the 'compile' directory
     */
    protected function copyAddAssets(BaseImage $image, $imageName)
    {
        echo 'Copy assets ...'. PHP_EOL;
        $assets = $image->getAssets();
        foreach ($assets as $asset) {
            system('cp -r '. IMAGES_DIR .'/'. $imageName .'/'. $asset .' '. ASSETS_DIR);
        }
    }

    /**
     * Clones GIT repesorities to projects/ in the current working directory.
     */
    protected function checkoutProjects()
    {
        $projects = array();

        /** @var WebProjectInterface $build */
        foreach ($this->buildFiles as $imageName => $build) {
            $projects = array_merge($projects, $build->getProjects());
        }

        $projects = array_unique($projects);

        // checkout the project or pull the latest commits if the project
        // has already been cloned
        foreach ($projects as $project) {
            $projectName = str_replace('.git', '', array_pop(explode('/', $project)));
            if (file_exists(PROJECT_DIR .'/'. $projectName)) {
                echo "Updating project ...";
                // @todo submodules may also have been updated
                system('cd '. PROJECT_DIR .'/'. $projectName .' && git pull && cd -');
            } else {
                echo "Cloning project ...";
                system('git clone --recursive '. $project .' '. PROJECT_DIR .'/'. $projectName);
            }
        }
    }

    public function buildAll()
    {
        $this->loadBuildFiles();

        $this->checkoutProjects();

        /** @var DockerFileInterface $build */
        foreach ($this->buildFiles as $imageName => $build) {

            if (in_array($imageName, $this->posignore)) {
                $this->outputHeader('SKIPPING'. $imageName);
                continue;
            }

            $this->outputHeader('BUILDING'. $imageName);
            $this->copyAddAssets($build, $imageName);

            file_put_contents(DOCKER_FILE, $build->toDockerFile());
            system("docker build --no-cache=true -t $imageName .");

            $tarFileName = str_replace('/', '_', $imageName);
//            system("docker save -o $tarFileName.tar $imageName");
//            system("mv $tarFileName.tar ../builds");
        }

        $this->cleanup();
    }

    protected function loadBuildFiles()
    {
        $objects = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(IMAGES_DIR),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach($objects as $fileName => $object){
            if  (basename($fileName) === 'build.php') {
                $imageName = str_replace(
                    array(IMAGES_DIR .'/', '/build.php'),
                    array('', ''),
                    $fileName
                );

                if (! in_array($imageName, $this->posignore)) {
                    $this->add($imageName, $fileName);
                }
            }
        }
    }

    protected function cleanup()
    {
        echo "Cleaning up ..." . PHP_EOL;
        unlink(DOCKER_FILE);
        system('rm -rf '. ASSETS_DIR .'/*');
    }

    protected function outputHeader($headerMessage)
    {
        echo PHP_EOL . PHP_EOL .str_pad("", 80, "#") . PHP_EOL;
        echo "# $headerMessage" . PHP_EOL;
        echo str_pad("", 80, "#") . PHP_EOL . PHP_EOL;
    }
}
