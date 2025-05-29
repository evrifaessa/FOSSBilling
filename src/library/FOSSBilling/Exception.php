<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Symfony\Component\Filesystem\Path;

/**
 * The base FOSSBilling exception class. Implements translation and stacktrace logging.
 */
class Exception extends \Exception
{
    /**
     * Creates a new translated exception.
     *
     * @param string     $message   error message
     * @param array|null $variables translation variables
     * @param int        $code      the exception code
     * @param bool       $protected if the variables in this should be considered protect, if so, hide them from the stack trace
     */
    public function __construct(string $message, ?array $variables = null, int $code = 0, bool $protected = false)
    {
        $logStack = Config::getProperty('debug_and_monitoring.log_stacktrace', true);
        $stackLength = Config::getProperty('debug_and_monitoring.stacktrace_length', 25);

        // Attempt to prepend module name
        $moduleName = $this->detectModuleFromStacktrace();
        if ($moduleName !== null) {
            $message = "[Module: {$moduleName}] " . $message;
        }

        // Translate the exception
        if (function_exists('__trans')) {
            $message = __trans($message, $variables);
        } elseif (is_array($variables)) {
            $message = strtr($message, $variables);
        }

        // Log stack trace if enabled
        if (DEBUG && $logStack) {
            error_log('An exception has been thrown. Stacktrace:');
            error_log($this->stackTrace($stackLength, $protected));
        }

        parent::__construct($message, $code);
    }

    /**
     * Detects the module name from the debug backtrace.
     *
     * @return string|null
     */
    private function detectModuleFromStacktrace(): ?string
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($stack as $entry) {
            if (!isset($entry['file'])) {
                continue;
            }

            $realPath = realpath($entry['file']);
            if (!$realPath) {
                continue;
            }

            // Normalize with Symfony
            $normalizedPath = Path::normalize($realPath);

            // Look for "modules/{moduleName}/" in path
            if (preg_match('~[\\/\\\\]modules[\\/\\\\]([^\\/\\\\]+)[\\/\\\\]~', $normalizedPath, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Big thank you to jhurliman and jambroseclarke on Stack Overflow for this backtrace formatter.
     * We have slightly modified it for our purposes
     * https://stackoverflow.com/a/32365961.
     */
    private function stackTrace($Length = 25, $protected = false)
    {
        $stack = debug_backtrace($Length);
        $output = '';

        $stackLen = count($stack);
        for ($i = 1; $i < $stackLen; ++$i) {
            $entry = $stack[$i];

            $func = $entry['function'] . '(';
            if (isset($entry['args'])) {
                $argsLen = count($entry['args']);
                for ($j = 0; $j < $argsLen; ++$j) {
                    $my_entry = $entry['args'][$j];
                    if ($protected) {
                        $func .= '***';
                    } elseif (is_string($my_entry)) {
                        $func .= $my_entry;
                    }
                    if ($j < $argsLen - 1) {
                        $func .= ', ';
                    }
                }
            }
            $func .= ')';

            $entry_file = 'NO_FILE';
            if (array_key_exists('file', $entry)) {
                $entry_file = str_replace(PATH_ROOT, '', $entry['file']);
            }
            $entry_line = 'NO_LINE';
            if (array_key_exists('line', $entry)) {
                $entry_line = $entry['line'];
            }
            $output .= $entry_file . ':' . $entry_line . ' - ' . $func . PHP_EOL;
        }

        return $output;
    }
}
