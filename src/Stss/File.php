<?php

namespace Stss;

/**
 * File class.
 */
class File
{
    /**
     * Returns the full path to a file to parse.
     * The file is looked for recursively under the template_location directory.
     *
     * @param string $filename to find
     * @param string $dirname  dirname
     *
     * @return string path to file
     */
    public static function getFile($filename, $dirname)
    {
        if (file_exists($filename)) {
            return $filename;
        }

        if (!empty($dirname)) {
            if (file_exists($dirname.DIRECTORY_SEPARATOR.$filename)) {
                return realpath($dirname.DIRECTORY_SEPARATOR.$filename);
            }
        }

        return false;
    }

    /**
     * Returns a cached version of the file if available.
     *
     * @param string $filename      to fetch
     * @param string $cacheLocation path to cache location
     * @param string $ext           path to cache location
     *
     * @return mixed the cached file if available or false if it is not
     */
    public static function getCachedFile($filename, $cacheLocation, $ext = 'tssc')
    {
        $cached = realpath($cacheLocation).DIRECTORY_SEPARATOR.md5($filename).'.'.$ext;

        if ($cached && file_exists($cached) &&
                filemtime($cached) >= filemtime($filename)) {
            return $cached;
        }

        return false;
    }

    /**
     * Saves a cached version of the file.
     *
     * @param RootNode $tssc          tree to save
     * @param string   $filename      to save
     * @param string   $cacheLocation path to cache location
     * @param string   $ext
     *
     * @return mixed the cached file if available or false if it is not
     */
    public static function setCachedFile($tssc, $filename, $cacheLocation, $ext = 'tssc')
    {
        $cacheDir = realpath($cacheLocation);

        if (!$cacheDir) {
            mkdir($cacheLocation);
            @chmod($cacheLocation, 0777);
            $cacheDir = realpath($cacheLocation);
        }

        $cached = $cacheDir.DIRECTORY_SEPARATOR.md5($filename).'.'.$ext;

        if (file_put_contents($cached, $tssc)) {
            return $cached;
        }

        return false;
    }
}
