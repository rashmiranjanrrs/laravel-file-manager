<?php

namespace Alexusmai\LaravelFileManager\Traits;

use Alexusmai\LaravelFileManager\Services\ACLService\ACL;
use Illuminate\Support\Arr;
use Storage;

trait ContentTrait
{

    /**
     * Get content for the selected disk and path
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array
     */
    public function getContent($disk, $path = null, $search = null)
    {
        $content = Storage::disk($disk)->listContents($path);

        // get a list of directories
        $directories = $this->filterDir($disk, $content, $search);

        // get a list of files
        $files = $this->filterFile($disk, $content, $search);

        return compact('directories', 'files');
    }

    /**
     * Get directories with properties
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array
     */
    public function directoriesWithProperties($disk, $path = null, $search = null)
    {
        $content = Storage::disk($disk)->listContents($path);

        return $this->filterDir($disk, $content, $search);
    }

    /**
     * Get files with properties
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array
     */
    public function filesWithProperties($disk, $path = null)
    {
        $content = Storage::disk($disk)->listContents($path);

        return $this->filterFile($disk, $content);
    }

    /**
     * Get directories for tree module
     *
     * @param $disk
     * @param $path
     *
     * @return array
     */
    public function getDirectoriesTree($disk, $path = null, $search = null)
    {
        $directories = $this->directoriesWithProperties($disk, $path, $search);

        foreach ($directories as $index => $dir) {
            $directories[$index]['props'] = [
                'hasSubdirectories' => Storage::disk($disk)
                    ->directories($dir['path']) ? true : false,
            ];
        }

        return $directories;
    }

    /**
     * File properties
     *
     * @param       $disk
     * @param  null $path
     *
     * @return mixed
     */
    public function fileProperties($disk, $path = null)
    {
        $file = Storage::disk($disk)->getMetadata($path);

        $pathInfo = pathinfo($path);

        $file['basename'] = $pathInfo['basename'];
        $file['dirname'] = $pathInfo['dirname'] === '.' ? ''
            : $pathInfo['dirname'];
        $file['extension'] = isset($pathInfo['extension'])
            ? $pathInfo['extension'] : '';
        $file['filename'] = $pathInfo['filename'];

        // if ACL ON
        if ($this->configRepository->getAcl()) {
            return $this->aclFilter($disk, [$file])[0];
        }

        return $file;
    }

    /**
     * Get properties for the selected directory
     *
     * @param       $disk
     * @param  null $path
     *
     * @return array|false
     */
    public function directoryProperties($disk, $path = null)
    {
        $directory = Storage::disk($disk)->getMetadata($path);

        $pathInfo = pathinfo($path);

        /**
         * S3 didn't return metadata for directories
         */
        if (!$directory) {
            $directory['path'] = $path;
            $directory['type'] = 'dir';
        }

        $directory['basename'] = $pathInfo['basename'];
        $directory['dirname'] = $pathInfo['dirname'] === '.' ? ''
            : $pathInfo['dirname'];

        // if ACL ON
        if ($this->configRepository->getAcl()) {
            return $this->aclFilter($disk, [$directory])[0];
        }

        return $directory;
    }

    /**
     * Get only directories
     *
     * @param $content
     *
     * @return array
     */
    protected function filterDir($disk, $content, $search = null)
    {
        // Select only directories
        $dirsList = Arr::where($content, function ($item) {
            return $item['type'] === 'dir';
        });

        // Remove 'filename' param
        $dirs = array_map(function ($item) {
            return Arr::except($item, ['filename']);
        }, $dirsList);

        // Filter directories based on the search term
        if ($search) {
            $search = strtolower($search);
            $dirs = array_filter($dirs, function ($item) use ($search) {
                return stripos(strtolower($item['path']), $search) !== false;
            });
        }

        // If ACL is ON
        if ($this->configRepository->getAcl()) {
            return array_values($this->aclFilter($disk, $dirs));
        }

        return array_values($dirs);
    }

    /**
     * Get only files
     *
     * @param $disk
     * @param $content
     *
     * @return array
     */
    protected function filterFile($disk, $content, $search = null)
    {
        // Select only files
        $files = Arr::where($content, function ($item) {
            return $item['type'] === 'file';
        });

        // If search term is provided
        if ($search) {
            $search = strtolower($search);
            $files = array_filter($files, function ($item) use ($search) {
                return stripos(strtolower($item['basename']), $search) !== false;
            });
        }

        // If ACL is ON
        if ($this->configRepository->getAcl()) {
            return array_values($this->aclFilter($disk, $files));
        }

        return array_values($files);
    }

    /**
     * ACL filter
     *
     * @param $disk
     * @param $content
     *
     * @return mixed
     */
    protected function aclFilter($disk, $content)
    {
        $acl = resolve(ACL::class);

        $withAccess = array_map(function ($item) use ($acl, $disk) {
            // add acl access level
            $item['acl'] = $acl->getAccessLevel($disk, $item['path']);

            return $item;
        }, $content);

        // filter files and folders
        if ($this->configRepository->getAclHideFromFM()) {
            return array_filter($withAccess, function ($item) {
                return $item['acl'] !== 0;
            });
        }

        return $withAccess;
    }
}
