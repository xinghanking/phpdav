<?php

/**
 * Class Dav_PhyOperation
 * 对操作系统物理存储的资源做操作
 */
class Dav_PhyOperation
{

    /**
     * 创建一个新文件
     * @param string $resourcePath 新资源路径地址
     * @return bool
     */
    public static function createFile($resourcePath)
    {
        $res = touch($resourcePath);
        if (false === $res && !file_exists($resourcePath)) {
            $res = file_put_contents($resourcePath, '');
        }
        return false !== $res;
    }

    /**
     * 创建一个新目录
     * @param string $dirPath 新目录路径地址
     * @return bool
     */
    public static function createDir($dirPath)
    {
        $res = mkdir($dirPath, 0700, true);
        if (false === $res) {
            $dirPath = escapeshellarg($dirPath);
            if (PHP_OS == 'Windows') {
                exec('mkdir ' . $dirPath, $msg, $status);
            } else {
                exec('mkdir -p ' . $dirPath, $msg, $status);
            }
            $res = 0 === $status;
        }
        if (false === $res) {
            Dav_Log::debug($msg);
        }
        return $res;
    }

    /**
     * 删除一个资源（文件、目录）
     * @param string $path 删除资源的路径地址
     * @return bool
     */
    public static function removePath($path)
    {
        try {
            $fds = basename($path);
            if ($fds == '*') {
                $upperfd = dirname($path);
                if (!file_exists($upperfd)) {
                    return true;
                }
                $fds = scandir($upperfd);
                $fds = array_diff($fds, ['.', '..']);
                if (empty($fds)) {
                    return true;
                }
                foreach ($fds as $fd) {
                    $resource = $upperfd . DIRECTORY_SEPARATOR . $fd;
                    if (false === self::removePath($resource)) {
                        throw new Exception('Fatal delete a resource:' . $fd);
                    }
                }
                return true;
            }
            if (!file_exists($path)){
               return true;
            }
            if (is_dir($path)) {
                $childrenResources = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*';
                $res = self::removePath($childrenResources);
                if ($res) {
                    $res = rmdir($path);
                }
            } else {
                $res = unlink($path);
            }
            if (false === $res) {
                throw new Exception('Fatal delete a resource:' . $path);
            }
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $path = escapeshellarg($path);
            if (PHP_OS == 'Windows') {
                exec('rd /s /p ' . $path, $msg, $status);
            } else {
                exec('rm -fr ' . $path, $msg, $status);
            }
            $res = 0 === $status;
            if (false === $res) {
                Dav_Log::error($e);
            }
        }
        return $res;
    }

    /**
     * 复制资源
     * @param string $source 资源的源地址
     * @param string $dPath 资源的目标地址
     * @return bool
     */
    public static function copyResource($source, $dPath)
    {
        $dPath = rtrim($dPath, '*');
        $res = copy($source, $dPath);
        if (false == $res) {
            $source = escapeshellarg($source);
            $dpath = escapeshellarg($dPath);
            if (PHP_OS == 'Windows') {
                exec('copy ' . $source . ' ' . $dPath, $msg, $status);
            } else {
                exec('cp -r ' . $source . ' ' . $dPath, $msg, $status);
            }
            if ($status !== 0) {
                Dav_Log::debug($msg);
                $res = false;
            } else {
                $res = true;
            }
        }
        return $res;
    }

    /**
     * 合并文件
     * @param string $filePath 合入目标文件地址
     * @param string $appendFile 附加合入文件地址
     * @return bool
     */
    public static function combineFile($filePath, $appendFile)
    {
        if (false === file_exists($appendFile)) {
            return true;
        }
        if (is_dir($appendFile)) {
            return false;
        }
        $appendContentSize = filesize($appendFile);
        if ($appendContentSize === 0) {
            return true;
        }
        if (PHP_OS == 'Linux') {
            exec('cat ' . $appendFile . ' >> ' . $filePath . ' && rm -fr ' . $filePath, $msg, $status);
            if ($status === 0) {
                return true;
            }
        }
        $maxSize = 10485760;
        $start = 0;
        while ($start < $appendContentSize) {
            $content = file_get_contents($appendFile, false, null, $start, $maxSize);
            $appendSize = file_put_contents($filePath, $content, FILE_APPEND);
            if (false === $appendSize) {
                return true;
            }
            $start += $appendSize;
        }
        unlink($appendSize);
        return true;
    }

    /**
     * 移动资源
     * @param string $source 资源的原地址
     * @param string $dest 资源的新地址
     * @return bool
     */
    public static function move($source, $dest)
    {
        if (is_dir($dest)) {
            rmdir($dest);
        }
        $res = rename($source, $dest);
        if (false == $res && PHP_OS == 'Linux') {
            exec('move -f ' . escapeshellarg($source) . ' ' . escapeshellarg($dest), $msg, $status);
            if ($status !== 0) {
                Dav_Log::debug($msg);
                $res = false;
            } else {
                $res = true;
            }
        }
        return $res;
    }

    /**
     * 获取文件目录大小
     * @param string $dir 文件目录路径
     * @return int
     */
    public static function getDirSize($dir)
    {
        if (isset($_SESSION['PHPDAV_DIR_SIZE'][$dir]) && intval($_SESSION['PHPDAV_DIR_SIZE'][$dir])) {
            return $_SESSION['PHPDAV_DIR_SIZE'][$dir];
        }
        $_SESSION['PHPDAV_DIR_SIZE'][$dir] = 0;
        if (PHP_OS == 'Linux') {
            exec('du -b --max-depth=1 ' . $dir, $output, $status);
            if ($status === 0) {
                foreach ($output as $info) {
                    $info = preg_split('/\s+/i', $info, 2);
                    $_SESSION['PHPDAV_DIR_SIZE'][$info[1]] = $info[0];
                }
            }
        } else {
            $children = scandir($dir);
            $children = array_diff($children, ['.', '..']);
            if (empty($children)) {
                return $_SESSION['PHPDAV_DIR_SIZE'][$dir];
            }
            foreach ($children as $d) {
                $d = $dir . DIRECTORY_SEPARATOR . $d;
                if (is_dir($d)) {
                    $_SESSION['PHPDAV_DIR_SIZE'][$dir] += self::getDirSize($d);
                } else {
                    $_SESSION['PHPDAV_DIR_SIZE'][$dir] += filesize($d);
                }
            }
        }
        return $_SESSION['PHPDAV_DIR_SIZE'][$dir];
    }
}
