<?php

include 'vendor/DockerFileInterface.php';
include 'vendor/WebProjectInterface.php';
include 'vendor/BaseImageInterface.php';
include 'vendor/BaseImage.php';
include 'vendor/DebianImageInterface.php';
include 'vendor/DebianImage.php';

init();

$path = 'images';

$objects = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path),
    RecursiveIteratorIterator::SELF_FIRST
);

$images = new Images();

foreach($objects as $fileName => $object){
    if  (basename($fileName) === 'build.php') {
        $imageName = str_replace(
            array('images/', '/build.php'),
            array('', ''),
            $fileName
        );

        $images->add($imageName, $fileName);
    }
}

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
        $this->buildFiles[$imageName] = include $buildFile;
    }

    /**
     * Copies files/directories to the 'compile' directory
     */
    protected function copyAddAssets(BaseImage $image, $imageName)
    {
        // we are in .tmp when the method is called

        $assets = $image->getAssets();
        foreach ($assets as $asset) {
            system("cp -r ../images/$imageName/$asset assets/");
        }
    }

    /**
     * Clones GIT repesorities to projects/ in the current working directory.
     */
    protected function checkoutProjects()
    {
        $projectRoot = 'project';
        $projects = array();

        /** @var WebProjectInterface $build */
        foreach ($this->buildFiles as $imageName => $build) {
            $projects = array_merge($projects, $build->getProjects());
        }

        $projects = array_unique($projects);

        $oldDir = array(getcwd());

        if (! file_exists($projectRoot)) {
            mkdir($projectRoot);
        }

        chdir($projectRoot);

        // checkout the project or pull the latest commits if the project
        // has already been cloned
        foreach ($projects as $project) {
            $projectName = str_replace('.git', '', array_pop(explode('/', $project)));
            if (file_exists($projectName)) {
                $oldDir[] = getcwd();
                chdir($projectName);
                system('git pull '. $project);
                chdir(array_pop($oldDir));
            } else {
                system('git clone '. $project);
            }
        }

        chdir(array_pop($oldDir));
    }

    public function buildAll()
    {
        $oldDir = getcwd();
        chdir('.tmp');

        $this->checkoutProjects();

        /** @var DockerFileInterface $build */
        foreach ($this->buildFiles as $imageName => $build) {

            if (in_array($imageName, $this->posignore)) {

                echo PHP_EOL . PHP_EOL .str_pad("", 80, "#") . PHP_EOL;
                echo "# SKIPPING $imageName" . PHP_EOL;
                echo str_pad("", 80, "#") . PHP_EOL . PHP_EOL;
                continue;
            }

            echo PHP_EOL . PHP_EOL .str_pad("", 80, "#") . PHP_EOL;
            echo "# BUILDING $imageName" . PHP_EOL;
            echo str_pad("", 80, "#") . PHP_EOL . PHP_EOL;

            $this->copyAddAssets($build, $imageName);

            $tarFileName = str_replace('/', '_', $imageName);

            // auto-generate the Dockerfile
            file_put_contents('Dockerfile', $build->toDockerFile());

            system("docker build --no-cache=true -t $imageName .");
//            system("docker save -o $tarFileName.tar $imageName");
//            system("mv $tarFileName.tar ../builds");
        }

        $this->cleanup();
        chdir($oldDir);
    }

    protected function cleanup()
    {
        unlink('Dockerfile');
        system('rm -rf assets/*');
    }
}

function init() {
    mkdir('.tmp/cache', 0755, true);
    mkdir('.tmp/assets', 0755, true);
    mkdir('.tmp/project', 0755, true);
}
