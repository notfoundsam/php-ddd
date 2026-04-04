<?php

class Packageloader {

    private string $namespace = '';

    private string $path = '';

    private static ?Packageloader $instance = null;

    public static function instance(): Packageloader
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function load(string $namespace, string $directoryName): void
    {
        // throw new Exception('Not implemented');
        $this->namespace = $namespace;
        $this->path = PKGPATH . $directoryName . '/classes/';

        if ($files = $this->getFilePaths()) {
            \Autoloader::add_core_namespace($this->namespace);
            \Autoloader::add_classes($this->getClasses($files));
        }
    }

    private function getFilePaths(): array
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->path));

        $files = [];

        /** @var SplFileInfo $file */
        foreach ($rii as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') continue;

            $files[] = str_replace($this->path, '', $file->getPathname());
        }

        return $files;
    }

    private function getClasses(array $files): array
    {
        $classes = [];

        foreach ($files as $file) {
            $classes[$this->getClassName($file)] = $this->getFilePath($file);
        }

        return $classes;
    }

    private function getClassName(string $file): string
    {
        return $this->namespace.'\\'.str_replace('/', '\\', str_replace('.php', '', $file));
    }

    private function getFilePath(string $file): string
    {
        return $this->path.$file;
    }
}
