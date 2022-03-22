<?php

namespace Corviz\Composer\LibInstallerPlugin;

use Composer\Factory;
use Composer\Installer\InstallerInterface;
use Composer\Installer\LibraryInstaller as BaseLibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

class LibraryInstaller extends BaseLibraryInstaller implements InstallerInterface
{
    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface $package
     * @return \React\Promise\FulfilledPromise|\React\Promise\Promise|\React\Promise\PromiseInterface|\React\Promise\RejectedPromise|null
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $response = parent::install($repo, $package);

        $extra = $package->getExtra();

        //Includes providers and middlewares to application
        $hasProviders = isset($extra['providers']);
        $hasMiddlewares = isset($extra['middleware']);

        $appConfigFile = $this->getRootPath().'configs/app.php';

        if ($hasProviders || $hasMiddlewares) {
            if (is_file($appConfigFile) && is_writable($appConfigFile)) {
                $appConfigFileContents = file_get_contents($appConfigFile);

                if ($hasProviders) {
                    $this->addItemsToArrayInCfgFile(
                        $appConfigFileContents, 'providers', $extra['providers']
                    );
                }

                if ($hasMiddlewares) {
                    $this->addItemsToArrayInCfgFile(
                        $appConfigFileContents, 'middleware', $extra['middleware']
                    );
                }
            }
        }


        return $response;
    }

    /**
     * @param $packageType
     * @return bool
     */
    public function supports($packageType)
    {
        return 'corviz-library';
    }

    /**
     * @return string
     */
    private function getRootPath()
    {
        return rtrim(dirname(Factory::getComposerFile()), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    }

    /**
     * @param $fileContent
     * @param $index
     * @param array $items
     * @return void
     */
    private function addItemsToArrayInCfgFile(&$fileContent, $index, array $items)
    {
        $re = '/([\'"]'.$index.'[\'"]\s*\=\>\s*(array\(|\[))([^\])]*)(\)|\])/m';
        $cb = function($matches) use (&$items){
            $text = '';
            $spaces = str_repeat(' ', 8);
            $lines = array_filter(preg_split("/(\r\n|\n|\r)/", $matches[3]), 'trim');
            foreach (array_merge($lines, $items) as $line) {
                $text .= $spaces.rtrim(trim($line), ',').','."\r\n";
            }

            return "{$matches[1]}\r\n$text    {$matches[4]}";
        };

        $fileContent = preg_replace_callback($re, $cb, $fileContent);
    }
}
