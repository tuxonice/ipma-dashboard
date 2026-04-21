<?php

declare(strict_types=1);

namespace App\Framework;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Builds the application Translator and registers every YAML catalogue
 * found under the configured translations directory.
 *
 * Catalogue files are expected to follow Symfony's
 * `<domain>.<locale>.yaml` naming convention (e.g. `messages.pt.yaml`).
 */
final class TranslatorFactory
{
    /**
     * @param list<string> $fallbackLocales
     */
    public static function create(string $translationsDir, string $defaultLocale, array $fallbackLocales): Translator
    {
        $translator = new Translator($defaultLocale);
        $translator->setFallbackLocales($fallbackLocales);
        $translator->addLoader('yaml', new YamlFileLoader());

        if (!is_dir($translationsDir)) {
            return $translator;
        }

        foreach (glob($translationsDir . '/*.yaml') ?: [] as $file) {
            $name = basename($file, '.yaml');
            $parts = explode('.', $name);
            if (count($parts) < 2) {
                continue;
            }
            $locale = array_pop($parts);
            $domain = implode('.', $parts);

            $translator->addResource('yaml', $file, $locale, $domain);
        }

        return $translator;
    }
}
