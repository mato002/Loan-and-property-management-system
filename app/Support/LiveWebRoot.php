<?php

namespace App\Support;

final class LiveWebRoot
{
    public static function path(): ?string
    {
        $env = trim((string) env('PUBLIC_HTML', env('PUBLIC_HTML_PATH', '')));
        if ($env !== '' && is_dir($env)) {
            return self::usable($env);
        }

        $buildEnv = trim((string) env('PUBLIC_HTML_BUILD', ''));
        if ($buildEnv !== '') {
            $parent = dirname($buildEnv);
            if (is_dir($parent)) {
                $usable = self::usable($parent);
                if ($usable !== null) {
                    return $usable;
                }
            }
        }

        $dir = base_path();
        for ($i = 0; $i < 6; $i++) {
            foreach (['public_html', 'www', 'httpdocs'] as $name) {
                $candidate = $dir.DIRECTORY_SEPARATOR.$name;
                $usable = self::usable($candidate);
                if ($usable !== null) {
                    return $usable;
                }
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        $home = rtrim((string) (getenv('HOME') ?: ''), DIRECTORY_SEPARATOR);
        if ($home !== '') {
            return self::usable($home.DIRECTORY_SEPARATOR.'public_html');
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function publish(string $webRoot): array
    {
        $copied = [];
        $source = public_path('build');
        if (! is_dir($source)) {
            return $copied;
        }

        $dest = rtrim($webRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'build';
        self::mirrorDirectory($source, $dest);
        $copied[] = $dest;

        $pwa = public_path('pwa');
        if (is_dir($pwa)) {
            $pwaDest = rtrim($webRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'pwa';
            self::mirrorDirectory($pwa, $pwaDest);
            $copied[] = $pwaDest;
        }

        $readings = public_path('js/field-readings.js');
        if (is_file($readings)) {
            $jsDir = rtrim($webRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'js';
            if (! is_dir($jsDir)) {
                mkdir($jsDir, 0755, true);
            }
            $jsDest = $jsDir.DIRECTORY_SEPARATOR.'field-readings.js';
            copy($readings, $jsDest);
            $copied[] = $jsDest;
        }

        $sw = public_path('sw.js');
        if (is_file($sw)) {
            $swDest = rtrim($webRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'sw.js';
            copy($sw, $swDest);
            $copied[] = $swDest;
        }

        $hot = rtrim($webRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'hot';
        if (is_file($hot)) {
            @unlink($hot);
        }

        return $copied;
    }

    private static function usable(string $webRoot): ?string
    {
        if (! is_dir($webRoot)) {
            return null;
        }

        $resolved = realpath($webRoot) ?: $webRoot;
        $appPublic = realpath(public_path()) ?: public_path();
        $appRoot = realpath(base_path()) ?: base_path();
        if ($resolved === $appPublic || $resolved === $appRoot) {
            return null;
        }

        return $resolved;
    }

    private static function mirrorDirectory(string $source, string $dest): void
    {
        if (is_dir($dest)) {
            self::deleteDirectory($dest);
        }
        mkdir($dest, 0755, true);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $dest.DIRECTORY_SEPARATOR.$iterator->getSubPathName();
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
                continue;
            }
            copy($item->getPathname(), $target);
        }
    }

    private static function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
