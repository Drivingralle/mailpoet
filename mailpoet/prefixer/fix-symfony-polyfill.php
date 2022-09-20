<?php

// throw exception if anything fails
set_error_handler(function ($severity, $message, $file, $line) {
  throw new ErrorException($message, 0, $severity, $file, $line);
});

$file = __DIR__ . '/../vendor-prefixed/symfony/polyfill-intl-idn/Idn.php';
$data = file_get_contents($file);
$data = str_replace('\\Normalizer::', '\\MailPoetVendor\\Normalizer::', $data);
$data = str_replace('use Normalizer;', 'use MailPoetVendor\\Normalizer;', $data);
file_put_contents($file, $data);

// Remove union return type from bootstrap file for PHP8 because we can't push PHP8 syntax to wp.org plugins repository
$file = __DIR__ . '/../vendor-prefixed/symfony/polyfill-intl-idn/bootstrap80.php';
$data = file_get_contents($file);
$data = str_replace(' : string|false', '', $data);
file_put_contents($file, $data);

// Add phpcs::ignore that are used for older PHP versions and throw error in PHPCompatibility check for versions 8.0
$file = __DIR__ . '/../vendor-prefixed/symfony/polyfill-intl-idn/bootstrap.php';
$data = file_get_contents($file);
$data = str_replace('\INTL_IDNA_VARIANT_2003, &$idna_info = null)', '\INTL_IDNA_VARIANT_2003, &$idna_info = null) //phpcs::ignore', $data);
file_put_contents($file, $data);

$file = __DIR__ . '/../vendor-prefixed/symfony/polyfill-intl-normalizer/Normalizer.php';
$data = file_get_contents($file);
$data = str_replace('\\Normalizer::', '\\MailPoetVendor\\Normalizer::', $data);
$data = str_replace('\'Normalizer::', '\'\\MailPoetVendor\\Normalizer::', $data); // for use in strings like defined('...')
file_put_contents($file, $data);

$file = __DIR__ . '/../vendor-prefixed/symfony/polyfill-iconv/Iconv.php';
$data = file_get_contents($file);
$data = str_replace('\\Normalizer::', '\\MailPoetVendor\\Normalizer::', $data);
file_put_contents($file, $data);

// Remove unnecessary polyfills these polyfills are required by symfony/console
// but don't use and remove the package
exec('rm -r ' . __DIR__ . '/../vendor-prefixed/symfony/polyfill-php73');
