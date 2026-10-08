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
        $copied = self::publishShellFiles($webRoot);
        $source = public_path('build');
        if (! is_dir($source)) {
            return $copied;
        }

        $dest = rtrim($webRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'build';
        self::mirrorDirectory($source, $dest);
        $copied[] = $dest;

        $hot = rtrim($webRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'hot';
        if (is_file($hot)) {
            @unlink($hot);
        }

        return $copied;
    }

    /**
     * @return list<string>
     */
    private static function publishShellFiles(string $webRoot): array
    {
        $copied = [];
        $root = rtrim($webRoot, DIRECTORY_SEPARATOR);

        $pwa = public_path('pwa');
        if (is_dir($pwa)) {
            $pwaDest = $root.DIRECTORY_SEPARATOR.'pwa';
            self::mirrorDirectory($pwa, $pwaDest);
            $copied[] = $pwaDest;
        }

        $jsDir = $root.DIRECTORY_SEPARATOR.'js';
        foreach (['field-readings.js', 'pwa-install.js'] as $name) {
            $src = public_path('js'.DIRECTORY_SEPARATOR.$name);
            if (! is_file($src)) {
                continue;
            }
            if (! is_dir($jsDir)) {
                mkdir($jsDir, 0755, true);
            }
            $dest = $jsDir.DIRECTORY_SEPARATOR.$name;
            copy($src, $dest);
            $copied[] = $dest;
        }

        foreach (['sw.js', 'offline.html'] as $name) {
            $src = public_path($name);
            if (! is_file($src)) {
                continue;
            }
            $dest = $root.DIRECTORY_SEPARATOR.$name;
            copy($src, $dest);
            $copied[] = $dest;
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
