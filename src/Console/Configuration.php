<?php

declare(strict_types=1);

/*
 * This file is part of the humbug/php-scoper package.
 *
 * Copyright (c) 2017 Théo FIDRY <theo.fidry@gmail.com>,
 *                    Pádraic Brady <padraic.brady@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Humbug\PhpScoper\Console;

use InvalidArgumentException;
use Symfony\Component\Finder\Finder;

final class Configuration
{
    private const FINDER_KEYWORD = 'finders';
    private const PATCHERS_KEYWORD = 'patchers';
    private const WHITELIST_KEYWORD = 'whitelist';
    private const GLOBAL_NAMESPACE_KEYWORD = 'global_namespace_whitelist';

    private const KEYWORDS = [
        self::FINDER_KEYWORD,
        self::PATCHERS_KEYWORD,
        self::WHITELIST_KEYWORD,
        self::GLOBAL_NAMESPACE_KEYWORD,
    ];

    private $path;
    private $patchers;
    private $finders;
    private $whitelist;
    private $globalNamespaceWhitelisters;

    /**
     * @param string|null $path Absolute path to the configuration file.
     *
     * @return self
     */
    public static function load(string $path = null): self
    {
        if (null === $path) {
            return new self(null, [], [], [], []);
        }

        $config = include $path;

        if (false === is_array($config)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected configuration to be an array, found "%s" instead.',
                    gettype($config)
                )
            );
        }

        self::validateConfigKeys($config);

        $finders = self::retrieveFinders($config);
        $patchers = self::retrievePatchers($config);
        $whitelist = self::retrieveWhitelist($config);
        $globalNamespace = self::retrieveGlobalNamespaceWhitelisters($config);

        return new self($path, $finders, $patchers, $whitelist, $globalNamespace);
    }

    /**
     * @param string|null         $path            Absolute path to the configuration file loaded.
     * @param Finder[]            $finders         List of finders which will provide the files that will be scoped.
     * @param callable[]          $patchers        List of closures which can alter the content of the files being
     *                                             scoped.
     * @param string[]            $whitelist       List of classes that will not be scoped.
     * @param callable[]|string[] $globalNamespace List of class names from the global namespace that should be scoped
     *                                             or closures filtering if the class should be scoped or not.
     */
    private function __construct(
        string $path = null,
        array $finders,
        array $patchers,
        array $whitelist,
        array $globalNamespace
    ) {
        $this->path = $path;
        $this->patchers = $patchers;
        $this->finders = $finders;
        $this->whitelist = $whitelist;
        $this->globalNamespaceWhitelisters = $globalNamespace;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return Finder[]
     */
    public function getFinders(): array
    {
        return $this->finders;
    }

    /**
     * @return callable[]
     */
    public function getPatchers(): array
    {
        return $this->patchers;
    }

    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    /**
     * @return callable[]|string[]
     */
    public function getGlobalNamespaceWhitelisters(): array
    {
        return $this->globalNamespaceWhitelisters;
    }

    private static function validateConfigKeys(array $config): void
    {
        array_map(
            ['self', 'validateConfigKey'],
            array_keys($config)
        );
    }

    private static function validateConfigKey(string $key): void
    {
        if (false === in_array($key, self::KEYWORDS)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid configuration key value "%s" found.',
                    $key
                )
            );
        }
    }

    private static function retrieveFinders(array $config): array
    {
        if (false === array_key_exists(self::FINDER_KEYWORD, $config)) {
            return [];
        }

        $finders = $config[self::FINDER_KEYWORD];

        if (false === is_array($finders)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected finders to be an array of "%s", found "%s" instead.',
                    Finder::class,
                    gettype($finders)
                )
            );
        }

        foreach ($finders as $index => $finder) {
            if ($finder instanceof Finder) {
                continue;
            }

            throw new InvalidArgumentException(
                sprintf(
                    'Expected finders to be an array of "%s", the "%d" element is not.',
                    Finder::class,
                    $index
                )
            );
        }

        return $finders;
    }

    private static function retrievePatchers(array $config): array
    {
        if (false === array_key_exists(self::PATCHERS_KEYWORD, $config)) {
            return [];
        }

        $patchers = $config[self::PATCHERS_KEYWORD];

        if (false === is_array($patchers)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected patchers to be an array of callables, found "%s" instead.',
                    gettype($patchers)
                )
            );
        }

        foreach ($patchers as $index => $patcher) {
            if (is_callable($patcher)) {
                continue;
            }

            throw new InvalidArgumentException(
                sprintf(
                    'Expected patchers to be an array of callables, the "%d" element is not.',
                    $index
                )
            );
        }

        return $patchers;
    }

    private static function retrieveWhitelist(array $config): array
    {
        if (false === array_key_exists(self::WHITELIST_KEYWORD, $config)) {
            return [];
        }

        $whitelist = $config[self::WHITELIST_KEYWORD];

        if (false === is_array($whitelist)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected whitelist to be an array of strings, found "%s" instead.',
                    gettype($whitelist)
                )
            );
        }

        foreach ($whitelist as $index => $className) {
            if (is_string($className)) {
                continue;
            }

            throw new InvalidArgumentException(
                sprintf(
                    'Expected whitelist to be an array of string, the "%d" element is not.',
                    $index
                )
            );
        }

        return $whitelist;
    }

    private static function retrieveGlobalNamespaceWhitelisters(array $config): array
    {
        if (false === array_key_exists(self::GLOBAL_NAMESPACE_KEYWORD, $config)) {
            return [];
        }

        $globalNamespace = $config[self::GLOBAL_NAMESPACE_KEYWORD];

        if (false === is_array($globalNamespace)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected "global_namespace" to be an array, found "%s" instead.',
                    gettype($globalNamespace)
                )
            );
        }

        foreach ($globalNamespace as $index => $className) {
            if (is_string($className) || is_callable($className)) {
                continue;
            }

            throw new InvalidArgumentException(
                sprintf(
                    'Expected "global_namespace" to be an array of callables or strings, the "%d" element '
                    .'is not.',
                    $index
                )
            );
        }

        return $globalNamespace;
    }
}
