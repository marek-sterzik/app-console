<?php

namespace SPSOstrov\Runtime;

final class Path
{
    public static function canonizeRelative($path)
    {
        $path = ltrim($path, '/');
        if ($path === '') {
            return null;
        }
        $path = self::canonize($path);
        if ($path === '..' || substr($path, 0, 3) === '../') {
            return null;
        }
        return $path;
    }

    public static function canonize($path)
    {
        $currentPath = [];
        if (substr($path, 0, 1) === "/") {
            $absolute = true;
            $path = substr($path, 1);
        } else {
            $absolute = false;
        }
        $out = false;

        foreach (explode("/", $path) as $component) {
            if ($component === "." || $component === "") {
                continue;
            }
            if ($component === ".." && !$out) {
                if (empty($currentPath)) {
                    $out = true;
                    $currentPath[] = "..";
                } else {
                    array_pop($currentPath);
                }
            } else {
                $currentPath[] = $component;
            }
        }


        if ($absolute) {
            return "/" . implode("/", $currentPath);
        } else {
            if (empty($currentPath)) {
                return '.';
            } else {
                return implode("/", $currentPath);
            }
        }
    }
}
